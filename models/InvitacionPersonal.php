<?php
require_once __DIR__ . '/Usuario.php';
require_once __DIR__ . '/Auditoria.php';
require_once __DIR__ . '/../helpers/Transaccion.php';
require_once __DIR__ . '/../helpers/Roles.php';
require_once __DIR__ . '/../helpers/Contexto.php';

/**
 * D2.2 (RE-T.7.5, RN-705, RN-G06) — Invitaciones al personal de una clínica.
 *
 * Hay dos clases de invitación pendiente y la clínica las ve igual:
 * - activacion_personal: la persona no tenía cuenta; se creó una pendiente e
 *   inerte (D2, CuentaTitular::crearPersonal).
 * - invitacion_personal: la persona ya tenía cuenta. No se crea ningún
 *   vínculo: solo su aceptación crea usuario_clinica. Mientras tanto la
 *   clínica ve lo que escribió el administrador, nunca los datos de la cuenta.
 *
 * Así la lista, los mensajes y la auditoría de la clínica no revelan si un
 * documento o un correo tienen cuenta en Zooki. Las dos se identifican por el
 * id de su fila en verificaciones_email.
 */
final class InvitacionPersonal
{
    public const PROPOSITO = 'invitacion_personal';
    public const ACTIVACION = 'activacion_personal';
    public const HORAS = 72;

    private Usuario $usuarios;
    private Auditoria $auditoria;

    public function __construct(private PDO $db)
    {
        $this->usuarios = new Usuario($db);
        $this->auditoria = new Auditoria($db);
    }

    /**
     * La misma entrada de auditoría para las dos clases de invitación. El
     * actor es null cuando lo decide el titular antes de ser personal: su
     * nombre no se le muestra a la clínica.
     */
    public function auditar(string $evento, int $idInvitacion, int $clinica, ?int $actor): void
    {
        $this->auditoria->log($actor, 'OTHER', 'verificaciones_email', $idInvitacion, null, null, 'Invitación al personal ' . $evento . ' (RE-T.7.5)', $clinica);
    }

