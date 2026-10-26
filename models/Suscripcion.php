<?php
require_once __DIR__ . '/../helpers/VigenciaSuscripcion.php';
require_once __DIR__ . '/../helpers/TarifaSuscripcion.php';
require_once __DIR__ . '/../helpers/Transaccion.php';
require_once __DIR__ . '/../helpers/ValidadorPlataforma.php';
require_once __DIR__ . '/Auditoria.php';

/** HU-0.5: única lectura contractual/efectiva, también para cuotas e Inicio. */
final class Suscripcion
{
    public function __construct(private PDO $db, private int $clinica, private mixed $reloj = null)
    {
        if ($clinica <= 0) {
            throw new InvalidArgumentException('La clínica no está disponible.');
        }
    }

    public function ahora(): DateTimeImmutable
    {
        return $this->reloj ? ($this->reloj)()->setTimezone(new DateTimeZone(PoliticaSuscripcion::ZONA))
            : new DateTimeImmutable('now', new DateTimeZone(PoliticaSuscripcion::ZONA));
    }

    public function estado(): array
    {
        $q = $this->db->prepare('SELECT * FROM clinicas WHERE id_clinica=?');
        $q->execute([$this->clinica]);
        $clinica = $q->fetch(PDO::FETCH_ASSOC) ?: throw new InvalidArgumentException('La clínica no está disponible.');
        $q = $this->db->prepare('SELECT * FROM suscripciones WHERE id_clinica=? ORDER BY id_suscripcion DESC LIMIT 1');
        $q->execute([$this->clinica]);
        $suscripcion = $q->fetch(PDO::FETCH_ASSOC) ?: null;
        $gratuito = $this->db->query("SELECT * FROM planes WHERE nombre='Gratuito'")->fetch(PDO::FETCH_ASSOC)
            ?: throw new RuntimeException('Falta el plan gratuito.');
        $q = $this->db->prepare('SELECT * FROM planes WHERE id_plan=?');
        $q->execute([$clinica['id_plan']]);
        $contratado = $q->fetch(PDO::FETCH_ASSOC) ?: $gratuito;
        return VigenciaSuscripcion::calcular($clinica, $suscripcion, $contratado, $gratuito, $this->ahora());
    }

    public function profesional(): array
    {
        return $this->db->query("SELECT * FROM planes WHERE nombre='Profesional'")->fetch(PDO::FETCH_ASSOC)
            ?: throw new RuntimeException('Falta el plan profesional.');
    }

