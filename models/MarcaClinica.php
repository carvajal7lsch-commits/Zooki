<?php
require_once __DIR__ . '/ModeloClinica.php';
require_once __DIR__ . '/../helpers/ValidadorConfiguracionClinica.php';
require_once __DIR__ . '/../helpers/EnlaceCuenta.php';

/** RE-7.4.4: presentación desde una clínica autorizada o la procedencia de un registro ya autorizado. */
final class MarcaClinica extends ModeloClinica
{
    public function actual(): array
    {
        $q = $this->conn->prepare('SELECT id_clinica, nombre, direccion, telefono, telefono_urgencias, email_contacto, url_logo FROM clinicas WHERE id_clinica = ?');
        $q->execute([$this->clinica()]);
        $marca = $q->fetch(PDO::FETCH_ASSOC);
        if ($marca === false) {
            $this->denegarAcceso('clinicas', $this->clinica(), 'Marca fuera del contexto autorizado');
        }
        return $marca;
    }

    /** No busca otra clínica ni deduce contexto desde el nombre: recibe la fila del acto/evento. */
    public static function delRegistro(array $fila): ?array
    {
        if (empty($fila['id_clinica']) || empty($fila['clinica_nombre'])) {
            return null;
        }
        return ['id_clinica' => (int) $fila['id_clinica'], 'nombre' => $fila['clinica_nombre'],
            'url_logo' => $fila['clinica_logo'] ?? null, 'direccion' => $fila['clinica_direccion'] ?? null,
            'telefono' => $fila['clinica_telefono'] ?? null, 'email_contacto' => $fila['clinica_email'] ?? null,
            'telefono_urgencias' => $fila['clinica_urgencias'] ?? null];
    }

    public static function logo(?string $url, bool $correo = false): string
    {
        $url = trim($url ?? '');
        if (!ValidadorConfiguracionClinica::logoValido($url)) {
            return '';
        }
        if ($correo && $url !== '' && parse_url($url, PHP_URL_SCHEME) === null) {
            $base = EnlaceCuenta::base();
            $p = parse_url($base);
            $origen = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
            $url = str_starts_with($url, '/') ? $origen . $url : substr($base, 0, strrpos($base, '/') + 1) . $url;
        }
        return $url;
    }

    public static function html(?array $marca, bool $correo = false): string
    {
        if ($marca === null) {
            return '';
        }
        $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $logo = self::logo($marca['url_logo'] ?? null, $correo);
        if ($correo) {
            return self::htmlCorreo($marca, $logo, $e);
        }
        $html = '<div class="marca-clinica">';
        if ($logo !== '') {
            $html .= '<img class="marca-clinica-logo" width="96" src="' . $e($logo) . '" alt="Logo de ' . $e($marca['nombre']) . '">';
        }
        $html .= '<strong>' . $e($marca['nombre']) . '</strong>';
        foreach (['direccion', 'telefono', 'email_contacto'] as $campo) {
            if (!empty($marca[$campo])) {
                $html .= '<span>' . $e($marca[$campo]) . '</span>';
            }
        }
        if (!empty($marca['telefono_urgencias'])) {
            $html .= '<span>Urgencias: ' . $e($marca['telefono_urgencias']) . '</span>';
        }
        return $html . '</div>';
    }

    /** RE-7.4.4: los clientes de correo no reciben el grid/CSS de la web. */
    private static function htmlCorreo(array $marca, string $logo, callable $e): string
    {
        $html = '<table class="marca-clinica-correo" role="presentation" border="0" cellpadding="0" cellspacing="0"><tbody>';
        if ($logo !== '') {
            // Decisión del usuario (2026-10-18): ancho 96; altura natural para no deformar.
            $html .= '<tr><td><img width="96" border="0" src="' . $e($logo) . '" alt="Logo de ' . $e($marca['nombre']) . '"></td></tr>';
        }
        $html .= '<tr><td><strong>' . $e($marca['nombre']) . '</strong></td></tr>';
        foreach (['direccion', 'telefono', 'email_contacto'] as $campo) {
            if (!empty($marca[$campo])) {
                $html .= '<tr><td>' . $e($marca[$campo]) . '</td></tr>';
            }
        }
        if (!empty($marca['telefono_urgencias'])) {
            $html .= '<tr><td>Urgencias: ' . $e($marca['telefono_urgencias']) . '</td></tr>';
        }
        return $html . '</tbody></table>';
    }
}
