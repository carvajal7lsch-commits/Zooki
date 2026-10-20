<?php

/** RE-4.9.2: intervalos absolutos [inicio, fin), sin redondear segundos. */
final class IntervalosAgenda
{
    public static function unir(array $intervalos): array
    {
        usort($intervalos, static fn ($a, $b) => $a[0] <=> $b[0]);
        $resultado = [];
        foreach ($intervalos as [$inicio, $fin]) {
            $ultimo = count($resultado) - 1;
            if ($ultimo >= 0 && $inicio <= $resultado[$ultimo][1]) {
                $resultado[$ultimo][1] = max($resultado[$ultimo][1], $fin);
            } else {
                $resultado[] = [$inicio, $fin];
            }
        }
        return $resultado;
    }

    public static function intersecar(array $a, array $b): array
    {
        $resultado = [];
        foreach ($a as [$inicio, $fin]) {
            foreach ($b as [$otroInicio, $otroFin]) {
                $desde = max($inicio, $otroInicio);
                $hasta = min($fin, $otroFin);
                if ($desde < $hasta) {
                    $resultado[] = [$desde, $hasta];
                }
            }
        }
        usort($resultado, static fn ($a, $b) => $a[0] <=> $b[0]);
        return $resultado;
    }

    public static function restar(array $franjas, array $ocupaciones): array
    {
        foreach ($ocupaciones as [$desde, $hasta]) {
            $restantes = [];
            foreach ($franjas as [$inicio, $fin]) {
                if ($desde >= $fin || $hasta <= $inicio) {
                    $restantes[] = [$inicio, $fin];
                    continue;
                }
                if ($inicio < $desde) {
                    $restantes[] = [$inicio, $desde];
                }
                if ($hasta < $fin) {
                    $restantes[] = [$hasta, $fin];
                }
            }
            $franjas = $restantes;
        }
        return $franjas;
    }

    public static function contiene(array $franjas, int $inicio, int $fin): bool
    {
        foreach ($franjas as [$desde, $hasta]) {
            if ($inicio >= $desde && $fin <= $hasta && $inicio < $fin) {
                return true;
            }
        }
        return false;
    }

    public static function fecha(string $valor): DateTimeImmutable
    {
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $valor, new DateTimeZone('America/Bogota'));
        if ($fecha === false || $fecha->format('Y-m-d H:i:s') !== $valor) {
            throw new InvalidArgumentException('La fecha y hora no son válidas.');
        }
        return $fecha;
    }
}
