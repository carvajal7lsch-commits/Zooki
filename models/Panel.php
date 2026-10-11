<?php
require_once __DIR__ . '/ModeloClinica.php';

/**
 * Consultas de los paneles de inicio del veterinario y del administrador
 * (HU-6.1, HU-6.2, HU-6.4).
 *
 * Solo lee y todo sale de la clínica activa (RNF-11): cambiar de contexto
 * cambia los números. El veterinario se identifica por su id_usuario.
 *
 * Las fechas llegan ya calculadas en la zona horaria de la clínica: nada usa
 * CURDATE() ni NOW(), que dependen del reloj del servidor de base de datos.
 * El SQL es portable (sin DATE_FORMAT) para poder probarlo con SQLite.
 */
class Panel extends ModeloClinica
{
    private const SELECT_CITA = "SELECT c.id_cita, c.fecha, c.hora, c.hora_fin, c.duracion_minutos, c.estado,
                c.motivo, c.id_veterinario, c.id_mascota, m.nombre AS mascota, m.id_propietario,
                e.nombre_especie AS especie, p.nombre_completo AS propietario,
                v.nombre_completo AS veterinario, COALESCE(tc.nombre_tipo, 'Consulta') AS tipo
            FROM citas c
            JOIN mascotas m ON m.id_mascota = c.id_mascota
            LEFT JOIN especies e ON e.id_especie = m.id_especie
            JOIN usuarios p ON p.id_usuario = m.id_propietario
            JOIN usuarios v ON v.id_usuario = c.id_veterinario
            LEFT JOIN tipos_cita tc ON tc.id_tipo_cita = c.id_tipo_cita
            WHERE c.id_clinica = ?";

    /** Citas de un día, de toda la clínica o de un veterinario. */
    public function citasDelDia(string $fecha, ?int $idVeterinario = null): array
    {
        $sql = self::SELECT_CITA . " AND c.fecha = ?";
        $params = [$this->clinica(), $fecha];
        if ($idVeterinario !== null) {
            $sql .= " AND c.id_veterinario = ?";
            $params[] = $idVeterinario;
        }
        return $this->filas($sql . " ORDER BY c.hora ASC", $params);
    }

    /** RN-409: atenciones iniciadas que siguen sin cerrarse, de cualquier día. */
    public function atencionesAbiertas(?int $idVeterinario = null): array
    {
        $sql = self::SELECT_CITA . " AND c.estado IN ('en_curso', 'sin_cerrar')";
        $params = [$this->clinica()];
        if ($idVeterinario !== null) {
            $sql .= " AND c.id_veterinario = ?";
            $params[] = $idVeterinario;
        }
        return $this->filas($sql . " ORDER BY c.fecha ASC, c.hora ASC", $params);
    }

    /**
     * RE-6.1.1: vacunas y desparasitaciones de la clínica con próxima dosis en
     * el rango, de las mascotas activas que el veterinario atendió o tiene
     * agendadas en ella. Una mascota con el vínculo inactivo ya no es
     * paciente de la clínica (RN-115).
     */
    public function recordatoriosDeSusPacientes(int $idVeterinario, string $desde, string $hasta): array
    {
        $pacientes = "SELECT id_mascota FROM citas WHERE id_clinica = ? AND id_veterinario = ?
                      UNION SELECT id_mascota FROM consultas WHERE id_clinica = ? AND id_veterinario = ?";
        $vinculo = "JOIN mascota_clinica mc ON mc.id_mascota = m.id_mascota AND mc.id_clinica = ? AND mc.estado = 'activo'";

        $sql = "SELECT * FROM (
                    SELECT 'vacuna' AS tipo, v.nombre_vacuna AS nombre, v.fecha_proxima_dosis AS fecha,
                           m.id_mascota, m.nombre AS mascota, m.id_propietario
                    FROM vacunas v
                    JOIN mascotas m ON m.id_mascota = v.id_mascota
                    $vinculo
                    WHERE v.id_clinica = ? AND v.fecha_proxima_dosis BETWEEN ? AND ? AND m.estado = 1
                      AND m.id_mascota IN ($pacientes)
                    UNION ALL
                    SELECT 'desparasitacion' AS tipo, d.producto AS nombre, d.fecha_proxima AS fecha,
                           m.id_mascota, m.nombre AS mascota, m.id_propietario
                    FROM desparasitaciones d
                    JOIN mascotas m ON m.id_mascota = d.id_mascota
                    $vinculo
                    WHERE d.id_clinica = ? AND d.fecha_proxima BETWEEN ? AND ? AND m.estado = 1
                      AND m.id_mascota IN ($pacientes)
                ) r
                ORDER BY fecha ASC, mascota ASC";

