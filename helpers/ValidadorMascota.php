<?php
/**
 * Datos de una mascota que llegan del portal del propietario (HU-15, RE-15.9).
 *
 * Antes se guardaban tal cual: el peso podía ser «abc» o -5, el sexo
 * cualquier texto, la fecha de nacimiento del futuro y el propietario podía
 * crear razas nuevas escribiendo lo que quisiera (ahora escribe la suya como
 * raza indicada, sin crearla). Aquí se valida el formato;
 * que la especie y la raza existan lo comprueba el controlador contra la base.
 * El color no lo pide el portal: lo registra la clínica en la consulta.
 */
final class ValidadorMascota
{
    public const NOMBRE_MAX = 50;
    public const PESO_MAX_KG = 150;
    public const EDAD_MAX_ANIOS = 40;
    public const SEXOS = ['Macho', 'Hembra'];
    public const RAZA_OTRA = 'otra';
    public const RAZA_MAX = 50;

    /**
     * @return array{datos: array<string, mixed>, error: ?string}
     */
    public static function validar(array $entrada, DateTimeImmutable $hoy): array
    {
        $datos = [];

        // Espacios repetidos fuera; letras (con tildes), números, espacio, punto, guion y apóstrofo.
        $nombre = preg_replace('/\s+/u', ' ', trim((string) ($entrada['nombre'] ?? '')));
        if ($nombre === '') {
            return self::error('Escribe el nombre de la mascota.');
        }
        if (mb_strlen($nombre) > self::NOMBRE_MAX) {
            return self::error('El nombre no puede tener más de ' . self::NOMBRE_MAX . ' caracteres.');
        }
        if (!preg_match("/^[\\p{L}\\p{N}][\\p{L}\\p{N} .'\\-]*$/u", $nombre)) {
            return self::error('El nombre solo puede tener letras, números, espacios, puntos, guiones y apóstrofos.');
        }
        $datos['nombre'] = $nombre;

        $especie = (string) ($entrada['especie'] ?? '');
        if (!ctype_digit($especie) || (int) $especie <= 0) {
            return self::error('Elige la especie de la lista.');
        }
        $datos['especie'] = (int) $especie;

        // «otra»: el propietario sabe la raza pero no está en la lista. Se guarda
        // como «Sin raza definida» y lo que escribió queda para que la clínica lo revise.
        $raza = (string) ($entrada['raza'] ?? '');
        $datos['raza_indicada'] = null;
        if ($raza === self::RAZA_OTRA) {
            $indicada = preg_replace('/\s+/u', ' ', trim((string) ($entrada['raza_indicada'] ?? '')));
            if (mb_strlen($indicada) < 2) {
                return self::error('Escribe el nombre de la raza.');
            }
            if (mb_strlen($indicada) > self::RAZA_MAX) {
                return self::error('El nombre de la raza no puede tener más de ' . self::RAZA_MAX . ' caracteres.');
            }
            if (!preg_match("/^[\\p{L}][\\p{L}\\p{N} .'()\\-]*$/u", $indicada)) {
                return self::error('La raza solo puede tener letras, números, espacios, puntos, guiones, apóstrofos y paréntesis.');
            }
            $datos['raza'] = null;
            $datos['raza_indicada'] = $indicada;
        } elseif (ctype_digit($raza) && (int) $raza > 0) {
            $datos['raza'] = (int) $raza;
        } else {
            return self::error('Elige la raza de la lista.');
        }

        $sexo = (string) ($entrada['sexo'] ?? '');
        if (!in_array($sexo, self::SEXOS, true)) {
            return self::error('Elige si la mascota es macho o hembra.');
        }
        $datos['sexo'] = $sexo;

        // Acepta coma decimal («8,5»), que es como se escribe en Colombia.
        $peso = str_replace(',', '.', trim((string) ($entrada['peso'] ?? '')));
        if (!preg_match('/^\d{1,3}(\.\d{1,2})?$/', $peso) || (float) $peso <= 0 || (float) $peso > self::PESO_MAX_KG) {
            return self::error('El peso debe ser un número mayor que 0 y hasta ' . self::PESO_MAX_KG . ' kg, con máximo dos decimales.');
        }
        $datos['peso'] = $peso;

        $nacimiento = trim((string) ($entrada['fecha_nacimiento'] ?? ''));
        if ($nacimiento === '') {
            $datos['fecha_nacimiento'] = null;
        } else {
            $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $nacimiento, $hoy->getTimezone());
            if (!$fecha || $fecha->format('Y-m-d') !== $nacimiento) {
                return self::error('La fecha de nacimiento no es válida.');
            }
            if ($fecha > $hoy) {
                return self::error('La fecha de nacimiento no puede ser posterior a hoy.');
            }
            if ($fecha < $hoy->modify('-' . self::EDAD_MAX_ANIOS . ' years')) {
                return self::error('Revisa la fecha de nacimiento: la mascota tendría más de ' . self::EDAD_MAX_ANIOS . ' años.');
            }
            $datos['fecha_nacimiento'] = $nacimiento;
        }

        return ['datos' => $datos, 'error' => null];
    }

    private static function error(string $mensaje): array
    {
        return ['datos' => [], 'error' => $mensaje];
    }
}
