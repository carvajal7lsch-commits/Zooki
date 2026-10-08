<?php
require_once __DIR__ . '/ModeloClinica.php';
require_once __DIR__ . '/CatalogoClinica.php';
require_once __DIR__ . '/../helpers/HorarioOcupado.php';

/**
 * C5: agenda v1 sobre el modelo v2. Cada cita es de la clínica activa y de un
 * veterinario por id_usuario (RN-G13); una cita de otra clínica da 403
 * auditado. El triage, los sobrecupos y el resto del Grafo II son del
 * módulo 4 de la v2: aquí toda cita es verde y no es sobrecupo.
 *
 * Las transiciones reciben la cita ya validada (getById o paraPropietario):
 * así ninguna cambia una cita que no pasó por la comprobación de acceso.
 */
class Cita extends ModeloClinica
{
    /** Estados desde los que se inicia, cancela, reprograma o marca "no asistió". */
    public const ESTADOS_ABIERTOS = ['pendiente', 'confirmada'];

    /** RN-409: atenciones iniciadas que se retoman hasta registrar su consulta. */
    public const ESTADOS_EN_ATENCION = ['en_curso', 'sin_cerrar'];

    /** Estados que liberan el horario (ocupa_horario NULL en la base, D-2). */
    private const ESTADOS_LIBRES = ['cancelada', 'no_asistio'];

    /** Duración que se asume si la cita no tiene tipo (como en la v1). */
    private const DURACION_POR_DEFECTO = 30;

    /** Columnas comunes de una cita con sus nombres para mostrar. */
    private const SELECT_CITA = "SELECT c.*, m.nombre AS mascota_nombre, m.id_propietario,
            v.nombre_completo AS veterinario_nombre, v.email AS veterinario_email,
            p.nombre_completo AS propietario_nombre, p.email, p.telefono AS propietario_telefono,
            tc.nombre_tipo AS tipo_cita_nombre,
            cl.nombre AS clinica_nombre, cl.direccion AS clinica_direccion
        FROM citas c
        JOIN mascotas m ON m.id_mascota = c.id_mascota
        JOIN usuarios v ON v.id_usuario = c.id_veterinario
        JOIN clinicas cl ON cl.id_clinica = c.id_clinica
        LEFT JOIN usuarios p ON p.id_usuario = m.id_propietario
        LEFT JOIN tipos_cita tc ON tc.id_tipo_cita = c.id_tipo_cita";

    /**
     * HU-4.1: agenda una cita en la clínica activa. Copia la duración y el
     * margen del tipo de cita (RE-4.13.6). Lanza HorarioOcupado si el
     * veterinario o la mascota ya tienen algo en ese horario (RN-401).
     */
    public function registrar(array $datos): int
    {
        $clinica = $this->clinica();
        $idMascota = (int) $datos['id_mascota'];
        $idVeterinario = (int) $datos['id_veterinario'];
        $fecha = $datos['fecha'];
        $hora = self::normalizarHora($datos['hora']);

        $this->exigirMascotaActiva($idMascota);
        $this->exigirVeterinarioDeLaClinica($idVeterinario);
        [$duracion, $margen] = $this->tiemposDelTipo($datos['id_tipo_cita'] ?? null);

        $this->exigirHorarioLibre($idVeterinario, $idMascota, $fecha, $hora, $duracion, null);

        $sql = "INSERT INTO citas
            (id_clinica, id_mascota, id_veterinario, id_tipo_cita, fecha, hora, hora_fin, motivo,
             duracion_minutos, margen_minutos, prioridad, es_sobrecupo, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'verde', 0, ?)";
        $parametros = [
            $clinica,
            $idMascota,
            $idVeterinario,
            $datos['id_tipo_cita'] ?: null,
            $fecha,
            $hora,
            self::horaFin($hora, $duracion),
            $datos['motivo'],
            $duracion,
            $margen,
            $datos['estado'] ?? 'confirmada',
        ];
        $this->ejecutarReserva($sql, $parametros);
        return (int) $this->conn->lastInsertId();
    }

