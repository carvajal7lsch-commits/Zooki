<?php
require_once __DIR__ . '/ModeloPropietario.php';

/**
 * RN-114 / RE-5.9.2: el propietario ve la historia completa de sus mascotas
 * en todas las clínicas, con la clínica de cada registro, aunque ya no esté
 * vinculado a alguna. No necesita autorización: la historia es de su
 * mascota (la autorización de RN-113 es entre clínicas).
 */
final class HistoriaPropietario extends ModeloPropietario
{
    public function consultas(int $idMascota): array
    {
        $this->exigirMascotaPropia($idMascota);

        $sql = 'SELECT c.id_consulta, c.id_clinica, c.id_cita, c.fecha_hora, c.motivo_consulta, c.diagnostico,
                c.plan_tratamiento, c.peso, c.temperatura, cl.nombre AS clinica_nombre, u.nombre_completo AS veterinario
            FROM consultas c
            JOIN clinicas cl ON cl.id_clinica = c.id_clinica
            JOIN usuarios u ON u.id_usuario = c.id_veterinario
            WHERE c.id_mascota = ?
            ORDER BY c.fecha_hora DESC, c.id_consulta DESC';
        $consulta = $this->conn->prepare($sql);
        $consulta->execute([$idMascota]);
        $consultas = $consulta->fetchAll(PDO::FETCH_ASSOC);

        $ids = array_map('intval', array_column($consultas, 'id_consulta'));
        $archivos = $this->agrupar('SELECT id_archivo, id_consulta, nombre_original, extension FROM archivos_clinicos WHERE id_consulta IN (%s) ORDER BY id_archivo', $ids);
        $tratamientos = $this->agrupar('SELECT id_tratamiento, id_consulta, medicamento, dosis, via_administracion, duracion, fecha_inicio FROM tratamientos WHERE id_consulta IN (%s) ORDER BY id_tratamiento', $ids);

        foreach ($consultas as &$fila) {
            $id = (int) $fila['id_consulta'];
            $fila['archivos'] = $archivos[$id] ?? [];
            $fila['tratamientos'] = $tratamientos[$id] ?? [];
        }
        unset($fila);
        return $consultas;
    }

    public function vacunas(int $idMascota): array
    {
        $this->exigirMascotaPropia($idMascota);
        $consulta = $this->conn->prepare('SELECT v.*, cl.nombre AS clinica_nombre FROM vacunas v
            JOIN clinicas cl ON cl.id_clinica = v.id_clinica
            WHERE v.id_mascota = ? ORDER BY v.fecha_aplicacion DESC, v.id_vacuna DESC');
        $consulta->execute([$idMascota]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function desparasitaciones(int $idMascota): array
    {
        $this->exigirMascotaPropia($idMascota);
        $consulta = $this->conn->prepare('SELECT d.*, cl.nombre AS clinica_nombre FROM desparasitaciones d
            JOIN clinicas cl ON cl.id_clinica = d.id_clinica
            WHERE d.id_mascota = ? ORDER BY d.fecha_aplicacion DESC, d.id_desparasitacion DESC');
        $consulta->execute([$idMascota]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Citas de la mascota en todas sus clínicas, menos las canceladas. */
    public function citas(int $idMascota): array
    {
        $this->exigirMascotaPropia($idMascota);
        $consulta = $this->conn->prepare("SELECT c.id_cita, c.id_clinica, c.id_mascota, c.fecha, c.hora, c.motivo, c.estado,
                cl.nombre AS clinica_nombre, u.nombre_completo AS veterinario_nombre, tc.nombre_tipo
            FROM citas c
            JOIN clinicas cl ON cl.id_clinica = c.id_clinica
            JOIN usuarios u ON u.id_usuario = c.id_veterinario
            LEFT JOIN tipos_cita tc ON tc.id_tipo_cita = c.id_tipo_cita
            WHERE c.id_mascota = ? AND c.estado <> 'cancelada'
            ORDER BY c.fecha DESC, c.hora DESC");
        $consulta->execute([$idMascota]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Detalle de una cita de una mascota propia, con su consulta y receta si
     * ya se atendió. Cita ajena o inexistente: 403 auditado.
     */
    public function detalleCita(int $idCita): array
    {
        $consulta = $this->conn->prepare("SELECT c.id_cita, c.id_mascota, c.fecha, c.hora, c.estado, c.motivo,
                cl.nombre AS clinica_nombre, u.nombre_completo AS veterinario_nombre, tc.nombre_tipo,
                m.nombre AS nombre_mascota, m.url_foto
            FROM citas c
            JOIN mascotas m ON m.id_mascota = c.id_mascota
            JOIN clinicas cl ON cl.id_clinica = c.id_clinica
            JOIN usuarios u ON u.id_usuario = c.id_veterinario
            LEFT JOIN tipos_cita tc ON tc.id_tipo_cita = c.id_tipo_cita
            WHERE c.id_cita = ? AND m.id_propietario = ?");
        $consulta->execute([$idCita, $this->propietario()]);
        $cita = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($cita === false) {
            $this->denegar('citas', $idCita, 'Cita de una mascota de otro propietario (RN-G02)');
        }

        $detalle = ['cita' => $cita, 'consulta' => null, 'tratamientos' => []];
        if ($cita['estado'] !== 'completada') {
            return $detalle;
        }

        $consultaCita = $this->conn->prepare('SELECT id_consulta, motivo_consulta, anamnesis, peso, temperatura,
                frecuencia_cardiaca, diagnostico, plan_tratamiento, fecha_hora
            FROM consultas WHERE id_cita = ?');
        $consultaCita->execute([$idCita]);
        $fila = $consultaCita->fetch(PDO::FETCH_ASSOC);
        if ($fila !== false) {
            $tratamientos = $this->agrupar('SELECT id_tratamiento, id_consulta, medicamento, dosis, via_administracion, duracion, fecha_inicio, observaciones FROM tratamientos WHERE id_consulta IN (%s) ORDER BY id_tratamiento', [(int) $fila['id_consulta']]);
            $detalle['consulta'] = $fila;
            $detalle['tratamientos'] = $tratamientos[(int) $fila['id_consulta']] ?? [];
        }
        return $detalle;
    }

    /** Filas de una tabla hija agrupadas por id_consulta, en una sola consulta SQL. */
    private function agrupar(string $sql, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $consulta = $this->conn->prepare(sprintf($sql, implode(', ', array_fill(0, count($ids), '?'))));
        $consulta->execute($ids);

        $porConsulta = [];
        foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $porConsulta[(int) $fila['id_consulta']][] = $fila;
        }
        return $porConsulta;
    }
}
