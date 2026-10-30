<?php
require_once __DIR__ . '/ModeloPropietario.php';
require_once __DIR__ . '/../helpers/ValidadorAlertaMedica.php';

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
                c.plan_tratamiento, c.peso, c.temperatura, cl.nombre AS clinica_nombre, cl.url_logo AS clinica_logo, cl.direccion AS clinica_direccion, cl.telefono AS clinica_telefono, cl.telefono_urgencias AS clinica_urgencias, cl.email_contacto AS clinica_email, u.nombre_completo AS veterinario
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
        // M2 C7 (RN-G02): los síntomas marcados de las consultas de su mascota; la etiqueta aunque el nodo se haya retirado.
        $sintomas = $this->agrupar('SELECT cs.id_consulta, n.codigo, n.etiqueta FROM consulta_sintomas cs JOIN grafo_nodos n ON n.id_nodo = cs.id_nodo WHERE cs.id_consulta IN (%s) ORDER BY cs.id_consulta, n.etiqueta, n.codigo', $ids);

        foreach ($consultas as &$fila) {
            $id = (int) $fila['id_consulta'];
            $fila['archivos'] = $archivos[$id] ?? [];
            $fila['tratamientos'] = $tratamientos[$id] ?? [];
            $fila['sintomas'] = array_map(static fn (array $sintoma): array => ['codigo' => $sintoma['codigo'], 'etiqueta' => $sintoma['etiqueta']], $sintomas[$id] ?? []);
        }
        unset($fila);
        return $consultas;
    }

    public function vacunas(int $idMascota): array
    {
        $this->exigirMascotaPropia($idMascota);
        $consulta = $this->conn->prepare('SELECT v.*, cl.nombre AS clinica_nombre, cl.url_logo AS clinica_logo, cl.direccion AS clinica_direccion, cl.telefono AS clinica_telefono, cl.telefono_urgencias AS clinica_urgencias, cl.email_contacto AS clinica_email FROM vacunas v
            JOIN clinicas cl ON cl.id_clinica = v.id_clinica
            WHERE v.id_mascota = ? ORDER BY v.fecha_aplicacion DESC, v.id_vacuna DESC');
        $consulta->execute([$idMascota]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function desparasitaciones(int $idMascota): array
    {
        $this->exigirMascotaPropia($idMascota);
        $consulta = $this->conn->prepare('SELECT d.*, cl.nombre AS clinica_nombre, cl.url_logo AS clinica_logo, cl.direccion AS clinica_direccion, cl.telefono AS clinica_telefono, cl.telefono_urgencias AS clinica_urgencias, cl.email_contacto AS clinica_email FROM desparasitaciones d
            JOIN clinicas cl ON cl.id_clinica = d.id_clinica
            WHERE d.id_mascota = ? ORDER BY d.fecha_aplicacion DESC, d.id_desparasitacion DESC');
        $consulta->execute([$idMascota]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * RE-2.10.1 y RN-114: alergias y alertas activas de su mascota en todas las
     * clínicas, con la clínica que las registró. De solo lectura (RN-G02).
     */
    public function alertas(int $idMascota): array
    {
        $this->exigirMascotaPropia($idMascota);
        $consulta = $this->conn->prepare('SELECT a.id_alerta, a.id_clinica, a.tipo, a.descripcion, a.fecha, cl.nombre AS clinica_nombre, cl.url_logo AS clinica_logo, cl.direccion AS clinica_direccion, cl.telefono AS clinica_telefono, cl.telefono_urgencias AS clinica_urgencias, cl.email_contacto AS clinica_email
            FROM alertas_medicas a
            JOIN clinicas cl ON cl.id_clinica = a.id_clinica
            WHERE a.id_mascota = ? AND a.activa = 1
            ORDER BY a.fecha DESC, a.id_alerta DESC');
        $consulta->execute([$idMascota]);
        $alertas = $consulta->fetchAll(PDO::FETCH_ASSOC);
        foreach ($alertas as &$alerta) {
            $alerta['tipo_nombre'] = ValidadorAlertaMedica::TIPOS[$alerta['tipo']] ?? $alerta['tipo'];
        }
        unset($alerta);
        return $alertas;
    }

    /** Citas de la mascota en todas sus clínicas, menos las canceladas. */
    public function citas(int $idMascota): array
    {
        $this->exigirMascotaPropia($idMascota);
        $consulta = $this->conn->prepare("SELECT c.id_cita, c.id_clinica, c.id_mascota, c.fecha, c.hora, c.motivo, c.estado, c.origen, c.prioridad, cl.anticipacion_cambios_min,
                cl.nombre AS clinica_nombre, cl.url_logo AS clinica_logo, cl.direccion AS clinica_direccion, cl.telefono AS clinica_telefono, cl.telefono_urgencias AS clinica_urgencias, cl.email_contacto AS clinica_email, u.nombre_completo AS veterinario_nombre, tc.nombre_tipo
            FROM citas c
            JOIN clinicas cl ON cl.id_clinica = c.id_clinica
            JOIN usuarios u ON u.id_usuario = c.id_veterinario
            LEFT JOIN tipos_cita tc ON tc.id_tipo_cita = c.id_tipo_cita
            WHERE c.id_mascota = ? AND c.estado <> 'cancelada'
            ORDER BY c.fecha DESC, c.hora DESC");
        $consulta->execute([$idMascota]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Inicio y Citas del portal (M5 C2): todas sus mascotas activas a la vez ──
    // Una consulta por tabla, sin importar cuántas mascotas tenga.

    /** Citas de todas sus mascotas activas, menos las canceladas. */
    public function citasDeSusMascotas(): array
    {
        $consulta = $this->conn->prepare("SELECT c.id_cita, c.id_clinica, c.id_mascota, c.fecha, c.hora, c.motivo, c.estado, c.origen, c.prioridad, cl.anticipacion_cambios_min,
                cl.nombre AS clinica_nombre, cl.url_logo AS clinica_logo, cl.direccion AS clinica_direccion, cl.telefono AS clinica_telefono, cl.telefono_urgencias AS clinica_urgencias, cl.email_contacto AS clinica_email, u.nombre_completo AS veterinario_nombre, tc.nombre_tipo
            FROM citas c
            JOIN mascotas m ON m.id_mascota = c.id_mascota
            JOIN clinicas cl ON cl.id_clinica = c.id_clinica
            JOIN usuarios u ON u.id_usuario = c.id_veterinario
            LEFT JOIN tipos_cita tc ON tc.id_tipo_cita = c.id_tipo_cita
            WHERE m.id_propietario = ? AND m.estado = 1 AND c.estado <> 'cancelada'
            ORDER BY c.fecha DESC, c.hora DESC");
        $consulta->execute([$this->propietario()]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Vacunas de todas sus mascotas activas, con la clínica que las aplicó (RN-114). */
    public function vacunasDeSusMascotas(): array
    {
        $consulta = $this->conn->prepare('SELECT v.*, cl.nombre AS clinica_nombre, cl.url_logo AS clinica_logo, cl.direccion AS clinica_direccion, cl.telefono AS clinica_telefono, cl.telefono_urgencias AS clinica_urgencias, cl.email_contacto AS clinica_email FROM vacunas v
            JOIN mascotas m ON m.id_mascota = v.id_mascota
            JOIN clinicas cl ON cl.id_clinica = v.id_clinica
            WHERE m.id_propietario = ? AND m.estado = 1
            ORDER BY v.fecha_aplicacion DESC, v.id_vacuna DESC');
        $consulta->execute([$this->propietario()]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Desparasitaciones de todas sus mascotas activas, con la clínica que las aplicó (RN-114). */
    public function desparasitacionesDeSusMascotas(): array
    {
        $consulta = $this->conn->prepare('SELECT d.*, cl.nombre AS clinica_nombre, cl.url_logo AS clinica_logo, cl.direccion AS clinica_direccion, cl.telefono AS clinica_telefono, cl.telefono_urgencias AS clinica_urgencias, cl.email_contacto AS clinica_email FROM desparasitaciones d
            JOIN mascotas m ON m.id_mascota = d.id_mascota
            JOIN clinicas cl ON cl.id_clinica = d.id_clinica
            WHERE m.id_propietario = ? AND m.estado = 1
            ORDER BY d.fecha_aplicacion DESC, d.id_desparasitacion DESC');
        $consulta->execute([$this->propietario()]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Detalle de una cita de una mascota propia, con su consulta y receta si
     * ya se atendió. Cita ajena o inexistente: 403 auditado.
     */
    public function detalleCita(int $idCita): array
    {
        $consulta = $this->conn->prepare("SELECT c.id_cita, c.id_mascota, c.fecha, c.hora, c.estado, c.motivo,
                cl.nombre AS clinica_nombre, cl.url_logo AS clinica_logo, cl.direccion AS clinica_direccion, cl.telefono AS clinica_telefono, cl.telefono_urgencias AS clinica_urgencias, cl.email_contacto AS clinica_email, u.nombre_completo AS veterinario_nombre, tc.nombre_tipo,
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