    /**
     * Invitación a una persona que ya tiene cuenta. El correo va a la cuenta
     * ($persona['email']); lo escrito por el administrador queda aparte.
     *
     * @return array{id: int, token: string}
     */
    public function crear(array $persona, int $clinica, array $escritos): array
    {
        $token = bin2hex(random_bytes(32));
        $stmt = $this->db->prepare('INSERT INTO verificaciones_email (id_usuario, email, proposito, id_clinica_vinculo, id_rol_vinculo,
                nombre_invitado, tipo_documento_invitado, documento_invitado, email_invitado, telefono_invitado, token_hash, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            (int) $persona['id_usuario'],
            (string) $persona['email'],
            self::PROPOSITO,
            $clinica,
            (int) $escritos['id_rol'],
            $escritos['nombre_completo'],
            $escritos['tipo_documento'],
            $escritos['documento'],
            $escritos['email'],
            ($escritos['telefono'] ?? '') !== '' ? $escritos['telefono'] : null,
            password_hash($token, PASSWORD_DEFAULT),
            date('Y-m-d H:i:s', time() + self::HORAS * 3600),
        ]);
        return ['id' => (int) $this->db->lastInsertId(), 'token' => $token];
    }

    /**
     * Invitaciones pendientes de la clínica, de las dos clases, con los datos
     * que la clínica puede ver: los de la cuenta nueva (los escribió ella) o
     * los escritos al invitar a una cuenta existente.
     */
    public function pendientesDeClinica(int $clinica): array
    {
        $stmt = $this->db->prepare(
            "SELECT v.id AS id_invitacion, u.nombre_completo, u.tipo_documento, u.documento, u.email, u.telefono, uc.id_rol
             FROM verificaciones_email v
             JOIN usuarios u ON u.id_usuario = v.id_usuario
             JOIN usuario_clinica uc ON uc.id_usuario = v.id_usuario AND uc.id_clinica = v.id_clinica_vinculo
             WHERE v.proposito = 'activacion_personal' AND v.used = 0 AND v.id_clinica_vinculo = ? AND u.estado = 0
             UNION ALL
             SELECT v.id, v.nombre_invitado, v.tipo_documento_invitado, v.documento_invitado, v.email_invitado,
                    v.telefono_invitado, v.id_rol_vinculo
             FROM verificaciones_email v
             WHERE v.proposito = 'invitacion_personal' AND v.used = 0 AND v.id_clinica_vinculo = ?
             ORDER BY nombre_completo"
        );
        $stmt->execute([$clinica, $clinica]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Una invitación pendiente de esta clínica (de cualquier clase), o null. */
    public function pendienteDeClinica(int $id, int $clinica): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT v.* FROM verificaciones_email v
             JOIN usuarios u ON u.id_usuario = v.id_usuario
             WHERE v.id = ? AND v.id_clinica_vinculo = ? AND v.used = 0
               AND (v.proposito = 'invitacion_personal' OR (v.proposito = 'activacion_personal' AND u.estado = 0))"
        );
        $stmt->execute([$id, $clinica]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    /**
     * ¿Ya hay en la clínica una invitación con ese documento o ese correo?
     * Compara con lo escrito al invitar, que el administrador ya conoce.
     */
    public function hayPendienteCon(int $clinica, string $documento, string $email): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM verificaciones_email
             WHERE proposito = 'invitacion_personal' AND used = 0 AND id_clinica_vinculo = ?
               AND (documento_invitado = ? OR email_invitado = ?)
             LIMIT 1"
        );
        $stmt->execute([$clinica, $documento, $email]);
        return (bool) $stmt->fetchColumn();
    }

    /** ¿Es una cuenta nueva que todavía espera su activación en esta clínica? */
    public function esAltaPendiente(int $idUsuario, int $clinica): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM verificaciones_email v JOIN usuarios u ON u.id_usuario = v.id_usuario
             WHERE v.id_usuario = ? AND v.id_clinica_vinculo = ? AND v.proposito = 'activacion_personal'
               AND v.used = 0 AND u.estado = 0
             LIMIT 1"
        );
        $stmt->execute([$idUsuario, $clinica]);
        return (bool) $stmt->fetchColumn();
    }

    /** True si el correo de la invitación llega a alguien que puede aceptarla. */
    public static function puedeAceptar(array $persona): bool
    {
        return (int) $persona['estado'] === 1 && (int) $persona['es_super_admin'] === 0;
    }

    /**
     * La invitación vigente detrás de un enlace, con el nombre de la clínica,
     * o null si no sirve. La página la muestra sin decidir nada (el GET no
     * cambia nada, como en confirmar_vinculo_propietario).
     */
    public function leer(int $id, string $token): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT v.*, c.nombre AS nombre_clinica, c.estado AS estado_clinica
             FROM verificaciones_email v JOIN clinicas c ON c.id_clinica = v.id_clinica_vinculo
             WHERE v.id = ? AND v.proposito = 'invitacion_personal'"
        );
        $stmt->execute([$id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila || (int) $fila['used'] !== 0 || strtotime($fila['expires_at']) <= time()
            || !password_verify($token, $fila['token_hash'])) {
            return null;
        }
        return $fila;
    }

    /**
     * El titular acepta: se crea su rol en la clínica y conserva los demás
     * (un propietario queda propietario y veterinario, Modelos §9).
     *
     * @return array{clinica: string, rol: string}
     */
    public function aceptar(int $id, string $token): array
    {
        return Transaccion::ejecutar($this->db, function () use ($id, $token): array {
            $fila = $this->bloquearVigente($id, $token);
            $persona = $this->usuarios->buscarPorId((int) $fila['id_usuario']);
            if ($persona === null || !self::puedeAceptar($persona) || $persona['email'] !== $fila['email']
                || $fila['estado_clinica'] !== 'activa') {
                throw new InvalidArgumentException('La invitación no es válida, venció o ya fue utilizada.');
            }
            $this->consumir($id);
            $idUsuario = (int) $persona['id_usuario'];
            $clinica = (int) $fila['id_clinica_vinculo'];
            $this->usuarios->asignarRolEnClinica($idUsuario, $clinica, (int) $fila['id_rol_vinculo']);
            // Las demás invitaciones de la misma clínica a esta persona ya no aplican.
            $this->db->prepare("UPDATE verificaciones_email SET used = 1 WHERE id_usuario = ? AND id_clinica_vinculo = ? AND proposito = 'invitacion_personal' AND used = 0")
                ->execute([$idUsuario, $clinica]);
            $this->auditar('aceptada', $id, $clinica, $idUsuario);
            return ['clinica' => (string) $fila['nombre_clinica'], 'rol' => Roles::nombre((int) $fila['id_rol_vinculo'])];
        });
    }

    /** El titular rechaza: no se crea nada y la invitación deja de servir. */
    public function rechazar(int $id, string $token): void
    {
        Transaccion::ejecutar($this->db, function () use ($id, $token): void {
            $fila = $this->bloquearVigente($id, $token);
            $this->consumir($id);
            $this->auditar('rechazada', $id, (int) $fila['id_clinica_vinculo'], null);
        });
    }

    /** El administrador retira una invitación a una cuenta existente. */
    public function cancelar(int $id, int $clinica): void
    {
        $stmt = $this->db->prepare("UPDATE verificaciones_email SET used = 1 WHERE id = ? AND id_clinica_vinculo = ? AND proposito = 'invitacion_personal' AND used = 0");
        $stmt->execute([$id, $clinica]);
        if ($stmt->rowCount() !== 1) {
            throw new InvalidArgumentException('La invitación ya no está pendiente.');
        }
    }

    /**
     * Reemplaza la invitación por otra con un enlace nuevo de 72 horas y los
     * mismos datos escritos. El correo va al de la cuenta en este momento.
     *
     * @return array{id: int, token: string, persona: array, id_rol: int}
     */
    public function reenviar(int $id, int $clinica): array
    {
        return Transaccion::ejecutar($this->db, function () use ($id, $clinica): array {
            $fila = $this->pendienteDeClinica($id, $clinica);
            if ($fila === null || $fila['proposito'] !== self::PROPOSITO) {
                throw new InvalidArgumentException('La invitación ya no está pendiente.');
            }
            $this->cancelar($id, $clinica);
            $persona = $this->usuarios->buscarPorId((int) $fila['id_usuario']);
            $escritos = [
                'id_rol' => (int) $fila['id_rol_vinculo'],
                'nombre_completo' => $fila['nombre_invitado'],
                'tipo_documento' => $fila['tipo_documento_invitado'],
                'documento' => $fila['documento_invitado'],
                'email' => $fila['email_invitado'],
                'telefono' => (string) $fila['telefono_invitado'],
            ];
            $nueva = $this->crear($persona, $clinica, $escritos);
            return $nueva + ['persona' => $persona, 'id_rol' => (int) $fila['id_rol_vinculo']];
        });
    }

    /** Proceso del sistema: las invitaciones vencidas dejan de estar pendientes. */
    public function vencerPendientes(): int
    {
        $stmt = $this->db->prepare("SELECT id, id_clinica_vinculo FROM verificaciones_email WHERE proposito = 'invitacion_personal' AND used = 0 AND expires_at <= ?");
        $stmt->execute([date('Y-m-d H:i:s')]);
        $vencidas = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $marcar = $this->db->prepare('UPDATE verificaciones_email SET used = 1 WHERE id = ? AND used = 0');
            $marcar->execute([$fila['id']]);
            if ($marcar->rowCount() === 1) {
                $this->auditar('vencida', (int) $fila['id'], (int) $fila['id_clinica_vinculo'], null);
                $vencidas++;
            }
        }
        return $vencidas;
    }

    private function bloquearVigente(int $id, string $token): array
    {
        $bloqueo = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare(
            "SELECT v.*, c.nombre AS nombre_clinica, c.estado AS estado_clinica
             FROM verificaciones_email v JOIN clinicas c ON c.id_clinica = v.id_clinica_vinculo
             WHERE v.id = ? AND v.proposito = 'invitacion_personal'" . $bloqueo
        );
        $stmt->execute([$id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila || (int) $fila['used'] !== 0 || strtotime($fila['expires_at']) <= time()
            || !password_verify($token, $fila['token_hash'])) {
            throw new InvalidArgumentException('La invitación no es válida, venció o ya fue utilizada.');
        }
        return $fila;
    }

    private function consumir(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE verificaciones_email SET used = 1 WHERE id = ? AND used = 0');
        $stmt->execute([$id]);
        if ($stmt->rowCount() !== 1) {
            throw new InvalidArgumentException('La invitación no es válida, venció o ya fue utilizada.');
        }
    }
}
