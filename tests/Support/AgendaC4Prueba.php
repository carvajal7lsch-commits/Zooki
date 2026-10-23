<?php
require_once __DIR__ . '/ConocimientoRealPrueba.php';
require_once __DIR__ . '/../../models/CargadorConocimiento.php';
require_once __DIR__ . '/../../models/SolicitudAgenda.php';

/** Las peticiones históricas ahora recorren la evaluación real previa, sin saltar la puerta C4. */
final class AgendaC4Prueba
{
    public static function capturar(PDO $db, array $datos, callable $reloj): array
    {
        $agenda = new SolicitudAgenda($db, $reloj);
        if (Contexto::rolActivo() === Roles::PROPIETARIO) {
            $agenda = $agenda->enClinicaDelPropietario((int) $datos['id_clinica']);
        }
        $entrada = array_intersect_key($datos, array_flip(['id_mascota', 'id_veterinario', 'id_tipo_cita', 'fecha']));
        $entrada['sintomas'] = [['codigo' => 'sintoma.control_preventivo', 'tiempo' => 'no_aplica']];
        $resultado = $agenda->evaluar($entrada);
        return $datos + ['id_solicitud' => (string) $resultado['id_solicitud']];
    }
}
