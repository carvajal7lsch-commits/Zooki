<?php
require_once __DIR__ . '/ModeloHistoria.php';
require_once __DIR__ . '/ArchivoClinico.php';
require_once __DIR__ . '/Tratamiento.php';
require_once __DIR__ . '/../helpers/Transaccion.php';
require_once __DIR__ . '/../helpers/ValidadorClinico.php';

/**
 * HU-2.1, HU-2.2, HU-2.5 y HU-2.6: consultas de la clínica activa.
 *
 * Cada consulta guarda su clínica y el id_usuario del veterinario (RN-112);
 * la visibilidad entre clínicas sale de ModeloHistoria (RN-113).
 */
class Consulta extends ModeloHistoria
{
    /** Estados de una cita cuya atención se documenta con la consulta (RN-409). */
    private const ESTADOS_EN_ATENCION = ['en_curso', 'sin_cerrar'];

    /** Segundos que se espera el candado de numeración antes de desistir. */
    protected const ESPERA_CANDADO_HC = 10;

    /**
     * Registra la consulta con sus adjuntos y tratamientos, todo o nada
     * (RE-2.6.1). $guardarAdjunto mueve el archivo al disco y devuelve sus
     * metadatos; si algo falla después, quien llama borra lo movido, porque
     * el disco no participa del rollback.
     *
     * @param callable(int, array, int): array $guardarAdjunto
     */
    public function registrar(array $entrada, array $tratamientos, array $adjuntos, callable $guardarAdjunto): int
    {
        $idMascota = ValidadorClinico::id($entrada['id_mascota'] ?? null);
        if ($idMascota === null) {
            throw new InvalidArgumentException('La mascota indicada no es válida.');
        }

        // Las comprobaciones de acceso van antes de la transacción para que
        // su auditoría no se pierda con un rollback.
        $this->exigirMascotaActiva($idMascota);
        $datos = $this->validar($entrada, $idMascota);
        if ($datos['id_cita'] !== null) {
            $this->exigirCitaParaConsulta($datos['id_cita'], $idMascota);
        }
        $tratamientosValidos = array_map([Tratamiento::class, 'validar'], $tratamientos);

        $clinica = $this->clinica();
        $esPrimeraConsulta = $this->numeroHistoria($idMascota, $clinica) === null;
        if ($esPrimeraConsulta) {
            $this->tomarCandadoNumeracion($clinica);
        }

        try {
            return Transaccion::ejecutar($this->conn, function () use ($datos, $tratamientosValidos, $adjuntos, $guardarAdjunto, $clinica) {
                return $this->escribir($datos, $tratamientosValidos, $adjuntos, $guardarAdjunto, $clinica);
            });
        } catch (AccesoDenegado $e) {
            // La auditoría escrita dentro de la transacción se deshizo con ella.
            $this->denegarAcceso('mascotas', $idMascota, 'Registro de consulta denegado; no se guardó nada (RN-112, RN-207)');
        } finally {
            if ($esPrimeraConsulta) {
                $this->soltarCandadoNumeracion($clinica);
            }
        }
    }

