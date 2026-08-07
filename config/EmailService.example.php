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
        $this->mail->Host = 'smtp.gmail.com'; 
        $this->mail->SMTPAuth = true;
        $this->mail->Username = 'TU_CORREO@gmail.com'; 
        $this->mail->Password = 'TU_CONTRASEÑA_DE_APLICACION'; 
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = 587;

        // Configuración general
        $this->mail->CharSet = 'UTF-8';
        $this->mail->setFrom('TU_CORREO@gmail.com', 'Zooki - Sistema Veterinario');
    }
    
    public function enviarCredencialesUsuario($email, $nombre, $documento, $password) {
        try {
            $this->mail->addAddress($email, $nombre);
            $this->mail->Subject = 'Bienvenido a Zooki - Tus credenciales de acceso';

            $this->mail->Body = $this->generarPlantillaCredenciales($nombre, $documento, $password);
            $this->mail->AltBody = "Hola $nombre,\n\nTus credenciales de acceso a Zooki son:\n\nDocumento: $documento\nContraseña: $password\n\nPor seguridad, te recomendamos cambiar tu contraseña en tu primer inicio de sesión.\n\nSaludos,\nEquipo de Zooki";

            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Error al enviar correo: " . $this->mail->ErrorInfo);
            return false;
        }
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
    
    private function generarPlantillaCredenciales($nombre, $documento, $password) {
        $appUrl = 'http://localhost/Zooki/public/index.php';

        $contenido = '
        <p style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;">
          Tu cuenta ha sido creada exitosamente en el sistema veterinario Zooki. A continuación te presentamos tus credenciales de acceso:
        </p>
        
        <div style="background-color:#f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 24px 0;">
            <p style="margin: 0 0 8px 0; font-size: 15px; color: #1d1c1d;"><strong>📋 Documento:</strong> ' . htmlspecialchars($documento) . '</p>
            <p style="margin: 0; font-size: 15px; color: #1d1c1d;"><strong>🔑 Contraseña:</strong> ' . htmlspecialchars($password) . '</p>
        </div>
        
        <p style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;">
          <strong>⚠️ Importante:</strong> Por motivos de seguridad, te sugerimos cambiar tu contraseña en tu primer inicio de sesión.
        </p>';

        return $this->obtenerPlantillaBaseHTML($nombre, 'Tus credenciales de acceso', $contenido, 'Acceder a Zooki', $appUrl);
    }

    public function obtenerPlantillaBaseHTML($nombre, $titulo, $contenidoHtml, $ctaTexto = null, $ctaEnlace = null) {
        $ctaHtml = '';
        if ($ctaTexto && $ctaEnlace) {
            $ctaHtml = '
            <table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin:28px 0">
              <tbody>
                <tr>
                  <td>
                    <a href="' . $ctaEnlace . '" style="line-height:22px;text-decoration:none;display:inline-block;max-width:100%;background-color:#0052ff;border-radius:4px;color:#ffffff;font-size:15px;font-weight:700;text-align:center;padding:12px 24px;" target="_blank">
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
