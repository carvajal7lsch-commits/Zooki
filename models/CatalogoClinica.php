<?php
require_once __DIR__ . '/ModeloClinica.php';
require_once __DIR__ . '/../helpers/Transaccion.php';

/**
 * Catálogos de la clínica activa (RE-T.15.1). C2 dejó la lectura; C4 suma
 * las altas que hace el veterinario al registrar un acto clínico («Otra…»),
 * siempre en la clínica activa. Quién gestiona los catálogos se decide con
 * HU-7.2 (RN-701, RN-702).
 */
final class CatalogoClinica extends ModeloClinica
{
    private const TIPOS_PRODUCTO = ['interna', 'externa', 'ambas'];

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

    /**
     * Agrega una vacuna base de la clínica asociada a la especie. Si ya
     * existe con ese nombre, solo se asegura la relación con la especie.
     */
    public function agregarVacuna(string $nombre, ?string $descripcion, int $idEspecie): int
    {
        $nombre = $this->nombreValido($nombre, 'El nombre de la vacuna es obligatorio.');

        return Transaccion::ejecutar($this->conn, function () use ($nombre, $descripcion, $idEspecie) {
            $existente = $this->leer('SELECT id_vacuna_base FROM vacunas_base WHERE id_clinica = ? AND LOWER(nombre_vacuna) = LOWER(?)', [$nombre]);
            if ($existente !== []) {
                $idVacuna = (int) $existente[0]['id_vacuna_base'];
            } else {
                $this->conn->prepare('INSERT INTO vacunas_base (id_clinica, nombre_vacuna, descripcion, estado) VALUES (?, ?, ?, 1)')
                    ->execute([$this->clinica(), $nombre, $descripcion]);
                $idVacuna = (int) $this->conn->lastInsertId();
            }

            $relacion = $this->conn->prepare('SELECT 1 FROM especie_vacunas WHERE id_especie = ? AND id_vacuna_base = ?');
            $relacion->execute([$idEspecie, $idVacuna]);
            if (!$relacion->fetchColumn()) {
                $this->conn->prepare('INSERT INTO especie_vacunas (id_especie, id_vacuna_base) VALUES (?, ?)')
                    ->execute([$idEspecie, $idVacuna]);
            }
            return $idVacuna;
        });
    }

    public function agregarLaboratorio(string $nombre): int
    {
        $nombre = $this->nombreValido($nombre, 'El nombre del laboratorio es obligatorio.');
        $existente = $this->leer('SELECT id_laboratorio FROM laboratorios_base WHERE id_clinica = ? AND LOWER(nombre_laboratorio) = LOWER(?)', [$nombre]);
        if ($existente !== []) {
            return (int) $existente[0]['id_laboratorio'];
        }

        $this->conn->prepare('INSERT INTO laboratorios_base (id_clinica, nombre_laboratorio, estado) VALUES (?, ?, 1)')
            ->execute([$this->clinica(), $nombre]);
        return (int) $this->conn->lastInsertId();
    }

    public function agregarProducto(string $nombre, string $tipo): int
    {
        $nombre = $this->nombreValido($nombre, 'El nombre del producto es obligatorio.');
        if (!in_array($tipo, self::TIPOS_PRODUCTO, true)) {
            throw new InvalidArgumentException('El tipo del producto debe ser interna, externa o ambas.');
        }
        $existente = $this->leer('SELECT id_producto FROM productos_desparasitacion_base WHERE id_clinica = ? AND LOWER(nombre_producto) = LOWER(?)', [$nombre]);
        if ($existente !== []) {
            return (int) $existente[0]['id_producto'];
        }

        $this->conn->prepare('INSERT INTO productos_desparasitacion_base (id_clinica, nombre_producto, tipo, estado) VALUES (?, ?, ?, 1)')
            ->execute([$this->clinica(), $nombre, $tipo]);
        return (int) $this->conn->lastInsertId();
    }

    private function nombreValido(string $nombre, string $mensaje): string
    {
        $nombre = trim($nombre);
        if ($nombre === '' || mb_strlen($nombre) > 150) {
            throw new InvalidArgumentException($mensaje);
        }
        return $nombre;
    }

    private function leer(string $sql, array $parametros = []): array
    {
        $consulta = $this->conn->prepare($sql);
        $consulta->execute([$this->clinica(), ...$parametros]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }
}
