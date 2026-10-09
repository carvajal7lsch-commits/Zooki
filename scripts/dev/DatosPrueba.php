<?php
require_once __DIR__ . '/../../helpers/PoliticaPassword.php';
require_once __DIR__ . '/../../helpers/PoliticaDatos.php';
require_once __DIR__ . '/../../models/ConsentimientoDatos.php';

/**
 * D2.1 — Apoyo de scripts/dev/datos_prueba.php, separado para probarlo sin la
 * base del usuario: la clave común opcional (--clave) y la política aceptada
 * de los usuarios de prueba.
 */
final class DatosPrueba
{
    /** Valor de --clave=<clave>, o null si no se pasó. */
    public static function claveDeArgumentos(array $argumentos): ?string
    {
        foreach ($argumentos as $argumento) {
            if (str_starts_with((string) $argumento, '--clave=')) {
                return substr((string) $argumento, strlen('--clave='));
            }
        }
        return null;
    }

    /**
     * Motivo por el que la clave común no sirve, o null si cumple la
     * política (RN-G10) para todas las personas: la política también la
     * compara con el documento, el nombre y el correo de cada una.
     *
     * @param array<int, array> $personas filas [nombre, documento, correo, ...]
     */
    public static function motivoClaveInvalida(string $clave, array $personas): ?string
    {
        foreach ($personas as [$nombre, $documento, $correo]) {
            $motivo = PoliticaPassword::validar($clave, [(string) $documento, (string) $nombre, (string) $correo]);
            if ($motivo !== null) {
                return "no cumple la política para {$nombre}: " . rtrim($motivo, ".");
            }
        }
        return null;
    }

    /**
     * RE-T.19.3: la persona de prueba ya aceptó la versión vigente, para no
     * pedírsela en cada prueba manual. Devuelve true si la registró ahora.
     */
    public static function aceptarPolitica(PDO $db, int $idUsuario): bool
    {
        $consentimientos = new ConsentimientoDatos($db);
        if ($consentimientos->aceptoVigente($idUsuario)) {
            return false;
        }
        $consentimientos->registrar($idUsuario, PoliticaDatos::MEDIO_FORMULARIO, '127.0.0.1');
        return true;
    }
}
