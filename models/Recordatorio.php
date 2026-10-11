<?php
require_once __DIR__ . '/../helpers/VentanaRecordatorio.php';

/**
 * Recordatorios por correo (HU-3.2, HU-3.6 y RE-4.4.1).
 *
 * Excepción explícita a RNF-11: solo la tarea del sistema recorre todas las
 * clínicas, sin contexto de sesión. Cada aviso conserva la clínica del
 * registro original; estos métodos no se usan para responder a pantallas.
 * Las fechas llegan en America/Bogota (RE-3.6.3), como en la v1: el MER
 * todavía no tiene una zona horaria configurable por clínica.
 */
class Recordatorio
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Vacunas y desparasitaciones que vencen de hoy a 7 días, con el aviso que
     * les toca. Quedan fuera (RE-3.6.4, RN-115):
     * · las mascotas inactivas, para no escribirle al dueño de una mascota fallecida;
     * · las dosis ya renovadas: si se aplicó una dosis posterior de la misma
     *   vacuna o del mismo tipo de desparasitación, la fecha vieja ya no aplica.
     */
    public function dosisPorRecordar(string $hoy): array
    {
        $hasta = VentanaRecordatorio::hasta($hoy);

        $vacunas = $this->filas(
            "SELECT 'vacuna' AS tipo_entidad, v.id_vacuna AS id_entidad, v.nombre_vacuna AS nombre_item,
                    v.fecha_proxima_dosis AS fecha_proxima, m.nombre AS mascota_nombre,
                    v.id_clinica, cl.nombre AS clinica_nombre,
                    u.id_usuario, u.nombre_completo AS prop_nombre, u.email
               FROM vacunas v
               JOIN clinicas cl ON cl.id_clinica = v.id_clinica
               JOIN mascotas m ON m.id_mascota = v.id_mascota
               JOIN mascota_clinica mc ON mc.id_mascota = m.id_mascota AND mc.id_clinica = v.id_clinica
               JOIN usuarios u ON u.id_usuario = m.id_propietario
              WHERE v.fecha_proxima_dosis BETWEEN ? AND ?
                AND m.estado = 1
                AND mc.estado = 'activo' AND u.estado = 1
                AND u.email IS NOT NULL AND u.email <> ''
                AND NOT EXISTS (SELECT 1 FROM vacunas r
                                 WHERE r.id_mascota = v.id_mascota
                                   AND r.nombre_vacuna = v.nombre_vacuna
                                   AND r.fecha_aplicacion > v.fecha_aplicacion)",
            [$hoy, $hasta]
        );

        $desparasitaciones = $this->filas(
            "SELECT 'desparasitacion' AS tipo_entidad, d.id_desparasitacion AS id_entidad,
                    d.tipo, d.producto, d.fecha_proxima, m.nombre AS mascota_nombre,
                    d.id_clinica, cl.nombre AS clinica_nombre,
                    u.id_usuario, u.nombre_completo AS prop_nombre, u.email
               FROM desparasitaciones d
               JOIN clinicas cl ON cl.id_clinica = d.id_clinica
               JOIN mascotas m ON m.id_mascota = d.id_mascota
               JOIN mascota_clinica mc ON mc.id_mascota = m.id_mascota AND mc.id_clinica = d.id_clinica
               JOIN usuarios u ON u.id_usuario = m.id_propietario
              WHERE d.fecha_proxima BETWEEN ? AND ?
                AND m.estado = 1
                AND mc.estado = 'activo' AND u.estado = 1
                AND u.email IS NOT NULL AND u.email <> ''
                AND NOT EXISTS (SELECT 1 FROM desparasitaciones r
                                 WHERE r.id_mascota = d.id_mascota
                                   AND r.tipo = d.tipo
                                   AND r.fecha_aplicacion > d.fecha_aplicacion)",
            [$hoy, $hasta]
        );
        foreach ($desparasitaciones as &$d) {
            $d['nombre_item'] = "Desparasitación {$d['tipo']} ({$d['producto']})";
            unset($d['tipo'], $d['producto']);
        }
        unset($d);

        $dosis = [];
        foreach (array_merge($vacunas, $desparasitaciones) as $fila) {
            $aviso = VentanaRecordatorio::aviso($fila['fecha_proxima'], $hoy);
            if ($aviso !== null) {
                $dosis[] = $fila + ['tipo_notificacion' => $aviso];
            }
        }
        return $dosis;
    }

    /** Citas activas de mañana, para el recordatorio de 24 horas. */
    public function citasDeManana(string $manana): array
    {
        return $this->filas(
            "SELECT c.id_cita, c.fecha, c.hora, c.motivo, m.nombre AS mascota_nombre,
                    c.id_clinica, cl.nombre AS clinica_nombre,
                    u.id_usuario, u.nombre_completo AS prop_nombre, u.email,
                    v.nombre_completo AS vet_nombre
               FROM citas c
               JOIN clinicas cl ON cl.id_clinica = c.id_clinica
               JOIN mascotas m ON m.id_mascota = c.id_mascota
               JOIN mascota_clinica mc ON mc.id_mascota = m.id_mascota AND mc.id_clinica = c.id_clinica
               JOIN usuarios u ON u.id_usuario = m.id_propietario
               JOIN usuarios v ON v.id_usuario = c.id_veterinario
              WHERE c.fecha = ?
                AND c.estado IN ('pendiente', 'confirmada')
                AND m.estado = 1 AND mc.estado = 'activo' AND u.estado = 1
                AND u.email IS NOT NULL AND u.email <> ''",
            [$manana]
        );
    }

    /**
     * RE-3.6.1/2: un aviso se envía si no salió ya y no agotó sus intentos.
     * Antes cualquier registro previo lo bloqueaba, incluso uno con estado
     * «error», así que un fallo pasajero de SMTP dejaba el aviso sin enviar
     * para siempre.
     */
    public function puedeEnviar(int $idClinica, string $tipoEntidad, int $idEntidad, string $tipoNotificacion): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(CASE WHEN estado = 'enviado' THEN 1 ELSE 0 END), 0) AS enviados,
                    COALESCE(SUM(CASE WHEN estado = 'error' THEN 1 ELSE 0 END), 0) AS fallidos
               FROM notificaciones
              WHERE id_clinica = ? AND tipo_entidad = ? AND id_entidad = ? AND tipo_notificacion = ?"
        );
        $stmt->execute([$idClinica, $tipoEntidad, $idEntidad, $tipoNotificacion]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) $fila['enviados'] === 0 && (int) $fila['fallidos'] < VentanaRecordatorio::MAX_INTENTOS;
    }

    /** RE-3.2.4: cada intento conserva clínica y persona, también si falla. */
    public function registrar(array $datos): void
    {
        $this->db->prepare(
            "INSERT INTO notificaciones
                    (id_clinica, id_usuario, tipo_entidad, id_entidad, destinatario_email, tipo_notificacion, asunto, mensaje, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $datos['id_clinica'],
            $datos['id_usuario'],
            $datos['tipo_entidad'],
            $datos['id_entidad'],
            $datos['email'],
            $datos['tipo_notificacion'],
            $datos['asunto'],
            $datos['mensaje'],
            $datos['enviado'] ? 'enviado' : 'error',
        ]);
    }

    private function filas(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
