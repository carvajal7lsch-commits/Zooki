<?php
require_once __DIR__ . '/ModeloClinica.php';
require_once __DIR__ . '/CatalogoClinica.php';
require_once __DIR__ . '/CoordinacionHorarios.php';
require_once __DIR__ . '/DisponibilidadAgenda.php';
require_once __DIR__ . '/EventoAgenda.php';
require_once __DIR__ . '/../helpers/CoordinadorAgenda.php';
require_once __DIR__ . '/../helpers/HorarioOcupado.php';
require_once __DIR__ . '/../helpers/LimitePlan.php';
require_once __DIR__ . '/../helpers/ContactoPropietario.php';
require_once __DIR__ . '/../helpers/ReglaAtencion.php';

/**
 * C3: disponibilidad única y escritura coordinada sobre el modelo v2. Cada cita es de un
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

    private const ESTADOS_LIBRES = ['cancelada', 'no_asistio'];

    private ?Closure $reloj;

    public function __construct(PDO $conn, ?callable $reloj = null)
    {
        parent::__construct($conn);
        $this->reloj = $reloj === null ? null : Closure::fromCallable($reloj);
    }

    private function disponibilidad(): DisponibilidadAgenda
    {
        return $this->conMismoAlcance(new DisponibilidadAgenda($this->conn, $this->reloj));
    }

    /**
     * Columnas comunes de una cita con sus nombres para mostrar. RN-115: el
     * correo y el teléfono del propietario solo con su vínculo activo en la
     * clínica de la cita (ContactoPropietario).
     */
    private static function selectCita(): string
    {
        return 'SELECT c.*, m.nombre AS mascota_nombre, m.id_propietario,
                v.nombre_completo AS veterinario_nombre, v.email AS veterinario_email,
                p.nombre_completo AS propietario_nombre, p.estado AS propietario_estado,
                ' . ContactoPropietario::columna('p.email', 'pcv', 'email') . ',
                ' . ContactoPropietario::columna('p.telefono', 'pcv', 'propietario_telefono') . ',
                tc.nombre_tipo AS tipo_cita_nombre,
                cl.nombre AS clinica_nombre, cl.direccion AS clinica_direccion, cl.url_logo AS clinica_logo,
                cl.telefono AS clinica_telefono, cl.telefono_urgencias AS clinica_urgencias, cl.email_contacto AS clinica_email
            FROM citas c
            JOIN mascotas m ON m.id_mascota = c.id_mascota
            JOIN usuarios v ON v.id_usuario = c.id_veterinario
            JOIN clinicas cl ON cl.id_clinica = c.id_clinica
            LEFT JOIN usuarios p ON p.id_usuario = m.id_propietario
            LEFT JOIN propietario_clinica pcv ON pcv.id_propietario = m.id_propietario AND pcv.id_clinica = c.id_clinica
            LEFT JOIN tipos_cita tc ON tc.id_tipo_cita = c.id_tipo_cita';
    }

    /**
     * HU-4.1: agenda una cita en la clínica activa. Copia la duración y el
     * margen del tipo de cita (RE-4.13.6). Lanza HorarioOcupado si el
     * veterinario o la mascota ya tienen algo en ese horario (RN-401).
     */
    public function registrar(array $datos): int
    {
        // Las peticiones ordinarias no pueden autodeclararse rojas para eludir el plan.
        return $this->registrarEvaluada($datos, 'verde');
    }

    public function reservaRepetida(mixed $clave): bool
    {
        return $this->conMismoAlcance(new OperacionAgenda($this->conn))->tieneResultado('reservar_cita', $clave);
    }

    public function registrarConResultado(array $datos): array
    {
        return $this->registrarResultado($datos, 'verde');
    }

    public function coordinarReserva(array $datos, callable $accion): mixed
    {
        try {
            return (new CoordinadorAgenda($this->conn))->ejecutar($this->clinica(), static fn () => [
                'usuarios' => [(int) $datos['id_veterinario']], 'mascotas' => [(int) $datos['id_mascota']]
            ], $accion);
        } catch (LimiteAlcanzado $error) {
            if (!$this->conn->inTransaction()) {
                (new LimitePlan($this->conn, $this->clinica()))->registrarRechazo($error);
            }
            throw $error;
        } catch (AccesoDenegado $error) {
            if (!$this->conn->inTransaction()) {
                $this->denegarAcceso('citas', null, 'Reserva denegada tras revalidación actual (RE-T.15.2)');
            }
            throw $error;
        }
    }

    /** RN-420: punto de entrada del futuro triage, con prioridad ya evaluada en servidor. */
    public function registrarEvaluada(array $datos, string $prioridad): int
    {
        return $this->registrarResultado($datos, $prioridad)['id_cita'];
    }

    /** C4: solo el servicio de triage decide la excepción naranja, nunca un campo HTTP. */
    public function registrarClasificada(array $datos, string $prioridad, bool $sobrecupo): int
    {
        if ($sobrecupo && $prioridad !== 'naranja') {
            throw new LogicException('Solo el naranja habilita esta excepción.');
        }
        return $this->registrarResultado($datos, $prioridad, $sobrecupo)['id_cita'];
    }

    private function registrarResultado(array $datos, string $prioridad, bool $sobrecupo = false): array
    {
        if (!in_array($prioridad, ['verde', 'amarillo', 'naranja', 'rojo'], true)) {
            throw new InvalidArgumentException('Prioridad clínica no válida.');
        }
        $clinica = $this->clinica();
        // RN-G13: un id ajeno sigue dando 403, incluso si la clínica ya agotó su plan.
        $this->exigirMascotaActiva((int) $datos['id_mascota']);
        $this->exigirVeterinarioDeLaClinica((int) $datos['id_veterinario']);
        return $this->coordinarReserva($datos, function () use ($datos, $prioridad, $clinica, $sobrecupo): array {
            $this->exigirActorVigente((int) $datos['id_mascota']);
            $this->disponibilidad()->autorizar((int) $datos['id_veterinario'], (int) $datos['id_mascota']);
            $operaciones = $this->conMismoAlcance(new OperacionAgenda($this->conn));
            $entrada = $datos;
            unset($entrada['clave_operacion']);
            $nueva = false;
            $huella = $sobrecupo ? [$entrada, $prioridad, true] : [$entrada, $prioridad];
            $resultado = $operaciones->ejecutar('reservar_cita', $datos['clave_operacion'] ?? null, $huella, function () use ($datos, $prioridad, $clinica, $sobrecupo, &$nueva) {
                $nueva = true;
                $idCita = (new LimitePlan($this->conn, $clinica))->consumir('citas', function () use ($datos, $prioridad, $clinica, $sobrecupo): int {
                    $idMascota = (int) $datos['id_mascota'];
                    $idVeterinario = (int) $datos['id_veterinario'];
                    $fecha = $datos['fecha'];
                    $hora = self::normalizarHora($datos['hora']);

                    $this->exigirMascotaActiva($idMascota);
                    $this->exigirVeterinarioDeLaClinica($idVeterinario);
                    [$duracion, $margen] = $this->tiemposDelTipo($datos['id_tipo_cita'] ?? null);

                    if ($sobrecupo) {
                        $opciones = $this->disponibilidad()->sugerir($idVeterinario, $idMascota, $fecha, $duracion, $margen, null, true);
                        if (!in_array($hora, array_map([self::class, 'normalizarHora'], $opciones), true)) {
                            throw new HorarioOcupado('El sobrecupo ya no es compatible.');
                        }
                    } else {
                        $this->exigirDisponibilidad($idVeterinario, $idMascota, $fecha, $hora, $duracion, $margen, null);
                    }

                    $sql = "INSERT INTO citas
                        (id_clinica, id_mascota, id_veterinario, id_tipo_cita, fecha, hora, hora_fin, motivo,
                         duracion_minutos, margen_minutos, prioridad, es_sobrecupo, estado,
                         origen,duracion_activa_objetivo_segundos,politica_temporal)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'reserva', ?, 'M')";
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
                        $prioridad,
                        (int) $sobrecupo,
                        $datos['estado'] ?? 'confirmada',
                        $duracion * 60,
                    ];
                    $this->ejecutarReserva($sql, $parametros);
                    $id = (int) $this->conn->lastInsertId();
                    if (!(new Auditoria($this->conn))->log(Contexto::idUsuario(), 'INSERT', 'citas', $id, null, ['fecha' => $fecha, 'prioridad' => $prioridad], 'Cita registrada con verificación del plan (RE-0.4.1, RN-420)', $clinica)) {
                        throw new RuntimeException('No se pudo auditar la cita.');
                    }
                    return $id;
                }, $datos['fecha'], $prioridad);
                return ['id_cita' => $idCita];
            });
            return ['id_cita' => (int) $resultado['id_cita'], 'nueva' => $nueva];
        });
    }

    /**
     * HU-4.2: cambia fecha, hora y, si se pide, el veterinario. Conserva la
     * duración y el margen que se copiaron al reservar.
     */
    public function reprogramar(array $cita, int $idVeterinario, string $fecha, string $hora, ?string $clave = null): bool
    {
        try {
            return $this->reprogramarCoordinada($cita, $idVeterinario, $fecha, $hora, $clave);
        } catch (AccesoDenegado $error) {
            if (!$this->conn->inTransaction()) {
                $this->denegarAcceso('citas', $cita['id_cita'], 'Reprogramación denegada tras rollback (RE-T.15.2)');
            }
            throw $error;
        } catch (LimiteAlcanzado $error) {
            if (!$this->conn->inTransaction()) {
                (new LimitePlan($this->conn, $this->clinica()))->registrarRechazo($error);
            }
            throw $error;
        }
    }

    private function reprogramarCoordinada(array $cita, int $idVeterinario, string $fecha, string $hora, ?string $clave): bool
    {
        $idCita = (int) $cita['id_cita'];
        return (new CoordinadorAgenda($this->conn))->ejecutar($this->clinica(), function () use ($idCita, $idVeterinario) {
            $actual = $this->getById($idCita);
            return ['usuarios' => [$idVeterinario, (int) $actual['id_veterinario']], 'mascotas' => [(int) $actual['id_mascota']]];
        }, function () use ($idCita, $idVeterinario, $fecha, $hora, $clave): bool {
            $cita = $this->getById($idCita);
            $this->exigirActorVigente((int) $cita['id_mascota']);
            $this->disponibilidad()->autorizar($idVeterinario, (int) $cita['id_mascota']);
            if (Contexto::rolActivo() === Roles::VETERINARIO && (int) $cita['id_veterinario'] !== Contexto::idUsuario()) {
                throw new AccesoDenegado(403, 'Solo puedes reprogramar tus citas.');
            }
            $nueva = false;
            $this->conMismoAlcance(new OperacionAgenda($this->conn))->ejecutar('reprogramar_cita', $clave, [$idCita, $idVeterinario, $fecha, $hora], function () use ($cita, $idCita, $idVeterinario, $fecha, $hora, &$nueva) {
                $nueva = true;
                $hora = self::normalizarHora($hora);
                if ($idVeterinario !== (int) $cita['id_veterinario']) {
                    $this->exigirVeterinarioDeLaClinica($idVeterinario);
                }
                [$duracion, $margen] = $this->snapshotReprogramable($cita);
                (new EventoAgenda($this->conn, $this->clinica()))->exigirSinDespacho($idCita);
                $this->exigirDisponibilidad($idVeterinario, (int) $cita['id_mascota'], $fecha, $hora, $duracion, $margen, $idCita);
                (new LimitePlan($this->conn, $this->clinica()))->comprobarReprogramacion($idCita, $fecha, $cita['prioridad']);

                $bloque = null;
                $orden = null;
                if ($cita['prioridad'] === 'naranja') {
                    require_once __DIR__ . '/SolicitudAgenda.php';
                    $bloque = $this->conMismoAlcance(new SolicitudAgenda($this->conn, $this->reloj))->bloqueParaCambio(['id_veterinario' => $idVeterinario, 'id_mascota' => (int) $cita['id_mascota'], 'fecha' => $fecha], $hora, $idCita);
                    if ($bloque === null) {
                        throw new HorarioOcupado('El destino no tiene cupo naranja disponible.');
                    }
                    if ((int) $cita['id_bloque'] === $bloque) $orden = $cita['orden_sobrecupo'];
                    else {
                        $q = $this->conn->prepare('SELECT COALESCE(MAX(orden_sobrecupo),0)+1 FROM citas WHERE id_clinica=? AND id_bloque=?');
                        $q->execute([$this->clinica(), $bloque]);
                        $orden = (int) $q->fetchColumn();
                    }
                }
                $marcas = $this->marcas(self::ESTADOS_ABIERTOS);
                $sql = "UPDATE citas SET id_veterinario = ?, fecha = ?, hora = ?, hora_fin = ?, version_agenda=version_agenda+1,
                    es_sobrecupo=0,id_bloque=?,orden_sobrecupo=?,
                    inicio_estimado=NULL,fin_estimado=NULL,fecha_ultimo_calculo=NULL,
                    estado_reajuste='pendiente_calculo',motivo_reajuste='Reprogramación pendiente de cálculo'
                    WHERE id_cita = ? AND id_clinica = ? AND estado IN ($marcas)";
                $parametros = [
                    $idVeterinario,
                    $fecha,
                    $hora,
                    self::horaFin($hora, $duracion),
                    $bloque,
                    $orden,
                    (int) $cita['id_cita'],
                    (int) $cita['id_clinica'],
                    ...self::ESTADOS_ABIERTOS,
                ];
                $filas = $this->ejecutarReserva($sql, $parametros);
                if ($filas !== 1) {
                    throw new InvalidArgumentException('La cita cambió de estado mientras se reprogramaba. Recarga la agenda.');
                }
                if (!(new Auditoria($this->conn))->log(Contexto::idUsuario(), 'UPDATE', 'citas', $idCita, $cita, ['fecha' => $fecha, 'hora' => $hora], 'Reprogramación coordinada (RE-4.11.4)', $this->clinica())) {
                    throw new RuntimeException('No se pudo auditar la reprogramación.');
                }
                $this->avisarCambio($idCita, 'CITA_REPROGRAMADA');
                return ['id_cita' => $idCita];
            });
            return $nueva;
        });
    }

    private function exigirActorVigente(int $mascota): void
    {
        $q = $this->conn->prepare('SELECT estado FROM clinicas WHERE id_clinica=?');
        $q->execute([$this->clinica()]);
        if ($q->fetchColumn() !== 'activa') {
            throw new AccesoDenegado(403, 'Contexto no disponible.');
        }
        $q = $this->conn->prepare('SELECT estado FROM usuarios WHERE id_usuario=?');
        $q->execute([Contexto::idUsuario()]);
        if ((int) $q->fetchColumn() !== 1) {
            throw new AccesoDenegado(403, 'Contexto no disponible.');
        }
        if ((Contexto::actual()['tipo'] ?? '') === Contexto::PROPIETARIO) {
            $q = $this->conn->prepare("SELECT 1 FROM propietario_clinica pc JOIN mascotas m ON m.id_propietario=pc.id_propietario WHERE pc.id_clinica=? AND pc.id_propietario=? AND pc.estado='activo' AND m.id_mascota=?" . (new CoordinadorAgenda($this->conn))->sufijo());
            $q->execute([$this->clinica(), Contexto::idUsuario(), $mascota]);
        } else {
            $q = $this->conn->prepare("SELECT 1 FROM usuario_clinica WHERE id_clinica=? AND id_usuario=? AND id_rol=? AND estado='activo'" . (new CoordinadorAgenda($this->conn))->sufijo());
            $q->execute([$this->clinica(), Contexto::idUsuario(), Contexto::rolActivo()]);
        }
        if (!$q->fetchColumn()) {
            throw new AccesoDenegado(403, 'Contexto no disponible.');
        }
        $q->closeCursor();
        $q = $this->conn->prepare('SELECT estado FROM mascota_clinica WHERE id_clinica=? AND id_mascota=?' . (new CoordinadorAgenda($this->conn))->sufijo());
        $q->execute([$this->clinica(), $mascota]);
        if ($q->fetchColumn() !== 'activo') {
            throw new AccesoDenegado(403, 'Vínculo de mascota no disponible.');
        }
    }

    /** Cita de la clínica activa; de otra clínica o inexistente, 403 auditado. */
    public function getById($idCita): array
    {
        $consulta = $this->conn->prepare(self::selectCita() . ' WHERE c.id_cita = ? AND c.id_clinica = ?');
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
        $sql = self::selectCita() . "
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
        $sql = self::selectCita() . ' WHERE c.id_clinica = ? AND c.fecha BETWEEN ? AND ?';
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
        $consulta = $this->conn->prepare(self::selectCita() . ' WHERE c.id_clinica = ? ORDER BY c.fecha DESC, c.hora DESC');
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
    public function sugerencias(int $idVeterinario, int $idMascota, int $idTipo, string $fecha, ?int $excluir = null): array
    {
        $disponibilidad = $this->disponibilidad();
        $disponibilidad->autorizar($idVeterinario, $idMascota);
        if ($excluir !== null) {
            $cita = $this->getById($excluir);
            if ((int) $cita['id_mascota'] !== $idMascota || (int) $cita['id_tipo_cita'] !== $idTipo) {
                $this->denegarAcceso('citas', $excluir, 'Exclusión de disponibilidad no legítima (RE-4.9.3)');
            }
            [$duracion, $margen] = $this->snapshotReprogramable($cita);
        } else {
            [$duracion, $margen] = $this->tiemposDelTipo($idTipo);
        }
        return $disponibilidad->sugerir($idVeterinario, $idMascota, $fecha, $duracion, $margen, $excluir);
    }

    private function snapshotReprogramable(array $cita): array
    {
        if (!in_array($cita['estado'], self::ESTADOS_ABIERTOS, true)) {
            throw new InvalidArgumentException('Solo se reprograman citas pendientes o confirmadas.');
        }
        if (Contexto::rolActivo() === Roles::VETERINARIO && (int) $cita['id_veterinario'] !== Contexto::idUsuario()) {
            $this->denegarAcceso('citas', $cita['id_cita'], 'Reprogramación de otro veterinario (RN-G13)');
        }
        if ((int) $cita['duracion_minutos'] <= 0 || $cita['margen_minutos'] === null || (int) $cita['margen_minutos'] < 0) {
            throw new InvalidArgumentException('La duración histórica requiere revisión antes de reprogramar.');
        }
        return [(int) $cita['duracion_minutos'], (int) $cita['margen_minutos']];
    }

    protected function exigirDisponibilidad(int $vet, int $mascota, string $fecha, string $hora, int $duracion, int $margen, ?int $excluir): void
    {
        $this->disponibilidad()->exigir($vet, $mascota, $fecha, $hora, $duracion, $margen, $excluir);
    }

    /** RN-407: inicia la atención y sella la hora real de inicio (RE-4.5.1). */
    public function iniciarAtencion(array $cita, string $ahora): bool
    {
        return $this->atencionCoordinada($cita, self::ESTADOS_ABIERTOS, "estado='en_curso',hora_inicio_real=?", $ahora);
    }

    /** RE-4.5.1/3: estado, sello y marcas se confirman bajo el mismo coordinador. */
    public function completarAtencion(array $cita, string $ahora): bool
    {
        return $this->atencionCoordinada($cita, self::ESTADOS_EN_ATENCION, "estado='completada',hora_fin_real=?", $ahora);
    }

    private function atencionCoordinada(array $cita, array $estados, string $cambio, string $ahora): bool
    {
        require_once __DIR__ . '/CascadaAgenda.php';
        $id = (int) $cita['id_cita'];
        $actual = $this->getById($id);
        $cascada = new CascadaAgenda($this->conn, static fn () => IntervalosAgenda::fecha($ahora));
        try {
            return (new CoordinadorAgenda($this->conn))->ejecutar($this->clinica(), fn () => $cascada->recursosDeCita($id), function () use ($id, $estados, $cambio, $ahora, $cascada): bool {
                $actual = $this->getById($id);
                $this->exigirActorVigente((int) $actual['id_mascota']);
                if (Contexto::rolActivo() !== Roles::VETERINARIO || (int) $actual['id_veterinario'] !== Contexto::idUsuario()) {
                    throw new AccesoDenegado(403, 'Solo el veterinario asignado registra la atención.');
                }
                $ok = $this->transicion($actual, $estados, $cambio . ",estado_reajuste='pendiente_calculo',version_agenda=version_agenda+1", [$ahora]);
                if ($ok) {
                    if (!(new Auditoria($this->conn))->log(Contexto::idUsuario(), 'UPDATE', 'citas', $id, ['estado' => $actual['estado']], ['sello' => $ahora], 'Disparo de cascada por atención (RE-4.5.1)', $this->clinica())) {
                        throw new RuntimeException('No se pudo auditar la atención.');
                    }
                    $cascada->procesar($actual['fecha']);
                }
                return $ok;
            });
        } catch (AccesoDenegado $error) {
            $this->denegarAcceso('citas', $id, 'Atención denegada tras rollback');
        }
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
    public function cancelar(array $cita, ?string $clave = null): bool
    {
        $id = (int) $cita['id_cita'];
        try {
            return (new CoordinadorAgenda($this->conn))->ejecutar($this->clinica(), function () use ($id): array {
                $actual = $this->getById($id);
                return ['usuarios' => [(int) $actual['id_veterinario']], 'mascotas' => [(int) $actual['id_mascota']]];
            }, function () use ($id, $clave): bool {
                $actual = $this->getById($id);
                $this->exigirActorVigente((int) $actual['id_mascota']);
                if (Contexto::rolActivo() === Roles::VETERINARIO && (int) $actual['id_veterinario'] !== Contexto::idUsuario()) {
                    throw new AccesoDenegado(403, 'Solo puedes cancelar tus citas.');
                }
                $nueva = false;
                $this->conMismoAlcance(new OperacionAgenda($this->conn))->ejecutar('cancelar_cita', $clave, [$id], function () use ($actual, $id, &$nueva): array {
                    if (!in_array($actual['estado'], self::ESTADOS_ABIERTOS, true)) {
                        throw new InvalidArgumentException('Solo se cancelan citas pendientes o confirmadas.');
                    }
                    $ahora = $this->reloj ? ($this->reloj)() : new DateTimeImmutable('now', new DateTimeZone('America/Bogota'));
                    if (Contexto::rolActivo() === Roles::PROPIETARIO && ReglaAtencion::inicio($actual['fecha'], $actual['hora']) <= $ahora) {
                        throw new InvalidArgumentException('Solo puedes cancelar una cita futura.');
                    }
                    (new EventoAgenda($this->conn, $this->clinica()))->exigirSinDespacho($id);
                    if (!$this->transicion($actual, self::ESTADOS_ABIERTOS, "estado='cancelada',version_agenda=version_agenda+1", [])) {
                        throw new InvalidArgumentException('La cita cambió. Recarga la agenda.');
                    }
                    if (!(new Auditoria($this->conn))->log(Contexto::idUsuario(), 'UPDATE', 'citas', $id, ['estado' => $actual['estado']], ['estado' => 'cancelada'], 'Cancelación coordinada (RE-5.6.4)', $this->clinica())) {
                        throw new RuntimeException('No se pudo auditar la cancelación.');
                    }
                    $this->avisarCambio($id, 'CITA_CANCELADA');
                    $nueva = true;
                    return ['id_cita' => $id];
                });
                return $nueva;
            });
        } catch (AccesoDenegado $error) {
            $this->denegarAcceso('citas', $id, 'Cancelación denegada tras rollback (RE-T.15.2)');
        }
    }

    private function avisarCambio(int $id, string $tipo): void
    {
        $cita = $this->getById($id);
        (new NotificacionInterna($this->conn))->expirarDeCita($id);
        $eventos = new EventoAgenda($this->conn, $this->clinica());
        if ((int) $cita['id_veterinario'] !== Contexto::idUsuario()) {
            $eventos->interno($tipo, $id, null, (int) $cita['version_agenda'], (int) $cita['id_veterinario'], null);
        }
        if (Contexto::rolActivo() !== Roles::ADMIN && ($tipo === 'CITA_CANCELADA' || Contexto::rolActivo() === Roles::PROPIETARIO)) {
            $eventos->interno($tipo, $id, null, (int) $cita['version_agenda'], null, Roles::ADMIN);
        }
        if ($cita['id_propietario'] && Contexto::rolActivo() !== Roles::PROPIETARIO) {
            $eventos->correo($tipo, $id, (int) $cita['version_agenda'], (int) $cita['id_propietario']);
        }
    }

    public function reordenarSobrecupo(int $id, int $posicion, string $motivo, ?string $clave): array
    {
        $cita = $this->getById($id);
        if (Contexto::rolActivo() !== Roles::VETERINARIO || (int) $cita['id_veterinario'] !== Contexto::idUsuario()) {
            $this->denegarAcceso('citas', $id, 'Reordenamiento solo del veterinario de la cita (RN-422)');
        }
        $motivo = trim($motivo);
        if ($motivo === '' || mb_strlen($motivo) > 255 || $posicion < 1 || !$cita['id_bloque']) {
            throw new InvalidArgumentException('Indica una posición y un motivo de 1 a 255 caracteres.');
        }
        $leer = function () use ($cita): array {
            $q = $this->conn->prepare("SELECT id_cita,id_mascota FROM citas WHERE id_clinica=? AND id_bloque=? AND prioridad='naranja' AND estado IN ('pendiente','confirmada') ORDER BY COALESCE(orden_sobrecupo,id_cita),fecha_registro,id_cita");
            $q->execute([$this->clinica(), $cita['id_bloque']]);
            return $q->fetchAll(PDO::FETCH_ASSOC);
        };
        return (new CoordinadorAgenda($this->conn))->ejecutar($this->clinica(), fn () => [
            'usuarios' => [(int) $cita['id_veterinario']], 'mascotas' => array_column($leer(), 'id_mascota')
        ], function () use ($id, $posicion, $motivo, $clave, $leer): array {
            $actual = $this->getById($id);
            $this->exigirActorVigente((int) $actual['id_mascota']);
            if ((int) $actual['id_veterinario'] !== Contexto::idUsuario()) {
                throw new AccesoDenegado(403, 'La asignación cambió.');
            }
            return $this->conMismoAlcance(new OperacionAgenda($this->conn))->ejecutar('reordenar_naranja', $clave, [$id, $posicion, $motivo], function () use ($id, $posicion, $motivo, $leer): array {
                $antes = array_map('intval', array_column($leer(), 'id_cita'));
                if (!in_array($id, $antes, true) || $posicion > count($antes)) {
                    throw new InvalidArgumentException('La cola cambió. Vuelve a consultar.');
                }
                $orden = array_values(array_diff($antes, [$id]));
                array_splice($orden, $posicion - 1, 0, [$id]);
                foreach ($orden as $indice => $idCita) {
                    (new EventoAgenda($this->conn, $this->clinica()))->exigirSinDespacho($idCita);
                    $this->conn->prepare('UPDATE citas SET orden_sobrecupo=?,version_agenda=version_agenda+1 WHERE id_cita=? AND id_clinica=?')->execute([$indice + 1, $idCita, $this->clinica()]);
                    // Solo cambió el orden: misma causa, hora y destinatario pendientes.
                    $version = (int) $this->getById($idCita)['version_agenda'];
                    $this->conn->prepare("UPDATE eventos_agenda SET version_recurso=? WHERE id_clinica=? AND id_cita=? AND canal='correo' AND estado IN ('pendiente','error') AND tipo IN ('NUEVA_CITA','CITA_REPROGRAMADA')")->execute([$version, $this->clinica(), $idCita]);
                }
                if (!(new Auditoria($this->conn))->log(Contexto::idUsuario(), 'UPDATE', 'citas', $id, ['orden' => $antes], ['orden' => $orden, 'motivo' => $motivo], 'Reordenamiento naranja motivado (RN-422)', $this->clinica())) {
                    throw new RuntimeException('No se pudo auditar el orden.');
                }
                return ['orden' => $orden, 'message' => 'Orden actualizado.'];
            });
        });
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

    /** RE-4.9.1: la reserva ordinaria exige un tipo activo; nunca acepta tiempos del cliente. */
    private function tiemposDelTipo($idTipoCita): array
    {
        $tipo = $this->catalogo()->tipoCita((int) $idTipoCita);
        if ($tipo === null || (int) $tipo['activo'] !== 1 || (int) $tipo['duracion_minutos'] <= 0 || (int) $tipo['margen_minutos'] < 0) {
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
        return IntervalosAgenda::fecha('2000-01-01 ' . $hora)->modify('+' . $duracion . ' minutes')->format('H:i:s');
    }

}
