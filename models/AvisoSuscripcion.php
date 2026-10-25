<?php
require_once __DIR__ . '/Suscripcion.php';
require_once __DIR__ . '/../helpers/Roles.php';

/** RN-017: outbox por fin/tipo/usuario; la clínica serializa reserva y reclamación. */
final class AvisoSuscripcion
{
    public function __construct(private PDO $db, private int $clinica, private mixed $reloj = null)
    {
    }

    private function servicio(): Suscripcion
    {
        return new Suscripcion($this->db, $this->clinica, $this->reloj);
    }

    public function destinatarios(): array
    {
        // Identidad v2: la activación verifica correo antes de activar cuenta/vínculo.
        // No confundir una solicitud de cambio de correo con un correo confirmado.
        $q = $this->db->prepare("SELECT u.id_usuario,u.email,u.nombre_completo FROM usuarios u JOIN usuario_clinica uc ON uc.id_usuario=u.id_usuario WHERE uc.id_clinica=? AND uc.id_rol=? AND uc.estado='activo' AND u.estado=1 AND u.perfil_completo=1 AND NOT EXISTS (SELECT 1 FROM verificaciones_email v WHERE v.id_usuario=u.id_usuario AND v.email=u.email AND v.used=0 AND v.proposito IN ('registro','activacion_personal','registro_clinica')) ORDER BY u.id_usuario");
        $q->execute([$this->clinica, Roles::ADMIN]);
        return array_values(array_filter($q->fetchAll(PDO::FETCH_ASSOC), static fn (array $u): bool => filter_var($u['email'], FILTER_VALIDATE_EMAIL) !== false));
    }

    public static function tipo(array $estado, DateTimeImmutable $ahora): ?string
    {
        if ($estado['legado_sin_fecha'] || $estado['periodo_fin'] === null || (int) $estado['plan_contratado']['id_plan'] === (int) $estado['plan_gratuito']['id_plan']
            || $estado['suscripcion']['estado'] !== 'activa' || !in_array($estado['clinica']['estado'], ['activa', 'suspendida'], true)) {
            return null;
        }
        if ($estado['situacion'] === 'congelada') {
            return 'congelada';
        }
        if ($estado['situacion'] !== 'al_dia' || $ahora->format('H:i:s') < PoliticaSuscripcion::HORA_AVISO) {
            return null;
        }
        $fin = VigenciaSuscripcion::fecha($estado['periodo_fin'])->setTime(0, 0);
        $dias = (int) $ahora->setTime(0, 0)->diff($fin)->format('%r%a');
        $c = $estado['clinica'];
        if ($dias <= (int) $c['aviso_vencimiento_2_dias']) {
            return 'vence_1';
        }
        return $dias <= (int) $c['aviso_vencimiento_1_dias'] ? 'vence_7' : null;
    }

    public function reservar(array $estado, bool $soloCongelacion = false): int
    {
        if (!$this->db->inTransaction()) {
            throw new LogicException('Los avisos se reservan con el bloqueo de clínica.');
        }
        $tipo = self::tipo($estado, $this->servicio()->ahora());
        if ($tipo === null || ($soloCongelacion && $tipo !== 'congelada')) {
            return 0;
        }
        $s = $estado['suscripcion'];
        $usuarios = $this->destinatarios();
        if (!$usuarios) {
            $motivo = 'RN-017 sin administrador verificado: ' . $estado['periodo_fin'];
            $q = $this->db->prepare('SELECT 1 FROM auditoria_sistema WHERE id_clinica=? AND tabla_afectada=? AND registro_id=? AND descripcion=?');
            $q->execute([$this->clinica, 'suscripciones', (string) $s['id_suscripcion'], $motivo]);
            if (!$q->fetchColumn()) {
                $this->servicio()->auditar('suscripciones', (int) $s['id_suscripcion'], [], ['incidencia' => 'sin_destinatario'], $motivo);
            }
        }
        $reservados = 0;
        foreach ($usuarios as $usuario) {
            $clave = hash('sha256', json_encode([$this->clinica, $s['id_suscripcion'], $estado['periodo_fin'], $tipo, $usuario['id_usuario']], JSON_THROW_ON_ERROR));
            $q = $this->db->prepare('SELECT id_aviso FROM avisos_suscripcion WHERE clave_aviso=? AND id_clinica=?');
            $q->execute([$clave, $this->clinica]);
            if ($q->fetchColumn()) {
                continue;
            }
            $this->db->prepare('INSERT INTO avisos_suscripcion (id_clinica,id_suscripcion,id_usuario_destinatario,tipo,clave_aviso,vencimiento_objetivo,programado_en) VALUES (?,?,?,?,?,?,?)')
                ->execute([$this->clinica, $s['id_suscripcion'], $usuario['id_usuario'], $tipo, $clave, $estado['periodo_fin'], $this->servicio()->ahora()->format('Y-m-d H:i:s')]);
            $this->servicio()->auditar('avisos_suscripcion', (int) $this->db->lastInsertId(), [], ['estado' => 'pendiente', 'tipo' => $tipo], 'Reserva de aviso (RN-017)', null, 'INSERT');
            $reservados++;
        }
        return $reservados;
    }

