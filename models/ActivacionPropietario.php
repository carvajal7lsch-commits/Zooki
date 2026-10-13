<?php
require_once __DIR__ . '/Usuario.php';
require_once __DIR__ . '/Auditoria.php';
require_once __DIR__ . '/../helpers/Transaccion.php';
require_once __DIR__ . '/../helpers/Contexto.php';

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
        $this->db->prepare('INSERT INTO verificaciones_email (id_usuario, email, proposito, id_clinica_vinculo, token_hash, expires_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$id, $email, self::PROPOSITO, $clinica, password_hash($token, PASSWORD_DEFAULT), date('Y-m-d H:i:s', time() + self::HORAS * 3600)]);
        return ['id' => (int) $this->db->lastInsertId(), 'token' => $token];
    }
}
