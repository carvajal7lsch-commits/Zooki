<?php
require_once __DIR__ . '/Usuario.php';
require_once __DIR__ . '/Auditoria.php';
require_once __DIR__ . '/../helpers/Transaccion.php';
require_once __DIR__ . '/../helpers/Contexto.php';
require_once __DIR__ . '/../helpers/PoliticaDatos.php';
require_once __DIR__ . '/ConsentimientoDatos.php';

/**
 * M5 C3 (decisión del usuario, 2026-10-12; RE-T.19.1, RN-G19, RN-109) —
 * Alta del propietario por el personal, sin aceptación presencial.
 *
 * La clínica registra al propietario con su correo y la cuenta queda
 * pendiente: sin contraseña, sin consentimiento y sin portal (estado 0). El
 * titular recibe un enlace de 72 horas donde él mismo acepta la política y
 * crea su contraseña, como la activación del personal (Modelos §10.1).
 * Mientras tanto la clínica registra y atiende a sus mascotas, pero no le
 * salen correos automáticos (recordatorios, citas, cambios de ficha).
 */
final class ActivacionPropietario
{
    public const PROPOSITO = 'activacion_propietario';
    public const HORAS = 72;
    public const ASUNTO = 'Activa tu cuenta en Zooki';

    /**
     * M5 F (observación 2 de «C4b — Revisión»): cuando el titular pide el
     * enlace él mismo, como mucho uno cada 5 minutos y 3 por hora. Cuentan
     * todos sus enlaces de activación, también los que envió la clínica.
     */
    public const TOPE_ESPERA_SEGUNDOS = 300;
    public const TOPE_POR_HORA = 3;

    /**
     * El único correo que recibe mientras no active su cuenta: lo envían la
     * clínica (al registrarlo o reenviarlo) y, desde C4b, el propio titular al
     * pedirlo con «¿Olvidaste tu contraseña?» o con el autorregistro.
     */
    public static function correoHtml(string $clinica, string $url): string
    {
        $texto = $clinica . ' te registró como cliente en Zooki. Para entrar a tu portal, acepta la política de tratamiento de datos y crea tu contraseña. El enlace vence en ' . self::HORAS . ' horas.';
        return '<p>' . htmlspecialchars($texto, ENT_QUOTES, 'UTF-8') . '</p><p><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">Continuar</a></p>';
    }

    private Auditoria $auditoria;

    public function __construct(private PDO $db)
    {
        $this->auditoria = new Auditoria($db);
    }

    /**
     * Condición SQL: el usuario con alias $alias es una cuenta de propietario
     * que la clínica registró y que todavía no se activa. Sin consentimiento,
     * para no confundirla con una cuenta activada y luego desactivada.
     */
    public static function pendiente(string $alias): string
    {
        return "($alias.estado = 0 AND $alias.password IS NULL AND $alias.es_super_admin = 0"
            . " AND EXISTS (SELECT 1 FROM verificaciones_email vap WHERE vap.id_usuario = $alias.id_usuario AND vap.proposito = '" . self::PROPOSITO . "')"
            . " AND NOT EXISTS (SELECT 1 FROM consentimientos_datos cap WHERE cap.id_usuario = $alias.id_usuario))";
    }

    /** Condición SQL: la cuenta puede ser cliente de una clínica (activa o pendiente de activar). */
    public static function usable(string $alias): string
    {
        return "($alias.estado = 1 OR " . self::pendiente($alias) . ')';
    }

