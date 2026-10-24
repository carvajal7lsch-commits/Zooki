<?php
require_once __DIR__ . '/QrCarnet.php';
require_once __DIR__ . '/Contexto.php';

/** RE-5.11.3: la sesión lleva un token, nunca un id recibido del enlace público. */
final class RetornoCarnet
{
    public static function recordar(mixed $token): void
    {
        try {
            $_SESSION['retorno_carnet'] = QrCarnet::validarToken($token);
        } catch (InvalidArgumentException $error) {
            unset($_SESSION['retorno_carnet']);
        }
    }

    public static function contexto(array $disponibles): ?array
    {
        if (empty($_SESSION['retorno_carnet'])) {
            return null;
        }
        return Contexto::buscar($disponibles, Contexto::PROPIETARIO);
    }

    public static function consumir(PDO $db): ?int
    {
        $token = $_SESSION['retorno_carnet'] ?? null;
        unset($_SESSION['retorno_carnet']);
        if (!is_string($token) || (Contexto::actual()['tipo'] ?? null) !== Contexto::PROPIETARIO) {
            return null;
        }
        $consulta = $db->prepare('SELECT id_mascota, token_carnet FROM mascotas WHERE token_carnet = ? AND id_propietario = ? AND estado = 1 AND carnet_activo = 1');
        $consulta->execute([$token, Contexto::idUsuario()]);
        $fila = $consulta->fetch(PDO::FETCH_ASSOC);
        return $fila === false || !hash_equals($fila['token_carnet'], $token) ? null : (int) $fila['id_mascota'];
    }
}
