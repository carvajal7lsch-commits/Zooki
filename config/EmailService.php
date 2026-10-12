<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../helpers/EntornoLocal.php';
require_once __DIR__ . '/../helpers/EnlaceCuenta.php';
require_once __DIR__ . '/Database.php';

class EmailService {
    private $mail;

    /** D2.1: en local, MAIL_MODO=archivo guarda cada correo en logs/correos/ en vez de enviarlo. */
    private bool $guardarEnArchivo = false;
    private string $carpetaCorreos;

    /**
     * @param array|null $entorno variables del .env; null las lee del archivo.
     *                            Las pruebas pasan las suyas (y CARPETA_CORREOS).
     */
    public function __construct(?array $entorno = null) {
        $this->mail = new PHPMailer(true);

        // Cargar credenciales desde .env si existe
        $envFile = __DIR__ . '/../.env';
        $env = $entorno ?? (file_exists($envFile) ? (parse_ini_file($envFile) ?: []) : []);
        // Valores por defecto (requieren configuración manual)
        $smtpHost = $env['SMTP_HOST'] ?? 'smtp-relay.sendinblue.com';
        $smtpPort = $env['SMTP_PORT'] ?? 587;
        $smtpUser = $env['SMTP_USER'] ?? 'TU_LOGIN@smtp-brevo.com';
        $smtpPass = $env['SMTP_PASS'] ?? 'TU_CLAVE_SMTP_DE_BREVO';
        $smtpFrom = $env['SMTP_FROM'] ?? 'no-reply@TU_DOMINIO';
        $this->carpetaCorreos = (string) ($env['CARPETA_CORREOS'] ?? dirname(__DIR__) . '/logs/correos');
        if (strtolower(trim((string) ($env['MAIL_MODO'] ?? ''))) === 'archivo') {
            // Solo en local, con la misma comprobación que datos_prueba.php.
            // D2.2: con el host al que de verdad se conecta Database (db → 127.0.0.1 en Windows).
            $motivo = EntornoLocal::motivoNoLocal(Database::hostEfectivo((string) ($env['DB_HOST'] ?? '')));
            if ($motivo === null) {
                $this->guardarEnArchivo = true;
            } else {
                error_log('MAIL_MODO=archivo se ignora: ' . $motivo . '. Los correos se envían por SMTP.');
            }
        }

        // Configuración del servidor SMTP
        $this->mail->isSMTP();
        $this->mail->Host = $smtpHost;
        $this->mail->SMTPAuth = true;
        $this->mail->Username = $smtpUser;
        $this->mail->Password = $smtpPass;
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = $smtpPort;
        // PHPMailer espera por defecto hasta 300 s si el servidor SMTP no
        // responde; con 10 s un correo caído no deja procesos colgados.
        $this->mail->Timeout = 10;
        $this->mail->CharSet = 'UTF-8';
        $this->mail->setFrom($smtpFrom, 'Zooki - Sistema Veterinario');

        // Incrustar imágenes locales usando CID (Content-ID) para que carguen de inmediato
        $iconPath = dirname(__DIR__) . '/public/img/icon_blue.png';
        $logoPath = dirname(__DIR__) . '/public/img/logotipo.png';
        try {
            if (file_exists($iconPath)) {
                $this->mail->addEmbeddedImage($iconPath, 'zooki_icon_blue');
            }
            if (file_exists($logoPath)) {
                $this->mail->addEmbeddedImage($logoPath, 'zooki_logotipo');
            }
        } catch (Exception $e) {
            error_log("Error al incrustar imágenes en EmailService: " . $e->getMessage());
        }
    }
    
