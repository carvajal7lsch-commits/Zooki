<?php
require_once __DIR__ . '/../helpers/Security.php';
require_once __DIR__ . '/../helpers/Transaccion.php';
require_once __DIR__ . '/../helpers/ValidadorPlataforma.php';
require_once __DIR__ . '/../helpers/LimitePlan.php';

/** RN-004/G13/G14: excepción de plataforma, limitada a metadatos; nunca datos clínicos. */
final class Plataforma
{
    public function __construct(private PDO $db)
    {
        $this->exigirPlataforma();
    }

    private function exigirPlataforma(): void
    {
        if ((Contexto::actual()['tipo'] ?? null) !== Contexto::PLATAFORMA || Contexto::rolActivo() !== Roles::SUPER_ADMIN) {
            throw new AccesoDenegado(403, 'Esta acción requiere el contexto de plataforma.');
        }
        $stmt = $this->db->prepare('SELECT 1 FROM usuarios WHERE id_usuario = ? AND es_super_admin = 1 AND estado = 1');
        $stmt->execute([Contexto::idUsuario()]);
        if (!$stmt->fetchColumn()) {
            throw new AccesoDenegado(403, 'Esta acción requiere una cuenta de plataforma activa.');
        }
    }

    private function bloqueo(): string
    {
        return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    }

