<?php
require_once __DIR__ . '/ModeloClinica.php';
require_once __DIR__ . '/../helpers/IntervalosAgenda.php';
require_once __DIR__ . '/../helpers/HorarioOcupado.php';

/** HU-4.9: única proyección de capacidad; no devuelve calendarios ajenos. */
final class DisponibilidadAgenda extends ModeloClinica
{
    private Closure $reloj;

    public function __construct(PDO $conn, ?callable $reloj = null)
    {
        parent::__construct($conn);
        $this->reloj = Closure::fromCallable($reloj ?? static fn () => new DateTimeImmutable('now', new DateTimeZone('America/Bogota')));
    }

    /** Revalida antes de cualquier lectura global, también en sugerencias. */
    public function autorizar(int $vet, int $mascota): void
    {
        $actor = Contexto::idUsuario();
        $portal = (Contexto::actual()['tipo'] ?? '') === Contexto::PROPIETARIO;
        $sql = $portal
            ? "SELECT 1 FROM propietario_clinica WHERE id_clinica=? AND id_propietario=? AND estado='activo'"
            : "SELECT 1 FROM usuario_clinica WHERE id_clinica=? AND id_usuario=? AND estado='activo' AND id_rol=?";
        $parametros = [$this->clinica(), $actor];
        if (!$portal) {
            $parametros[] = Contexto::rolActivo();
            if (!in_array(Contexto::rolActivo(), [Roles::ADMIN, Roles::VETERINARIO], true)) {
                $this->denegarAcceso('citas', null, 'Disponibilidad fuera de contexto (RE-4.9.1)');
            }
        }
        $vigente = $this->filas($sql, $parametros);
        $activo = $this->filas("SELECT 1 FROM usuarios u JOIN clinicas c ON c.id_clinica=? WHERE u.id_usuario=? AND u.estado=1 AND c.estado='activa'", [$this->clinica(), $actor]);
        if (!$vigente || !$activo) {
            $this->denegarAcceso('citas', null, 'Contexto de disponibilidad no vigente (RE-T.15.2)');
        }
        if ($portal) {
            // Sugerir no vincula la mascota: el vínculo se crea atómicamente al reservar.
            $propia = $this->filas('SELECT 1 FROM mascotas WHERE id_mascota=? AND id_propietario=? AND estado=1', [$mascota, $actor]);
            if (!$propia) {
                $this->denegarAcceso('mascotas', $mascota, 'Disponibilidad de mascota ajena (RN-G02)');
            }
        } else {
            $this->exigirMascotaActiva($mascota);
        }
        $miembro = $this->filas("SELECT 1 FROM usuario_clinica uc JOIN usuarios u ON u.id_usuario=uc.id_usuario WHERE uc.id_clinica=? AND uc.id_usuario=? AND uc.id_rol=2 AND uc.estado='activo' AND u.estado=1", [$this->clinica(), $vet]);
        if (!$miembro) {
            $this->denegarAcceso('usuarios', $vet, 'Disponibilidad de veterinario fuera de la clínica activa (RN-G13, RE-T.15.2)');
        }
    }

    /** Las franjas base se conservan para anclar el paso de presentación existente. */
    private function jornada(int $vet, string $fecha): array
    {
        $dia = IntervalosAgenda::fecha($fecha . ' 00:00:00');
        $apertura = [];
        foreach ($this->filas('SELECT * FROM horarios_clinica WHERE id_clinica=? AND dia_semana=? AND activo=1', [$this->clinica(), (int) $dia->format('N')]) as $fila) {
            foreach (['morning', 'afternoon'] as $bloque) {
                if ((int) $fila['bloque_' . $bloque . '_activo'] === 1) {
                    $apertura[] = $this->intervalo($fecha, $fila['bloque_' . $bloque . '_inicio'], $fila['bloque_' . $bloque . '_fin']);
                }
            }
        }
        $jornada = [];
        foreach ($this->filas('SELECT hora_inicio,hora_fin FROM horarios_veterinario WHERE id_clinica=? AND id_veterinario=? AND dia_semana=? AND activo=1', [$this->clinica(), $vet, (int) $dia->format('N')]) as $fila) {
            $jornada[] = $this->intervalo($fecha, $fila['hora_inicio'], $fila['hora_fin']);
        }
        return IntervalosAgenda::intersecar(IntervalosAgenda::unir($apertura), IntervalosAgenda::unir($jornada));
    }

    private function intervalo(string $fecha, string $inicio, string $fin): array
    {
        return [IntervalosAgenda::fecha($fecha . ' ' . self::hora($inicio))->getTimestamp(), IntervalosAgenda::fecha($fecha . ' ' . self::hora($fin))->getTimestamp()];
    }

    private static function hora(string $hora): string
    {
        return strlen($hora) === 5 ? $hora . ':00' : $hora;
    }

