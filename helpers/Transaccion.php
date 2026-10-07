<?php
/** Atomicidad de una operación, también dentro de una transacción exterior. */
final class Transaccion
{
    public static function ejecutar(PDO $db, callable $operacion): mixed
    {
        $propia = !$db->inTransaction();
        $punto = 'operacion_' . bin2hex(random_bytes(6));
        if ($propia) $db->beginTransaction();
        else $db->exec("SAVEPOINT $punto");
        try {
            $resultado = $operacion();
            if ($propia) $db->commit();
            else $db->exec("RELEASE SAVEPOINT $punto");
            return $resultado;
        } catch (Throwable $e) {
            if ($propia) $db->rollBack();
            else { $db->exec("ROLLBACK TO SAVEPOINT $punto"); $db->exec("RELEASE SAVEPOINT $punto"); }
            throw $e;
        }
    }
}
