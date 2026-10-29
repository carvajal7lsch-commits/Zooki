<?php
require_once __DIR__ . '/../helpers/Transaccion.php';
require_once __DIR__ . '/../helpers/EnlaceCuenta.php';

/**
 * Casos que revisa el super-administrador (HU-0.3). Un solo lugar para
 * abrirlos, sin repetir un caso que sigue abierto.
 */
final class CasoSoporte
{
    private static bool $respuestaPreparada = false;

    public function __construct(private PDO $db, private mixed $correo = null) {}

    /**
     * RN-G24 y Modelos §13.1 (nodo D2): el titular pidió un documento que ya
     * usa otra cuenta. El caso guarda quién lo pidió y qué documento, para
     * que soporte resuelva el posible duplicado; al titular nunca se le dice
     * de quién es. $regla dice desde dónde llegó (cambio de documento o
     * completar el perfil).
     */
    public function documentoDuplicado(int $idUsuario, string $documento, string $regla): void
    {
        // RE-T.18.3/RN-G24: la regla pertenece al código, nunca al texto visible.
        $descripcion = 'El titular solicitó el documento ' . $documento . ' ya asociado a otra cuenta.';
        $id = Transaccion::ejecutar($this->db, function () use ($idUsuario, $documento, $descripcion): ?int {
            $this->bloquearUsuario($idUsuario);
            // Incluye descripciones anteriores con el sufijo de la regla, sin duplicarlas.
            $abiertos = $this->db->prepare("SELECT descripcion FROM casos_soporte WHERE tipo = 'documento_duplicado' AND id_usuario = ? AND estado = 'abierto'");
            $abiertos->execute([$idUsuario]);
            foreach ($abiertos->fetchAll(PDO::FETCH_COLUMN) as $anterior) {
                if (self::documentoSolicitado($anterior) === $documento) return null;
            }
            $this->db->prepare("INSERT INTO casos_soporte (tipo, id_usuario, descripcion) VALUES ('documento_duplicado', ?, ?)")
                ->execute([$idUsuario, $descripcion]);
            return (int) $this->db->lastInsertId();
        });
        if ($id !== null) $this->programarAviso($id);
    }

    /** Sin esquema nuevo: solo el formato que ya escribe este modelo identifica el documento. */
    public static function documentoSolicitado(string $descripcion): ?string
    {
        return preg_match('/^El titular solicitó el documento ([0-9]{5,15}) ya asociado a otra cuenta(?:[. ]|$)/u', $descripcion, $coincidencia) === 1 ? $coincidencia[1] : null;
    }

    public static function descripcionVisible(string $descripcion): string
    {
        return preg_replace('/ \((?:al completar el perfil, RE-T\.18\.3|RN-G24)\)(?=\.|$)/u', '', $descripcion);
    }

    public static function tipoVisible(string $tipo): string
    {
        return match ($tipo) {
            'documento_duplicado' => 'Documento asociado a otra cuenta',
            'clinica_duplicada' => 'Reporte de clínica duplicada',
            default => 'Solicitud de soporte',
        };
    }

    /** RN-010: el reporte de NIT comparte el mismo aviso privado de plataforma. */
    public function clinicaDuplicada(int $idClinica, string $descripcion): void
    {
        $id = Transaccion::ejecutar($this->db, function () use ($idClinica, $descripcion): int {
            $this->db->prepare("INSERT INTO casos_soporte (tipo, id_clinica, descripcion) VALUES ('clinica_duplicada', ?, ?)")
                ->execute([$idClinica, $descripcion]);
            return (int) $this->db->lastInsertId();
        });
        $this->programarAviso($id);
    }

