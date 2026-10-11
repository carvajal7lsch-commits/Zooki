<?php
require_once __DIR__ . '/AccesoDenegado.php';

/**
 * Respuestas JSON de las acciones *_ajax. Una acción que modifica datos
 * responde siempre igual: 405 si no es POST, 422 con el mensaje de una
 * validación, 500 genérico ante un error inesperado. AccesoDenegado sigue
 * hasta el front controller, que responde 401/403 (RE-T.15.2).
 */
final class RespuestaJson
{
    public static function enviar(array $datos): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    }

    public static function error(int $codigo, string $mensaje, array $extra = []): void
    {
        http_response_code($codigo);
        self::enviar(['success' => false, 'message' => $mensaje] + $extra);
    }

    /**
     * Una lectura (GET): 422 con el mensaje de una validación, 500 genérico
     * ante un error inesperado; AccesoDenegado sigue hasta el front
     * controller. $accion escribe su propia respuesta.
     */
    public static function consulta(callable $accion, string $origen): void
    {
        try {
            $accion();
        } catch (AccesoDenegado $e) {
            throw $e;
        } catch (InvalidArgumentException $e) {
            self::error(422, $e->getMessage());
        } catch (Throwable $e) {
            error_log($origen . ': ' . $e->getMessage());
            self::error(500, 'No se pudo cargar. Intenta nuevamente.');
        }
    }

    /**
     * @param callable(): array $accion devuelve los datos de la respuesta. Si
     *        trae 'success' => false (un aviso que pide confirmar, no un
     *        error), se respeta.
     */
    public static function modificacion(callable $accion, string $origen): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            self::error(405, 'Método no permitido.');
            return;
        }

        try {
            $respuesta = $accion();
        } catch (AccesoDenegado $e) {
            throw $e;
        } catch (InvalidArgumentException $e) {
            self::error(422, $e->getMessage());
            return;
        } catch (Throwable $e) {
            error_log($origen . ': ' . $e->getMessage());
            self::error(500, 'No se pudo guardar. Intenta nuevamente.');
            return;
        }

        self::enviar($respuesta + ['success' => true]);
    }
}
