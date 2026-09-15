<?php
/**
 * Consultas de los paneles de inicio del veterinario y del administrador
 * (HU-18, HU-20, HU-57).
 *
 * Solo lee. Las fechas llegan ya calculadas en la zona horaria de la clínica:
 * nada usa CURDATE() ni NOW(), que dependen del reloj del servidor de base de
 * datos. El SQL es portable (sin DATE_FORMAT) para poder probarlo con SQLite.
 */
class Panel
{
    private PDO $db;

    private const SELECT_CITA = "SELECT c.id_cita, c.fecha, c.hora, c.hora_fin, c.duracion_minutos, c.estado,
                c.motivo, c.doc_veterinario, c.id_mascota, m.nombre AS mascota, m.doc_propietario,
                e.nombre_especie AS especie, p.nombre_completo AS propietario,
                v.nombre_completo AS veterinario, COALESCE(tc.nombre_tipo, 'Consulta') AS tipo
            FROM citas c
            JOIN mascotas m ON m.id_mascota = c.id_mascota
            JOIN especies e ON e.id_especie = m.id_especie
            JOIN usuarios p ON p.documento = m.doc_propietario
            JOIN usuarios v ON v.documento = c.doc_veterinario
            LEFT JOIN tipos_cita tc ON tc.id_tipo_cita = c.id_tipo_cita";

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** Citas de un día, de toda la clínica o de un veterinario. */
    public function citasDelDia(string $fecha, ?string $docVeterinario = null): array
    {
        $sql = self::SELECT_CITA . " WHERE c.fecha = ?";
        $params = [$fecha];
        if ($docVeterinario !== null) {
            $sql .= " AND c.doc_veterinario = ?";
            $params[] = $docVeterinario;
        }
        return $this->filas($sql . " ORDER BY c.hora ASC", $params);
    }

    /** RN-410: atenciones iniciadas que siguen sin cerrarse, de cualquier día. */
    public function atencionesAbiertas(?string $docVeterinario = null): array
    {
        $sql = self::SELECT_CITA . " WHERE c.estado IN ('en_curso', 'sin_cerrar')";
        $params = [];
        if ($docVeterinario !== null) {
            $sql .= " AND c.doc_veterinario = ?";
            $params[] = $docVeterinario;
        }
        return $this->filas($sql . " ORDER BY c.fecha ASC, c.hora ASC", $params);
    }

    /**
     * HU-20: vacunas y desparasitaciones con próxima dosis en el rango, solo de
     * mascotas activas que el veterinario ha atendido o tiene agendadas.
     */
    public function recordatoriosDeSusPacientes(string $docVeterinario, string $desde, string $hasta): array
    {
        $pacientes = "SELECT id_mascota FROM citas WHERE doc_veterinario = ?
                      UNION SELECT id_mascota FROM consultas WHERE doc_veterinario = ?";

        $sql = "SELECT * FROM (
                    SELECT 'vacuna' AS tipo, v.nombre_vacuna AS nombre, v.fecha_proxima_dosis AS fecha,
                           m.id_mascota, m.nombre AS mascota, m.doc_propietario
                    FROM vacunas v JOIN mascotas m ON m.id_mascota = v.id_mascota
                    WHERE v.fecha_proxima_dosis BETWEEN ? AND ? AND m.estado = 1
                      AND m.id_mascota IN ($pacientes)
                    UNION ALL
                    SELECT 'desparasitacion' AS tipo, d.producto AS nombre, d.fecha_proxima AS fecha,
                           m.id_mascota, m.nombre AS mascota, m.doc_propietario
                    FROM desparasitaciones d JOIN mascotas m ON m.id_mascota = d.id_mascota
                    WHERE d.fecha_proxima BETWEEN ? AND ? AND m.estado = 1
                      AND m.id_mascota IN ($pacientes)
                ) r
                ORDER BY fecha ASC, mascota ASC";

        return $this->filas($sql, [
            $desde, $hasta, $docVeterinario, $docVeterinario,
            $desde, $hasta, $docVeterinario, $docVeterinario,
        ]);
    }

    /** Consultas registradas entre dos instantes, [desde, hasta). */
    public function contarConsultas(string $desde, string $hasta): int
    {
        return $this->contar(
            "SELECT COUNT(*) FROM consultas WHERE fecha_hora >= ? AND fecha_hora < ?",
            [$desde, $hasta]
        );
    }

    /** Citas pendientes de confirmar con fecha entre dos días, ambos incluidos. */
    public function contarPorConfirmar(string $desde, string $hasta): int
    {
        return $this->contar(
            "SELECT COUNT(*) FROM citas WHERE estado = 'pendiente' AND fecha BETWEEN ? AND ?",
            [$desde, $hasta]
        );
    }

    /**
     * RN-409: citas cuya hora ya pasó y siguen pendientes o confirmadas; nadie
     * las atendió ni las marcó como no asistidas.
     */
    public function contarSinMarcar(string $desde, string $hoy, string $horaActual): int
    {
        return $this->contar(
            "SELECT COUNT(*) FROM citas
             WHERE estado IN ('pendiente', 'confirmada')
               AND fecha >= ?
               AND (fecha < ? OR (fecha = ? AND COALESCE(hora_fin, hora) <= ?))",
            [$desde, $hoy, $hoy, $horaActual]
        );
    }

    /** Citas del día por veterinario activo, incluidos los que no tienen ninguna. */
    public function cargaPorVeterinario(string $fecha): array
    {
        return $this->filas(
            "SELECT u.documento, u.nombre_completo AS veterinario,
                    COUNT(c.id_cita) AS total,
                    SUM(CASE WHEN c.estado = 'completada' THEN 1 ELSE 0 END) AS atendidas
             FROM usuarios u
             LEFT JOIN citas c ON c.doc_veterinario = u.documento AND c.fecha = ?
                              AND c.estado NOT IN ('cancelada')
             WHERE u.id_rol = 2 AND u.estado = 1
             GROUP BY u.documento, u.nombre_completo
             ORDER BY total DESC, u.nombre_completo ASC",
            [$fecha]
        );
    }

    /** Citas atendidas y no asistidas por mes (clave AAAA-MM) desde un día. */
    public function tendenciaMensual(string $desde, string $hasta): array
    {
        return $this->filas(
            "SELECT SUBSTR(fecha, 1, 7) AS mes,
                    SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) AS atendidas,
                    SUM(CASE WHEN estado = 'no_asistio' THEN 1 ELSE 0 END) AS no_asistidas
             FROM citas
             WHERE fecha BETWEEN ? AND ?
             GROUP BY SUBSTR(fecha, 1, 7)
             ORDER BY mes ASC",
            [$desde, $hasta]
        );
    }

    private function filas(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function contar(string $sql, array $params): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}
