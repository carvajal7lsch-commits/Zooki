<?php
require_once __DIR__ . '/ModeloClinica.php';

/** B1: resultado durable en el mismo commit; revalidar acceso antes de consultar. */
final class OperacionAgenda extends ModeloClinica
{
    /** Solo orienta la advertencia visual; ejecutar vuelve a validar huella y acceso en la unidad. */
    public function tieneResultado(string $accion, mixed $clave): bool
    {
        if (!is_string($clave) || !preg_match('/^[a-f0-9]{64}$/D', $clave)) {
            return false;
        }
        $q = $this->conn->prepare('SELECT 1 FROM operaciones_agenda WHERE id_clinica=? AND actor_clave=? AND accion=? AND clave_operacion=?');
        $q->execute([$this->clinica(), 'usuario:' . Contexto::idUsuario(), $accion, $clave]);
        return $q->fetchColumn() !== false;
    }

    private static function canonica(array $entrada): array
    {
        foreach ($entrada as &$valor) {
            if (is_array($valor)) {
                $valor = self::canonica($valor);
            }
        }
        unset($valor);
        if (!array_is_list($entrada)) {
            ksort($entrada, SORT_STRING);
        }
        return $entrada;
    }

    public function ejecutar(string $accion, ?string $clave, array $entrada, callable $escribir): array
    {
        if (!$this->conn->inTransaction()) {
            throw new LogicException('La operación idempotente necesita la unidad de agenda.');
        }
        $clave ??= bin2hex(random_bytes(32));
        if (!preg_match('/^[a-f0-9]{64}$/D', $clave)) {
            throw new InvalidArgumentException('Clave de operación no válida.');
        }
        $actor = 'usuario:' . Contexto::idUsuario();
        $huella = hash('sha256', json_encode(self::canonica($entrada), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $q = $this->conn->prepare('SELECT huella_entrada, resultado FROM operaciones_agenda WHERE id_clinica=? AND actor_clave=? AND accion=? AND clave_operacion=?');
        $q->execute([$this->clinica(), $actor, $accion, $clave]);
        $fila = $q->fetch(PDO::FETCH_ASSOC);
        if ($fila) {
            if (!hash_equals($fila['huella_entrada'], $huella)) {
                throw new InvalidArgumentException('La clave corresponde a otro comando. Recarga y revisa los datos.');
            }
            return json_decode($fila['resultado'], true, 512, JSON_THROW_ON_ERROR);
        }
        $resultado = $escribir();
        $q = $this->conn->prepare('INSERT INTO operaciones_agenda (id_clinica,id_actor,actor_clave,accion,clave_operacion,huella_entrada,resultado) VALUES (?,?,?,?,?,?,?)');
        $q->execute([$this->clinica(), Contexto::idUsuario(), $actor, $accion, $clave, $huella, json_encode($resultado, JSON_THROW_ON_ERROR)]);
        return $resultado;
    }
}