    public function esPendiente(int $idUsuario): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM usuarios u WHERE u.id_usuario = ? AND ' . self::pendiente('u'));
        $stmt->execute([$idUsuario]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Crea la cuenta pendiente, su vínculo con la clínica y el enlace de
     * activación. Va dentro de la transacción de quien llama.
     *
     * @return array{id_usuario: int, id: int, token: string}
     */
    public function crear(array $datos, int $clinica): array
    {
        $id = (new Usuario($this->db))->crear(array_replace($datos, ['password' => null, 'debe_cambiar_password' => 0]));
        $this->db->prepare('UPDATE usuarios SET estado = 0 WHERE id_usuario = ?')->execute([$id]);
        $this->db->prepare('INSERT INTO propietario_clinica (id_propietario, id_clinica) VALUES (?, ?)')->execute([$id, $clinica]);
        $enlace = $this->enlace($id, (string) $datos['email'], $clinica);
        $this->auditoria->log(Contexto::idUsuario(), 'INSERT', 'usuarios', $id, null, ['estado' => 0], 'Propietario registrado por la clínica, pendiente de activar su cuenta (RE-T.19.1)', $clinica);
        return ['id_usuario' => $id] + $enlace;
    }

    /**
     * La clínica reenvía el enlace: el anterior deja de servir y el nuevo
     * vence en 72 horas. Solo a un cliente suyo, con el vínculo activo, que
     * todavía no activó su cuenta.
     *
     * @return array{id: int, token: string, email: string, nombre: string}
     */
    public function reenviar(int $idPersona, int $clinica): array
    {
        return Transaccion::ejecutar($this->db, function () use ($idPersona, $clinica): array {
            $stmt = $this->db->prepare("SELECT u.id_usuario, u.email, u.nombre_completo
                FROM usuarios u
                JOIN propietario_clinica pc ON pc.id_propietario = u.id_usuario AND pc.id_clinica = ? AND pc.estado = 'activo'
                WHERE u.id_usuario = ? AND " . self::pendiente('u'));
            $stmt->execute([$clinica, $idPersona]);
            $persona = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($persona === false) {
                throw new InvalidArgumentException('Este cliente ya activó su cuenta o no está vinculado a la clínica.');
            }
            $enlace = $this->enlace($idPersona, (string) $persona['email'], $clinica);
            $this->auditoria->log(Contexto::idUsuario(), 'OTHER', 'verificaciones_email', $enlace['id'], null, null, 'Enlace de activación del propietario reenviado (RE-T.19.1)', $clinica);
            return $enlace + ['email' => (string) $persona['email'], 'nombre' => (string) $persona['nombre_completo']];
        });
    }

    /**
     * M5 C4b (decisión 2 del usuario, 2026-10-13): el titular pide un enlace
     * nuevo sin depender de la clínica («¿Olvidaste tu contraseña?» o el
     * autorregistro con su correo). Solo si la cuenta sigue pendiente; si no,
     * null, y quien llama responde lo mismo que siempre (RN-G15). El enlace
     * lleva la clínica que lo registró, la de su último enlace.
     *
     * @return array{id: int, token: string, email: string, nombre: string, clinica: string}|null
     */
    public function renovarPorTitular(int $idUsuario): ?array
    {
        if (!$this->esPendiente($idUsuario)) {
            return null;
        }
        return Transaccion::ejecutar($this->db, function () use ($idUsuario): ?array {
            // RE-T.19.1: dos solicitudes simultáneas no pueden reservar dos envíos.
            $bloqueo = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $stmt = $this->db->prepare('SELECT u.id_usuario FROM usuarios u WHERE u.id_usuario = ? AND ' . self::pendiente('u') . $bloqueo);
            $stmt->execute([$idUsuario]);
            // Volver a comprobar tras esperar el bloqueo: la cuenta pudo activarse.
            if (!$stmt->fetchColumn() || $this->topeAlcanzado($idUsuario)) {
                return null;
            }
            $origen = $this->origen($idUsuario);
            if ($origen === null) {
                return null;
            }
            $enlace = $this->enlace($idUsuario, $origen['email'], $origen['id_clinica']);
            $this->auditoria->log(null, 'OTHER', 'verificaciones_email', $enlace['id'], null, null, 'Enlace de activación pedido por el titular (RE-T.19.1)', $origen['id_clinica']);
            return $enlace + ['email' => $origen['email'], 'nombre' => $origen['nombre'], 'clinica' => $origen['clinica']];
        });
    }

    /** Nombre de la clínica que lo registró, para el modal de Google. Null si no está pendiente. */
    public function clinicaDeOrigen(int $idUsuario): ?string
    {
        if (!$this->esPendiente($idUsuario)) {
            return null;
        }
        $origen = $this->origen($idUsuario);
        return $origen === null ? null : $origen['clinica'];
    }

    /**
     * M5 C4b (decisión 2; RN-G21): entrar con Google con una cuenta pendiente
     * la activa sin contraseña, porque Google ya demostró que el correo es
     * suyo. Exige aceptar la política (RN-G19), liga Google y anula los
     * enlaces de activación que queden.
     */
    public function activarConGoogle(int $idUsuario, string $email, ?string $googleUid, bool $acepta, string $ip): void
    {
        if (!$acepta) {
            throw new InvalidArgumentException('Debes aceptar la política de tratamiento de datos.');
        }
        Transaccion::ejecutar($this->db, function () use ($idUsuario, $email, $googleUid, $ip): void {
            $bloqueo = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $stmt = $this->db->prepare('SELECT u.email FROM usuarios u WHERE u.id_usuario = ? AND ' . self::pendiente('u') . $bloqueo);
            $stmt->execute([$idUsuario]);
            $actual = $stmt->fetchColumn();
            if ($actual === false || mb_strtolower((string) $actual) !== mb_strtolower($email)) {
                throw new InvalidArgumentException('Tu cuenta ya no está pendiente. Vuelve a continuar con Google.');
            }
            $origen = $this->origen($idUsuario);
            $this->db->prepare('UPDATE usuarios SET estado = 1, google_uid = COALESCE(google_uid, ?) WHERE id_usuario = ? AND estado = 0 AND password IS NULL')
                ->execute([$googleUid, $idUsuario]);
            $this->db->prepare('UPDATE verificaciones_email SET used = 1 WHERE id_usuario = ? AND proposito = ? AND used = 0')
                ->execute([$idUsuario, self::PROPOSITO]);
            (new ConsentimientoDatos($this->db))->registrar($idUsuario, PoliticaDatos::MEDIO_GOOGLE, $ip);
            $this->auditoria->log($idUsuario, 'UPDATE', 'usuarios', $idUsuario, ['estado' => 0], ['estado' => 1], 'Propietario activado con Google: aceptó la política (RE-T.19.1, RN-G21)', $origen['id_clinica'] ?? null);
        });
    }

    /** Un enlace en los últimos 5 minutos, o 3 en la última hora. */
    private function topeAlcanzado(int $idUsuario): bool
    {
        // El autorregistro ya puede tener una lectura anterior en su transacción.
        // FOR UPDATE lee el contador actual, no la instantánea anterior de MySQL.
        $bloqueo = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare('SELECT created_at FROM verificaciones_email
            WHERE id_usuario = ? AND proposito = ? AND created_at >= ?
            ORDER BY created_at DESC' . $bloqueo);
        $stmt->execute([$idUsuario, self::PROPOSITO, date('Y-m-d H:i:s', time() - 3600)]);
        $fechas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($fechas) >= self::TOPE_POR_HORA) {
            return true;
        }
        return $fechas !== [] && strtotime((string) $fechas[0]) > time() - self::TOPE_ESPERA_SEGUNDOS;
    }

    /** Correo, nombre y clínica del último enlace de activación de la persona. */
    private function origen(int $idUsuario): ?array
    {
        $stmt = $this->db->prepare('SELECT u.email, u.nombre_completo, v.id_clinica_vinculo, c.nombre AS clinica
            FROM usuarios u
            JOIN verificaciones_email v ON v.id_usuario = u.id_usuario AND v.proposito = ?
            LEFT JOIN clinicas c ON c.id_clinica = v.id_clinica_vinculo
            WHERE u.id_usuario = ?
            ORDER BY v.id DESC LIMIT 1');
        $stmt->execute([self::PROPOSITO, $idUsuario]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($fila === false) {
            return null;
        }
        return [
            'email' => (string) $fila['email'],
            'nombre' => (string) $fila['nombre_completo'],
            'id_clinica' => (int) $fila['id_clinica_vinculo'],
            'clinica' => (string) ($fila['clinica'] ?? 'Tu clínica'),
        ];
    }

    /**
     * Proceso del sistema: una cuenta pendiente con el enlace vencido se
     * borra solo si no tiene mascotas ni otros vínculos. Con mascotas, sus
     * registros son de la clínica: la cuenta se conserva y la clínica puede
     * reenviar el enlace.
     */
    public function limpiar(): int
    {
        $stmt = $this->db->prepare('SELECT DISTINCT v.id_usuario, v.id_clinica_vinculo FROM verificaciones_email v
            JOIN usuarios u ON u.id_usuario = v.id_usuario
            WHERE v.proposito = ? AND v.used = 0 AND v.expires_at <= ? AND ' . self::pendiente('u'));
        $stmt->execute([self::PROPOSITO, date('Y-m-d H:i:s')]);
        $borradas = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            try {
                $borradas += $this->limpiarUna((int) $fila['id_usuario'], (int) $fila['id_clinica_vinculo']);
            } catch (Throwable $e) {
                // D2.1: una cuenta que no se puede borrar no detiene la limpieza de las demás.
                error_log(sprintf('M5 C3 limpieza: no se eliminó el propietario pendiente %d (%s)', (int) $fila['id_usuario'], $e->getMessage()));
            }
        }
        return $borradas;
    }

    private function limpiarUna(int $id, int $clinica): int
    {
        return Transaccion::ejecutar($this->db, function () use ($id, $clinica): int {
            $bloqueo = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $this->db->prepare('SELECT id_usuario FROM usuarios WHERE id_usuario = ?' . $bloqueo)->execute([$id]);
            if ($this->debeConservarse($id, $clinica)) {
                return 0;
            }
            $this->db->prepare('DELETE FROM verificaciones_email WHERE id_usuario = ?')->execute([$id]);
            $this->db->prepare('DELETE FROM password_resets WHERE id_usuario = ?')->execute([$id]);
            $this->db->prepare('DELETE FROM propietario_clinica WHERE id_propietario = ? AND id_clinica = ?')->execute([$id, $clinica]);
            // Se conserva la auditoría; el registro_id sigue identificando el alta vencida.
            $this->db->prepare('UPDATE auditoria_sistema SET id_usuario = NULL WHERE id_usuario = ?')->execute([$id]);
            $borrar = $this->db->prepare('DELETE FROM usuarios WHERE id_usuario = ? AND estado = 0 AND password IS NULL');
            $borrar->execute([$id]);
            if ($borrar->rowCount() !== 1) {
                throw new RuntimeException('La cuenta dejó de estar pendiente durante la limpieza.');
            }
            $this->auditoria->log(null, 'DELETE', 'usuarios', $id, ['estado' => 'pendiente'], null, 'Propietario sin activar eliminado: sin mascotas ni otros vínculos (RE-T.19.1)', $clinica);
            return 1;
        });
    }

    /** Mascotas (y con ellas sus registros clínicos), otros vínculos o un enlace todavía vigente. */
    private function debeConservarse(int $id, int $clinica): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM mascotas WHERE id_propietario = ?
            UNION SELECT 1 FROM usuario_clinica WHERE id_usuario = ?
            UNION SELECT 1 FROM propietario_clinica WHERE id_propietario = ? AND id_clinica <> ?
            UNION SELECT 1 FROM verificaciones_email WHERE id_usuario = ? AND proposito = ? AND used = 0 AND expires_at > ?');
        $stmt->execute([$id, $id, $id, $clinica, $id, self::PROPOSITO, date('Y-m-d H:i:s')]);
        return (bool) $stmt->fetchColumn();
    }

    /** Un enlace nuevo de 72 horas; el anterior de la misma persona deja de servir. */
    private function enlace(int $id, string $email, int $clinica): array
    {
        $this->db->prepare('UPDATE verificaciones_email SET used = 1 WHERE id_usuario = ? AND proposito = ? AND used = 0')
            ->execute([$id, self::PROPOSITO]);
        $token = bin2hex(random_bytes(32));
        // created_at lo pone PHP, con la misma hora con que se mide el tope (no la de la base).
        $this->db->prepare('INSERT INTO verificaciones_email (id_usuario, email, proposito, id_clinica_vinculo, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$id, $email, self::PROPOSITO, $clinica, password_hash($token, PASSWORD_DEFAULT), date('Y-m-d H:i:s', time() + self::HORAS * 3600), date('Y-m-d H:i:s')]);
        return ['id' => (int) $this->db->lastInsertId(), 'token' => $token];
    }
}
