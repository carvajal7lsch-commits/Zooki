<?php
require_once __DIR__ . '/Contexto.php';

/** B1/B1.1: una entrada exterior, clínica → usuarios → mascotas; sin instantáneas previas. */
final class CoordinadorAgenda
{
    private static ?WeakMap $unidades = null;

    public function __construct(private PDO $db)
    {
    }

    public function ejecutar(int $clinica, callable $recursos, callable $accion): mixed
    {
        self::$unidades ??= new WeakMap();
        if (isset(self::$unidades[$this->db])) {
            $unidad = self::$unidades[$this->db];
            $necesarios = $recursos();
            if ($unidad['clinica'] !== $clinica || array_diff($necesarios['usuarios'] ?? [], $unidad['usuarios'])
                || array_diff($necesarios['mascotas'] ?? [], $unidad['mascotas'])) {
                throw new LogicException('Reinicia la operación con todos sus recursos antes de bloquear.');
            }
            return $accion();
        }
        if ($this->db->inTransaction()) {
            throw new LogicException('La agenda exige iniciar su unidad exterior con el coordinador.');
        }
        if ($this->mysql()) {
            // Solo esta transacción: ninguna lectura de decisión hereda REPEATABLE READ.
            $this->db->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        }
        $this->db->beginTransaction();
        try {
            $this->bloquear('clinicas', 'id_clinica', [$clinica]);
            // Descubrir después de esperar a la clínica incluye reservas recién confirmadas.
            $necesarios = $recursos();
            $usuarios = $this->ids([...($necesarios['usuarios'] ?? []), Contexto::idUsuario()]);
            $mascotas = $this->ids($necesarios['mascotas'] ?? []);
            $this->bloquear('usuarios', 'id_usuario', $usuarios);
            $this->bloquear('mascotas', 'id_mascota', $mascotas);
            self::$unidades[$this->db] = ['clinica' => $clinica, 'usuarios' => $usuarios, 'mascotas' => $mascotas];
            $resultado = $accion();
            $this->db->commit();
            return $resultado;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($error instanceof PDOException && ($error->getCode() === '40001' || in_array((int) ($error->errorInfo[1] ?? 0), [1205, 1213], true))) {
                throw new InvalidArgumentException('La agenda cambió mientras se esperaba. Reintenta el comando completo.', 0, $error);
            }
            throw $error;
        } finally {
            unset(self::$unidades[$this->db]);
        }
    }

    private function ids(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        sort($ids, SORT_NUMERIC);
        return $ids;
    }

    private function bloquear(string $tabla, string $columna, array $ids): void
    {
        foreach ($ids as $id) {
            $q = $this->db->prepare("SELECT $columna FROM $tabla WHERE $columna = ?" . $this->sufijo());
            $q->execute([$id]);
            if ($q->fetchColumn() === false) {
                throw new InvalidArgumentException('Recurso de agenda no disponible.');
            }
            $q->closeCursor();
        }
    }

    public function sufijo(): string
    {
        return $this->mysql() ? ' FOR UPDATE' : '';
    }

    private function mysql(): bool
    {
        return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    }
}
