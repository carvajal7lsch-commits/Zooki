<?php
require_once __DIR__ . '/ModeloPropietario.php';
require_once __DIR__ . '/../helpers/Transaccion.php';

/**
 * HU-5.1 y HU-5.4: las mascotas del propietario en el portal. La mascota es
 * global (RN-110): el propietario ve todas las suyas, en todas las
 * clínicas, y cambia también especie, raza, sexo y nacimiento.
 */
final class MascotaPropietario extends ModeloPropietario
{
    /** Campos que el propietario edita desde el portal (el color lo registra la clínica). */
    private const EDITABLES = ['nombre', 'id_especie', 'id_raza', 'raza_indicada', 'sexo', 'fecha_nacimiento', 'peso', 'url_foto'];

    private const FICHA = 'SELECT m.*, e.nombre_especie, r.nombre_raza
        FROM mascotas m
        LEFT JOIN especies e ON e.id_especie = m.id_especie
        LEFT JOIN razas r ON r.id_raza = m.id_raza';

    /** RE-5.1.2: solo las mascotas activas del propietario. */
    public function listar(): array
    {
        $consulta = $this->conn->prepare(self::FICHA . ' WHERE m.id_propietario = ? AND m.estado = 1 ORDER BY m.nombre');
        $consulta->execute([$this->propietario()]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    /** RE-5.1.4: la ficha de una mascota propia; ajena, 403 auditado. */
    public function ficha(int $idMascota): array
    {
        $this->exigirMascotaPropia($idMascota);
        $consulta = $this->conn->prepare(self::FICHA . ' WHERE m.id_mascota = ?');
        $consulta->execute([$idMascota]);
        return $consulta->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Alta desde el portal: la ficha queda global y sin clínica; se vincula a
     * una clínica al agendar allí o cuando la clínica la atiende (RE-5.13.2).
     * Los datos llegan validados (ValidadorMascota y catálogo).
     */
    public function registrar(array $datos): int
    {
        $idPropietario = $this->propietario();

        return Transaccion::ejecutar($this->conn, function () use ($datos, $idPropietario): int {
            // RN-502: 256 bits independientes del id; base64url de 43 caracteres.
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $campos = array_intersect_key($datos, array_flip(self::EDITABLES));
            $campos['id_propietario'] = $idPropietario;
            $campos['token_carnet'] = $token;

            $columnas = implode(', ', array_keys($campos));
            $marcas = implode(', ', array_fill(0, count($campos), '?'));
            $this->conn->prepare("INSERT INTO mascotas ($columnas) VALUES ($marcas)")->execute(array_values($campos));
            $idMascota = (int) $this->conn->lastInsertId();

            $this->auditar('INSERT', 'mascotas', $idMascota, null, ['nombre' => $datos['nombre']], 'Mascota registrada desde el portal');
            return $idMascota;
        });
    }

    /**
     * RE-5.4.1/2 y RN-110: el propietario cambia los datos de su mascota,
     * incluidos especie, raza, sexo y nacimiento. Cada campo cambiado queda
     * en auditoria_sistema (sin clínica: el portal no tiene una). Devuelve
     * los campos que cambiaron.
     */
    public function actualizar(int $idMascota, array $datos): array
    {
        $actual = $this->exigirMascotaPropia($idMascota);
        $campos = array_intersect_key($datos, array_flip(self::EDITABLES));

        $cambios = [];
        foreach ($campos as $campo => $valor) {
            if (self::cambio($actual[$campo] ?? null, $valor)) {
                $cambios[$campo] = $valor;
            }
        }
        if ($cambios === []) {
            return [];
        }

        Transaccion::ejecutar($this->conn, function () use ($idMascota, $actual, $cambios): void {
            $asignaciones = implode(', ', array_map(fn (string $campo): string => "$campo = ?", array_keys($cambios)));
            $sql = "UPDATE mascotas SET $asignaciones WHERE id_mascota = ? AND id_propietario = ?";
            $this->conn->prepare($sql)->execute([...array_values($cambios), $idMascota, $this->propietario()]);

            foreach ($cambios as $campo => $valor) {
                $this->auditar('UPDATE', 'mascotas', $idMascota, [$campo => $actual[$campo]], [$campo => $valor], 'Ficha cambiada por el propietario (RN-110)');
            }
        });
        return array_keys($cambios);
    }

    /** «8.50» y 8.5 son el mismo peso; null y '' tampoco son un cambio. */
    private static function cambio($antes, $despues): bool
    {
        if (is_numeric($antes) && is_numeric($despues)) {
            return (float) $antes !== (float) $despues;
        }
        return (string) ($antes ?? '') !== (string) ($despues ?? '');
    }
}