    private function franjas(int $vet, int $mascota, string $fecha, ?int $excluir, int $corte): array
    {
        $this->autorizar($vet, $mascota);
        if ($excluir !== null) {
            $cita = $this->filas("SELECT id_mascota,id_veterinario FROM citas WHERE id_cita=? AND id_clinica=? AND estado IN ('pendiente','confirmada')", [$excluir, $this->clinica()]);
            if (!$cita || (int) $cita[0]['id_mascota'] !== $mascota
                || (Contexto::rolActivo() === Roles::VETERINARIO && (int) $cita[0]['id_veterinario'] !== Contexto::idUsuario())
                || (Contexto::actual()['tipo'] ?? '') === Contexto::PROPIETARIO) {
                $this->denegarAcceso('citas', $excluir, 'Exclusión no autorizada (RE-4.9.3)');
            }
        }
        $inicioDia = IntervalosAgenda::fecha($fecha . ' 00:00:00');
        $finDia = $inicioDia->modify('+1 day');
        $ocupaciones = [];
        $desde = $inicioDia->format('Y-m-d H:i:s');
        $hasta = $finDia->format('Y-m-d H:i:s');
        foreach ($this->filas('SELECT inicio,fin FROM cierres_clinica WHERE id_clinica=? AND activo=1 AND inicio<? AND fin>?', [$this->clinica(), $hasta, $desde]) as $fila) {
            $ocupaciones[] = [IntervalosAgenda::fecha($fila['inicio'])->getTimestamp(), IntervalosAgenda::fecha($fila['fin'])->getTimestamp()];
        }
        foreach ($this->filas("SELECT fecha_hora_inicio,fecha_hora_fin FROM ausencias_veterinario WHERE id_clinica=? AND id_veterinario=? AND estado='aprobada' AND fecha_hora_inicio<? AND fecha_hora_fin>?", [$this->clinica(), $vet, $hasta, $desde]) as $fila) {
            $ocupaciones[] = [IntervalosAgenda::fecha($fila['fecha_hora_inicio'])->getTimestamp(), IntervalosAgenda::fecha($fila['fecha_hora_fin'])->getTimestamp()];
        }
        foreach ($this->ocupacionGlobal($vet, $mascota, $fecha, $excluir, $corte) as $intervalo) {
            $ocupaciones[] = $intervalo;
        }
        return IntervalosAgenda::restar($this->jornada($vet, $fecha), $ocupaciones);
    }

