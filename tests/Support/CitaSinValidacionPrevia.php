<?php
require_once __DIR__ . '/../../models/Cita.php';

/**
 * Simula la carrera de RN-401: dos peticiones pasan la validación de la
 * aplicación a la vez y la base decide con el índice único (D-2).
 */
final class CitaSinValidacionPrevia extends Cita
{
    protected function exigirHorarioLibre(int $idVeterinario, int $idMascota, string $fecha, string $hora, int $duracion, ?int $excluir): void
    {
    }
}
