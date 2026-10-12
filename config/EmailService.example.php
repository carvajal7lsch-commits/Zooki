<?php
/**
 * CONFIRMACIÓN DE SEGURIDAD:
 * Todas las contraseñas, tokens y correos electrónicos definidos en este archivo 
 * (y cualquier otro archivo de prueba, ejemplo o semilla) son estrictamente ficticios
 * y no representan credenciales reales bajo ninguna circunstancia.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

class EmailService {
    private $mail;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);

        // Configuración del servidor SMTP (Valores ficticios de ejemplo)
        $this->mail->isSMTP();
        $this->mail->Host = 'smtp-relay.sendinblue.com';
        $this->mail->SMTPAuth = true;
        $this->mail->Username = 'TU_LOGIN@smtp-brevo.com';
        $this->mail->Password = 'TU_CLAVE_SMTP_DE_BREVO';
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = 587;

        // Configuración general
        $this->mail->CharSet = 'UTF-8';
        $this->mail->setFrom('no-reply@TU_DOMINIO', 'Zooki - Sistema Veterinario');
    }
    
    public function enviarCorreoPersonalizado($email, $nombre, $asunto, $cuerpoHTML) {
        try {
            $this->mail->addAddress($email, $nombre);
            $this->mail->Subject = $asunto;
            $this->mail->Body = $cuerpoHTML;
            $this->mail->isHTML(true);
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Error al enviar correo: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    public function limpiarDirecciones() {
        $this->mail->clearAddresses();
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
