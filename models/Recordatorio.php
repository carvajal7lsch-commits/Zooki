<?php
require_once __DIR__ . '/../helpers/VentanaRecordatorio.php';

/**
 * Datos de los recordatorios automáticos por correo (HU-10, HU-37).
 *
 * Las fechas llegan calculadas en la zona de la clínica (RE-37.3): nada usa
 * CURDATE(). El SQL es portable para poder probarlo con SQLite.
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
     * les toca. Quedan fuera (RE-37.4):
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
                    u.documento AS doc_propietario, u.nombre_completo AS prop_nombre, u.email
               FROM vacunas v
               JOIN mascotas m ON m.id_mascota = v.id_mascota
               JOIN usuarios u ON u.documento = m.doc_propietario
              WHERE v.fecha_proxima_dosis BETWEEN ? AND ?
                AND m.estado = 1
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
                    u.documento AS doc_propietario, u.nombre_completo AS prop_nombre, u.email
               FROM desparasitaciones d
               JOIN mascotas m ON m.id_mascota = d.id_mascota
               JOIN usuarios u ON u.documento = m.doc_propietario
              WHERE d.fecha_proxima BETWEEN ? AND ?
                AND m.estado = 1
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
                    u.documento AS doc_propietario, u.nombre_completo AS prop_nombre, u.email,
                    v.nombre_completo AS vet_nombre
               FROM citas c
               JOIN mascotas m ON m.id_mascota = c.id_mascota
               JOIN usuarios u ON u.documento = m.doc_propietario
               JOIN usuarios v ON v.documento = c.doc_veterinario
              WHERE c.fecha = ?
                AND c.estado IN ('pendiente', 'confirmada')
                AND u.email IS NOT NULL AND u.email <> ''",
            [$manana]
        );
    }

    /**
     * RE-37.2: un aviso se envía si no salió ya y no agotó sus intentos.
     * Antes cualquier registro previo lo bloqueaba, incluso uno con estado
     * «error», así que un fallo pasajero de SMTP dejaba el aviso sin enviar
     * para siempre.
     */
    public function puedeEnviar(string $tipoEntidad, int $idEntidad, string $tipoNotificacion): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(CASE WHEN estado = 'enviado' THEN 1 ELSE 0 END), 0) AS enviados,
                    COALESCE(SUM(CASE WHEN estado = 'error' THEN 1 ELSE 0 END), 0) AS fallidos
               FROM notificaciones
              WHERE tipo_entidad = ? AND id_entidad = ? AND tipo_notificacion = ?"
        );
        $stmt->execute([$tipoEntidad, $idEntidad, $tipoNotificacion]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) $fila['enviados'] === 0 && (int) $fila['fallidos'] < VentanaRecordatorio::MAX_INTENTOS;
    }

    /** RE-10.4: cada intento queda registrado con su resultado. */
    public function registrar(array $datos): void
    {
        $this->db->prepare(
            "INSERT INTO notificaciones
                    (doc_propietario, tipo_entidad, id_entidad, destinatario_email, tipo_notificacion, asunto, mensaje, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $datos['doc_propietario'],
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