    /** El shutdown ocurre después de generar la respuesta, también en los controladores heredados. */
    public function programarAviso(int $id): void
    {
        if (defined('ZOOKI_PRUEBAS')) return;
        // JSON solo termina cuando llega todo el cuerpo: flush solo no basta bajo Apache.
        // Capturamos la respuesta antes de que el controlador la escriba para anunciar su longitud.
        if (!self::$respuestaPreparada) {
            ob_start();
            self::$respuestaPreparada = true;
        }
        register_shutdown_function(function () use ($id): void {
            // Un SAVEPOINT liberado no es un commit: nunca avisar de una transacción abierta.
            if ($this->db->inTransaction()) return;
            ignore_user_abort(true);
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
            if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
            else {
                // Con output_buffering del php.ini (XAMPP) hay otro búfer debajo del nuestro:
                // se juntan en uno para que Content-Length mida el cuerpo completo y no lo recorte.
                while (ob_get_level() > 1) {
                    if (!@ob_end_flush()) break;
                }
                if (!headers_sent() && ob_get_level() === 1) {
                    header('Content-Length: ' . ob_get_length());
                }
                while (ob_get_level() > 0) {
                    if (!@ob_end_flush()) break;
                }
                flush();
            }
            $this->enviarAviso($id);
        });
    }

    /** HU-0.3/RN-G15: lista blanca del correo, sin descripción ni identidad del titular. */
    public function enviarAviso(int $id): void
    {
        if ($this->db->inTransaction() || (defined('ZOOKI_PRUEBAS') && $this->correo === null)) return;
        try {
            $q = $this->db->prepare('SELECT tipo, fecha FROM casos_soporte WHERE id_caso = ?');
            $q->execute([$id]);
            $caso = $q->fetch(PDO::FETCH_ASSOC);
            if (!$caso) return; // El commit exterior pudo haberse deshecho.
            $destinatarios = $this->db->query("SELECT u.email, u.nombre_completo FROM usuarios u WHERE u.es_super_admin = 1 AND u.estado = 1 AND u.email IS NOT NULL AND NOT EXISTS (SELECT 1 FROM verificaciones_email v WHERE v.id_usuario = u.id_usuario AND v.email = u.email AND v.used = 0 AND v.proposito IN ('registro', 'activacion_personal', 'registro_clinica')) ORDER BY u.id_usuario")->fetchAll(PDO::FETCH_ASSOC);
            $e = static fn (string $valor): string => htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
            $asunto = 'Zooki: nuevo caso de soporte #' . $id;
            $enlace = EnlaceCuenta::base() . '?action=plataforma_inicio#soporte';
            $fecha = date('d/m/Y H:i', strtotime($caso['fecha']));
            $cuerpo = '<p>Tipo: ' . $e(self::tipoVisible($caso['tipo'])) . '</p><p>Fecha: ' . $e($fecha) . '</p><p><a href="' . $e($enlace) . '">Ver los casos de soporte en plataforma</a></p>';
            foreach ($destinatarios as $destinatario) {
                try {
                    if ($this->correo !== null) {
                        $enviado = ($this->correo)($destinatario['email'], $asunto, $cuerpo);
                    } else {
                        require_once __DIR__ . '/../config/EmailService.php';
                        $mailer = new EmailService();
                        $enviado = $mailer->enviarCorreoPersonalizado($destinatario['email'], $destinatario['nombre_completo'], $asunto, $cuerpo);
                    }
                    if (!$enviado) error_log('No se pudo enviar el aviso del caso de soporte #' . $id);
                } catch (Throwable $error) {
                    error_log('No se pudo enviar el aviso del caso de soporte #' . $id);
                }
            }
        } catch (Throwable $error) {
            error_log('No se pudo preparar el aviso del caso de soporte #' . $id);
        }
    }

    /** Serializa dos solicitudes del mismo titular: así el caso no se duplica. */
    private function bloquearUsuario(int $idUsuario): void
    {
        $bloqueo = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare('SELECT id_usuario FROM usuarios WHERE id_usuario = ?' . $bloqueo);
        $stmt->execute([$idUsuario]);
        $stmt->fetchColumn();
    }
}
