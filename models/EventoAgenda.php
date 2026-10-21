<?php
require_once __DIR__ . '/NotificacionInterna.php';
require_once __DIR__ . '/../helpers/CoordinadorAgenda.php';

/** B1/B1.1: causa durable, destinatario estable y contenido reconstruido antes de enviar. */
final class EventoAgenda
{
    public static function despacharPendientes(PDO $db, callable $transportar): int
    {
        $pendientes = $db->query("SELECT id_evento,id_clinica FROM eventos_agenda WHERE canal='correo' AND estado IN ('pendiente','error') ORDER BY fecha,id_evento")->fetchAll(PDO::FETCH_ASSOC);
        $enviados = 0;
        foreach ($pendientes as $evento) {
            try {
                $enviados += (int) (new self($db, (int) $evento['id_clinica']))->despachar((int) $evento['id_evento'], $transportar);
            } catch (Throwable $error) {
                error_log('Agenda: no se pudo despachar el evento #' . $evento['id_evento']);
            }
        }
        return $enviados;
    }

    public function __construct(private PDO $db, private int $clinica)
    {
    }

    public function interno(string $tipo, ?int $cita, ?int $solicitud, int $version, ?int $usuario, ?int $rol): void
    {
        $id = $this->crear($tipo, $cita, $solicitud, $version, $usuario, $rol, 'interno');
        $q = $this->db->prepare("SELECT estado FROM eventos_agenda WHERE id_evento=? AND id_clinica=?");
        $q->execute([$id, $this->clinica]);
        if ($q->fetchColumn() !== 'pendiente') {
            return;
        }
        $avisos = new NotificacionInterna($this->db);
        $enlace = 'index.php?action=' . ($rol === Roles::ADMIN ? 'admin_citas' : 'vet_agenda');
        $mensaje = $solicitud ? 'Solicitud de agenda #' . $solicitud . '. Consulta la revisión en la agenda.' : 'Hay un cambio en la cita #' . $cita . '.';
        $creada = $usuario !== null
            ? $avisos->crearParaUsuario($this->clinica, $usuario, $tipo, $tipo, $mensaje, $enlace, $cita)
            : $avisos->crearParaRol($this->clinica, $rol, $tipo, $tipo, $mensaje, $enlace, $cita);
        if (!$creada) {
            throw new RuntimeException('No se pudo guardar el aviso.');
        }
        $this->db->prepare("UPDATE eventos_agenda SET estado='enviado',fecha_resultado=CURRENT_TIMESTAMP WHERE id_evento=? AND id_clinica=?")->execute([$id, $this->clinica]);
    }

    public function correo(string $tipo, int $cita, int $version, int $propietario): void
    {
        $this->crear($tipo, $cita, null, $version, $propietario, null, 'correo');
    }

    private function crear(string $tipo, ?int $cita, ?int $solicitud, int $version, ?int $usuario, ?int $rol, string $canal): int
    {
        if (!$this->db->inTransaction()) {
            throw new LogicException('El evento pertenece a la misma unidad de agenda.');
        }
        $clave = hash('sha256', json_encode([$tipo, $cita, $solicitud, $version], JSON_THROW_ON_ERROR));
        $destino = $usuario !== null ? 'usuario:' . $usuario : 'rol:' . $rol;
        $q = $this->db->prepare('SELECT id_evento FROM eventos_agenda WHERE id_clinica=? AND clave_evento=? AND destino_clave=? AND canal=?');
        $q->execute([$this->clinica, $clave, $destino, $canal]);
        $id = $q->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }
        $this->db->prepare('INSERT INTO eventos_agenda (id_clinica,id_cita,id_solicitud,tipo,clave_evento,destino_clave,id_destinatario,id_rol_destino,canal,version_recurso) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([$this->clinica, $cita, $solicitud, $tipo, $clave, $destino, $usuario, $rol, $canal, $version]);
        return (int) $this->db->lastInsertId();
    }

    /** Una reclamación puede haber transmitido: nunca invalidarla ni reenviarla a ciegas. */
    public function exigirSinDespacho(int $cita): void
    {
        $q = $this->db->prepare("SELECT 1 FROM eventos_agenda WHERE id_clinica=? AND id_cita=? AND estado IN ('reclamado','incierto')");
        $q->execute([$this->clinica, $cita]);
        if ($q->fetchColumn()) {
            throw new InvalidArgumentException('Hay una comunicación en curso. Reintenta cuando termine.');
        }
    }

