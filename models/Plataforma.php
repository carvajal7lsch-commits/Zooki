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

    public function clinicas(string $buscar = '', string $estado = '', string $plan = ''): array
    {
        $this->exigirPlataforma();
        $buscar = ValidadorPlataforma::busqueda($buscar);
        if (mb_strlen($buscar) > ValidadorPlataforma::BUSQUEDA_MAX || !in_array($estado, ['', 'activa', 'pendiente_verificacion', 'suspendida', 'baja'], true)
            || ($plan !== '' && !ctype_digit($plan))) {
            throw new InvalidArgumentException('Revisa la búsqueda, el estado y el plan.');
        }
        $sql = "SELECT c.id_clinica, c.nombre, c.nit, c.estado, c.fecha_registro, c.id_plan, p.nombre AS plan,
            NULL AS suscripcion,
            (SELECT u.email FROM usuario_clinica uc JOIN usuarios u ON u.id_usuario = uc.id_usuario WHERE uc.id_clinica = c.id_clinica AND uc.id_rol = 1 AND uc.estado = 'activo' AND u.estado = 1 ORDER BY u.id_usuario LIMIT 1) AS administrador,
            (SELECT v.email FROM verificaciones_email v WHERE v.id_clinica_vinculo = c.id_clinica AND v.proposito IN ('registro_clinica','vinculo_admin_clinica') AND v.used = 0 ORDER BY v.id DESC LIMIT 1) AS administrador_pendiente
            FROM clinicas c LEFT JOIN planes p ON p.id_plan = c.id_plan
            WHERE (? = '' OR c.estado = ?) AND (? = '' OR c.id_plan = ?) AND (? = '' OR c.nombre LIKE ? ESCAPE '!' OR c.nit LIKE ? ESCAPE '!') ORDER BY c.fecha_registro DESC, c.id_clinica DESC";
        $patron = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $buscar) . '%';
        $stmt = $this->db->prepare($sql);
        // M0.2: el NIT se guarda sin guion; buscar «901234567-7» también lo encuentra.
        $patronNit = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], str_replace('-', '', $buscar)) . '%';
        $stmt->execute([$estado, $estado, $plan, $plan === '' ? 0 : (int) $plan, $buscar, $patron, $patronNit]);
        $clinicas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($clinicas as &$clinica) {
            // E2: excepción explícita de plataforma para números, sin fichas ni citas.
            $vigencia = (new Suscripcion($this->db, (int) $clinica['id_clinica']))->estado();
            $clinica['suscripcion'] = $vigencia['suscripcion']['estado'] ?? null;
            $clinica['vigencia'] = $vigencia;
            $clinica['plan_efectivo'] = $vigencia['plan_efectivo']['nombre'];
            $clinica['fecha_fin_suscripcion'] = $vigencia['suscripcion']['fecha_fin'] ?? '';
            $clinica['uso_plan'] = (new LimitePlan($this->db, (int) $clinica['id_clinica']))->resumen();
        }
        return $clinicas;
    }

    public function planes(): array
    {
        $this->exigirPlataforma();
        // M0.2.1: los topes se muestran en las tarjetas de plan del modal (metadatos, RN-004).
        return $this->db->query('SELECT id_plan, nombre, precio_mensual, precio_anual, limite_personal, limite_mascotas, limite_citas_mes FROM planes WHERE estado = 1 ORDER BY id_plan')->fetchAll(PDO::FETCH_ASSOC);
    }

    /** RE-0.3.7: excepción global de plataforma, solo columnas financieras permitidas. */
    public function finanzas(int $id, int $pagina = 1): array
    {
        $this->exigirPlataforma();
        if ($id < 0 || $pagina < 1 || $pagina > 1000000) {
            throw new InvalidArgumentException('La clínica o la página no son válidas.');
        }
        $resumen = null;
        $pagos = [];
        $ajustes = [];
        $total = 0;
        if ($id > 0) {
            $vigencia = (new Suscripcion($this->db, $id))->estado();
            $s = $vigencia['suscripcion'];
            $motivo = $s['motivo_automatico_apagado'] ?? null;
            if (!$motivo && $s && !(int) $s['cobro_automatico']) {
                // S3 auditaba la causa combinada; no atribuir una causa histórica más precisa.
                $q = $this->db->prepare("SELECT descripcion FROM auditoria_sistema WHERE tabla_afectada='suscripciones' AND registro_id=? AND id_clinica=? ORDER BY id_auditoria DESC LIMIT 1");
                $q->execute([(string) $s['id_suscripcion'], $id]);
                if ($q->fetchColumn() === 'Importe o condiciones cambiaron (RE-0.5.26)') {
                    $motivo = 'precio_o_condiciones';
                }
            }
            $resumen = [
                'id_clinica' => $id,
                'nombre' => $vigencia['clinica']['nombre'],
                'contratado' => $vigencia['plan_contratado']['nombre'],
                'efectivo' => $vigencia['plan_efectivo']['nombre'],
                'situacion' => $vigencia['legado_sin_fecha'] ? 'legado_sin_fecha' : $vigencia['situacion'],
                'fin' => VigenciaSuscripcion::fechaIncluida($vigencia['periodo_fin']),
                'automatico' => (bool) ($s['cobro_automatico'] ?? false),
                'motivo_apagado' => $motivo,
            ];
            $q = $this->db->prepare('SELECT COUNT(*) FROM intentos_pago i JOIN pagos_suscripcion p ON p.id_pago=i.id_pago WHERE p.id_clinica=?');
            $q->execute([$id]);
            $total = (int) $q->fetchColumn();
            $q = $this->db->prepare('SELECT i.creado_en AS fecha,i.referencia,i.entorno,i.estado,p.ciclo,p.total_centavos,p.moneda,r.inicio,r.fin FROM intentos_pago i JOIN pagos_suscripcion p ON p.id_pago=i.id_pago LEFT JOIN suscripcion_periodos r ON r.id_pago=p.id_pago AND r.id_clinica=p.id_clinica AND i.estado=\'aprobado\' WHERE p.id_clinica=? ORDER BY i.creado_en DESC,i.id_intento DESC LIMIT 20 OFFSET ' . (($pagina - 1) * 20));
            $q->execute([$id]);
            $pagos = $q->fetchAll(PDO::FETCH_ASSOC);
            $q = $this->db->prepare("SELECT creado_en,inicio,fin,ciclo,motivo,origen FROM suscripcion_periodos WHERE id_clinica=? AND origen IN ('administrativo','legado') ORDER BY creado_en DESC,id_periodo DESC");
            $q->execute([$id]);
            $ajustes = $q->fetchAll(PDO::FETCH_ASSOC);
        }
        $q = $this->db->prepare("SELECT e.id_evento,e.id_transaccion_wompi,e.recibido_en,e.codigo_error,e.entorno,e.revisado_en,e.nota_revision,e.revisado_por,i.referencia FROM wompi_eventos e LEFT JOIN intentos_pago i ON i.id_intento=e.id_intento LEFT JOIN pagos_suscripcion p ON p.id_pago=i.id_pago WHERE e.estado='revision' AND " . ($id === 0 ? 'p.id_clinica IS NULL' : 'p.id_clinica=?') . ' ORDER BY e.recibido_en DESC,e.id_evento DESC');
        $q->execute($id === 0 ? [] : [$id]);
        return ['resumen' => $resumen, 'pagos' => $pagos, 'ajustes' => $ajustes, 'incidencias' => $q->fetchAll(PDO::FETCH_ASSOC), 'pagina' => $pagina, 'hay_mas' => $pagina * 20 < $total, 'id_clinica' => $id];
    }

    /** Revisar no altera la conciliación ni concede o retira periodos (D5). */
    public function revisarIncidencia(int $id, int $clinica, string $nota): void
    {
        $this->exigirPlataforma();
        $nota = ValidadorPlataforma::texto($nota);
        if ($id <= 0 || $clinica < 0) {
            throw new InvalidArgumentException('La incidencia no está disponible.');
        }
        Transaccion::ejecutar($this->db, function () use ($id, $clinica, $nota): void {
            $this->exigirPlataforma();
            $q = $this->db->prepare("SELECT e.id_evento,e.estado,e.revisado_en,e.codigo_error,p.id_clinica FROM wompi_eventos e LEFT JOIN intentos_pago i ON i.id_intento=e.id_intento LEFT JOIN pagos_suscripcion p ON p.id_pago=i.id_pago WHERE e.id_evento=?" . $this->bloqueo());
            $q->execute([$id]);
            $evento = $q->fetch(PDO::FETCH_ASSOC);
            if (!$evento || $evento['estado'] !== 'revision' || (int) ($evento['id_clinica'] ?? 0) !== $clinica) {
                throw new InvalidArgumentException('La incidencia no pertenece a esta sección.');
            }
            if ($evento['revisado_en'] !== null) {
                return;
            }
            $nuevo = ['revisado_por' => Contexto::idUsuario(), 'revisado_en' => date('Y-m-d H:i:s'), 'nota_revision' => $nota];
            $this->db->prepare('UPDATE wompi_eventos SET revisado_por=?,revisado_en=?,nota_revision=? WHERE id_evento=? AND revisado_en IS NULL')->execute([$nuevo['revisado_por'], $nuevo['revisado_en'], $nota, $id]);
            $this->auditar('wompi_eventos', $id, $evento['id_clinica'] === null ? null : (int) $evento['id_clinica'], ['revisado_en' => null, 'codigo_error' => $evento['codigo_error']], $nuevo, 'Revisión financiera sin alterar el pago (RE-0.3.7)');
        });
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

    public function cambiarSuscripcion(int $id, int $plan, string $estado, string $motivo, ?string $fechaFin = null, string $ciclo = 'mensual'): void
    {
        $this->exigirPlataforma();
        (new Suscripcion($this->db, $id))->ajustar($plan, $estado, $motivo, $fechaFin, $ciclo);
    }

    /** RE-0.3.7/0.5.13: nunca aceptar una clínica indicada por el formulario. */
    public function conciliarPago(string $id, string $motivo, ConciliadorPago $conciliador): bool
    {
        $this->exigirPlataforma();
        $motivo = ValidadorPlataforma::texto($motivo);
        if (!FirmaWompi::idValido($id)) {
            throw new InvalidArgumentException('Revisa el ID de transacción Wompi.');
        }
        // Registrar también consultas fallidas; sin auditoría no se consulta ni se acredita.
        $this->auditar('wompi_eventos', 0, null, [], ['id_transaccion' => $id, 'origen' => 'conciliacion_manual'], $motivo);
        return $conciliador->recuperar($id);
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
        // M0.2.1: el nombre de la clínica del caso, para su tarjeta (metadato de plataforma).
        return $this->db->query('SELECT cs.id_caso, cs.tipo, cs.id_clinica, c.nombre AS clinica, cs.descripcion, cs.estado, cs.fecha, cs.fecha_cierre FROM casos_soporte cs LEFT JOIN clinicas c ON c.id_clinica = cs.id_clinica ORDER BY cs.fecha DESC, cs.id_caso DESC')->fetchAll(PDO::FETCH_ASSOC);
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
