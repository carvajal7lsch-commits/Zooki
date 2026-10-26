<?php

/** RN-018 y RE-0.5.26: prueba de condiciones; nunca persistir JWT. */
final class AutorizacionCobro
{
    public const VERSION = 'mensual-v1';

    public static function contratos(array $comercio): array
    {
        $prueba = [];
        $tokens = [];
        foreach (['presigned_acceptance' => 'acceptance_token', 'presigned_personal_data_auth' => 'accept_personal_auth'] as $campo => $destino) {
            $contrato = $comercio[$campo] ?? [];
            $token = $contrato['acceptance_token'] ?? '';
            $url = $contrato['permalink'] ?? '';
            if (!is_string($token) || strlen($token) > 16384 || !is_string($url) || strlen($url) > 2048
                || !filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
                throw new RuntimeException('No se pudieron consultar las condiciones de Wompi.');
            }
            $partes = explode('.', $token);
            $json = count($partes) === 3 ? base64_decode(strtr($partes[1], '-_', '+/'), true) : false;
            $datos = $json === false ? null : json_decode($json, true);
            if (!is_array($datos) || !is_scalar($datos['contract_id'] ?? null) || !is_string($datos['file_hash'] ?? null)
                || $datos['file_hash'] === '' || (isset($datos['exp']) && (!is_numeric($datos['exp']) || (int) $datos['exp'] <= time()))) {
                throw new RuntimeException('Las condiciones de Wompi deben actualizarse.');
            }
            // El JWT proviene del GET del proveedor; aquí solo extraemos identidad, no confiamos en uno del navegador.
            $prueba[$destino] = ['enlace' => $url, 'contrato' => (string) $datos['contract_id'], 'huella' => $datos['file_hash']];
            $tokens[$destino] = $token;
        }
        return ['prueba' => $prueba, 'version' => hash('sha256', json_encode($prueba, JSON_THROW_ON_ERROR)), 'tokens' => $tokens];
    }

}
