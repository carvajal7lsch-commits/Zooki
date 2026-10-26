<?php
require_once __DIR__ . '/../helpers/ValidadorClinica.php';
require_once __DIR__ . '/../helpers/NitDuplicado.php';
require_once __DIR__ . '/../helpers/Transaccion.php';
require_once __DIR__ . '/../helpers/InicializadorClinica.php';
require_once __DIR__ . '/ConsentimientoDatos.php';
require_once __DIR__ . '/Auditoria.php';
require_once __DIR__ . '/VerificacionEmail.php';

/**
 * HU-0.1/0.2: excepción pública de plataforma. Ninguna lectura de negocio se
 * libera aquí; el único ámbito accesible es el de un enlace secreto vigente.
 */
final class RegistroClinica
{
    public const VIGENCIA_HORAS = 24;
    public const MAXIMO_DIARIO = 3;
    public const REPORTES_DIARIOS = 5;
    public const MENSAJE = 'Revisa tu correo para activar la clínica';
    private const NUEVA = 'registro_clinica';
    private const EXISTENTE = 'vinculo_admin_clinica';

    public function __construct(private PDO $db)
    {
    }

    /** RE-0.1.6: contador diario con fila única bloqueada; también serializa envíos concurrentes. */
    private function contarRegistro(string $ip, string $prefijo = 'alta-clinica', int $maximo = self::MAXIMO_DIARIO): void
    {
        $clave = $prefijo . ':' . date('Y-m-d') . ':' . $ip;
        $ahora = date('Y-m-d H:i:s');
        $insertar = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
        $this->db->prepare("$insertar INTO intentos_login (identificador, intentos, primer_intento, ultimo_intento) VALUES (?, 0, ?, ?)")
            ->execute([$clave, $ahora, $ahora]);
        $stmt = $this->db->prepare('SELECT intentos FROM intentos_login WHERE identificador = ?' . $this->bloqueo());
        $stmt->execute([$clave]);
        if ((int) $stmt->fetchColumn() >= $maximo) {
            throw new InvalidArgumentException('Alcanzaste el límite de solicitudes de hoy. Inténtalo mañana.');
        }
        $this->db->prepare('UPDATE intentos_login SET intentos = intentos + 1, ultimo_intento = ? WHERE identificador = ?')
            ->execute([$ahora, $clave]);
    }

    private function bloqueo(): string
    {
        return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    }

