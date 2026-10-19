<?php
require_once __DIR__ . '/ModeloHistoria.php';
require_once __DIR__ . '/../helpers/ValidadorAlertaMedica.php';
require_once __DIR__ . '/../helpers/ValidadorClinico.php';
require_once __DIR__ . '/../helpers/Transaccion.php';

/**
 * HU-2.1, RE-2.1.5 y RE-2.10.1 — Alergias y alertas médicas de la mascota.
 *
 * - Registra un veterinario de una clínica con vínculo activo (RN-207, RN-115).
 * - Toda clínica con vínculo activo ve las de todas las clínicas con la que la
 *   registró (RN-113); con el vínculo inactivo, solo las propias; sin vínculo,
 *   403 auditado.
 * - Solo la clínica que la registró la desactiva (RN-112). Nunca se borra: queda
 *   inactiva, y quién y cuándo la desactivó queda en auditoria_sistema, en la
 *   misma transacción.
 */
class AlertaMedica extends ModeloHistoria
{
    private const DESCRIPCION_DESACTIVAR = 'Alerta médica desactivada (RE-2.1.5, RN-112)';

    /** RE-2.1.5: registra la alerta; devuelve su id. */
    public function registrar(array $entrada): int
    {
        $idMascota = ValidadorClinico::id($entrada['id_mascota'] ?? null);
        if ($idMascota === null) {
            throw new InvalidArgumentException('La mascota indicada no es válida.');
        }
        $this->exigirMascotaActiva($idMascota);
        $tipo = ValidadorAlertaMedica::tipo($entrada['tipo'] ?? null);
        $descripcion = ValidadorAlertaMedica::descripcion($entrada['descripcion'] ?? null);
        $idNodo = $this->nodoPermitido($tipo, $entrada['nodo'] ?? null);
        $clinica = $this->clinica();
        $veterinario = $this->veterinarioActual();

        return Transaccion::ejecutar($this->conn, function () use ($idMascota, $tipo, $descripcion, $idNodo, $clinica, $veterinario): int {
            $this->conn->prepare('INSERT INTO alertas_medicas (id_mascota, id_clinica, id_veterinario, tipo, id_nodo, descripcion, activa) VALUES (?, ?, ?, ?, ?, ?, 1)')
                ->execute([$idMascota, $clinica, $veterinario, $tipo, $idNodo, $descripcion]);
            $id = (int) $this->conn->lastInsertId();
            $this->auditar('INSERT', $id, null, ['id_mascota' => $idMascota, 'tipo' => $tipo, 'id_nodo' => $idNodo, 'descripcion' => $descripcion], 'Alerta médica registrada (RE-2.1.5)');
            return $id;
        });
    }

    /** RN-112: solo la clínica que la registró; devuelve el id de la mascota. */
    public function desactivar(mixed $idAlerta): int
    {
        $id = ValidadorClinico::id($idAlerta);
        if ($id === null) {
            throw new InvalidArgumentException('La alerta indicada no es válida.');
        }
        $consulta = $this->conn->prepare('SELECT id_alerta, id_mascota, activa FROM alertas_medicas WHERE id_alerta = ? AND id_clinica = ?');
        $consulta->execute([$id, $this->clinica()]);
        $alerta = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($alerta === false) {
            // No distingue «no existe» de «es de otra clínica» (RE-T.15.2).
            $this->denegarAcceso('alertas_medicas', $id, 'Desactivación de una alerta de otra clínica (RN-112)');
        }
        $mascota = $this->exigirMascotaVinculada((int) $alerta['id_mascota']);
        if ($mascota['vinculo'] !== 'activo') {
            $this->denegarAcceso('alertas_medicas', $id, 'Mascota desvinculada: la historia es de solo lectura (RN-115)');
        }
        if ((int) $alerta['activa'] !== 1) {
            throw new InvalidArgumentException('Esa alerta ya estaba desactivada.');
        }

        Transaccion::ejecutar($this->conn, function () use ($id): void {
            $this->conn->prepare('UPDATE alertas_medicas SET activa = 0 WHERE id_alerta = ? AND id_clinica = ? AND activa = 1')
                ->execute([$id, $this->clinica()]);
            $this->auditar('UPDATE', $id, ['activa' => 1], ['activa' => 0], self::DESCRIPCION_DESACTIVAR);
        });
        return (int) $alerta['id_mascota'];
    }

