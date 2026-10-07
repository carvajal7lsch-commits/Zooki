<?php
require_once __DIR__ . '/ModeloClinica.php';
require_once __DIR__ . '/../helpers/ValoresInicialesClinica.php';

/** HU-7.1: todas las lecturas y escrituras usan la clínica del contexto. */
final class HorarioClinica extends ModeloClinica
{
    public function listar(): array
    {
        $consulta = $this->conn->prepare('SELECT * FROM horarios_clinica WHERE id_clinica = ? ORDER BY dia_semana');
        $consulta->execute([$this->clinica()]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function dia(int $dia): ?array
    {
        $consulta = $this->conn->prepare('SELECT * FROM horarios_clinica WHERE id_clinica = ? AND dia_semana = ?');
        $consulta->execute([$this->clinica(), $dia]);
        return $consulta->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Datos ya validados; una semana se guarda completa o no cambia. */
    public function guardar(array $filas): void
    {
        $id = $this->clinica();
        $this->conn->beginTransaction();
        try {
            foreach ($filas as $fila) {
                $consulta = $this->conn->prepare('SELECT id FROM horarios_clinica WHERE id_clinica = ? AND dia_semana = ?');
                $consulta->execute([$id, $fila[0]]);
                if ($consulta->fetchColumn() !== false) {
                    $this->conn->prepare('UPDATE horarios_clinica SET activo = ?, bloque_morning_activo = ?, bloque_afternoon_activo = ?,
                        bloque_morning_inicio = ?, bloque_morning_fin = ?, bloque_afternoon_inicio = ?, bloque_afternoon_fin = ?
                        WHERE id_clinica = ? AND dia_semana = ?')->execute([...array_slice($fila, 1), $id, $fila[0]]);
                } else {
                    $this->conn->prepare('INSERT INTO horarios_clinica (id_clinica, dia_semana, activo, bloque_morning_activo,
                        bloque_afternoon_activo, bloque_morning_inicio, bloque_morning_fin, bloque_afternoon_inicio, bloque_afternoon_fin)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([$id, ...$fila]);
                }
            }
            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /** RE-7.1.3: restaurar sin borrar filas ni tocar otra clínica. */
    public function restaurar(): void
    {
        $this->guardar(ValoresInicialesClinica::HORARIOS);
    }
}