    private function porNit(string $nit): ?array
    {
        // E2: el mismo NIT antiguo con guion también reserva el número (RE-0.1.2).
        $stmt = $this->db->prepare("SELECT id_clinica FROM clinicas WHERE REPLACE(nit, '-', '') = ?");
        $stmt->execute([$nit]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** RE-0.1.2: el NIT es público; no devuelve identidad ni contacto. */
    public function nitExiste(string $valor): bool
    {
        return $this->porNit(ValidadorNit::normalizarEntrada($valor)) !== null;
    }

    public function registrar(array $entrada, string $ip): array
    {
        $datos = ValidadorClinica::formulario($entrada);
        if ($this->porNit($datos['nit']) !== null) {
            throw new NitDuplicado();
        }
        try {
            return Transaccion::ejecutar($this->db, function () use ($datos, $ip): array {
                $this->contarRegistro($ip);
                $this->db->prepare("INSERT INTO clinicas (nombre, nit, direccion, telefono, email_contacto, estado) VALUES (?, ?, ?, ?, ?, 'pendiente_verificacion')")
                    ->execute([$datos['nombre_clinica'], $datos['nit'], $datos['direccion'], $datos['telefono_clinica'], $datos['email_clinica']]);
                $clinica = (int) $this->db->lastInsertId();
                $usuarios = new Usuario($this->db);
                // Decisión 2026-10-10: prevalece correo; si es nuevo, confirmar al titular del documento.
                $persona = $usuarios->buscarPorEmail($datos['email']) ?? $usuarios->buscarPorDocumento($datos['documento']);
                $nueva = $persona === null;
                if ($nueva) {
                    $datosCuenta = $datos;
                    $datosCuenta['password'] = password_hash($datos['password'], PASSWORD_DEFAULT);
                    $id = $usuarios->crear($datosCuenta);
                    $this->db->prepare('UPDATE usuarios SET password = ?, estado = 0 WHERE id_usuario = ?')
                        ->execute([$datosCuenta['password'], $id]);
                    (new ConsentimientoDatos($this->db))->registrar($id, PoliticaDatos::MEDIO_FORMULARIO, $ip);
                    $persona = $usuarios->buscarPorId($id);
                }
                $token = bin2hex(random_bytes(32));
                $this->db->prepare('INSERT INTO verificaciones_email (id_usuario, email, proposito, id_clinica_vinculo, id_rol_vinculo, token_hash, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$persona['id_usuario'], $persona['email'], $nueva ? self::NUEVA : self::EXISTENTE, $clinica, Roles::ADMIN, password_hash($token, PASSWORD_DEFAULT), date('Y-m-d H:i:s', time() + 86400)]);
                $enlace = ['id' => (int) $this->db->lastInsertId(), 'token' => $token];
                $this->auditar(null, 'INSERT', $clinica, 'Registro de clínica pendiente solicitado (RE-0.1.9)');
                return ['email' => $persona['email'], 'nombre' => $persona['nombre_completo'], 'clinica' => $datos['nombre_clinica'], 'nueva' => $nueva, 'enlace' => $enlace];
            });
        } catch (PDOException $e) {
            if ($this->porNit($datos['nit']) !== null) {
                throw new NitDuplicado();
            }
            throw $e;
        }
    }

    /** GET de confirmación: solo lectura. Nunca habilita ni vincula a la persona. */
    public function leer(int $id, string $token): ?array
    {
        $fila = (new VerificacionEmail($this->db))->buscarPorId($id);
        if ($fila === null || !in_array($fila['proposito'], [self::NUEVA, self::EXISTENTE], true)
            || (int) $fila['used'] !== 0 || strtotime($fila['expires_at']) <= time()
            || !password_verify($token, $fila['token_hash'])) {
            return null;
        }
        $stmt = $this->db->prepare("SELECT nombre FROM clinicas WHERE id_clinica = ? AND estado = 'pendiente_verificacion'");
        $stmt->execute([$fila['id_clinica_vinculo']]);
        $nombre = $stmt->fetchColumn();
        return $nombre !== false ? $fila + ['clinica' => $nombre] : null;
    }

    /**
     * M0.2 (bitácora de F1): quien abre el enlace ya probó que el correo es
     * suyo, así que se le puede decir por qué no sigue: un super-administrador
     * no tiene roles de clínica (RN-G13).
     */
    public const MENSAJE_SUPER_ADMIN = 'Esta cuenta administra la plataforma y no puede administrar una clínica. Regístrala con otro correo.';

    public function esDeSuperAdministrador(array $fila): bool
    {
        $stmt = $this->db->prepare('SELECT es_super_admin FROM usuarios WHERE id_usuario = ?');
        $stmt->execute([(int) $fila['id_usuario']]);
        return (int) $stmt->fetchColumn() === 1;
    }

    public function activar(int $id, string $token, array $entrada, string $ip): int
    {
        return Transaccion::ejecutar($this->db, function () use ($id, $token, $entrada, $ip): int {
            $fila = $this->leer($id, $token);
            if ($fila === null) {
                throw new InvalidArgumentException('El enlace no está disponible. Registra la clínica nuevamente si venció.');
            }
            if (($entrada['acepta_datos'] ?? '') !== '1') {
                throw new InvalidArgumentException('Debes aceptar la política de tratamiento de datos.');
            }
            $clinica = (int) $fila['id_clinica_vinculo'];
            $usuario = (int) $fila['id_usuario'];
            // Orden común con la limpieza: clínica, persona, enlace.
            $stmt = $this->db->prepare('SELECT estado FROM clinicas WHERE id_clinica = ?' . $this->bloqueo());
            $stmt->execute([$clinica]);
            if ($stmt->fetchColumn() !== 'pendiente_verificacion') {
                throw new InvalidArgumentException('El enlace no está disponible.');
            }
            $stmt = $this->db->prepare('SELECT * FROM usuarios WHERE id_usuario = ?' . $this->bloqueo());
            $stmt->execute([$usuario]);
            $persona = $stmt->fetch(PDO::FETCH_ASSOC);
            $nueva = $fila['proposito'] === self::NUEVA;
            if ($persona && (int) $persona['es_super_admin'] === 1) {
                throw new InvalidArgumentException(self::MENSAJE_SUPER_ADMIN);
            }
            if (!$persona || $persona['email'] !== $fila['email']
                || (int) $persona['estado'] !== ($nueva ? 0 : 1)
                || (!$nueva && (new VerificacionEmail($this->db))->hayPendiente($usuario))) {
                throw new InvalidArgumentException('No se pudo confirmar esta solicitud. Contacta a soporte.');
            }
            $stmt = $this->db->prepare('UPDATE verificaciones_email SET used = 1 WHERE id = ? AND used = 0 AND expires_at > ?');
            $stmt->execute([$id, date('Y-m-d H:i:s')]);
            if ($stmt->rowCount() !== 1) {
                throw new InvalidArgumentException('El enlace no está disponible.');
            }
            $plan = $this->db->query("SELECT id_plan FROM planes WHERE nombre = 'Gratuito' AND estado = 1 AND precio_mensual = 0")->fetchColumn();
            if ($plan === false) {
                throw new RuntimeException('No está disponible el plan gratuito.');
            }
            $this->db->prepare("UPDATE clinicas SET estado = 'activa', id_plan = ? WHERE id_clinica = ?")->execute([$plan, $clinica]);
            if ($nueva) {
                $this->db->prepare('UPDATE usuarios SET estado = 1 WHERE id_usuario = ?')->execute([$usuario]);
            }
            (new Usuario($this->db))->asignarRolEnClinica($usuario, $clinica, Roles::ADMIN);
            (new ConsentimientoDatos($this->db))->registrar($usuario, PoliticaDatos::MEDIO_FORMULARIO, $ip);
            (new InicializadorClinica($this->db))->copiar($clinica);
            $this->auditar($usuario, 'UPDATE', $clinica, 'Clínica activada con plan gratuito, administrador y catálogos iniciales (RE-0.2.2/5)');
            return $clinica;
        });
    }

    private function auditar(?int $usuario, string $accion, int $clinica, string $descripcion): void
    {
        if (!(new Auditoria($this->db))->log($usuario, $accion, 'clinicas', $clinica, null, null, $descripcion, $clinica)) {
            throw new RuntimeException('No se pudo registrar la auditoría de la clínica.');
        }
    }

    public function reportarNit(array $entrada): void
    {
        $valorNit = (string) ($entrada['nit'] ?? '');
        $nit = ValidadorNit::normalizarEntrada($valorNit);
        $email = ValidadorCuenta::normalizar('email', (string) ($entrada['email'] ?? ''));
        if (ValidadorCuenta::correo($email) !== null) {
            throw new InvalidArgumentException('Revisa el NIT y el correo de contacto.');
        }
        $clinica = $this->porNit($nit);
        if ($clinica === null) {
            throw new InvalidArgumentException('No se pudo registrar el reporte. Revisa el NIT.');
        }
        // RN-010: solo se abre el caso; la resolución pertenece al panel de la etapa E.
        Transaccion::ejecutar($this->db, function () use ($clinica, $nit, $email): void {
            $this->contarRegistro(Auditoria::ipCliente(), 'reporte-nit', self::REPORTES_DIARIOS);
            require_once __DIR__ . '/CasoSoporte.php';
            (new CasoSoporte($this->db))->clinicaDuplicada((int) $clinica['id_clinica'], 'Reporte público de NIT ' . $nit . '. Contacto: ' . $email . '. IP: ' . Auditoria::ipCliente());
        });
    }

    /** RE-0.2.3: proceso del sistema; nunca elimina identidades preexistentes. */
    public function limpiarPendientes(): int
    {
        $stmt = $this->db->prepare("SELECT v.id FROM verificaciones_email v JOIN clinicas c ON c.id_clinica = v.id_clinica_vinculo WHERE v.proposito IN (?, ?) AND v.used = 0 AND v.expires_at <= ? AND c.estado = 'pendiente_verificacion'");
        $stmt->execute([self::NUEVA, self::EXISTENTE, date('Y-m-d H:i:s')]);
        $total = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            try {
                $total += Transaccion::ejecutar($this->db, fn (): int => $this->limpiarUna((int) $id));
            } catch (Throwable $e) {
                error_log('D3: no se pudo limpiar la solicitud pendiente ' . (int) $id . '.');
            }
        }
        // La limpieza del personal puede haber borrado el enlace junto con su cuenta.
        $sinEnlace = $this->db->prepare("SELECT c.id_clinica FROM clinicas c WHERE c.estado = 'pendiente_verificacion' AND c.fecha_registro <= ? AND NOT EXISTS (SELECT 1 FROM verificaciones_email v WHERE v.id_clinica_vinculo = c.id_clinica AND v.proposito IN (?, ?))");
        $sinEnlace->execute([date('Y-m-d H:i:s', time() - 86400), self::NUEVA, self::EXISTENTE]);
        foreach ($sinEnlace->fetchAll(PDO::FETCH_COLUMN) as $clinica) {
            try {
                $total += Transaccion::ejecutar($this->db, function () use ($clinica): int {
                    $stmt = $this->db->prepare("SELECT estado FROM clinicas WHERE id_clinica = ?" . $this->bloqueo());
                    $stmt->execute([$clinica]);
                    if ($stmt->fetchColumn() !== 'pendiente_verificacion') {
                        return 0;
                    }
                    $enlace = $this->db->prepare('SELECT 1 FROM verificaciones_email WHERE id_clinica_vinculo = ? LIMIT 1');
                    $enlace->execute([$clinica]);
                    if ($enlace->fetchColumn()) {
                        return 0;
                    }
                    $this->eliminarClinica((int) $clinica);
                    return 1;
                });
            } catch (Throwable $e) {
                error_log('D3: no se pudo limpiar la clínica pendiente sin enlace ' . (int) $clinica . '.');
            }
        }
        return $total;
    }

    private function limpiarUna(int $id): int
    {
        $fila = (new VerificacionEmail($this->db))->buscarPorId($id);
        if ($fila === null) {
            return 0;
        }
        $clinica = (int) $fila['id_clinica_vinculo'];
        $usuario = (int) $fila['id_usuario'];
        $stmt = $this->db->prepare('SELECT estado FROM clinicas WHERE id_clinica = ?' . $this->bloqueo());
        $stmt->execute([$clinica]);
        if ($stmt->fetchColumn() !== 'pendiente_verificacion') {
            return 0;
        }
        $stmt = $this->db->prepare('SELECT estado, es_super_admin FROM usuarios WHERE id_usuario = ?' . $this->bloqueo());
        $stmt->execute([$usuario]);
        $persona = $stmt->fetch(PDO::FETCH_ASSOC);
        $fila = (new VerificacionEmail($this->db))->buscarPorId($id);
        if ($fila === null || (int) $fila['used'] !== 0 || strtotime($fila['expires_at']) > time()) {
            return 0;
        }
        $otros = $this->db->prepare('SELECT 1 FROM usuario_clinica WHERE id_usuario = ? UNION SELECT 1 FROM propietario_clinica WHERE id_propietario = ? UNION SELECT 1 FROM verificaciones_email WHERE id_usuario = ? AND id <> ? AND used = 0');
        $otros->execute([$usuario, $usuario, $usuario, $id]);
        $origen = $this->db->prepare("SELECT 1 FROM verificaciones_email WHERE id_usuario = ? AND proposito = 'alta_clinica_vencida' LIMIT 1");
        $origen->execute([$usuario]);
        $cuentaNueva = $fila['proposito'] === self::NUEVA || (bool) $origen->fetchColumn();
        $borrar = $cuentaNueva && $persona && (int) $persona['estado'] === 0
            && (int) $persona['es_super_admin'] === 0 && !$otros->fetchColumn();
        if (!$borrar && $fila['proposito'] === self::NUEVA) {
            // Conservar el origen mientras otras invitaciones impiden borrar esta identidad.
            $this->db->prepare("UPDATE verificaciones_email SET proposito = 'alta_clinica_vencida', used = 1, id_clinica_vinculo = NULL WHERE id = ?")->execute([$id]);
        }
        $this->eliminarClinica($clinica);
        if ($borrar) {
            $this->db->prepare('DELETE FROM consentimientos_datos WHERE id_usuario = ?')->execute([$usuario]);
            $this->db->prepare('DELETE FROM password_resets WHERE id_usuario = ?')->execute([$usuario]);
            $this->db->prepare('DELETE FROM verificaciones_email WHERE id_usuario = ?')->execute([$usuario]);
            $this->db->prepare('UPDATE auditoria_sistema SET id_usuario = NULL WHERE id_usuario = ?')->execute([$usuario]);
            $this->db->prepare('DELETE FROM usuarios WHERE id_usuario = ? AND estado = 0')->execute([$usuario]);
        }
        return 1;
    }

    private function eliminarClinica(int $clinica): void
    {
        $this->auditar(null, 'DELETE', $clinica, 'Registro de clínica vencido eliminado (RE-0.2.3)');
        $this->db->prepare('DELETE FROM verificaciones_email WHERE id_clinica_vinculo = ?')->execute([$clinica]);
        // Se conserva el caso y la auditoría aunque el alta pendiente ya no exista.
        $this->db->prepare('UPDATE casos_soporte SET id_clinica = NULL WHERE id_clinica = ?')->execute([$clinica]);
        $this->db->prepare('UPDATE auditoria_sistema SET id_clinica = NULL WHERE id_clinica = ?')->execute([$clinica]);
        $this->db->prepare('DELETE FROM clinicas WHERE id_clinica = ?')->execute([$clinica]);
    }
}