    /**
     * HU-4.2: cambia fecha, hora y, si se pide, el veterinario. Conserva la
     * duración y el margen que se copiaron al reservar.
     */
    public function reprogramar(array $cita, int $idVeterinario, string $fecha, string $hora): void
    {
        $hora = self::normalizarHora($hora);
        if ($idVeterinario !== (int) $cita['id_veterinario']) {
            $this->exigirVeterinarioDeLaClinica($idVeterinario);
        }
        $duracion = (int) ($cita['duracion_minutos'] ?: self::DURACION_POR_DEFECTO);

        $this->exigirHorarioLibre($idVeterinario, (int) $cita['id_mascota'], $fecha, $hora, $duracion, (int) $cita['id_cita']);

        $marcas = $this->marcas(self::ESTADOS_ABIERTOS);
        $sql = "UPDATE citas SET id_veterinario = ?, fecha = ?, hora = ?, hora_fin = ?
            WHERE id_cita = ? AND id_clinica = ? AND estado IN ($marcas)";
        $parametros = [
            $idVeterinario,
            $fecha,
            $hora,
            self::horaFin($hora, $duracion),
            (int) $cita['id_cita'],
            (int) $cita['id_clinica'],
            ...self::ESTADOS_ABIERTOS,
        ];
        $filas = $this->ejecutarReserva($sql, $parametros);
        if ($filas !== 1) {
            throw new InvalidArgumentException('La cita cambió de estado mientras se reprogramaba. Recarga la agenda.');
        }
    }

    /** Cita de la clínica activa; de otra clínica o inexistente, 403 auditado. */
    public function getById($idCita): array
    {
        $consulta = $this->conn->prepare(self::SELECT_CITA . ' WHERE c.id_cita = ? AND c.id_clinica = ?');
        $consulta->execute([(int) $idCita, $this->clinica()]);
        $cita = $consulta->fetch(PDO::FETCH_ASSOC);

        if ($cita === false) {
            $this->denegarAcceso('citas', $idCita, 'Cita fuera de la clínica activa (RN-G13)');
        }
        return $cita;
    }

    /**
     * Contexto de propietario (RN-G02): la cita es de una mascota suya y de
     * una clínica con la que tiene un vínculo activo. Si no, 403 auditado.
     */
    public function paraPropietario(int $idCita, int $idPropietario): array
    {
        $sql = self::SELECT_CITA . "
            JOIN propietario_clinica pc ON pc.id_propietario = m.id_propietario AND pc.id_clinica = c.id_clinica
            WHERE c.id_cita = ? AND m.id_propietario = ? AND pc.estado = 'activo'";
        $consulta = $this->conn->prepare($sql);
        $consulta->execute([$idCita, $idPropietario]);
        $cita = $consulta->fetch(PDO::FETCH_ASSOC);

        if ($cita === false) {
            $this->denegarAcceso('citas', $idCita, 'Cita de una mascota ajena o sin vínculo activo (RN-G02)');
        }
        return $cita;
    }