    /**
     * RN-113 / RE-2.10.1: activas e historial (desactivadas) de todas las clínicas,
     * con la clínica y el veterinario que la registraron.
     *
     * @return array{activas: list<array>, historial: list<array>, solo_lectura: bool}
     */
    public function deMascota(int $idMascota): array
    {
        $mascota = $this->exigirMascotaVinculada($idMascota);
        $sql = 'SELECT a.id_alerta, a.id_clinica, a.tipo, a.descripcion, a.activa, a.fecha,
                cl.nombre AS clinica_nombre, cl.url_logo AS clinica_logo, cl.direccion AS clinica_direccion, cl.telefono AS clinica_telefono, cl.telefono_urgencias AS clinica_urgencias, cl.email_contacto AS clinica_email, u.nombre_completo AS veterinario,
                n.codigo AS nodo_codigo, n.etiqueta AS nodo_etiqueta, n.tipo AS nodo_tipo
            FROM alertas_medicas a
            JOIN clinicas cl ON cl.id_clinica = a.id_clinica
            LEFT JOIN usuarios u ON u.id_usuario = a.id_veterinario
            LEFT JOIN grafo_nodos n ON n.id_nodo = a.id_nodo
            WHERE a.id_mascota = ?';
        $parametros = [$idMascota];
        // RN-115: tras desvincularse, la clínica solo lee lo que ella registró.
        if ($mascota['vinculo'] !== 'activo') {
            $sql .= ' AND a.id_clinica = ?';
            $parametros[] = $this->clinica();
        }
        $consulta = $this->conn->prepare($sql . ' ORDER BY a.fecha DESC, a.id_alerta DESC');
        $consulta->execute($parametros);
        $soloLectura = $mascota['vinculo'] !== 'activo';
        $filas = $this->marcarOrigen($consulta->fetchAll(PDO::FETCH_ASSOC));
        $activas = [];
        $historial = [];
        foreach ($filas as $fila) {
            $fila['tipo_nombre'] = ValidadorAlertaMedica::TIPOS[$fila['tipo']] ?? $fila['tipo'];
            $fila['puede_desactivar'] = $fila['es_propia'] && !$soloLectura && (int) $fila['activa'] === 1;
            if ((int) $fila['activa'] === 1) {
                $activas[] = $fila;
            } else {
                $historial[] = $fila;
            }
        }
        return ['activas' => $activas, 'historial' => $this->conDesactivacion($historial), 'solo_lectura' => $soloLectura];
    }

    /** Quién y cuándo desactivó cada alerta, desde la auditoría escrita en la misma transacción. */
    private function conDesactivacion(array $historial): array
    {
        if ($historial === []) {
            return [];
        }
        $ids = array_map(static fn (array $fila): string => (string) $fila['id_alerta'], $historial);
        $marcas = implode(', ', array_fill(0, count($ids), '?'));
        $consulta = $this->conn->prepare("SELECT au.registro_id, au.fecha_hora, u.nombre_completo
            FROM auditoria_sistema au
            LEFT JOIN usuarios u ON u.id_usuario = au.id_usuario
            WHERE au.tabla_afectada = 'alertas_medicas' AND au.accion = 'UPDATE' AND au.descripcion = ?
              AND au.registro_id IN ($marcas)
            ORDER BY au.id_auditoria");
        $consulta->execute([self::DESCRIPCION_DESACTIVAR, ...$ids]);
        $desactivaciones = [];
        foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $desactivaciones[$fila['registro_id']] = $fila;
        }
        foreach ($historial as &$fila) {
            $marca = $desactivaciones[(string) $fila['id_alerta']] ?? null;
            $fila['desactivada_por'] = $marca['nombre_completo'] ?? null;
            $fila['desactivada_en'] = $marca['fecha_hora'] ?? null;
        }
        unset($fila);
        return $historial;
    }

    /**
     * A.2: el enlace al conocimiento es opcional, pero si viene tiene que ser un nodo
     * activo del tipo que admite la alerta. Se recibe el código estable, nunca un id.
     */
    private function nodoPermitido(string $tipo, mixed $codigo): ?int
    {
        if ($codigo === null || $codigo === '') {
            return null;
        }
        $permitidos = ValidadorAlertaMedica::tiposDeNodo($tipo);
        if (!is_string($codigo) || $permitidos === []) {
            throw new InvalidArgumentException('Este tipo de alerta no se enlaza con el conocimiento.');
        }
        $consulta = $this->conn->prepare('SELECT id_nodo, tipo FROM grafo_nodos WHERE codigo = ? AND activo = 1');
        $consulta->execute([$codigo]);
        $nodo = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($nodo === false) {
            throw new InvalidArgumentException('El elemento del conocimiento elegido no existe o ya no está activo.');
        }
        if (!in_array($nodo['tipo'], $permitidos, true)) {
            throw new InvalidArgumentException('El elemento del conocimiento elegido no corresponde a este tipo de alerta.');
        }
        return (int) $nodo['id_nodo'];
    }

    private function auditar(string $accion, int $id, ?array $antes, ?array $despues, string $descripcion): void
    {
        $auditoria = new Auditoria($this->conn);
        if ($auditoria->log(Contexto::idUsuario(), $accion, 'alertas_medicas', $id, $antes, $despues, $descripcion, $this->clinica()) !== true) {
            // Sin su rastro no se guarda el cambio: quién y cuándo lo hizo es parte del registro.
            throw new RuntimeException('No se pudo auditar el cambio de la alerta médica.');
        }
    }
}
