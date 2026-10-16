<?php
require_once __DIR__ . '/CampoInvalido.php';

/**
 * M2 C5b (HU-2.4, RE-2.4.1): límites y avisos de una fila de tratamiento.
 * El formulario de la consulta usa los mismos máximos, vías y avisos
 * (validación en tiempo real y restricción de entrada); el servidor valida igual.
 */
final class ValidadorTratamiento
{
    public const MEDICAMENTO_MAX = 150;
    public const DOSIS_MAX = 100;
    public const DURACION_MAX = 100;
    public const OBSERVACIONES_MAX = 500;

    /** Vías de administración que ofrece la fila; caben en tratamientos.via_administracion (50). */
    public const VIAS = ['Oral', 'Subcutánea', 'Intramuscular', 'Intravenosa', 'Tópica', 'Oftálmica', 'Ótica', 'Inhalada', 'Rectal'];

    /** Texto libre de la fila: letras, números, signos y espacios; sin caracteres de control. */
    public const CARACTERES = '\p{L}\p{M}\p{N}\p{P}\p{S} ';

    public const AVISOS = [
        'medicamento' => 'Escribe el medicamento o elígelo del catálogo.',
        'dosis' => 'Indica la dosis.',
        'via_administracion' => 'Elige la vía de administración.',
        'duracion' => 'Indica la duración.',
        'fecha_inicio' => 'Indica una fecha de inicio válida.',
        'fecha_fin' => 'El fin no puede ser anterior al inicio.',
    ];

    /** El error va junto a su campo en la fila N (tratamientos.N.campo). */
    public static function error(int $indice, string $campo, string $mensaje): never
    {
        throw new CampoInvalido('tratamientos.' . $indice . '.' . $campo, self::AVISOS[$campo] ?? $mensaje, $mensaje);
    }
}