    /** RE-4.1.5: citas de la clínica activa en un rango; el veterinario ve solo las suyas. */
    public function listarRango(string $inicio, string $fin, ?int $idVeterinario = null): array
    {
        $sql = self::SELECT_CITA . ' WHERE c.id_clinica = ? AND c.fecha BETWEEN ? AND ?';
        $parametros = [$this->clinica(), $inicio, $fin];
        if ($idVeterinario !== null) {
            $sql .= ' AND c.id_veterinario = ?';
            $parametros[] = $idVeterinario;
        }
        $consulta = $this->conn->prepare($sql . ' ORDER BY c.fecha ASC, c.hora ASC');
        $consulta->execute($parametros);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Supervisión del administrador: todas las citas de la clínica activa. */
    public function listarTodas(): array
    {
        $consulta = $this->conn->prepare(self::SELECT_CITA . ' WHERE c.id_clinica = ? ORDER BY c.fecha DESC, c.hora DESC');
        $consulta->execute([$this->clinica()]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Veterinarios activos de la clínica activa (usuario_clinica), para los selectores. */
    public function veterinariosActivos(): array
    {
        $sql = "SELECT u.id_usuario, u.nombre_completo
            FROM usuario_clinica uc
            JOIN usuarios u ON u.id_usuario = uc.id_usuario
            WHERE uc.id_clinica = ? AND uc.id_rol = 2 AND uc.estado = 'activo' AND u.estado = 1
            ORDER BY u.nombre_completo";
        $consulta = $this->conn->prepare($sql);
        $consulta->execute([$this->clinica()]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function esVeterinarioDeLaClinica(int $idVeterinario): bool
    {
        $sql = "SELECT 1 FROM usuario_clinica uc
            JOIN usuarios u ON u.id_usuario = uc.id_usuario
            WHERE uc.id_usuario = ? AND uc.id_clinica = ? AND uc.id_rol = 2 AND uc.estado = 'activo' AND u.estado = 1";
        $consulta = $this->conn->prepare($sql);
        $consulta->execute([$idVeterinario, $this->clinica()]);
        return (bool) $consulta->fetchColumn();
    }

    /** Hora de otra cita activa de la mascota ese día en la clínica activa (aviso al agendar), o null. */
    public function horaDeOtraCitaDelDia(int $idMascota, string $fecha): ?string
    {
        $marcas = $this->marcas(self::ESTADOS_LIBRES);
        $sql = "SELECT hora FROM citas
            WHERE id_clinica = ? AND id_mascota = ? AND fecha = ? AND estado NOT IN ($marcas)
            ORDER BY hora LIMIT 1";
        $consulta = $this->conn->prepare($sql);
        $consulta->execute([$this->clinica(), $idMascota, $fecha, ...self::ESTADOS_LIBRES]);
        $hora = $consulta->fetchColumn();
        return $hora === false ? null : (string) $hora;
    }

    public function getTiposCita(): array
    {
        $tipos = $this->catalogo()->tiposCita();
        return array_map(static fn (array $tipo): array => $tipo + ['nombre' => $tipo['nombre_tipo']], $tipos);
    }

    public function getTipoCitaById($idTipoCita)
    {
        return $this->catalogo()->tipoCita((int) $idTipoCita) ?? false;
    }

    /**
     * Horarios libres del veterinario ese día, de 08:00 a 18:00 como en la
     * v1 (la pantalla los cruza con el horario de la clínica; RE-4.9.1 es del
     * módulo 4). Cuenta sus citas en todas sus clínicas (decisión C5).
     */
    public function sugerencias(int $idVeterinario, string $fecha, int $duracion, ?int $excluir, DateTimeImmutable $ahora): array
    {
        if (!$this->esVeterinarioDeLaClinica($idVeterinario)) {
            return [];
        }
        $duracion = max($duracion, 1);
        $ocupadas = $this->intervalos($this->ocupadasDelVeterinario($idVeterinario, $fecha, $excluir));
        $minutoActual = $fecha === $ahora->format('Y-m-d') ? self::minutos($ahora->format('H:i:s')) : null;

        $sugerencias = [];
        for ($inicio = 8 * 60; $inicio + $duracion <= 18 * 60; $inicio += $duracion) {
            if ($minutoActual !== null && $inicio < $minutoActual) {
                continue;
            }
            if (!self::seSolapa($ocupadas, $inicio, $inicio + $duracion)) {
                $sugerencias[] = sprintf('%02d:%02d', intdiv($inicio, 60), $inicio % 60);
            }
        }
        return $sugerencias;
    }

    /** RN-407: inicia la atención y sella la hora real de inicio (RE-4.5.1). */
    public function iniciarAtencion(array $cita, string $ahora): bool
    {
        return $this->transicion($cita, self::ESTADOS_ABIERTOS, "estado = 'en_curso', hora_inicio_real = ?", [$ahora]);
    }

    /**
     * RN-406: completa una cita en curso o sin cerrar. Solo lo usa la cita
     * cuya consulta ya existe y quedó abierta; lo normal es completarla al
     * guardar la consulta (Consulta::registrar).
     */
    public function completarAtencion(array $cita, string $ahora): bool
    {
        return $this->transicion($cita, self::ESTADOS_EN_ATENCION, "estado = 'completada', hora_fin_real = ?", [$ahora]);
    }

    /** RN-408: el paciente no llegó; la cita deja de ocupar su horario. */
    public function marcarNoAsistio(array $cita): bool
    {
        return $this->transicion($cita, self::ESTADOS_ABIERTOS, "estado = 'no_asistio'", []);
    }

    /** Solo una cita pendiente se confirma: confirmar una cancelada la reactivaba. */
    public function confirmar(array $cita): bool
    {
        return $this->transicion($cita, ['pendiente'], "estado = 'confirmada'", []);
    }

    /** RN-405: solo se cancela una cita pendiente o confirmada; no se borra. */
    public function cancelar(array $cita): bool
    {
        return $this->transicion($cita, self::ESTADOS_ABIERTOS, "estado = 'cancelada'", []);
    }

    /**
     * Cambia el estado solo si la cita sigue en uno de los de origen y en su
     * clínica. false si no tocó ninguna fila (otra petición se adelantó).
     */
    private function transicion(array $cita, array $desde, string $cambio, array $parametros): bool
    {
        $marcas = $this->marcas($desde);
        $sql = "UPDATE citas SET $cambio WHERE id_cita = ? AND id_clinica = ? AND estado IN ($marcas)";
        $consulta = $this->conn->prepare($sql);
        $consulta->execute([...$parametros, (int) $cita['id_cita'], (int) $cita['id_clinica'], ...$desde]);
        return $consulta->rowCount() === 1;
    }

    /**
     * RN-401: el veterinario no puede tener dos citas que se solapan, en
     * ninguna de sus clínicas, y la mascota tampoco.
     */
    protected function exigirHorarioLibre(int $idVeterinario, int $idMascota, string $fecha, string $hora, int $duracion, ?int $excluir): void
    {
        $inicio = self::minutos($hora);
        $fin = $inicio + max($duracion, 1);

        $delVeterinario = $this->intervalos($this->ocupadasDelVeterinario($idVeterinario, $fecha, $excluir));
        if (self::seSolapa($delVeterinario, $inicio, $fin)) {
            throw new HorarioOcupado('El veterinario no está disponible en ese horario. Hay solapamiento con otra cita.');
        }

        $deLaMascota = $this->intervalos($this->ocupadasDeLaMascota($idMascota, $fecha, $excluir));
        if (self::seSolapa($deLaMascota, $inicio, $fin)) {
            throw new HorarioOcupado('La mascota ya tiene otra cita que se solapa con ese horario. Elige otra hora.');
        }
    }

    /** Solo horas: de otra clínica no se lee ni se devuelve ningún otro dato. */
    private function ocupadasDelVeterinario(int $idVeterinario, string $fecha, ?int $excluir): array
    {
        return $this->ocupadas('id_veterinario', $idVeterinario, $fecha, $excluir);
    }

    private function ocupadasDeLaMascota(int $idMascota, string $fecha, ?int $excluir): array
    {
        return $this->ocupadas('id_mascota', $idMascota, $fecha, $excluir);
    }

    private function ocupadas(string $columna, int $id, string $fecha, ?int $excluir): array
    {
        $marcas = $this->marcas(self::ESTADOS_LIBRES);
        $sql = "SELECT hora, hora_fin, duracion_minutos FROM citas
            WHERE $columna = ? AND fecha = ? AND es_sobrecupo = 0 AND estado NOT IN ($marcas)";
        $parametros = [$id, $fecha, ...self::ESTADOS_LIBRES];
        if ($excluir !== null) {
            $sql .= ' AND id_cita <> ?';
            $parametros[] = $excluir;
        }
        $consulta = $this->conn->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array{0: int, 1: int}> minutos de inicio y fin de cada cita */
    private function intervalos(array $citas): array
    {
        $intervalos = [];
        foreach ($citas as $cita) {
            $inicio = self::minutos($cita['hora']);
            $fin = !empty($cita['hora_fin'])
                ? self::minutos($cita['hora_fin'])
                : $inicio + ((int) $cita['duracion_minutos'] ?: self::DURACION_POR_DEFECTO);
            $intervalos[] = [$inicio, $fin];
        }
        return $intervalos;
    }

    private static function seSolapa(array $intervalos, int $inicio, int $fin): bool
    {
        foreach ($intervalos as [$ocupadoDesde, $ocupadoHasta]) {
            if ($inicio < $ocupadoHasta && $fin > $ocupadoDesde) {
                return true;
            }
        }
        return false;
    }

    /**
     * Ejecuta el INSERT o el UPDATE de una reserva. Si otra reserva tomó la
     * misma hora entre la validación y la escritura, el índice único
     * uq_cita_veterinario_horario (D-2, sin id_clinica) la rechaza: se
     * convierte en HorarioOcupado en vez de un 500.
     */
    private function ejecutarReserva(string $sql, array $parametros): int
    {
        try {
            $consulta = $this->conn->prepare($sql);
            $consulta->execute($parametros);
            return $consulta->rowCount();
        } catch (PDOException $e) {
            if ($this->esReservaDuplicada($e)) {
                throw new HorarioOcupado('Ese horario acaba de ocuparse. Elige otro de los horarios disponibles.');
            }
            throw $e;
        }
    }

    private function esReservaDuplicada(PDOException $e): bool
    {
        $codigoMysql = (int) ($e->errorInfo[1] ?? 0);
        if ($codigoMysql === 1062) {
            return true;
        }
        return str_contains($e->getMessage(), 'UNIQUE constraint failed: citas.');
    }

    private function exigirVeterinarioDeLaClinica(int $idVeterinario): void
    {
        if (!$this->esVeterinarioDeLaClinica($idVeterinario)) {
            throw new InvalidArgumentException('El veterinario seleccionado no existe o no está activo en la clínica.');
        }
    }

    /** RE-4.13.6: duración y margen del tipo de cita de la clínica; sin tipo, 30 minutos y sin margen. */
    private function tiemposDelTipo($idTipoCita): array
    {
        if (empty($idTipoCita)) {
            return [self::DURACION_POR_DEFECTO, 0];
        }
        $tipo = $this->catalogo()->tipoCita((int) $idTipoCita);
        if ($tipo === null) {
            throw new InvalidArgumentException('Elige el tipo de cita de la lista.');
        }
        return [(int) $tipo['duracion_minutos'], (int) $tipo['margen_minutos']];
    }

    /** Catálogo de la misma clínica (la activa o la que eligió el propietario en el portal). */
    private function catalogo(): CatalogoClinica
    {
        return $this->conMismoAlcance(new CatalogoClinica($this->conn));
    }

    private function marcas(array $valores): string
    {
        return implode(', ', array_fill(0, count($valores), '?'));
    }

    /** "8:30" o "08:30:00" → "08:30:00"; otra cosa no es una hora válida. */
    public static function normalizarHora(string $hora): string
    {
        $hora = trim($hora);
        $formato = strlen($hora) > 5 ? 'H:i:s' : 'H:i';
        $leida = DateTimeImmutable::createFromFormat('!' . $formato, $hora);
        if ($leida === false || $leida->format($formato) !== $hora) {
            throw new InvalidArgumentException('La hora de la cita no es válida.');
        }
        return $leida->format('H:i:s');
    }

    private static function horaFin(string $hora, int $duracion): string
    {
        $fin = self::minutos($hora) + $duracion;
        return sprintf('%02d:%02d:00', intdiv($fin, 60) % 24, $fin % 60);
    }

    private static function minutos(string $hora): int
    {
        [$horas, $minutos] = array_map('intval', explode(':', $hora));
        return $horas * 60 + $minutos;
    }
}