        $clinica = $this->clinica();
        $porTabla = [$clinica, $clinica, $desde, $hasta, $clinica, $idVeterinario, $clinica, $idVeterinario];
        return $this->filas($sql, array_merge($porTabla, $porTabla));
    }

    /** Consultas registradas entre dos instantes, [desde, hasta). */
    public function contarConsultas(string $desde, string $hasta): int
    {
        return $this->contar(
            "SELECT COUNT(*) FROM consultas WHERE id_clinica = ? AND fecha_hora >= ? AND fecha_hora < ?",
            [$this->clinica(), $desde, $hasta]
        );
    }

    /** Citas pendientes de confirmar con fecha entre dos días, ambos incluidos. */
    public function contarPorConfirmar(string $desde, string $hasta): int
    {
        return $this->contar(
            "SELECT COUNT(*) FROM citas WHERE id_clinica = ? AND estado = 'pendiente' AND fecha BETWEEN ? AND ?",
            [$this->clinica(), $desde, $hasta]
        );
    }

    /**
     * RN-408: citas cuya hora ya pasó y siguen pendientes o confirmadas; nadie
     * las atendió ni las marcó como no asistidas.
     */
    public function contarSinMarcar(string $desde, string $hoy, string $horaActual): int
    {
        return $this->contar(
            "SELECT COUNT(*) FROM citas
             WHERE id_clinica = ?
               AND estado IN ('pendiente', 'confirmada')
               AND fecha >= ?
               AND (fecha < ? OR (fecha = ? AND COALESCE(hora_fin, hora) <= ?))",
            [$this->clinica(), $desde, $hoy, $hoy, $horaActual]
        );
    }

    /**
     * RE-6.2.2: citas del día de cada veterinario activo de la clínica,
     * incluidos los que no tienen ninguna. El rol sale de usuario_clinica, no
     * de la cuenta: la misma persona puede ser administradora en otra clínica.
     */
    public function cargaPorVeterinario(string $fecha): array
    {
        return $this->filas(
            "SELECT u.id_usuario, u.nombre_completo AS veterinario,
                    COUNT(c.id_cita) AS total,
                    SUM(CASE WHEN c.estado = 'completada' THEN 1 ELSE 0 END) AS atendidas
             FROM usuario_clinica uc
             JOIN usuarios u ON u.id_usuario = uc.id_usuario
             LEFT JOIN citas c ON c.id_veterinario = uc.id_usuario AND c.id_clinica = uc.id_clinica
                              AND c.fecha = ? AND c.estado <> 'cancelada'
             WHERE uc.id_clinica = ? AND uc.id_rol = ? AND uc.estado = 'activo' AND u.estado = 1
             GROUP BY u.id_usuario, u.nombre_completo
             ORDER BY total DESC, u.nombre_completo ASC",
            [$fecha, $this->clinica(), Roles::VETERINARIO]
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
             WHERE id_clinica = ? AND fecha BETWEEN ? AND ?
             GROUP BY SUBSTR(fecha, 1, 7)
             ORDER BY mes ASC",
            [$this->clinica(), $desde, $hasta]
        );
    }

    /**
     * RE-6.4.1: mascotas activas vinculadas a la clínica. Las de vínculo
     * inactivo (RN-115) ya no son pacientes de esta clínica.
     */
    public function contarPacientesActivos(): int
    {
        return $this->contar(
            "SELECT COUNT(*) FROM mascota_clinica mc
             JOIN mascotas m ON m.id_mascota = mc.id_mascota
             WHERE mc.id_clinica = ? AND mc.estado = 'activo' AND m.estado = 1",
            [$this->clinica()]
        );
    }

    /** RE-6.4.1: clientes, es decir, propietarios con vínculo activo con la clínica y cuenta activa. */
    public function contarPropietarios(): int
    {
        return $this->contar(
            "SELECT COUNT(*) FROM propietario_clinica pc
             JOIN usuarios u ON u.id_usuario = pc.id_propietario
             WHERE pc.id_clinica = ? AND pc.estado = 'activo' AND u.estado = 1",
            [$this->clinica()]
        );
    }

    private function filas(string $sql, array $params): array
    {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function contar(string $sql, array $params): int
    {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}
