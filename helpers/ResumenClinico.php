<?php

/**
 * Resumen clínico de una mascota para la pantalla de atención.
 *
 * Arma, a partir de los registros que ya se consultan, lo que el veterinario
 * necesita ver de un vistazo antes de atender: la última consulta, el último
 * peso registrado y las vacunas o desparasitaciones vencidas o por vencer.
 *
 * Es una clase sin estado ni base de datos (recibe arreglos y la fecha de
 * hoy), para que la lógica no viva en la vista y se pueda probar sola.
 */
class ResumenClinico
{
    /** Días hacia adelante en que una dosis se considera "próxima". */
    public const DIAS_PROXIMA = 30;

    /**
     * @param array $consultas         Filas de consultas, de la más reciente a la más antigua.
     * @param array $vacunas           Filas de vacunas (nombre_vacuna, fecha_aplicacion, fecha_proxima_dosis).
     * @param array $desparasitaciones Filas de desparasitaciones (tipo, producto, fecha_aplicacion, fecha_proxima).
     */
    public static function construir(array $consultas, array $vacunas, array $desparasitaciones, DateTimeImmutable $hoy): array
    {
        $ultima = $consultas[0] ?? null;

        $ultimoPeso = null;
        foreach ($consultas as $consulta) {
            if (isset($consulta['peso']) && $consulta['peso'] !== null && $consulta['peso'] !== '') {
                $ultimoPeso = (float) $consulta['peso'];
                break;
            }
        }

        $alertas = array_merge(
            self::alertasPorDosis($vacunas, 'nombre_vacuna', 'fecha_proxima_dosis', 'vacuna', $hoy),
            self::alertasPorDosis($desparasitaciones, 'tipo', 'fecha_proxima', 'desparasitacion', $hoy)
        );
        // Vencidas primero; dentro de cada grupo, la fecha más cercana primero.
        usort($alertas, fn($a, $b) => [$a['estado'] !== 'vencida', $a['fecha']] <=> [$b['estado'] !== 'vencida', $b['fecha']]);

        return [
            'ultima_consulta' => $ultima ? [
                'fecha' => $ultima['fecha_hora'] ?? null,
                'diagnostico' => $ultima['diagnostico'] ?? '',
                'veterinario' => $ultima['veterinario'] ?? '',
            ] : null,
            'ultimo_peso' => $ultimoPeso,
            'alertas' => $alertas,
        ];
    }

    /**
     * Solo cuenta la aplicación más reciente de cada vacuna (o tipo de
     * desparasitación): si ya se aplicó el refuerzo, la dosis anterior no
     * está "vencida" aunque su fecha próxima haya pasado.
     */
    private static function alertasPorDosis(array $filas, string $campoNombre, string $campoProxima, string $clase, DateTimeImmutable $hoy): array
    {
        $ultimas = [];
        foreach ($filas as $fila) {
            $nombre = trim((string) ($fila[$campoNombre] ?? ''));
            if ($nombre === '') continue;
            $clave = mb_strtolower($nombre);
            if (!isset($ultimas[$clave]) || ($fila['fecha_aplicacion'] ?? '') > ($ultimas[$clave]['fecha_aplicacion'] ?? '')) {
                $ultimas[$clave] = $fila + ['_nombre' => $nombre];
            }
        }

        $limite = $hoy->modify('+' . self::DIAS_PROXIMA . ' days')->format('Y-m-d');
        $hoyTexto = $hoy->format('Y-m-d');
        $alertas = [];

        foreach ($ultimas as $fila) {
            $proxima = $fila[$campoProxima] ?? null;
            if (empty($proxima) || $proxima > $limite) continue;

            $alertas[] = [
                'clase' => $clase,
                'nombre' => $fila['_nombre'],
                'fecha' => substr($proxima, 0, 10),
                'estado' => $proxima < $hoyTexto ? 'vencida' : 'proxima',
            ];
        }

        return $alertas;
    }

    /** Edad legible: "3 años", "5 meses", "Recién nacido" o "Desconocida". */
    public static function edadLegible(?string $fechaNacimiento, DateTimeImmutable $hoy): string
    {
        if (empty($fechaNacimiento)) return 'Desconocida';

        try {
            $diferencia = $hoy->diff(new DateTimeImmutable($fechaNacimiento));
        } catch (Exception $e) {
            return 'Desconocida';
        }

        if ($diferencia->y) return $diferencia->y . ' año' . ($diferencia->y > 1 ? 's' : '');
        if ($diferencia->m) return $diferencia->m . ' mes' . ($diferencia->m > 1 ? 'es' : '');
        return 'Recién nacido';
    }
}
