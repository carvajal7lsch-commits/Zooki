<?php

/** RE-0.5.11/24: configuración cerrada por defecto, independiente de la atención. */
final class ConfiguracionWompi
{
    private array $valores;

    public function __construct(?array $valores = null)
    {
        if ($valores !== null) {
            $this->valores = $valores;
            return;
        }
        $nombres = ['ENTORNO', 'LLAVE_PUBLICA', 'LLAVE_PRIVADA', 'SECRETO_EVENTOS', 'SECRETO_INTEGRIDAD', 'COBROS_HABILITADOS'];
        $archivo = [];
        $faltantes = array_filter($nombres, static fn ($nombre) => getenv('WOMPI_' . $nombre) === false);
        if ($faltantes && !defined('ZOOKI_PRUEBAS') && getenv('ZOOKI_ENTORNO_PRUEBAS') !== '1' && is_file(__DIR__ . '/../.env')) {
            $archivo = parse_ini_file(__DIR__ . '/../.env', false, INI_SCANNER_RAW) ?: [];
        }
        $this->valores = [];
        foreach ($nombres as $nombre) {
            $valor = getenv('WOMPI_' . $nombre);
            $this->valores[$nombre] = $valor === false ? ($archivo['WOMPI_' . $nombre] ?? '') : $valor;
        }
    }

    public function valor(string $nombre): string
    {
        return (string) ($this->valores[$nombre] ?? '');
    }

    public function entorno(): string
    {
        return match ($this->valor('ENTORNO')) {
            'sandbox' => 'test',
            'produccion' => 'prod',
            default => throw new RuntimeException('Configura el entorno de Wompi.'),
        };
    }

    public function validar(bool $cobrar = true): void
    {
        $entorno = $this->entorno();
        foreach (['LLAVE_PUBLICA' => 'pub_', 'LLAVE_PRIVADA' => 'prv_', 'SECRETO_EVENTOS' => $entorno . '_events_', 'SECRETO_INTEGRIDAD' => $entorno . '_integrity_'] as $campo => $prefijo) {
            $prefijo = in_array($campo, ['LLAVE_PUBLICA', 'LLAVE_PRIVADA'], true) ? $prefijo . $entorno . '_' : $prefijo;
            if (!str_starts_with($this->valor($campo), $prefijo) || strlen($this->valor($campo)) <= strlen($prefijo)) {
                throw new RuntimeException('Pago no disponible: falta configuración de Wompi o corresponde a otro entorno.');
            }
        }
        if ($cobrar && $this->valor('COBROS_HABILITADOS') !== '1') {
            throw new RuntimeException('Los pagos están deshabilitados temporalmente. La atención continúa disponible.');
        }
    }

    /** El inbox no depende de la llave de consulta ni del permiso para crear cargos. */
    public function validarEventos(): void
    {
        $prefijo = $this->entorno() . '_events_';
        if (!str_starts_with($this->valor('SECRETO_EVENTOS'), $prefijo) || strlen($this->valor('SECRETO_EVENTOS')) <= strlen($prefijo)) {
            throw new RuntimeException('No está configurada la verificación de eventos.');
        }
    }

    public function baseApi(): string
    {
        return $this->entorno() === 'test' ? 'https://sandbox.wompi.co/v1' : 'https://production.wompi.co/v1';
    }
}
