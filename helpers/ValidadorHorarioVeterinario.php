<?php
require_once __DIR__ . '/CampoInvalido.php';

final class ValidadorHorarioVeterinario
{
    public static function franjas(array $entrada): array
    {
        $salida = [];
        foreach ($entrada as $indice => $fila) {
            if (!is_array($fila) || (!is_int($fila['dia_semana'] ?? null) && !is_string($fila['dia_semana'] ?? null)) || !preg_match('/^[1-7]$/D', (string) $fila['dia_semana'])) {
                throw new CampoInvalido('franjas', 'Día entre 1 y 7.', 'Revisa el día de cada franja.');
            }
            $horas = [];
            foreach (['hora_inicio', 'hora_fin'] as $campo) {
                $valor = $fila[$campo] ?? '';
                if (!is_string($valor) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/D', $valor)) {
                    throw new CampoInvalido('franjas', 'Hora no válida.', 'Usa horas y minutos válidos.');
                }
                $horas[$campo] = strlen($valor) === 5 ? $valor . ':00' : $valor;
            }
            if ($horas['hora_inicio'] >= $horas['hora_fin']) {
                throw new CampoInvalido('franjas', 'Intervalo no válido.', 'El fin debe ser posterior al inicio.');
            }
            $salida[] = ['dia_semana' => (int) $fila['dia_semana']] + $horas;
        }
        usort($salida, static fn ($a, $b) => [$a['dia_semana'], $a['hora_inicio']] <=> [$b['dia_semana'], $b['hora_inicio']]);
        $anterior = null;
        foreach ($salida as $fila) {
            if ($anterior && $fila['dia_semana'] === $anterior['dia_semana'] && $fila['hora_inicio'] < $anterior['hora_fin']) {
                throw new CampoInvalido('franjas', 'Franjas solapadas.', 'Las franjas del mismo día no pueden solaparse.');
            }
            $anterior = $fila;
        }
        return $salida;
    }

    public static function fecha(mixed $valor, string $campo = 'inicio'): string
    {
        if (!is_string($valor)) {
            throw new CampoInvalido($campo, 'Fecha no válida.', 'Revisa fecha y hora.');
        }
        $valor = str_replace('T', ' ', $valor);
        if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}(?::[0-9]{2})?$/D', $valor)) {
            throw new CampoInvalido($campo, 'Fecha no válida.', 'Revisa fecha y hora.');
        }
        $formato = strlen($valor) === 19 ? 'Y-m-d H:i:s' : 'Y-m-d H:i';
        $fecha = DateTimeImmutable::createFromFormat('!' . $formato, $valor);
        if (!$fecha || $fecha->format($formato) !== $valor) {
            throw new CampoInvalido($campo, 'Fecha no válida.', 'Revisa fecha y hora.');
        }
        return $fecha->format('Y-m-d H:i:s');
    }

    public static function motivo(mixed $valor): string
    {
        if (!is_string($valor) || trim($valor) === '' || mb_strlen(trim($valor)) > 255) {
            throw new CampoInvalido('motivo', 'Motivo obligatorio, hasta 255 caracteres.', 'Escribe el motivo (hasta 255 caracteres).');
        }
        return trim($valor);
    }
}
