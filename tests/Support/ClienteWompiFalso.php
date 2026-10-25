<?php
require_once __DIR__ . '/../../helpers/ClienteWompi.php';
require_once __DIR__ . '/../../helpers/ConfiguracionWompi.php';
require_once __DIR__ . '/../../helpers/FirmaWompi.php';

/** Sin transporte: también sandbox se simula completamente. */
final class ClienteWompiFalso implements ClienteWompi
{
    public array $respuestas = [];
    public array $consultas = [];
    public mixed $antesDeConsultar = null;
    public array $datosComercio = ['id' => 123];

    public static function configuracion(string $entorno = 'test', string $habilitado = '1'): ConfiguracionWompi
    {
        return new ConfiguracionWompi(['ENTORNO' => $entorno === 'test' ? 'sandbox' : 'produccion', 'LLAVE_PUBLICA' => 'pub_' . $entorno . '_falsa',
            'LLAVE_PRIVADA' => 'prv_' . $entorno . '_falsa', 'SECRETO_EVENTOS' => $entorno . '_events_falso', 'SECRETO_INTEGRIDAD' => $entorno . '_integrity_falso', 'COBROS_HABILITADOS' => $habilitado]);
    }

    public function transaccion(string $id): array
    {
        $this->consultas[] = $id;
        if ($this->antesDeConsultar !== null) {
            ($this->antesDeConsultar)();
        }
        $respuesta = $this->respuestas[$id] ?? new RuntimeException('404 simulado');
        if ($respuesta instanceof Throwable) {
            throw $respuesta;
        }
        return $respuesta;
    }

    public function comercio(): array
    {
        if ($this->antesDeConsultar !== null) {
            ($this->antesDeConsultar)();
        }
        return $this->datosComercio;
    }

    public static function cuerpo(array $transaccion, string $entorno = 'test', int $timestamp = 1792846800, ?array $propiedades = null): string
    {
        $propiedades ??= ['transaction.id', 'transaction.status', 'transaction.amount_in_cents'];
        $cadena = '';
        foreach ($propiedades as $propiedad) {
            $valor = ['transaction' => $transaccion];
            foreach (explode('.', $propiedad) as $parte) {
                $valor = $valor[$parte];
            }
            $cadena .= (string) $valor;
        }
        return json_encode(['event' => 'transaction.updated', 'data' => ['transaction' => $transaccion], 'environment' => $entorno,
            'timestamp' => $timestamp, 'signature' => ['properties' => $propiedades, 'checksum' => hash('sha256', $cadena . $timestamp . $entorno . '_events_falso')]], JSON_THROW_ON_ERROR);
    }
}