    public function bloqueada(callable $operacion): mixed
    {
        return Transaccion::ejecutar($this->db, function () use ($operacion): mixed {
            $q = $this->db->prepare('SELECT id_clinica FROM clinicas WHERE id_clinica=?' . ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''));
            $q->execute([$this->clinica]);
            if (!$q->fetchColumn()) {
                throw new InvalidArgumentException('La clínica no está disponible.');
            }
            return $operacion();
        });
    }

    public function auditar(string $tabla, int $id, array $antes, array $despues, string $motivo, ?int $actor = null, string $accion = 'UPDATE'): void
    {
        if (!(new Auditoria($this->db))->log($actor, $accion, $tabla, $id, $antes, $despues, $motivo, $this->clinica)) {
            throw new RuntimeException('No se pudo auditar la suscripción.');
        }
    }

    public function sincronizar(): bool
    {
        return $this->bloqueada(function (): bool {
            $estado = $this->estado();
            $s = $estado['suscripcion'];
            if (!$s || $estado['legado_sin_fecha']) {
                return false;
            }
            $alDia = (int) ($estado['situacion'] === 'al_dia');
            if ($s['estado_cobro'] === $estado['situacion'] && (int) $s['al_dia'] === $alDia) {
                return false;
            }
            $this->db->prepare('UPDATE suscripciones SET estado_cobro=?,al_dia=? WHERE id_suscripcion=? AND id_clinica=?')
                ->execute([$estado['situacion'], $alDia, $s['id_suscripcion'], $this->clinica]);
            $this->auditar('suscripciones', (int) $s['id_suscripcion'], ['estado_cobro' => $s['estado_cobro']], ['estado_cobro' => $estado['situacion']], 'Trabajador de suscripciones (RN-007/014)');
            return true;
        });
    }

    public function ajustar(int $plan, string $estado, string $motivo, ?string $fechaFin, string $ciclo = 'mensual'): void
    {
        if ((Contexto::actual()['tipo'] ?? null) !== Contexto::PLATAFORMA || Contexto::rolActivo() !== Roles::SUPER_ADMIN) {
            throw new AccesoDenegado(403, 'El ajuste requiere plataforma.');
        }
        $actor = $this->db->prepare('SELECT 1 FROM usuarios WHERE id_usuario=? AND es_super_admin=1 AND estado=1');
        $actor->execute([Contexto::idUsuario()]);
        if (!$actor->fetchColumn()) {
            throw new AccesoDenegado(403, 'El ajuste requiere una cuenta de plataforma activa.');
        }
        $motivo = ValidadorPlataforma::texto($motivo);
        if (!in_array($estado, ['activa', 'suspendida', 'cancelada'], true) || !in_array($ciclo, ['mensual', 'anual'], true)) {
            throw new InvalidArgumentException('Revisa el estado y el ciclo.');
        }
        $this->bloqueada(function () use ($plan, $estado, $motivo, $fechaFin, $ciclo): void {
            $antes = $this->estado();
            if (in_array($antes['clinica']['estado'], ['pendiente_verificacion', 'baja'], true)) {
                throw new InvalidArgumentException('La clínica debe estar verificada y no dada de baja.');
            }
            $q = $this->db->prepare('SELECT * FROM planes WHERE id_plan=? AND estado=1');
            $q->execute([$plan]);
            $catalogo = $q->fetch(PDO::FETCH_ASSOC) ?: throw new InvalidArgumentException('Elige un plan vigente.');
            $pago = (int) $catalogo['id_plan'] !== (int) $antes['plan_gratuito']['id_plan'];
            if ($pago && ((string) $fechaFin < '0001-01-01' || (string) $fechaFin > VigenciaSuscripcion::FECHA_FIN_MAX)) {
                throw new InvalidArgumentException('Indica el último día incluido de la concesión.');
            }
            $fin = $pago ? VigenciaSuscripcion::fecha((string) $fechaFin . ' 00:00:00')->modify('+1 day') : null;
            $ahora = $this->ahora();
            $q = $this->db->prepare("SELECT 1 FROM suscripcion_periodos WHERE id_clinica=? AND origen='wompi' AND fin>? LIMIT 1");
            $q->execute([$this->clinica, $ahora->format('Y-m-d H:i:s')]);
            if ($q->fetchColumn()) {
                throw new InvalidArgumentException('El ajuste no puede modificar un periodo pagado vigente.');
            }
            $s = $antes['suscripcion'];
            $inicio = $s['periodo_inicio'] ?? ($s['fecha_inicio'] ?? $ahora->format('Y-m-d')) . ' 00:00:00';
            if ($fin !== null && $fin <= VigenciaSuscripcion::fecha($inicio)) {
                throw new InvalidArgumentException('El fin debe ser posterior al inicio de la concesión.');
            }
            if (!$s) {
                $this->db->prepare('INSERT INTO suscripciones (id_clinica,id_plan,fecha_inicio) VALUES (?,?,?)')->execute([$this->clinica, $plan, $ahora->format('Y-m-d')]);
                $s = ['id_suscripcion' => (int) $this->db->lastInsertId()];
            }
            $this->db->prepare("UPDATE suscripciones SET id_plan=?,estado=?,ciclo=?,gestion_cobro='gestionada',periodo_inicio=?,periodo_fin=?,fecha_fin=?,version_periodo=version_periodo+1,cobro_automatico=0,motivo_automatico_apagado=NULL WHERE id_suscripcion=? AND id_clinica=?")
                ->execute([$plan, $estado, $ciclo, $inicio, $fin?->format('Y-m-d H:i:s'), $pago ? $fechaFin : null, $s['id_suscripcion'], $this->clinica]);
            $this->db->prepare('UPDATE clinicas SET id_plan=? WHERE id_clinica=?')->execute([$plan, $this->clinica]);
            if ($fin !== null && $fin > $ahora) {
                $q = $this->db->prepare('SELECT MAX(fin) FROM suscripcion_periodos WHERE id_clinica=? AND id_suscripcion=?');
                $q->execute([$this->clinica, $s['id_suscripcion']]);
                $ultimoFin = $q->fetchColumn();
                $desde = $ultimoFin && VigenciaSuscripcion::fecha($ultimoFin) > $ahora ? VigenciaSuscripcion::fecha($ultimoFin) : $ahora;
                if ($fin > $desde) {
                    // D6: evidencia administrativa; sin importe cobrado ni pago ficticio.
                    $this->db->prepare("INSERT INTO suscripcion_periodos (id_clinica,id_suscripcion,id_plan,origen,ciclo,inicio,fin,dia_ancla,mes_ancla,hora_ancla,creado_en,id_usuario_actor,motivo) VALUES (?,?,?,'administrativo',?,?,?,?,?,?,?,?,?)")
                        ->execute([$this->clinica, $s['id_suscripcion'], $plan, $ciclo, $desde->format('Y-m-d H:i:s'), $fin->format('Y-m-d H:i:s'), (int) $desde->format('d'), (int) $desde->format('m'), $desde->format('H:i:s'), $ahora->format('Y-m-d H:i:s'), Contexto::idUsuario(), $motivo]);
                    $this->auditar('suscripcion_periodos', (int) $this->db->lastInsertId(), [], ['origen' => 'administrativo', 'fin' => $fin->format('Y-m-d H:i:s')], $motivo, Contexto::idUsuario(), 'INSERT');
                }
            }
            $nuevo = $this->estado();
            $this->db->prepare('UPDATE suscripciones SET estado_cobro=?,al_dia=? WHERE id_suscripcion=? AND id_clinica=?')->execute([$nuevo['situacion'], (int) ($nuevo['situacion'] === 'al_dia'), $s['id_suscripcion'], $this->clinica]);
            $this->auditar('clinicas', $this->clinica, ['id_plan' => $antes['clinica']['id_plan'], 'suscripcion' => $antes['suscripcion']], ['id_plan' => $plan, 'estado' => $estado, 'fecha_fin' => $fechaFin, 'origen' => 'administrativo'], $motivo, Contexto::idUsuario());
        });
    }
}
