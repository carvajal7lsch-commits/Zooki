<?php

/** RN-212: instantánea inmutable por petición; sin conexión, sesión ni reloj. */
final readonly class ConocimientoClinico
{
    public array $nodos;
    public array $aristas;
    public array $reglas;

    public function __construct(array $nodos, array $aristas, array $reglas, public ?string $version, public string $limitaciones)
    {
        $this->nodos = array_column(self::decodificar($nodos), null, 'codigo');
        $this->aristas = self::decodificar($aristas);
        $this->reglas = self::decodificar($reglas);
    }

    private static function decodificar(array $filas): array
    {
        foreach ($filas as &$fila) {
            foreach (['fuentes', 'metadatos', 'condiciones', 'resultado', 'pauta'] as $campo) {
                if (isset($fila[$campo]) && is_string($fila[$campo])) {
                    $fila[$campo] = json_decode($fila[$campo], true, 64, JSON_THROW_ON_ERROR);
                }
            }
        }
        unset($fila);
        return $filas;
    }
}
