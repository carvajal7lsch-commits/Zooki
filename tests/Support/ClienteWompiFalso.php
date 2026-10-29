<?php
require_once __DIR__ . '/../../helpers/ClienteWompi.php';
require_once __DIR__ . '/../../helpers/ConfiguracionWompi.php';
require_once __DIR__ . '/../../helpers/FirmaWompi.php';

/** Sin transporte: también sandbox se simula completamente. */
final class ClienteWompiFalso implements ClienteWompi
{
    public array $respuestas = [];
    public array $consultas = [];
    public array $porReferencia = [];
    public array $referenciasConsultadas = [];
    public mixed $antesDeConsultar = null;
    public array $datosComercio = ['id' => 123];
    public array $fuente = ['id' => 321, 'type' => 'CARD', 'status' => 'AVAILABLE', 'public_data' => ['brand' => 'VISA', 'last_four' => '4242']];
    public string $estadoNequi = 'APPROVED';
    public array $creaciones = [];
    public mixed $alCrear = null;

    public static function configuracion(string $entorno = 'test', string $habilitado = '1', string $automaticos = '0'): ConfiguracionWompi
    {
        return new ConfiguracionWompi(['ENTORNO' => $entorno === 'test' ? 'sandbox' : 'produccion', 'LLAVE_PUBLICA' => 'pub_' . $entorno . '_falsa',
            'LLAVE_PRIVADA' => 'prv_' . $entorno . '_falsa', 'SECRETO_EVENTOS' => $entorno . '_events_falso', 'SECRETO_INTEGRIDAD' => $entorno . '_integrity_falso', 'COBROS_HABILITADOS' => $habilitado, 'AUTOMATICOS_HABILITADOS' => $automaticos]);
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

    public function transaccionesPorReferencia(string $referencia): array
    {
        $this->referenciasConsultadas[] = $referencia;
        if ($this->antesDeConsultar !== null) {
            ($this->antesDeConsultar)();
        }
        $respuesta = $this->porReferencia[$referencia] ?? new RuntimeException('Búsqueda no simulada');
        if ($respuesta instanceof Throwable) {
            throw $respuesta;
        }
        return $respuesta;
    }

    public function tokenNequi(string $token): array
    {
        if ($this->antesDeConsultar !== null) {
            ($this->antesDeConsultar)();
        }
        return ['id' => $token, 'status' => $this->estadoNequi];
    }

    public function crearFuente(array $datos): array
    {
        if ($this->antesDeConsultar !== null) {
            ($this->antesDeConsultar)();
        }
        $this->creaciones[] = ['fuente' => $datos];
        return $this->fuente;
    }

    public function crearTransaccion(array $datos): array
    {
        if ($this->antesDeConsultar !== null) {
            ($this->antesDeConsultar)();
        }
        $this->creaciones[] = ['transaccion' => $datos];
        if ($this->alCrear !== null) {
            return ($this->alCrear)($datos);
        }
        return ['id' => 'tx_automatica_' . count($this->creaciones), 'status' => 'PENDING', 'reference' => $datos['reference'], 'amount_in_cents' => $datos['amount_in_cents'], 'currency' => $datos['currency']];
    }

    public static function contratos(string $huella = 'version1'): array
    {
        $comercio = ['id' => 123];
        foreach (['presigned_acceptance' => 1, 'presigned_personal_data_auth' => 2] as $campo => $id) {
            $payload = rtrim(strtr(base64_encode(json_encode(['contract_id' => $id, 'file_hash' => $huella, 'exp' => 4102444800], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
            $comercio[$campo] = ['permalink' => 'https://wompi.co/contrato-' . $id . '.pdf', 'acceptance_token' => 'falso.' . $payload . '.falso'];
        }
        return $comercio;
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
