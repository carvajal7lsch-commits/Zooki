<?php
require_once __DIR__ . '/ModeloClinica.php';

/** C2: solo lectura de catálogos de la clínica activa (RE-T.15.1). */
final class CatalogoClinica extends ModeloClinica
{
    public function tiposCita(): array
    {
        return $this->leer('SELECT * FROM tipos_cita WHERE id_clinica = ? AND activo = 1 ORDER BY duracion_minutos');
    }

    public function tipoCita(int $id): ?array
    {
        return $this->leer('SELECT * FROM tipos_cita WHERE id_clinica = ? AND id_tipo_cita = ?', [$id])[0] ?? null;
    }

    public function vacunasPorEspecie(int $id): array
    {
        return $this->leer('SELECT vb.id_vacuna_base, vb.nombre_vacuna, vb.descripcion
            FROM vacunas_base vb JOIN especie_vacunas ev ON ev.id_vacuna_base = vb.id_vacuna_base
            WHERE vb.id_clinica = ? AND vb.estado = 1 AND ev.id_especie = ? ORDER BY vb.nombre_vacuna', [$id]);
    }

    public function laboratorios(): array
    {
        return $this->leer('SELECT id_laboratorio, nombre_laboratorio FROM laboratorios_base
            WHERE id_clinica = ? AND estado = 1 ORDER BY nombre_laboratorio');
    }

    public function productos(): array
    {
        return $this->leer('SELECT id_producto, nombre_producto, tipo FROM productos_desparasitacion_base
            WHERE id_clinica = ? AND estado = 1 ORDER BY nombre_producto');
    }

    private function leer(string $sql, array $parametros = []): array
    {
        $consulta = $this->conn->prepare($sql);
        $consulta->execute([$this->clinica(), ...$parametros]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }
}