    /**
     * HU-2.2: consultas de la clínica activa para el listado, la más reciente
     * primero. Incluye las de mascotas desvinculadas (RN-115: la clínica
     * conserva lo suyo en solo lectura), marcadas con `vinculo`.
     */
    public function listarDeLaClinica(): array
    {
        $sql = "SELECT c.*, mc.estado AS vinculo, m.nombre AS nombre_mascota, e.nombre_especie,
                p.nombre_completo AS nombre_propietario, u.nombre_completo AS veterinario
            FROM consultas c
            JOIN mascotas m ON m.id_mascota = c.id_mascota
            JOIN mascota_clinica mc ON mc.id_mascota = c.id_mascota AND mc.id_clinica = c.id_clinica
            LEFT JOIN especies e ON e.id_especie = m.id_especie
            LEFT JOIN usuarios p ON p.id_usuario = m.id_propietario
            JOIN usuarios u ON u.id_usuario = c.id_veterinario
            WHERE c.id_clinica = ?
            ORDER BY c.fecha_hora DESC, c.id_consulta DESC";
        $consulta = $this->conn->prepare($sql);
        $consulta->execute([$this->clinica()]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * RN-206 / RN-113: consultas visibles de la mascota, la más reciente
     * primero, con la clínica y el veterinario de cada una.
     */
    public function findByMascota($idMascota): array
    {
        $idMascota = (int) $idMascota;
        $this->exigirMascotaVinculada($idMascota);

        [$visible, $parametros] = $this->consultaVisible('c');
        $sql = "SELECT c.*, u.nombre_completo AS veterinario, cl.nombre AS clinica_nombre
            FROM consultas c
            JOIN usuarios u ON u.id_usuario = c.id_veterinario
            JOIN clinicas cl ON cl.id_clinica = c.id_clinica
            WHERE c.id_mascota = ? AND $visible
            ORDER BY c.fecha_hora DESC, c.id_consulta DESC";
        $consulta = $this->conn->prepare($sql);
        $consulta->execute([$idMascota, ...$parametros]);
        return $this->marcarOrigen($consulta->fetchAll(PDO::FETCH_ASSOC));
    }

    /** HU-2.5: historial completo con adjuntos y tratamientos (5 consultas SQL fijas, M2-10). */
    public function historialDeMascota(int $idMascota): array
    {
        $consultas = $this->findByMascota($idMascota);
        $ids = array_column($consultas, 'id_consulta');
        $archivos = (new ArchivoClinico($this->conn))->deConsultas($ids);
        $tratamientos = (new Tratamiento($this->conn))->findByConsultas($ids);

        foreach ($consultas as &$consulta) {
            $id = (int) $consulta['id_consulta'];
            $consulta['archivos'] = $archivos[$id] ?? [];
            $consulta['tratamientos'] = $tratamientos[$id] ?? [];
        }
        unset($consulta);
        return $consultas;
    }

    /** Consulta de una cita de la clínica activa (una cita genera una sola consulta). */
    public function findByCita($idCita)
    {
        $consulta = $this->conn->prepare('SELECT * FROM consultas WHERE id_cita = ? AND id_clinica = ? LIMIT 1');
        $consulta->execute([(int) $idCita, $this->clinica()]);
        return $consulta->fetch(PDO::FETCH_ASSOC);
    }

    /** RN-112 / RE-2.10.4: punto único por el que pasa cualquier modificación. */
    public function paraModificar(int $idConsulta): array
    {
        $consulta = $this->conn->prepare('SELECT * FROM consultas WHERE id_consulta = ? AND id_clinica = ?');
        $consulta->execute([$idConsulta, $this->clinica()]);
        $fila = $consulta->fetch(PDO::FETCH_ASSOC);

        if ($fila === false) {
            $this->denegarAcceso('consultas', $idConsulta, 'Modificación de una consulta de otra clínica (RN-112)');
        }
        return $fila;
    }

    private function escribir(array $datos, array $tratamientos, array $adjuntos, callable $guardarAdjunto, int $clinica): int
    {
        // RN-207: se relee dentro de la transacción, por si la mascota se dio
        // de baja o se desvinculó mientras se revisaban los adjuntos.
        $this->exigirMascotaActiva($datos['id_mascota']);
        $idConsulta = $this->insertar($datos, $clinica);

        $modeloArchivo = new ArchivoClinico($this->conn);
        foreach ($adjuntos as $indice => $adjunto) {
            $metadatos = $guardarAdjunto($idConsulta, $adjunto, $indice);
            $modeloArchivo->insertarEnConsulta($idConsulta, $metadatos);
        }

        $modeloTratamiento = new Tratamiento($this->conn);
        foreach ($tratamientos as $tratamiento) {
            $modeloTratamiento->insertarEnConsulta($idConsulta, $tratamiento);
        }

        if ($datos['id_cita'] !== null) {
            $this->completarCita($datos['id_cita'], $datos['id_mascota'], $clinica);
        }

        $this->asignarNumeroHistoria($datos['id_mascota'], $clinica);
        return $idConsulta;
    }

    private function insertar(array $datos, int $clinica): int
    {
        $sql = 'INSERT INTO consultas
            (id_clinica, id_cita, id_mascota, id_veterinario, fecha_hora, motivo_consulta, anamnesis,
             peso, temperatura, frecuencia_cardiaca, frecuencia_respiratoria, diagnostico, plan_tratamiento, observaciones)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $this->conn->prepare($sql)->execute([
            $clinica,
            $datos['id_cita'],
            $datos['id_mascota'],
            $this->veterinarioActual(),
            $this->ahora(),
            $datos['motivo_consulta'],
            $datos['anamnesis'],
            $datos['peso'],
            $datos['temperatura'],
            $datos['frecuencia_cardiaca'],
            $datos['frecuencia_respiratoria'],
            $datos['diagnostico'],
            $datos['plan_tratamiento'],
            $datos['observaciones'],
        ]);
        return (int) $this->conn->lastInsertId();
    }

    /** RE-2.1.1, RE-2.1.3 (RN-202) y RE-2.2.2: campos de la consulta, validados en el servidor. */
    private function validar(array $entrada, int $idMascota): array
    {
        $diagnostico = ValidadorClinico::textoRequerido($entrada['diagnostico'] ?? null, 5000);
        if ($diagnostico === null) {
            throw new InvalidArgumentException('El diagnóstico es obligatorio.');
        }

        $motivo = ValidadorClinico::textoOpcional($entrada['motivo'] ?? null, 5000);
        if ($motivo === '') {
            throw new InvalidArgumentException('El motivo de la consulta es obligatorio.');
        }

        $idCita = null;
        if (!empty($entrada['id_cita'])) {
            $idCita = ValidadorClinico::id($entrada['id_cita']);
            if ($idCita === null) {
                throw new InvalidArgumentException('La cita indicada no es válida.');
            }
        }

        // Un signo vital fuera de rango es un error de digitación, y
        // guardarlo ensucia la historia de forma permanente (RN-206).
        $peso = $this->signoDecimal($entrada['peso'] ?? null, ValidadorClinico::PESO_MIN, ValidadorClinico::PESO_MAX, 'El peso debe estar entre 0.01 y 200 kg.');
        $temperatura = $this->signoDecimal($entrada['temperatura'] ?? null, ValidadorClinico::TEMP_MIN, ValidadorClinico::TEMP_MAX, 'La temperatura debe estar entre 25 y 45 °C.');
        $cardiaca = $this->signoEntero($entrada['frecuencia_cardiaca'] ?? null, ValidadorClinico::FC_MIN, ValidadorClinico::FC_MAX, 'La frecuencia cardíaca debe estar entre 10 y 400 lpm.');
        $respiratoria = $this->signoEntero($entrada['frecuencia_respiratoria'] ?? null, ValidadorClinico::FR_MIN, ValidadorClinico::FR_MAX, 'La frecuencia respiratoria debe estar entre 5 y 150 rpm.');

        return [
            'id_mascota' => $idMascota,
            'id_cita' => $idCita,
            'motivo_consulta' => $motivo,
            'anamnesis' => ValidadorClinico::textoOpcional($entrada['anamnesis'] ?? null, 5000),
            'peso' => $peso,
            'temperatura' => $temperatura,
            'frecuencia_cardiaca' => $cardiaca,
            'frecuencia_respiratoria' => $respiratoria,
            'diagnostico' => $diagnostico,
            'plan_tratamiento' => ValidadorClinico::textoOpcional($entrada['plan_tratamiento'] ?? null, 5000),
            'observaciones' => ValidadorClinico::textoOpcional($entrada['observaciones'] ?? null, 5000),
        ];
    }

    private function signoDecimal($crudo, float $minimo, float $maximo, string $mensaje): ?float
    {
        if ($crudo === null || $crudo === '') {
            return null;
        }
        $valor = ValidadorClinico::decimal($crudo, $minimo, $maximo);
        if ($valor === null) {
            throw new InvalidArgumentException($mensaje);
        }
        return $valor;
    }

    private function signoEntero($crudo, int $minimo, int $maximo, string $mensaje): ?int
    {
        if ($crudo === null || $crudo === '') {
            return null;
        }
        $valor = ValidadorClinico::entero($crudo, $minimo, $maximo);
        if ($valor === null) {
            throw new InvalidArgumentException($mensaje);
        }
        return $valor;
    }

    /**
     * RN-203 y RN-407: la cita es de la clínica activa, de esta mascota y del
     * veterinario que registra, y su atención está en curso. Una cita de otra
     * clínica (o inexistente) da 403 auditado.
     */
    private function exigirCitaParaConsulta(int $idCita, int $idMascota): void
    {
        $consulta = $this->conn->prepare('SELECT id_mascota, id_veterinario, estado FROM citas WHERE id_cita = ? AND id_clinica = ?');
        $consulta->execute([$idCita, $this->clinica()]);
        $cita = $consulta->fetch(PDO::FETCH_ASSOC);

        if ($cita === false) {
            $this->denegarAcceso('citas', $idCita, 'Consulta ligada a una cita de otra clínica (RN-407)');
        }
        if ((int) $cita['id_mascota'] !== $idMascota) {
            throw new InvalidArgumentException('La cita indicada no corresponde a esta mascota.');
        }
        if ((int) $cita['id_veterinario'] !== $this->veterinarioActual()) {
            throw new InvalidArgumentException('Solo el veterinario asignado puede registrar la consulta de esta cita.');
        }
        if ($this->findByCita($idCita)) {
            throw new InvalidArgumentException('Esta cita ya tiene una consulta registrada.');
        }
        if (!in_array($cita['estado'], self::ESTADOS_EN_ATENCION, true)) {
            throw new InvalidArgumentException('La atención de esta cita no está en curso. Iníciala desde el calendario.');
        }
    }

    /**
     * RN-406: la consulta completa su cita en la misma transacción. La
     * condición repite clínica, mascota, veterinario y estado para que un
     * cambio entre la revisión y el guardado no complete una cita ajena.
     * El resto de la agenda se adapta en C5.
     */
    private function completarCita(int $idCita, int $idMascota, int $clinica): void
    {
        $marcas = implode(',', array_fill(0, count(self::ESTADOS_EN_ATENCION), '?'));
        $sql = "UPDATE citas SET estado = 'completada', hora_fin_real = ?
            WHERE id_cita = ? AND id_clinica = ? AND id_mascota = ? AND id_veterinario = ?
              AND estado IN ($marcas)";
        $consulta = $this->conn->prepare($sql);
        $consulta->execute([$this->ahora(), $idCita, $clinica, $idMascota, $this->veterinarioActual(), ...self::ESTADOS_EN_ATENCION]);

        if ($consulta->rowCount() !== 1) {
            throw new RuntimeException('La cita ' . $idCita . ' dejó de estar en curso antes de guardar la consulta.');
        }
    }

    private function numeroHistoria(int $idMascota, int $clinica): ?string
    {
        $consulta = $this->conn->prepare('SELECT numero_historia_clinica FROM mascota_clinica WHERE id_mascota = ? AND id_clinica = ?');
        $consulta->execute([$idMascota, $clinica]);
        $numero = $consulta->fetchColumn();
        return $numero === false || $numero === null || $numero === '' ? null : (string) $numero;
    }

    /**
     * RF-2.4 / RN-102: el número de historia se asigna en la primera consulta
     * de la mascota en la clínica, es correlativo por clínica y no cambia
     * después. El candado de numeración (tomado antes de abrir la
     * transacción) hace que esta lectura ya vea el último número confirmado;
     * el índice único (id_clinica, numero) es la última barrera.
     */
    private function asignarNumeroHistoria(int $idMascota, int $clinica): void
    {
        if ($this->numeroHistoria($idMascota, $clinica) !== null) {
            return;
        }

        $consulta = $this->conn->prepare("SELECT MAX(numero_historia_clinica) FROM mascota_clinica
            WHERE id_clinica = ? AND numero_historia_clinica LIKE 'HC-______'");
        $consulta->execute([$clinica]);
        $ultimo = $consulta->fetchColumn();
        $siguiente = $ultimo ? (int) substr((string) $ultimo, 3) + 1 : 1;
        $numero = sprintf('HC-%06d', $siguiente);

        $actualizar = $this->conn->prepare('UPDATE mascota_clinica SET numero_historia_clinica = ?
            WHERE id_mascota = ? AND id_clinica = ? AND numero_historia_clinica IS NULL');
        $actualizar->execute([$numero, $idMascota, $clinica]);
    }

    /**
     * Serializa las primeras consultas de una clínica. Es un candado con
     * nombre de MySQL y no un FOR UPDATE: bloquear la fila de clinicas choca
     * con los bloqueos compartidos que toman las FK al insertar, y contar
     * con bloqueo de rango puede trabar dos primeras consultas entre sí. Se
     * toma antes de abrir la transacción y se suelta después del commit, así
     * la transacción siguiente ya lee el número confirmado. SQLite serializa
     * las escrituras por sí mismo.
     */
    private function tomarCandadoNumeracion(int $clinica): void
    {
        if (!$this->esMysql()) {
            return;
        }
        $consulta = $this->conn->prepare('SELECT GET_LOCK(?, ?)');
        $consulta->execute([$this->nombreCandado($clinica), static::ESPERA_CANDADO_HC]);
        if ((int) $consulta->fetchColumn() !== 1) {
            throw new RuntimeException('No se pudo reservar el número de historia clínica: otra consulta lo está asignando.');
        }
    }

    private function soltarCandadoNumeracion(int $clinica): void
    {
        if (!$this->esMysql()) {
            return;
        }
        $consulta = $this->conn->prepare('SELECT RELEASE_LOCK(?)');
        $consulta->execute([$this->nombreCandado($clinica)]);
    }

    private function nombreCandado(int $clinica): string
    {
        return 'zooki_numero_hc_' . $clinica;
    }

    private function esMysql(): bool
    {
        return $this->conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    }

    private function ahora(): string
    {
        $ahora = new DateTimeImmutable('now', new DateTimeZone('America/Bogota'));
        return $ahora->format('Y-m-d H:i:s');
    }
}
