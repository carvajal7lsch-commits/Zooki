<?php
require_once __DIR__ . '/ClienteWompi.php';
require_once __DIR__ . '/ConfiguracionWompi.php';

/** Solo GET y hosts fijos en S2; nunca HTTP dentro de una transacción PDO. */
final class ClienteWompiHttp implements ClienteWompi
{
    public function __construct(private ConfiguracionWompi $configuracion, private mixed $transporte = null)
    {
    }

    public function transaccion(string $id): array
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{1,128}$/D', $id)) {
            throw new InvalidArgumentException('Identificador de transacción inválido.');
        }
        return $this->get('/transactions/' . rawurlencode($id), ['Authorization: Bearer ' . $this->configuracion->valor('LLAVE_PRIVADA')]);
    }

    public function comercio(): array
    {
        return $this->get('/merchants/info', ['x-merchant-public-key: ' . $this->configuracion->valor('LLAVE_PUBLICA')]);
    }

    private function get(string $ruta, array $cabeceras): array
    {
        $this->configuracion->validar(false);
        $url = $this->configuracion->baseApi() . $ruta;
        $cabeceras[] = 'Accept: application/json';
        if ($this->transporte !== null) {
            $respuesta = ($this->transporte)($url, $cabeceras);
        } else {
            if (defined('ZOOKI_PRUEBAS') || getenv('ZOOKI_ENTORNO_PRUEBAS') === '1') {
                throw new LogicException('Las pruebas requieren transporte Wompi falso.');
            }
            if (!function_exists('curl_init')) {
                throw new RuntimeException('Transporte de pagos no disponible.');
            }
            $curl = curl_init($url);
            curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $cabeceras, CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
            $cuerpo = '';
            curl_setopt($curl, CURLOPT_WRITEFUNCTION, static function ($recurso, string $trozo) use (&$cuerpo): int {
                if (strlen($cuerpo) + strlen($trozo) > 262144) {
                    return 0;
                }
                $cuerpo .= $trozo;
                return strlen($trozo);
            });
            $ok = curl_exec($curl);
            $codigo = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            if ($ok === false) {
                throw new RuntimeException('Wompi no disponible; conciliación pendiente.');
            }
            $respuesta = ['codigo' => $codigo, 'cuerpo' => $cuerpo];
        }
        if (($respuesta['codigo'] ?? 0) !== 200) {
            throw new RuntimeException('Consulta Wompi pendiente.');
        }
        $json = json_decode($respuesta['cuerpo'], true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($json['data'] ?? null)) {
            throw new RuntimeException('Respuesta Wompi inválida.');
        }
        return $json['data'];
    }
}