    public function despachar(int $id, callable $transportar): bool
    {
        if ($this->db->inTransaction()) {
            throw new LogicException('El SMTP requiere que no exista una transacción exterior.');
        }
        $servicio = $this->servicio();
        $token = bin2hex(random_bytes(32));
        $contenido = $servicio->bloqueada(function () use ($servicio, $id, $token): ?array {
            $q = $this->db->prepare('SELECT * FROM avisos_suscripcion WHERE id_aviso=? AND id_clinica=?');
            $q->execute([$id, $this->clinica]);
            $aviso = $q->fetch(PDO::FETCH_ASSOC);
            if (!$aviso) {
                return null;
            }
            $ahora = $servicio->ahora()->format('Y-m-d H:i:s');
            if ($aviso['estado'] === 'reclamado' && $aviso['lease_hasta'] <= $ahora) {
                $this->cambiarEstado($aviso, 'incierto');
                return null;
            }
            if ($aviso['estado'] !== 'pendiente' || $aviso['programado_en'] > $ahora) {
                return null;
            }
            $estado = $servicio->estado();
            $destinatarios = array_column($this->destinatarios(), null, 'id_usuario');
            $usuario = $destinatarios[$aviso['id_usuario_destinatario']] ?? null;
            // Una reserva anterior no puede avisar del fin antiguo tras una renovación.
            if (!$usuario || $aviso['vencimiento_objetivo'] !== $estado['periodo_fin'] || self::tipo($estado, $servicio->ahora()) !== $aviso['tipo']) {
                $this->cambiarEstado($aviso, 'obsoleto');
                return null;
            }
            $asunto = $aviso['tipo'] === 'congelada' ? 'Beneficios del plan congelados' : 'Tu suscripción vence pronto';
            $mensaje = $asunto . '. Clínica: ' . $estado['clinica']['nombre'] . '. Fin del periodo: ' . $estado['periodo_fin'] . '. Fin de gracia: ' . $estado['gracia_hasta'] . '. Tus datos se conservan.';
            $this->db->prepare("INSERT INTO notificaciones (id_clinica,id_usuario,tipo_entidad,id_entidad,destinatario_email,tipo_notificacion,asunto,mensaje,estado) VALUES (?,?,'suscripcion',?,?,?,?,?,'pendiente')")
                ->execute([$this->clinica, $usuario['id_usuario'], $aviso['id_suscripcion'], $usuario['email'], 'suscripcion_' . $aviso['tipo'], $asunto, $mensaje]);
            $notificacion = (int) $this->db->lastInsertId();
            $hasta = $servicio->ahora()->modify('+' . PoliticaSuscripcion::RECLAMACION_SEGUNDOS . ' seconds')->format('Y-m-d H:i:s');
            $this->db->prepare("UPDATE avisos_suscripcion SET estado='reclamado',lease_token=?,lease_hasta=?,intentos=intentos+1,id_notificacion=? WHERE id_aviso=? AND id_clinica=?")
                ->execute([$token, $hasta, $notificacion, $id, $this->clinica]);
            $servicio->auditar('avisos_suscripcion', $id, ['estado' => 'pendiente'], ['estado' => 'reclamado'], 'Reclamación de correo (RN-017)');
            return $usuario + ['asunto' => $asunto, 'mensaje' => $mensaje, 'id_notificacion' => $notificacion];
        });
        if ($contenido === null) {
            return false;
        }
        // RN-017: el SMTP siempre va después de confirmar la reclamación.
        try {
            $enviado = (bool) $transportar($contenido);
        } catch (Throwable $error) {
            $enviado = false;
        }
        $servicio->bloqueada(function () use ($contenido, $enviado, $token, $id): void {
            $q = $this->db->prepare("SELECT * FROM avisos_suscripcion WHERE id_aviso=? AND id_clinica=? AND lease_token=? AND estado='reclamado'");
            $q->execute([$id, $this->clinica, $token]);
            $aviso = $q->fetch(PDO::FETCH_ASSOC);
            if ($aviso) {
                $this->cambiarEstado($aviso, $enviado ? 'enviado' : 'incierto');
                $this->db->prepare('UPDATE notificaciones SET estado=? WHERE id_notificacion=? AND id_clinica=?')
                    ->execute([$enviado ? 'enviado' : 'error', $contenido['id_notificacion'], $this->clinica]);
            }
        });
        return $enviado;
    }

    private function cambiarEstado(array $aviso, string $estado): void
    {
        $this->db->prepare('UPDATE avisos_suscripcion SET estado=?,enviado_en=? WHERE id_aviso=? AND id_clinica=?')
            ->execute([$estado, $estado === 'enviado' ? $this->servicio()->ahora()->format('Y-m-d H:i:s') : null, $aviso['id_aviso'], $this->clinica]);
        $this->servicio()->auditar('avisos_suscripcion', (int) $aviso['id_aviso'], ['estado' => $aviso['estado']], ['estado' => $estado], 'Resultado de correo (RN-017)');
    }
}
