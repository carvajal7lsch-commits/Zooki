<?php

/** Algoritmos SHA256 publicados por Wompi; no HMAC ni firma inventada. */
final class FirmaWompi
{
    public const EVENTO_MAX_BYTES = 65536;
    public const PROPIEDADES_MAX = 64;

    public static function integridad(string $referencia, int $centavos, string $moneda, string $secreto, string $expiracion = ''): string
    {
        return hash('sha256', $referencia . $centavos . $moneda . $expiracion . $secreto);
    }

    public static function evento(string $cuerpo, string $cabecera, string $entorno, string $secreto): array
    {
        if (strlen($cuerpo) > self::EVENTO_MAX_BYTES) {
            throw new LengthException('Evento demasiado grande.');
        }
        try {
            $evento = json_decode($cuerpo, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Evento inválido.');
        }
        if (!is_array($evento) || ($evento['environment'] ?? null) !== $entorno || !is_int($evento['timestamp'] ?? null)
            || $evento['timestamp'] < 0 || !is_string($evento['event'] ?? null) || !preg_match('/^[a-z][a-z0-9_.]{0,79}$/D', $evento['event'])
            || !is_array($evento['data'] ?? null) || !is_array($evento['signature'] ?? null)) {
            throw new InvalidArgumentException('Evento o entorno inválido.');
        }
        $firma = $evento['signature'];
        $propiedades = $firma['properties'] ?? null;
        if (!is_array($propiedades) || !array_is_list($propiedades) || !$propiedades || count($propiedades) > self::PROPIEDADES_MAX) {
            throw new InvalidArgumentException('Propiedades inválidas.');
        }
        $cadena = '';
        $valores = [];
        foreach ($propiedades as $propiedad) {
            if (!is_string($propiedad) || !preg_match('/^[a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*$/D', $propiedad) || strlen($propiedad) > 200) {
                throw new InvalidArgumentException('Propiedad inválida.');
            }
            $valor = $evento['data'];
            foreach (explode('.', $propiedad) as $parte) {
                if (!is_array($valor) || !array_key_exists($parte, $valor)) {
                    throw new InvalidArgumentException('Propiedad ausente.');
                }
                $valor = $valor[$parte];
            }
            if (!is_string($valor) && !is_int($valor) && !is_bool($valor)) {
                throw new InvalidArgumentException('Propiedad no escalar.');
            }
            $cadena .= is_bool($valor) ? ($valor ? 'true' : 'false') : (string) $valor;
            $valores[] = $valor;
        }
        $checksum = $firma['checksum'] ?? ($cabecera ?: null);
        if (!is_string($checksum) || !preg_match('/^[a-fA-F0-9]{64}$/D', $checksum)
            || ($cabecera !== '' && !hash_equals(strtolower($checksum), strtolower($cabecera)))
            || !hash_equals(hash('sha256', $cadena . $evento['timestamp'] . $secreto), strtolower($checksum))) {
            throw new UnexpectedValueException('Firma inválida.');
        }
        $id = $evento['data']['transaction']['id'] ?? '';
        if ($evento['event'] === 'transaction.updated' && (!is_string($id) || !preg_match('/^[a-zA-Z0-9_-]{1,128}$/D', $id)
            || !in_array('transaction.id', $propiedades, true))) {
            throw new InvalidArgumentException('El identificador debe estar firmado.');
        }
        $canonico = json_encode([$entorno, $evento['event'], $id, $evento['timestamp'], $propiedades, strtolower($checksum)], JSON_THROW_ON_ERROR);
        return ['entorno' => $entorno, 'tipo' => $evento['event'], 'id_transaccion_wompi' => $evento['event'] === 'transaction.updated' ? $id : '',
            'timestamp_proveedor' => $evento['timestamp'], 'checksum' => strtolower($checksum), 'clave_evento' => hash('sha256', $canonico),
            'huella_datos_firmados' => hash('sha256', json_encode([$propiedades, $valores], JSON_THROW_ON_ERROR))];
    }
}
