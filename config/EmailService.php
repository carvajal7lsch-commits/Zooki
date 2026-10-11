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

            $this->mail->Body = $this->obtenerPlantillaBaseHTML($nombre, '¡Te damos la bienvenida a Zooki!', $contenido, 'Ir a mi Portal', htmlspecialchars($appUrl, ENT_QUOTES, 'UTF-8'));
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
            $this->mail->Body = $this->obtenerPlantillaBaseHTML($e($nombre), 'Activa tu cuenta', $contenido, 'Activar mi cuenta', $e($enlace));
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
            $this->mail->Body = $this->obtenerPlantillaBaseHTML($e($nombre), 'Crea una nueva contraseña', $contenido, 'Crear mi contraseña', $e($enlace));
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
            $this->mail->Body = $this->obtenerPlantillaBaseHTML($e($nombre), 'Invitación a un equipo', $contenido, 'Ver la invitación', $e($enlace));
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
            $this->mail->Body = $this->obtenerPlantillaBaseHTML(htmlspecialchars((string) $nombre, ENT_QUOTES, 'UTF-8'), 'Tu contraseña cambió', $contenido);
            $this->mail->AltBody = "Hola $nombre,\n\nLa contraseña de tu cuenta Zooki cambió y se cerraron las demás sesiones abiertas.\nSi no fuiste tú, usa ¿Olvidaste tu contraseña? en el inicio de sesión y avisa a soporte.";
            $this->mail->isHTML(true);
            $this->entregar();
            return true;
        } catch (Exception $e) {
            error_log('Error al enviar el aviso de cambio de contraseña: ' . $this->mail->ErrorInfo . ' ' . $e->getMessage());
            return false;
        }
    }

    public function obtenerPlantillaBaseHTML($nombre, $titulo, $contenidoHtml, $ctaTexto = null, $ctaEnlace = null) {
        $ctaHtml = '';
        if ($ctaTexto && $ctaEnlace) {
            $ctaHtml = '
            <table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin:28px 0">
              <tbody>
                <tr>
                  <td>
                    <a href="' . $ctaEnlace . '" style="line-height:22px;text-decoration:none;display:inline-block;max-width:100%;mso-padding-alt:0px;background-color:#0052ff;border-radius:4px;color:#ffffff;font-size:15px;font-weight:700;text-align:center;padding:12px 24px;" target="_blank">
                      <span style="max-width:100%;display:inline-block;line-height:120%;">
                        ' . $ctaTexto . '
                      </span>
                    </a>
                  </td>
                </tr>
              </tbody>
            </table>';
        }

        return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html dir="ltr" lang="es">
  <head>
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type" />
    <meta name="x-apple-disable-message-reformatting" />
  </head>
  <body style="background-color:#ffffff">
    <table border="0" width="100%" cellpadding="0" cellspacing="0" role="presentation" align="center">
      <tbody>
        <tr>
          <td style=\'background-color:#ffffff;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif\'>
            <table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="max-width:37.5em;margin:0 auto;padding:40px 20px 64px 20px;width:600px">
              <tbody>
                <tr style="width:100%">
                  <td>
                    <table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:32px;text-align:left">
                      <tbody>
                        <tr>
                          <td>
                            <table border="0" cellpadding="0" cellspacing="0" style="border-collapse:collapse">
                              <tr>
                                <td style="vertical-align:middle;padding-right:0px">
                                  <img alt="Zooki Icon" height="36" src="cid:zooki_icon_blue" style="display:block;outline:none;border:none;text-decoration:none;height:auto" width="36" />
                                </td>
                                <td style="vertical-align:middle">
                                  <img alt="Zooki logotipo" src="cid:zooki_logotipo" style="display:block;outline:none;border:none;text-decoration:none;margin:-15px 0 -15px -10px;height:auto" width="110" />
                                </td>
                              </tr>
                            </table>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                    
                    <h1 style="color:#1d1c1d;font-size:36px;font-weight:800;letter-spacing:-1.2px;line-height:42px;margin:0 0 20px 0">
                      ' . $titulo . '
                    </h1>
                    
                    <p style="font-size:20px;line-height:28px;color:#1d1c1d;margin:0 0 24px 0;">
                      Hola, ' . htmlspecialchars($nombre) . '.
                    </p>
                    
                    ' . $contenidoHtml . '
                    
                    ' . $ctaHtml . '
                    
                    <table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="border-top:1px solid #dddddd;margin:32px 0 24px 0">
                      <tbody>
                        <tr>
                          <td></td>
                        </tr>
                      </tbody>
                    </table>
                    
                    <table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="text-align:left">
                      <tbody>
                        <tr>
                          <td>
                            <p style="font-size:13px;line-height:18px;color:#868686;margin:0 0 8px 0;">
                              Enviado con 💙 por el equipo de Zooki<br />Zooki Inc. · Gestión y Cuidado Veterinario
                            </p>
                            <p style="font-size:11px;line-height:16px;color:#b0b0b0;margin:0;">
                              Si tienes alguna duda o consideras que esto es un error de seguridad, por favor comunícate con nuestro soporte administrativo.
                            </p>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </td>
                </tr>
              </tbody>
            </table>
          </td>
        </tr>
      </tbody>
    </table>
  </body>
</html>';
    }
}