    public function despachar(int $evento, callable $transportar): bool
    {
        $reclamacion = bin2hex(random_bytes(32));
        $unidad = new CoordinadorAgenda($this->db);
        $leer = function () use ($evento): array {
            $q = $this->db->prepare('SELECT e.*,u.estado AS usuario_activo,CASE WHEN pc.estado=\'activo\' THEN 1 ELSE 0 END AS vinculo_activo,CASE WHEN cl.estado=\'activa\' THEN 1 ELSE 0 END AS clinica_activa,c.id_mascota,c.id_veterinario,c.version_agenda,c.estado AS estado_cita,c.fecha,c.hora,m.id_propietario,u.email,u.nombre_completo AS propietario_nombre,m.nombre AS mascota_nombre,v.nombre_completo AS veterinario_nombre,cl.nombre AS clinica_nombre,cl.url_logo AS clinica_logo,cl.telefono AS clinica_telefono,cl.telefono_urgencias AS clinica_urgencias,cl.email_contacto AS clinica_email,cl.direccion AS clinica_direccion FROM eventos_agenda e JOIN citas c ON c.id_cita=e.id_cita AND c.id_clinica=e.id_clinica JOIN mascotas m ON m.id_mascota=c.id_mascota JOIN usuarios v ON v.id_usuario=c.id_veterinario JOIN usuarios u ON u.id_usuario=m.id_propietario LEFT JOIN propietario_clinica pc ON pc.id_propietario=u.id_usuario AND pc.id_clinica=c.id_clinica JOIN clinicas cl ON cl.id_clinica=c.id_clinica WHERE e.id_evento=? AND e.id_clinica=? AND e.canal=\'correo\'');
            $q->execute([$evento, $this->clinica]);
            return $q->fetch(PDO::FETCH_ASSOC) ?: [];
        };
        $contenido = $unidad->ejecutar($this->clinica, function () use ($leer): array {
            $e = $leer();
            return ['usuarios' => array_filter([$e['id_veterinario'] ?? null, $e['id_destinatario'] ?? null]), 'mascotas' => [$e['id_mascota'] ?? 0]];
        }, function () use ($leer, $evento, $reclamacion): array {
            $e = $leer();
            if (!$e || !in_array($e['estado'], ['pendiente', 'error'], true)) {
                return [];
            }
            $legitimo = (int) $e['id_propietario'] === (int) $e['id_destinatario'] && (int) $e['version_agenda'] === (int) $e['version_recurso'];
            $legitimo = $legitimo && $e['usuario_activo'] && $e['vinculo_activo'] && $e['clinica_activa'];
            $legitimo = $legitimo && ($e['tipo'] === 'CITA_CANCELADA' ? $e['estado_cita'] === 'cancelada' : in_array($e['estado_cita'], Cita::ESTADOS_ABIERTOS, true));
            if (!$legitimo) {
                $this->db->prepare("UPDATE eventos_agenda SET estado='omitido',error_tecnico='Causa o destinatario sin vigencia' WHERE id_evento=? AND id_clinica=?")->execute([$evento, $this->clinica]);
                return [];
            }
            $this->db->prepare("UPDATE eventos_agenda SET estado='reclamado',reclamacion=?,fecha_reclamacion=CURRENT_TIMESTAMP,intentos=intentos+1 WHERE id_evento=? AND id_clinica=?")->execute([$reclamacion, $evento, $this->clinica]);
            return $e;
        });
        if (!$contenido) {
            return false;
        }
        try {
            // El booleano del transporte no prueba que un timeout ocurriera antes de DATA.
            $estado = $transportar($contenido) === true ? 'enviado' : 'incierto';
        } catch (Throwable $error) {
            // No se demuestra si SMTP aceptó el mensaje; requiere resolución operativa.
            $estado = 'incierto';
        }
        $q = $this->db->prepare("UPDATE eventos_agenda SET estado=?,fecha_resultado=CURRENT_TIMESTAMP WHERE id_evento=? AND id_clinica=? AND reclamacion=? AND estado='reclamado'");
        $q->execute([$estado, $evento, $this->clinica, $reclamacion]);
        return $estado === 'enviado';
    }
}
