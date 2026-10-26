<?php
require_once __DIR__ . '/ClienteWompi.php';
require_once __DIR__ . '/ConfiguracionWompi.php';
require_once __DIR__ . '/FirmaWompi.php';

/** Hosts fijos; las pruebas siempre inyectan transporte (RE-0.5.15/18). */
final class ClienteWompiHttp implements ClienteWompi
{
    public function __construct(private ConfiguracionWompi $configuracion, private mixed $transporte = null)
    {
    }

    public function transaccion(string $id): array
    {
        if (!FirmaWompi::idValido($id)) {
            throw new InvalidArgumentException('Identificador de transacción inválido.');
        }
        return $this->get('/transactions/' . rawurlencode($id), ['Authorization: Bearer ' . $this->configuracion->valor('LLAVE_PRIVADA')]);
    }

    public function comercio(): array
    {
        return $this->get('/merchants/info', ['x-merchant-public-key: ' . $this->configuracion->valor('LLAVE_PUBLICA')]);
    }

    public function transaccionesPorReferencia(string $referencia): array
    {
        if (!FirmaWompi::idValido($referencia)) {
            throw new InvalidArgumentException('Referencia de pago inválida.');
        }
        // RE-0.5.15: búsqueda privada sin filtros que oculten pagos en curso (OpenAPI Wompi 1.2.0).
        $lista = $this->peticion('/transactions?reference=' . rawurlencode($referencia), ['Authorization: Bearer ' . $this->configuracion->valor('LLAVE_PRIVADA')], null, true);
        foreach ($lista as $transaccion) {
            if (!is_array($transaccion) || ($transaccion['reference'] ?? null) !== $referencia
                || !FirmaWompi::idValido($transaccion['id'] ?? null)
                || !in_array($transaccion['status'] ?? null, ['PENDING', 'APPROVED', 'DECLINED', 'ERROR', 'VOIDED'], true)) {
                throw new RuntimeException('Búsqueda Wompi no concluyente.');
            }
        }
        return $lista;
    }

    public function tokenNequi(string $token): array
    {
        if (!preg_match('/^nequi_' . $this->configuracion->entorno() . '_[a-zA-Z0-9_-]{1,180}$/D', $token)) {
            throw new InvalidArgumentException('Token Nequi inválido o de otro entorno.');
        }
        return $this->get('/tokens/nequi/' . rawurlencode($token), ['Authorization: Bearer ' . $this->configuracion->valor('LLAVE_PUBLICA')]);
    }

    public function crearFuente(array $datos): array
    {
        return $this->peticion('/payment_sources', ['Authorization: Bearer ' . $this->configuracion->valor('LLAVE_PRIVADA')], $datos);
    }

    public function crearTransaccion(array $datos): array
    {
        $this->configuracion->validarAutomaticos();
        return $this->peticion('/transactions', ['Authorization: Bearer ' . $this->configuracion->valor('LLAVE_PRIVADA')], $datos);
    }

    private function get(string $ruta, array $cabeceras): array
    {
        return $this->peticion($ruta, $cabeceras);
    }

    private function peticion(string $ruta, array $cabeceras, ?array $datos = null, bool $listaCompleta = false): array
    {
        $this->configuracion->validar(false);
        $url = $this->configuracion->baseApi() . $ruta;
        $cabeceras[] = 'Accept: application/json';
        if ($this->transporte !== null) {
            $respuesta = ($this->transporte)($url, $cabeceras, $datos === null ? 'GET' : 'POST', $datos);
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
            if ($datos !== null) {
                $cabeceras[] = 'Content-Type: application/json';
                curl_setopt($curl, CURLOPT_HTTPHEADER, $cabeceras);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($datos, JSON_THROW_ON_ERROR));
            }
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
        if (!in_array($respuesta['codigo'] ?? 0, $datos === null ? [200] : [200, 201], true)) {
            throw new RuntimeException('Consulta Wompi pendiente.');
        }
        if (!is_string($respuesta['cuerpo'] ?? null) || strlen($respuesta['cuerpo']) > 262144) {
            throw new RuntimeException('Respuesta Wompi inválida.');
        }
        $json = json_decode($respuesta['cuerpo'], true, 32, JSON_THROW_ON_ERROR);
        if ($listaCompleta) {
            $objeto = json_decode($respuesta['cuerpo'], false, 32, JSON_THROW_ON_ERROR);
            // {} no es []; metadatos de paginación desconocidos no prueban una lista completa.
            if (!is_array($objeto->data ?? null) || !empty($json['meta'])
                || array_diff(array_keys($json), ['data', 'meta'])) {
                throw new RuntimeException('Búsqueda Wompi incompleta.');
            }
        }
        if (!is_array($json['data'] ?? null)) {
            throw new RuntimeException('Respuesta Wompi inválida.');
        }
        return $json['data'];
    }
}