    public function clinicas(string $buscar = '', string $estado = ''): array
    {
        $this->exigirPlataforma();
        $buscar = ValidadorPlataforma::busqueda($buscar);
        if (mb_strlen($buscar) > ValidadorPlataforma::BUSQUEDA_MAX || !in_array($estado, ['', 'activa', 'pendiente_verificacion', 'suspendida', 'baja'], true)) {
            throw new InvalidArgumentException('Revisa la búsqueda y el estado.');
        }
        $sql = "SELECT c.id_clinica, c.nombre, c.nit, c.estado, c.fecha_registro, c.id_plan, p.nombre AS plan,
            s.estado AS suscripcion,
            (SELECT u.email FROM usuario_clinica uc JOIN usuarios u ON u.id_usuario = uc.id_usuario WHERE uc.id_clinica = c.id_clinica AND uc.id_rol = 1 AND uc.estado = 'activo' AND u.estado = 1 ORDER BY u.id_usuario LIMIT 1) AS administrador,
            (SELECT v.email FROM verificaciones_email v WHERE v.id_clinica_vinculo = c.id_clinica AND v.proposito IN ('registro_clinica','vinculo_admin_clinica') AND v.used = 0 ORDER BY v.id DESC LIMIT 1) AS administrador_pendiente
            FROM clinicas c LEFT JOIN planes p ON p.id_plan = c.id_plan
            LEFT JOIN suscripciones s ON s.id_suscripcion = (SELECT MAX(ss.id_suscripcion) FROM suscripciones ss WHERE ss.id_clinica = c.id_clinica)
            WHERE (? = '' OR c.estado = ?) AND (? = '' OR c.nombre LIKE ? ESCAPE '!' OR c.nit LIKE ? ESCAPE '!') ORDER BY c.fecha_registro DESC, c.id_clinica DESC";
        $patron = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $buscar) . '%';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$estado, $estado, $buscar, $patron, $patron]);
        $clinicas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($clinicas as &$clinica) {
            // E2: excepción explícita de plataforma para números, sin fichas ni citas.
            $clinica['uso_plan'] = (new LimitePlan($this->db, (int) $clinica['id_clinica']))->resumen();
        }
        return $clinicas;
    }

    public function planes(): array
    {
        $this->exigirPlataforma();
        return $this->db->query('SELECT id_plan, nombre FROM planes WHERE estado = 1 ORDER BY id_plan')->fetchAll(PDO::FETCH_ASSOC);
    }

    private function clinica(int $id): array
    {
        $stmt = $this->db->prepare('SELECT id_clinica, estado, id_plan FROM clinicas WHERE id_clinica = ?' . $this->bloqueo());
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: throw new InvalidArgumentException('La clínica no está disponible.');
    }

    private function auditar(string $tabla, int|string $id, ?int $clinica, array $anterior, array $nuevo, string $motivo): void
    {
        if (!(new Auditoria($this->db))->log(Contexto::idUsuario(), 'UPDATE', $tabla, $id, $anterior, $nuevo, $motivo, $clinica)) {
            throw new RuntimeException('No se pudo registrar la auditoría.');
        }
    }

    public function cambiarSuscripcion(int $id, int $plan, string $estado, string $motivo): void
    {
        $this->exigirPlataforma();
        $motivo = ValidadorPlataforma::texto($motivo);
        if (!in_array($estado, ['activa', 'suspendida', 'cancelada'], true)) {
            throw new InvalidArgumentException('Elige un estado de suscripción válido.');
        }
        Transaccion::ejecutar($this->db, function () use ($id, $plan, $estado, $motivo): void {
            $antes = $this->clinica($id);
            if ($antes['estado'] === 'pendiente_verificacion' || $antes['estado'] === 'baja') {
                throw new InvalidArgumentException('La clínica debe estar verificada y no dada de baja.');
            }
            $stmt = $this->db->prepare('SELECT id_plan FROM planes WHERE id_plan = ? AND estado = 1');
            $stmt->execute([$plan]);
            if (!$stmt->fetchColumn()) {
                throw new InvalidArgumentException('Elige un plan vigente.');
            }
            $stmt = $this->db->prepare('SELECT * FROM suscripciones WHERE id_clinica = ? ORDER BY id_suscripcion DESC LIMIT 1' . $this->bloqueo());
            $stmt->execute([$id]);
            $suscripcion = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($suscripcion) {
                $this->db->prepare('UPDATE suscripciones SET id_plan = ?, estado = ? WHERE id_suscripcion = ?')->execute([$plan, $estado, $suscripcion['id_suscripcion']]);
            } else {
                // El gratuito no crea suscripción al activar (D3); se usan los valores del MER.
                $this->db->prepare('INSERT INTO suscripciones (id_clinica, id_plan, estado, fecha_inicio) VALUES (?, ?, ?, ?)')->execute([$id, $plan, $estado, date('Y-m-d')]);
            }
            $this->db->prepare('UPDATE clinicas SET id_plan = ? WHERE id_clinica = ?')->execute([$plan, $id]);
            $this->auditar('clinicas', $id, $id, $antes + ['suscripcion' => $suscripcion], ['id_plan' => $plan, 'suscripcion' => $estado], $motivo);
        });
    }

    public function cambiarEstado(int $id, string $estado, string $motivo): void
    {
        $this->exigirPlataforma();
        $motivo = ValidadorPlataforma::texto($motivo);
        Transaccion::ejecutar($this->db, function () use ($id, $estado, $motivo): void {
            $antes = $this->clinica($id);
            $permitidos = ['activa' => ['suspendida', 'baja'], 'pendiente_verificacion' => ['suspendida', 'baja'], 'suspendida' => ['activa', 'baja'], 'baja' => []];
            if (!in_array($estado, $permitidos[$antes['estado']] ?? [], true)) {
                throw new InvalidArgumentException('Ese cambio de estado no está disponible.');
            }
            if ($estado === 'activa' && $antes['id_plan'] === null) {
                throw new InvalidArgumentException('No se puede activar una clínica cuyo registro no se ha verificado.');
            }
            $this->db->prepare('UPDATE clinicas SET estado = ? WHERE id_clinica = ?')->execute([$estado, $id]);
            $this->auditar('clinicas', $id, $id, $antes, ['estado' => $estado], $motivo);
        });
    }

    public function casos(): array
    {
        $this->exigirPlataforma();
        return $this->db->query('SELECT id_caso, tipo, id_clinica, descripcion, estado, fecha, fecha_cierre FROM casos_soporte ORDER BY fecha DESC, id_caso DESC')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cerrarCaso(int $id, string $estado, string $nota): void
    {
        $this->exigirPlataforma();
        $nota = ValidadorPlataforma::texto($nota);
        if (!in_array($estado, ['resuelto', 'descartado'], true)) {
            throw new InvalidArgumentException('Elige resuelto o descartado.');
        }
        Transaccion::ejecutar($this->db, function () use ($id, $estado, $nota): void {
            $stmt = $this->db->prepare('SELECT id_caso, id_clinica, estado FROM casos_soporte WHERE id_caso = ?' . $this->bloqueo());
            $stmt->execute([$id]);
            $antes = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$antes || $antes['estado'] !== 'abierto') {
                throw new InvalidArgumentException('El caso ya está cerrado o no está disponible.');
            }
            $this->db->prepare('UPDATE casos_soporte SET estado = ?, fecha_cierre = ? WHERE id_caso = ?')->execute([$estado, date('Y-m-d H:i:s'), $id]);
            $this->auditar('casos_soporte', $id, $antes['id_clinica'] === null ? null : (int) $antes['id_clinica'], $antes, ['estado' => $estado, 'nota' => $nota], $nota);
        });
    }

    public function ips(): array
    {
        $this->exigirPlataforma();
        $stmt = $this->db->prepare("SELECT identificador, primer_intento, bloqueado_hasta FROM intentos_login WHERE identificador LIKE 'ip:%' AND bloqueado_hasta > ? ORDER BY bloqueado_hasta");
        $stmt->execute([date('Y-m-d H:i:s')]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($filas as &$fila) {
            $fila['ip'] = substr($fila['identificador'], 3);
            $cuentas = $this->db->prepare("SELECT DISTINCT a.id_usuario, u.email, u.nombre_completo FROM auditoria_sistema a LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario WHERE a.accion = 'LOGIN_FAIL' AND a.ip_address = ? AND a.fecha_hora >= ? AND a.fecha_hora <= ?");
            $cuentas->execute([$fila['ip'], $fila['primer_intento'], date('Y-m-d H:i:s')]);
            $fila['cuentas'] = $cuentas->fetchAll(PDO::FETCH_ASSOC);
            $fila['administradores'] = [];
            foreach ($fila['cuentas'] as &$cuenta) {
                $clinicas = $this->db->prepare("SELECT DISTINCT c.id_clinica, c.nombre FROM clinicas c WHERE EXISTS (SELECT 1 FROM usuario_clinica uc WHERE uc.id_usuario = ? AND uc.id_clinica = c.id_clinica) OR EXISTS (SELECT 1 FROM propietario_clinica pc WHERE pc.id_propietario = ? AND pc.id_clinica = c.id_clinica)");
                $clinicas->execute([$cuenta['id_usuario'], $cuenta['id_usuario']]);
                $cuenta['clinicas'] = $clinicas->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cuenta['clinicas'] as $clinica) {
                    $admins = $this->db->prepare("SELECT u.id_usuario, u.email, u.nombre_completo FROM usuarios u JOIN usuario_clinica uc ON uc.id_usuario = u.id_usuario WHERE uc.id_clinica = ? AND uc.id_rol = 1 AND uc.estado = 'activo' AND u.estado = 1");
                    $admins->execute([$clinica['id_clinica']]);
                    foreach ($admins->fetchAll(PDO::FETCH_ASSOC) as $admin) {
                        $fila['administradores'][$clinica['id_clinica'] . ':' . $admin['id_usuario']] = $admin + ['id_clinica' => $clinica['id_clinica'], 'clinica' => $clinica['nombre']];
                    }
                }
            }
            unset($cuenta);
        }
        unset($fila);
        return $filas;
    }

    public function desbloquear(string $ip): void
    {
        $this->exigirPlataforma();
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException('La IP no es válida.');
        }
        Transaccion::ejecutar($this->db, function () use ($ip): void {
            $stmt = $this->db->prepare('SELECT identificador, bloqueado_hasta FROM intentos_login WHERE identificador = ?' . $this->bloqueo());
            $stmt->execute(['ip:' . $ip]);
            $antes = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$antes || strtotime($antes['bloqueado_hasta'] ?? '') <= time()) {
                throw new InvalidArgumentException('La IP ya no está bloqueada.');
            }
            $this->db->prepare('DELETE FROM intentos_login WHERE identificador = ?')->execute(['ip:' . $ip]);
            $this->auditar('intentos_login', $ip, null, $antes, ['desbloqueada' => true], 'Desbloqueo manual de IP (RN-G15)');
        });
    }

    /** Nunca se acepta un correo arbitrario del navegador (decisión 2026-10-09). */
    public function avisar(string $ip, string $destinatario, string $texto, callable $enviar): bool
    {
        $this->exigirPlataforma();
        $texto = ValidadorPlataforma::texto($texto);
        $admin = null;
        foreach ($this->ips() as $fila) {
            if ($fila['ip'] === $ip) {
                $admin = $fila['administradores'][$destinatario] ?? null;
            }
        }
        if ($admin === null) {
            throw new InvalidArgumentException('Elige un administrador de las clínicas de esta IP bloqueada.');
        }
        // Primero se conserva la intención; un fallo de auditoría impide enviar un correo sin rastro.
        $this->auditar('intentos_login', $ip, (int) $admin['id_clinica'], [], ['destinatario' => $admin['email'], 'texto' => $texto, 'estado' => 'solicitado'], 'Aviso manual de IP bloqueada');
        $enviado = $enviar($admin, $texto);
        $this->auditar('intentos_login', $ip, (int) $admin['id_clinica'], [], ['destinatario' => $admin['email'], 'enviado' => $enviado], 'Resultado del aviso manual de IP bloqueada');
        return $enviado;
    }
}