    public function enviarCorreoBienvenida($email, $nombre) {
        try {
            $this->mail->addAddress($email, $nombre);
            $this->mail->Subject = '¡Bienvenido a Zooki!';
            // D2.2: la instalación configurada (o la local que abrió la página), nunca producción fija.
            $appUrl = EnlaceCuenta::base();

            $contenido = '
            <p style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;">
              Nos alegra muchísimo que te hayas unido a Zooki. A partir de ahora podrás agendar citas, ver el historial de tus mascotas, vacunas, desparasitaciones y mucho más desde la comodidad de tu celular.
            </p>
            <p style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;">
              Tu compañero peludo está en las mejores manos. Si tienes alguna duda, escríbenos directamente.
            </p>';

            $this->mail->Body = $this->obtenerPlantillaBaseHTML($nombre, '¡Te damos la bienvenida a Zooki!', $contenido, 'Ir a mi Portal', $appUrl);
            $this->mail->isHTML(true);
            $this->entregar();
            return true;
        } catch (Throwable $e) {
            error_log("Error al enviar correo de bienvenida: " . $this->mail->ErrorInfo . ' ' . $e->getMessage());
            return false;
        }
    }

    /**
     * HU-36 — Confirmacion del correo en el auto-registro.
     * Usa la misma plantilla base que la bienvenida y las credenciales, para
     * que el logo, la tipografia y el pie sean identicos en todos los envios.
     */
    public function enviarCorreoVerificacion($email, $nombre, $enlace, $horasVigencia = 24) {
        try {
            $this->mail->addAddress($email, $nombre);
            $this->mail->Subject = 'Confirma tu correo en Zooki';

            $contenido = '
            <p style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;">
              Creaste una cuenta en Zooki con esta dirección de correo. Solo falta un paso: confirmar que el buzón es tuyo para poder iniciar sesión.
            </p>
            <p style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;">
              El enlace estará disponible durante las próximas <strong>' . (int) $horasVigencia . ' horas</strong>.
            </p>';

            $cierre = '
            <p style="font-size:13px;line-height:20px;color:#868686;margin:0;">
              Si no fuiste tú quien se registró, puedes ignorar este mensaje: sin confirmar, la cuenta no se puede usar.
            </p>';

            $this->mail->Body = $this->obtenerPlantillaBaseHTML(
                $nombre,
                'Confirma tu correo',
                $contenido . $cierre,
                'Confirmar mi correo',
                $enlace
            );
            $this->mail->AltBody = "Hola $nombre,

Confirma tu correo para activar tu cuenta de Zooki:
$enlace

El enlace vence en $horasVigencia horas.

Si no fuiste tú, ignora este mensaje.";
            $this->mail->isHTML(true);
            $this->entregar();
            return true;
        } catch (Exception $e) {
            error_log("Error al enviar correo de verificacion: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    public function enviarCorreoPersonalizado($email, $nombre, $asunto, $cuerpoHTML) {
        try {
            $this->mail->addAddress($email, $nombre);
            $this->mail->Subject = $asunto;
            $this->mail->Body = $cuerpoHTML;
            $this->mail->isHTML(true);
            $this->entregar();
            return true;
        } catch (Exception $e) {
            error_log("Error al enviar correo: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    public function limpiarDirecciones() {
        $this->mail->clearAddresses();
    }

    /** True si los correos se guardan en logs/correos/ en vez de enviarse (solo en local). */
    public function guardaEnArchivo(): bool {
        return $this->guardarEnArchivo;
    }

    /**
     * Único punto de salida de los correos. En MAIL_MODO=archivo guarda el
     * HTML con la fecha, el destinatario y el asunto en el nombre, para
     * probar en local sin buzones reales ni depender de SMTP.
     */
    private function entregar(): void {
        if (!$this->guardarEnArchivo) {
            $this->mail->send();
            return;
        }
        if (!is_dir($this->carpetaCorreos) && !mkdir($this->carpetaCorreos, 0775, true) && !is_dir($this->carpetaCorreos)) {
            throw new Exception('No se pudo crear ' . $this->carpetaCorreos);
        }
        $destinatarios = array_map(static fn (array $direccion): string => $direccion[0], $this->mail->getToAddresses());
        $limpio = static fn (string $texto): string => trim(preg_replace('/[^a-z0-9@._-]+/i', '-', $texto), '-');
        $nombre = date('Ymd-His') . '-' . substr((string) hrtime(true), -6)
            . '_' . substr($limpio(implode(',', $destinatarios)), 0, 60)
            . '_' . substr($limpio($this->mail->Subject), 0, 60) . '.html';
        $cabecera = '<!-- Para: ' . htmlspecialchars(implode(', ', $destinatarios), ENT_QUOTES, 'UTF-8')
            . ' | Asunto: ' . htmlspecialchars($this->mail->Subject, ENT_QUOTES, 'UTF-8') . ' -->' . PHP_EOL;
        if (file_put_contents($this->carpetaCorreos . '/' . $nombre, $cabecera . $this->mail->Body) === false) {
            throw new Exception('No se pudo guardar el correo en ' . $this->carpetaCorreos);
        }
    }

    /**
     * D2.1 (RE-T.19.1) — Invitación del personal: la clínica lo invita con un
     * rol; en 72 horas acepta la política y crea su contraseña.
     */
    public function enviarInvitacionPersonal($email, $nombre, $clinica, $rol, $enlace, $horasVigencia = 72) {
        $e = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
        try {
            $this->mail->addAddress($email, $nombre);
            $this->mail->Subject = 'Te invitaron a ' . $clinica . ' en Zooki';
            $contenido = '
            <p style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;">
              <strong>' . $e($clinica) . '</strong> te invitó a su equipo en Zooki como <strong>' . $e($rol) . '</strong>.
            </p>
            <p style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;">
              Para activar tu cuenta abre el enlace, acepta la política de tratamiento de datos y crea tu contraseña. El enlace vence en <strong>' . (int) $horasVigencia . ' horas</strong>.
            </p>
            <p style="font-size:13px;line-height:20px;color:#868686;margin:0;">
              Si no esperabas esta invitación, ignora el mensaje: sin activarla, la cuenta no se puede usar.
            </p>';
            $this->mail->Body = $this->obtenerPlantillaBaseHTML($nombre, 'Activa tu cuenta', $contenido, 'Activar mi cuenta', $enlace);
            $this->mail->AltBody = "Hola $nombre,\n\n$clinica te invitó a su equipo en Zooki como $rol.\nActiva tu cuenta, acepta la política de tratamiento de datos y crea tu contraseña:\n$enlace\n\nEl enlace vence en $horasVigencia horas.";
            $this->mail->isHTML(true);
            $this->entregar();
            return true;
        } catch (Exception $e) {
            error_log('Error al enviar la invitación del personal: ' . $this->mail->ErrorInfo . ' ' . $e->getMessage());
            return false;
        }
    }

    /**
     * D2.1 (HU-T.14) — El administrador restableció la contraseña: la
     * anterior ya no sirve y el titular crea otra con un enlace de 24 horas.
     */
    public function enviarRestablecimientoPorAdministrador($email, $nombre, $clinica, $enlace, $horasVigencia = 24) {
        $e = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
        try {
            $this->mail->addAddress($email, $nombre);
            $this->mail->Subject = 'Crea una nueva contraseña en Zooki';
            $contenido = '
            <p style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;">
              El administrador de <strong>' . $e($clinica) . '</strong> restableció tu contraseña. <strong>Tu contraseña anterior ya no sirve</strong> y se cerraron las sesiones que tenías abiertas.
            </p>
            <p style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;">
              Crea una nueva desde el enlace. Vence en <strong>' . (int) $horasVigencia . ' horas</strong>.
            </p>
            <p style="font-size:13px;line-height:20px;color:#868686;margin:0;">
              Si no lo pediste, habla con el administrador de tu clínica.
            </p>';
            $this->mail->Body = $this->obtenerPlantillaBaseHTML($nombre, 'Crea una nueva contraseña', $contenido, 'Crear mi contraseña', $enlace);
            $this->mail->AltBody = "Hola $nombre,\n\nEl administrador de $clinica restableció tu contraseña; la anterior ya no sirve.\nCrea una nueva:\n$enlace\n\nEl enlace vence en $horasVigencia horas.";
            $this->mail->isHTML(true);
            $this->entregar();
            return true;
        } catch (Exception $e) {
            error_log('Error al enviar el restablecimiento: ' . $this->mail->ErrorInfo . ' ' . $e->getMessage());
            return false;
        }
    }

    /**
     * D2.2 (RE-T.7.5) — Invitación a una persona que ya tiene cuenta: la
     * clínica solo la vincula si el titular acepta en el enlace.
     */
    public function enviarInvitacionClinica($email, $nombre, $clinica, $rol, $enlace, $horasVigencia = 72) {
        $e = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
        try {
            $this->mail->addAddress($email, $nombre);
            $this->mail->Subject = $clinica . ' te invitó a su equipo en Zooki';
            $contenido = '
            <p style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;">
              <strong>' . $e($clinica) . '</strong> te invitó a su equipo como <strong>' . $e($rol) . '</strong>.
            </p>
            <p style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;">
              Ya tienes cuenta en Zooki: abre el enlace para aceptar o rechazar. Si aceptas, la clínica verá tu nombre, documento, correo y teléfono, y conservas tus otros perfiles. El enlace vence en <strong>' . (int) $horasVigencia . ' horas</strong>.
            </p>
            <p style="font-size:13px;line-height:20px;color:#868686;margin:0;">
              Si no conoces a esta clínica, ignora el mensaje: sin tu aceptación no pasa nada.
            </p>';
            $this->mail->Body = $this->obtenerPlantillaBaseHTML($nombre, 'Invitación a un equipo', $contenido, 'Ver la invitación', $enlace);
            $this->mail->AltBody = "Hola $nombre,\n\n$clinica te invitó a su equipo en Zooki como $rol.\nAcepta o rechaza la invitación:\n$enlace\n\nEl enlace vence en $horasVigencia horas. Si no conoces a esta clínica, ignora el mensaje.";
            $this->mail->isHTML(true);
            $this->entregar();
            return true;
        } catch (Exception $e) {
            error_log('Error al enviar la invitación a la clínica: ' . $this->mail->ErrorInfo . ' ' . $e->getMessage());
            return false;
        }
    }

    /** D2.2 (RE-T.2.5) — Aviso de que la contraseña cambió y se cerraron las demás sesiones. */
    public function enviarAvisoCambioPassword($email, $nombre) {
        try {
            $this->mail->addAddress($email, $nombre);
            $this->mail->Subject = 'Se cambió la contraseña de tu cuenta Zooki';
            $contenido = '
            <p style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;">
              La contraseña de tu cuenta cambió y se cerraron las demás sesiones que tenías abiertas.
            </p>
            <p style="font-size:13px;line-height:20px;color:#868686;margin:0;">
              Si no fuiste tú, usa «¿Olvidaste tu contraseña?» en el inicio de sesión para crear otra y avisa a soporte.
            </p>';
            $this->mail->Body = $this->obtenerPlantillaBaseHTML($nombre, 'Tu contraseña cambió', $contenido);
            $this->mail->AltBody = "Hola $nombre,\n\nLa contraseña de tu cuenta Zooki cambió y se cerraron las demás sesiones abiertas.\nSi no fuiste tú, usa ¿Olvidaste tu contraseña? en el inicio de sesión y avisa a soporte.";
            $this->mail->isHTML(true);
            $this->entregar();
            return true;
        } catch (Exception $e) {
            error_log('Error al enviar el aviso de cambio de contraseña: ' . $this->mail->ErrorInfo . ' ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Plantilla común de los correos.
     *
     * M0.2: $nombre, $titulo, $ctaTexto y $ctaEnlace son texto plano y se
     * escapan aquí (el título lleva a veces el nombre de una clínica escrito
     * por el público); $contenidoHtml ya viene armado y escapado por quien
     * llama. Para Gmail claro y oscuro: colores de fondo en cada celda, botón
     * como celda con su color, el logotipo sobre una placa blanca para que no
     * desaparezca cuando Gmail oscurece el fondo, y ancho fluido en el celular.
     */
    public function obtenerPlantillaBaseHTML($nombre, $titulo, $contenidoHtml, $ctaTexto = null, $ctaEnlace = null) {
        $h = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
        $ctaHtml = '';
        if ($ctaTexto && $ctaEnlace) {
            $ctaHtml = '
            <table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin:28px 0">
              <tr>
                <td align="center" bgcolor="#0052ff" style="background-color:#0052ff;border-radius:10px;mso-padding-alt:12px 24px;">
                  <a href="' . $h($ctaEnlace) . '" target="_blank" style="display:inline-block;padding:13px 26px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:15px;font-weight:700;line-height:20px;color:#ffffff;text-decoration:none;border-radius:10px;">' . $h($ctaTexto) . '</a>
                </td>
              </tr>
            </table>
            <p style="font-size:12px;line-height:18px;color:#6b7280;margin:0 0 8px 0;">Si el botón no funciona, copia este enlace en tu navegador:<br /><a href="' . $h($ctaEnlace) . '" style="color:#0052ff;word-break:break-all;">' . $h($ctaEnlace) . '</a></p>';
        }

        return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html dir="ltr" lang="es">
  <head>
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="x-apple-disable-message-reformatting" />
    <meta name="color-scheme" content="light" />
    <meta name="supported-color-schemes" content="light" />
    <title>' . $h($titulo) . '</title>
  </head>
  <body style="margin:0;padding:0;background-color:#f4f6fb;">
    <table border="0" width="100%" cellpadding="0" cellspacing="0" role="presentation" bgcolor="#f4f6fb" style="background-color:#f4f6fb;">
      <tr>
        <td align="center" style="padding:32px 12px;">
          <table border="0" width="100%" cellpadding="0" cellspacing="0" role="presentation" bgcolor="#ffffff" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:16px;">
            <tr>
              <td style="padding:32px 28px 28px 28px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:#1d1c1d;">
                <table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:28px;">
                  <tr>
                    <td bgcolor="#ffffff" style="background-color:#ffffff;border-radius:10px;padding:6px 10px 6px 6px;">
                      <table border="0" cellpadding="0" cellspacing="0" role="presentation">
                        <tr>
                          <td style="vertical-align:middle;padding-right:6px;"><img alt="" height="36" width="36" src="cid:zooki_icon_blue" style="display:block;border:0;outline:none;text-decoration:none;" /></td>
                          <td style="vertical-align:middle;"><img alt="Zooki" width="96" src="cid:zooki_logotipo" style="display:block;border:0;outline:none;text-decoration:none;height:auto;" /></td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                </table>
                <h1 style="color:#1d1c1d;font-size:26px;font-weight:800;letter-spacing:-0.5px;line-height:32px;margin:0 0 16px 0;">' . $h($titulo) . '</h1>
                <p style="font-size:17px;line-height:26px;color:#1d1c1d;margin:0 0 20px 0;">Hola, ' . $h($nombre) . '.</p>
                ' . $contenidoHtml . '
                ' . $ctaHtml . '
                <table border="0" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:28px 0 20px 0;">
                  <tr><td style="border-top:1px solid #e5e7eb;font-size:0;line-height:0;">&nbsp;</td></tr>
                </table>
                <p style="font-size:13px;line-height:18px;color:#6b7280;margin:0 0 8px 0;">Enviado con 💙 por el equipo de Zooki<br />Zooki · Gestión y cuidado veterinario</p>
                <p style="font-size:12px;line-height:17px;color:#6b7280;margin:0;">Si tienes alguna duda o crees que este correo es un error de seguridad, comunícate con soporte de Zooki.</p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>';
    }
}
