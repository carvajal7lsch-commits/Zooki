<?php
/** Enlaces de cuenta reutilizables, con la URL pública configurada de la app. */
final class EnlaceCuenta
{
    public static function crear(string $accion,int $id,string $token): string
    {
        return self::base() . '?' . http_build_query(['action'=>$accion,'id'=>$id,'token'=>$token]);
    }
    public static function base(): string
    {
        $env = is_file(__DIR__ . '/../.env') ? parse_ini_file(__DIR__ . '/../.env') : [];
        $base = rtrim(getenv('APP_URL') ?: ($env['APP_URL'] ?? ''), '/');
        return self::validarBase($base,(string)($_SERVER['HTTP_HOST'] ?? 'localhost'),
            (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'),!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    }
    public static function validarBase(string $base,string $host,string $script,bool $https): string
    {
        if ($base === '') {
            // El Host lo controla el cliente: no enviar secretos a un dominio arbitrario.
            if (!preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:[0-9]{1,5})?$/D',$host)) {
                throw new RuntimeException('Configura APP_URL para enviar enlaces de cuenta.');
            }
            $base = ($https ? 'https://' : 'http://') . $host .
                rtrim(str_replace('\\','/',dirname($script)),'/');
        }
        $partes=parse_url($base);
        if (!$partes || !in_array($partes['scheme'] ?? '',['http','https'],true) || empty($partes['host'])
            || isset($partes['user']) || isset($partes['pass']) || isset($partes['query']) || isset($partes['fragment'])) {
            throw new RuntimeException('APP_URL no es una URL base válida.');
        }
        $base=rtrim($base,'/');
        if (!str_ends_with($base,'index.php')) $base .= '/index.php';
        return $base;
    }
}