    /** RN-401: excepción global mínima; nunca nombres, clínica, motivo o contactos. */
    private function ocupacionGlobal(int $vet, int $mascota, string $fecha, ?int $excluir, int $ahora): array
    {
        $sql = "SELECT c.id_cita,c.fecha,c.hora,c.hora_fin,c.duracion_minutos,c.margen_minutos,c.estado,
            c.hora_inicio_real,c.hora_fin_real,c.inicio_estimado,c.fin_estimado,c.duracion_activa_objetivo_segundos,
            p.inicio AS pausa_inicio,p.fin AS pausa_fin
            FROM citas c LEFT JOIN pausas_cita p ON p.id_cita=c.id_cita
            WHERE (c.id_veterinario=? OR c.id_mascota=?) AND c.estado NOT IN ('cancelada','no_asistio')
            AND (c.fecha=? OR (c.fecha<=? AND c.duracion_minutos>0 AND c.margen_minutos IS NOT NULL AND c.estado IN ('pendiente','confirmada'))
                OR (c.hora_inicio_real<? AND (c.hora_fin_real IS NULL OR c.hora_fin_real>=?))
                OR (c.inicio_estimado<? AND c.fin_estimado>=?))";
        $finDia = IntervalosAgenda::fecha($fecha . ' 00:00:00')->modify('+1 day')->format('Y-m-d H:i:s');
        $parametros = [$vet, $mascota, $fecha, $fecha, $finDia, $fecha . ' 00:00:00', $finDia, $fecha . ' 00:00:00'];
        if ($excluir !== null) {
            $sql .= ' AND c.id_cita<>?';
            $parametros[] = $excluir;
        }
        $sql .= ' ORDER BY c.id_cita,p.inicio';
        $citas = [];
        foreach ($this->filas($sql, $parametros) as $fila) {
            $id = (int) $fila['id_cita'];
            $citas[$id] ??= ['fila' => $fila, 'pausas' => []];
            if ($fila['pausa_inicio'] !== null) {
                $citas[$id]['pausas'][] = [$fila['pausa_inicio'], $fila['pausa_fin']];
            }
        }
        $resultado = [];
        foreach ($citas as $datos) {
            $c = $datos['fila'];
            $s = IntervalosAgenda::fecha($c['fecha'] . ' ' . self::hora($c['hora']))->getTimestamp();
            $duracion = (int) $c['duracion_minutos'];
            $margen = $c['margen_minutos'] === null ? null : (int) $c['margen_minutos'] * 60;
            // Decisión C3 del dueño: incertidumbre no equivale a capacidad libre.
            if ($margen === null || $margen < 0) {
                $this->requiereRevision();
            }
            if (in_array($c['estado'], ['pendiente', 'confirmada'], true)) {
                if ($duracion > 0) {
                    $fin = $s + $duracion * 60;
                } elseif ($c['hora_fin'] !== null) {
                    $fin = IntervalosAgenda::fecha($c['fecha'] . ' ' . self::hora($c['hora_fin']))->getTimestamp();
                    if ($fin <= $s) {
                        $this->requiereRevision();
                    }
                } else {
                    $this->requiereRevision();
                }
                $inicio = $c['inicio_estimado'] === null ? $s : IntervalosAgenda::fecha($c['inicio_estimado'])->getTimestamp();
                $fin = $c['fin_estimado'] === null ? $inicio + ($fin - $s) : IntervalosAgenda::fecha($c['fin_estimado'])->getTimestamp();
                $resultado[] = [min($s, $inicio), max($s + $duracion * 60, $fin) + $margen];
                continue;
            }
            if ($c['hora_inicio_real'] === null || (int) $c['duracion_activa_objetivo_segundos'] <= 0) {
                $this->requiereRevision();
            }
            $r = IntervalosAgenda::fecha($c['hora_inicio_real'])->getTimestamp();
            $nominal = $r + (int) $c['duracion_activa_objetivo_segundos'];
            foreach ($datos['pausas'] as [$desde, $hasta]) {
                if ($hasta === null) {
                    $this->requiereRevision();
                }
                $nominal += IntervalosAgenda::fecha($hasta)->getTimestamp() - IntervalosAgenda::fecha($desde)->getTimestamp();
            }
            if ($c['estado'] === 'pausada') {
                $this->requiereRevision();
            }
            if ($c['estado'] === 'completada' && $c['hora_fin_real'] === null) {
                $this->requiereRevision();
            }
            if ($c['hora_fin_real'] !== null) {
                $fin = IntervalosAgenda::fecha($c['hora_fin_real'])->getTimestamp();
            } else {
                $restante = max(0, $nominal - $ahora);
                $fin = max($nominal, $ahora + $restante);
                if ($c['fin_estimado'] !== null) {
                    $fin = max($fin, IntervalosAgenda::fecha($c['fin_estimado'])->getTimestamp());
                }
                // A.3: agotada la cota no se anuncia liberación sin cierre real.
                if ($ahora >= max($nominal + $margen, $fin)) {
                    $this->requiereRevision();
                }
            }
            $resultado[] = [min($s, $r), max($nominal + $margen, $fin)];
        }
        return $resultado;
    }

    private function requiereRevision(): never
    {
        throw new HorarioOcupado('La disponibilidad de esta fecha requiere revisión.');
    }

    public function exigir(int $vet, int $mascota, string $fecha, string $hora, int $duracion, int $margen, ?int $excluir = null): void
    {
        $inicio = IntervalosAgenda::fecha($fecha . ' ' . self::hora($hora));
        $fin = $inicio->modify('+' . ($duracion + $margen) . ' minutes');
        $corte = ($this->reloj)()->getTimestamp();
        if ($duracion <= 0 || $margen < 0 || $inicio->getTimestamp() < $corte || $fin->format('Y-m-d') !== $fecha
            || !IntervalosAgenda::contiene($this->franjas($vet, $mascota, $fecha, $excluir, $corte), $inicio->getTimestamp(), $fin->getTimestamp())) {
            throw new HorarioOcupado('No hay disponibilidad para el intervalo completo. Elige otro horario.');
        }
    }

    public function sugerir(int $vet, int $mascota, string $fecha, int $duracion, int $margen, ?int $excluir = null): array
    {
        $ahora = ($this->reloj)()->getTimestamp();
        $libres = $this->franjas($vet, $mascota, $fecha, $excluir, $ahora);
        $horas = [];
        foreach ($this->jornada($vet, $fecha) as [$desde, $hasta]) {
            for ($inicio = $desde; $inicio + ($duracion + $margen) * 60 <= $hasta; $inicio += $duracion * 60) {
                if ($inicio >= $ahora && IntervalosAgenda::contiene($libres, $inicio, $inicio + ($duracion + $margen) * 60)) {
                    $instante = (new DateTimeImmutable('@' . $inicio))->setTimezone(new DateTimeZone('America/Bogota'));
                    // RE-4.9.1/2: HH:mm solo abrevia segundos cero, nunca redondea la sugerencia.
                    $horas[] = $instante->format($instante->format('s') === '00' ? 'H:i' : 'H:i:s');
                }
            }
        }
        return array_values(array_unique($horas));
    }

    private function filas(string $sql, array $parametros): array
    {
        $consulta = $this->conn->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }
}
