<?php
require_once __DIR__ . '/../helpers/ValoresInicialesClinica.php';

/**
 * RE-0.2.5 / D-1: copia inicial por clínica, reutilizable en la activación D.
 * Respeta la transacción de quien lo llama y no sobrescribe catálogos propios.
 */
final class CopiaCatalogosClinica
{
    public function __construct(private PDO $db) {}

    public function copiar(int $idClinica): void
    {
        $propia = !$this->db->inTransaction();
        if ($propia) $this->db->beginTransaction();
        else $this->db->exec('SAVEPOINT copia_catalogos_clinica');
        try {
            // Serializa activaciones simultáneas sin agregar claves al MER.
            $bloqueo = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $consulta = $this->db->prepare('SELECT id_clinica FROM clinicas WHERE id_clinica = ?' . $bloqueo);
            $consulta->execute([$idClinica]);
            if ($consulta->fetchColumn() === false) throw new InvalidArgumentException('La clínica no existe.');

            $this->copiarTabla($idClinica, 'tipos_cita',
                ['nombre_tipo','duracion_minutos','descripcion','color','activo'], ValoresInicialesClinica::TIPOS);
            $this->copiarTabla($idClinica, 'horarios_clinica',
                ['dia_semana','activo','bloque_morning_activo','bloque_afternoon_activo','bloque_morning_inicio',
                 'bloque_morning_fin','bloque_afternoon_inicio','bloque_afternoon_fin'], ValoresInicialesClinica::HORARIOS, false);
            $vacunas = $this->copiarTabla($idClinica, 'vacunas_base',
                ['nombre_vacuna','descripcion','estado'], ValoresInicialesClinica::VACUNAS);
            foreach (ValoresInicialesClinica::ESPECIES_VACUNAS as [$relacion, $especie, $vacuna]) {
                if (isset($vacunas[$vacuna])) {
                    $this->db->prepare('INSERT INTO especie_vacunas (id_especie,id_vacuna_base) VALUES (?,?)')
                        ->execute([$especie, $vacunas[$vacuna]]);
                }
            }
            $this->copiarTabla($idClinica, 'laboratorios_base', ['nombre_laboratorio','estado'], ValoresInicialesClinica::LABORATORIOS);
            $this->copiarTabla($idClinica, 'productos_desparasitacion_base', ['nombre_producto','tipo','estado'], ValoresInicialesClinica::PRODUCTOS);
            if ($propia) $this->db->commit();
            else $this->db->exec('RELEASE SAVEPOINT copia_catalogos_clinica');
        } catch (Throwable $e) {
            if ($propia) $this->db->rollBack();
            else {
                $this->db->exec('ROLLBACK TO SAVEPOINT copia_catalogos_clinica');
                $this->db->exec('RELEASE SAVEPOINT copia_catalogos_clinica');
            }
            throw $e;
        }
    }

    /** Tablas y columnas son constantes internas, nunca datos de una petición. */
    private function copiarTabla(int $idClinica, string $tabla, array $columnas, array $filas, bool $conId = true): array
    {
        $consulta = $this->db->prepare("SELECT 1 FROM $tabla WHERE id_clinica = ? LIMIT 1");
        $consulta->execute([$idClinica]);
        if ($consulta->fetchColumn() !== false) return [];
        $sql = "INSERT INTO $tabla (id_clinica," . implode(',', $columnas) . ') VALUES ('
            . implode(',', array_fill(0, count($columnas) + 1, '?')) . ')';
        $insertar = $this->db->prepare($sql);
        $ids = [];
        foreach ($filas as $fila) {
            $original = $conId ? array_shift($fila) : null;
            $insertar->execute([$idClinica, ...$fila]);
            if ($conId) $ids[$original] = (int) $this->db->lastInsertId();
        }
        return $ids;
    }
}
