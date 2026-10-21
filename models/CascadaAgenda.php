<?php
require_once __DIR__ . '/ModeloClinica.php';
require_once __DIR__ . '/DisponibilidadAgenda.php';
require_once __DIR__ . '/OperacionAgenda.php';
require_once __DIR__ . '/EventoAgenda.php';
require_once __DIR__ . '/../helpers/CoordinadorAgenda.php';
require_once __DIR__ . '/../helpers/GrafoAgenda.php';

/** C5: proyección y reasignación en vivo; la cobertura por ausencia pertenece a C6. */
final class CascadaAgenda extends ModeloClinica
{
    private Closure $reloj;
    private ?int $clinicaSistema = null;
    public ?GrafoAgenda $grafo = null;

    public function __construct(PDO $db, ?callable $reloj = null)
    {
        parent::__construct($db);
        $this->reloj = Closure::fromCallable($reloj ?? static fn () => new DateTimeImmutable('now', new DateTimeZone('America/Bogota')));
    }

    protected function clinica(): int
    {
        return $this->clinicaSistema ?? parent::clinica();
    }

    private function filas(string $sql, array $parametros = []): array
    {
        $q = $this->conn->prepare($sql);
        $q->execute($parametros);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    private function autorizar(bool $destino = false): void
    {
        if ($this->clinicaSistema !== null) {
            return;
        }
        $rol = Contexto::rolActivo();
        $permitidos = $destino ? [Roles::VETERINARIO] : [Roles::ADMIN, Roles::VETERINARIO];
        $vigente = $this->filas("SELECT 1 FROM usuario_clinica uc JOIN usuarios u ON u.id_usuario=uc.id_usuario JOIN clinicas c ON c.id_clinica=uc.id_clinica WHERE uc.id_clinica=? AND uc.id_usuario=? AND uc.id_rol=? AND uc.estado='activo' AND u.estado=1 AND c.estado='activa'", [$this->clinica(), Contexto::idUsuario(), $rol]);
        if (!in_array($rol, $permitidos, true) || !$vigente) {
            $this->denegarAcceso('reasignaciones', null, 'Contexto de reasignación no autorizado (RE-4.14.4)');
        }
    }

    /** Excepción global mínima del trabajador: descubre clínica/fecha, nunca expone datos al cliente. */
    public static function procesarPendientes(PDO $db, ?callable $reloj = null): int
    {
        $modelo = new self($db, $reloj);
        $hoy = ($modelo->reloj)()->format('Y-m-d');
        $q = $db->prepare("SELECT DISTINCT c.id_clinica,c.fecha FROM citas c JOIN clinicas cl ON cl.id_clinica=c.id_clinica AND cl.estado='activa' WHERE c.estado_reajuste='pendiente_calculo' OR c.estado IN ('en_curso','pausada','sin_cerrar') OR (c.fecha=? AND c.estado IN ('pendiente','confirmada')) OR EXISTS (SELECT 1 FROM reasignaciones r WHERE r.id_cita=c.id_cita AND r.id_clinica=c.id_clinica AND r.estado='pendiente')");
        // C5.1: el reloj solo mueve las citas de hoy; otras fechas llegan por sus marcas pendiente_calculo.
        // Recorrer todas las fechas futuras en cada pasada del vigilante tomaba candados de toda la clínica sin necesidad.
        $q->execute([$hoy]);
        $fechas = $q->fetchAll(PDO::FETCH_ASSOC);
        $procesadas = 0;
        foreach ($fechas as $fecha) {
            $modelo->clinicaSistema = (int) $fecha['id_clinica'];
            $modelo->procesar($fecha['fecha']);
            $procesadas++;
        }
        return $procesadas;
    }

    public function recursos(string $fecha): array
    {
        $citas = $this->filas("SELECT id_veterinario,id_mascota FROM citas WHERE id_clinica=? AND (fecha=? OR estado IN ('en_curso','pausada','sin_cerrar'))", [$this->clinica(), $fecha]);
        $vets = $this->filas('SELECT id_usuario FROM usuario_clinica WHERE id_clinica=? AND id_rol=2', [$this->clinica()]);
        $destinos = $this->filas('SELECT r.id_veterinario_destino FROM reasignaciones r JOIN citas c ON c.id_cita=r.id_cita AND c.id_clinica=r.id_clinica WHERE r.id_clinica=? AND c.fecha=?', [$this->clinica(), $fecha]);
        return ['usuarios' => [...array_column($vets, 'id_usuario'), ...array_column($citas, 'id_veterinario'), ...array_column($destinos, 'id_veterinario_destino')], 'mascotas' => array_column($citas, 'id_mascota')];
    }

    public function recursosDeCita(int $id): array
    {
        return $this->recursos($this->cita($id)['fecha']);
    }

    public function exigirContexto(): void
    {
        $this->autorizar();
    }

    private function disponibilidad(): DisponibilidadAgenda
    {
        return DisponibilidadAgenda::paraProcesador($this->conn, $this->clinica(), $this->reloj);
    }

    /** Cierre confirmado: si hay una transacción exterior, la marca queda para el vigilante. */
    public static function despuesDeAtencion(PDO $db, int $clinica, int $cita, string $ahora): void
    {
        if ($db->inTransaction()) {
            return;
        }
        $modelo = new self($db, static fn () => IntervalosAgenda::fecha($ahora));
        $modelo->clinicaSistema = $clinica;
        try {
            $fila = $modelo->cita($cita);
            $modelo->procesar($fila['fecha']);
        } catch (Throwable $error) {
            // El acto ya está confirmado; no simular su fracaso ni perder el disparo durable.
            error_log('Cascada pendiente tras atención #' . $cita . ': ' . $error->getMessage());
        }
    }

    public function procesar(string $fecha): array
    {
        IntervalosAgenda::fecha($fecha . ' 00:00:00');
        $this->autorizar();
        try {
            return (new CoordinadorAgenda($this->conn))->ejecutar($this->clinica(), fn () => $this->recursos($fecha), function () use ($fecha): array {
                $this->autorizar();
                $citas = $this->filas('SELECT c.*,m.id_propietario FROM citas c JOIN mascotas m ON m.id_mascota=c.id_mascota WHERE c.id_clinica=? AND c.fecha=? ORDER BY c.id_cita', [$this->clinica(), $fecha]);
                foreach ($citas as &$cita) {
                    $acuerdos = $this->filas("SELECT inicio_propuesto,fin_propuesto FROM reasignaciones WHERE id_clinica=? AND id_cita=? AND id_veterinario_destino=? AND estado='aceptada' ORDER BY id_reasignacion DESC LIMIT 1", [$this->clinica(), $cita['id_cita'], $cita['id_veterinario']]);
                    $acuerdo = $acuerdos[0] ?? null;
                    if ($acuerdo && $cita['inicio_estimado'] === $acuerdo['inicio_propuesto'] && $cita['fin_estimado'] !== null && IntervalosAgenda::fecha($cita['fin_estimado'])->getTimestamp() + (int) $cita['margen_minutos'] * 60 === IntervalosAgenda::fecha($acuerdo['fin_propuesto'])->getTimestamp()) {
                        $cita['no_antes_de'] = IntervalosAgenda::fecha($acuerdo['inicio_propuesto'])->getTimestamp();
                    }
                }
                unset($cita);
                $pausas = [];
                foreach ($this->filas('SELECT p.* FROM pausas_cita p JOIN citas c ON c.id_cita=p.id_cita AND c.id_clinica=p.id_clinica WHERE p.id_clinica=? AND c.fecha=? ORDER BY p.inicio,p.id_pausa', [$this->clinica(), $fecha]) as $p) {
                    $pausas[$p['id_cita']][] = $p;
                }
                $this->grafo = new GrafoAgenda();
                $estimaciones = $this->grafo->calcular($citas, $pausas, ($this->reloj)());
                foreach ($citas as $cita) {
                    $id = (int) $cita['id_cita'];
                    if (!isset($estimaciones[$id])) {
                        $this->retirarPropuestas($id, 'La cita ya no admite una propuesta');
                        continue;
                    }
                    if (str_starts_with((string) $cita['motivo_reajuste'], 'Ausencia efectiva:')) {
                        continue;
                    }
                    $e = $estimaciones[$id];
                    $estado = 'ninguno';
                    $motivo = null;
                    if (isset($e['revision'])) {
                        $estado = 'pendiente_revision';
                        $motivo = $e['revision'];
                    } elseif (in_array($cita['estado'], ['pendiente', 'confirmada'], true)) {
                        $secuencia = array_column(array_filter($citas, static fn ($otra) => $otra['id_veterinario'] === $cita['id_veterinario']), 'id_cita');
                        try {
                            $libres = $this->disponibilidad()->franjasParaCascada((int) $cita['id_veterinario'], (int) $cita['id_mascota'], $fecha, $secuencia);
                            $cabe = IntervalosAgenda::contiene($libres, $e['inicio'], $e['extremo']);
                        } catch (HorarioOcupado $error) {
                            $cabe = false;
                        }
                        if (!$cabe) {
                            $this->grafo->conflictos[] = [$id, 'capacidad'];
                            $estado = $cita['estado_reajuste'] === 'pendiente_reprogramacion' ? 'pendiente_reprogramacion' : 'pendiente_reasignacion';
                            $motivo = 'La estimación no cabe en una franja compatible';
                        }
                    }
                    $inicio = isset($e['inicio']) ? PoliticaTemporalAgenda::sello($e['inicio']) : null;
                    $fin = isset($e['fin']) ? PoliticaTemporalAgenda::sello($e['fin']) : null;
                    $cambio = $cita['inicio_estimado'] !== $inicio || $cita['fin_estimado'] !== $fin || $cita['estado_reajuste'] !== $estado || $cita['motivo_reajuste'] !== $motivo;
                    if ($cambio) {
                        // C5.1: una estimación que solo se corre con el reloj no es un cambio de la cita.
                        // Subir la versión en cada tick vencía y reemitía la propuesta de reasignación cada minuto.
                        $nuevaVersion = $cita['estado_reajuste'] !== $estado ? 1 : 0;
                        $this->conn->prepare('UPDATE citas SET inicio_estimado=?,fin_estimado=?,estado_reajuste=?,motivo_reajuste=?,fecha_ultimo_calculo=?,version_agenda=version_agenda+? WHERE id_cita=? AND id_clinica=?')->execute([$inicio, $fin, $estado, $motivo, ($this->reloj)()->format('Y-m-d H:i:s'), $nuevaVersion, $id, $this->clinica()]);
                        if ($nuevaVersion === 1) {
                            $this->conn->prepare("UPDATE eventos_agenda SET version_recurso=version_recurso+1 WHERE id_clinica=? AND id_cita=? AND version_recurso=? AND canal='correo' AND estado IN ('pendiente','error') AND tipo IN ('NUEVA_CITA','CITA_REPROGRAMADA','CITA_REASIGNADA')")->execute([$this->clinica(), $id, $cita['version_agenda']]);
                        }
                        $this->auditar('citas', $id, ['inicio_estimado' => $cita['inicio_estimado'], 'fin_estimado' => $cita['fin_estimado']], ['inicio_estimado' => $inicio, 'fin_estimado' => $fin, 'estado_reajuste' => $estado], 'Cascada política M (RE-4.14.1/2)');
                    }
                }
                // Todas las estimaciones se publican dentro de la unidad antes de emparejar candidatos.
                foreach ($citas as $original) {
                    $cita = $this->cita((int) $original['id_cita']);
                    if (in_array($cita['estado_reajuste'], ['pendiente_reasignacion', 'pendiente_reprogramacion'], true) && in_array($cita['estado'], ['pendiente', 'confirmada'], true)) {
                        if (str_starts_with((string) $cita['motivo_reajuste'], 'Ausencia efectiva:')) {
                            $this->cubrirAusencia((int) $cita['id_cita']);
                        } else {
                            $this->proponer($cita);
                        }
                    } else {
                        $this->retirarPropuestas((int) $cita['id_cita'], 'La propuesta dejó de ser necesaria');
                    }
                    if (in_array($cita['estado'], ['pendiente', 'confirmada'], true) && $cita['inicio_estimado'] !== null) {
                        (new EventoAgenda($this->conn, $this->clinica(), $this->reloj))->estimacion($cita);
                    }
                }
                return ['calculadas' => count($estimaciones)];
            });
        } catch (AccesoDenegado $error) {
            $this->denegarAcceso('reasignaciones', null, 'Procesamiento denegado tras rollback');
        }
    }

    private function cita(int $id): array
    {
        $filas = $this->filas('SELECT c.*,m.id_propietario FROM citas c JOIN mascotas m ON m.id_mascota=c.id_mascota WHERE c.id_cita=? AND c.id_clinica=?', [$id, $this->clinica()]);
        if (!$filas) {
            $this->denegarAcceso('citas', $id, 'Cita de otra clínica');
        }
        return $filas[0];
    }

    private function retirarPropuestas(int $cita, string $motivo): void
    {
        foreach ($this->filas("SELECT * FROM reasignaciones WHERE id_clinica=? AND id_cita=? AND estado='pendiente'", [$this->clinica(), $cita]) as $r) {
            $this->resolverFila($r, 'vencida', $motivo);
        }
    }

    private function resolverFila(array $r, string $estado, string $motivo): void
    {
        $this->conn->prepare("UPDATE reasignaciones SET estado=?,fecha_respuesta=?,motivo=? WHERE id_reasignacion=? AND id_clinica=? AND estado='pendiente'")->execute([$estado, ($this->reloj)()->format('Y-m-d H:i:s'), $motivo, $r['id_reasignacion'], $this->clinica()]);
        $this->auditar('reasignaciones', (int) $r['id_reasignacion'], ['estado' => 'pendiente'], ['estado' => $estado, 'motivo' => $motivo], 'Resolución en vivo (RN-428)');
    }

    private function candidatos(array $cita, array $intentados): array
    {
        $opciones = [];
        $duracion = (int) $cita['duracion_activa_objetivo_segundos'];
        if ($duracion <= 0) {
            $duracion = (int) $cita['duracion_minutos'] * 60;
        }
        $necesarios = $duracion + (int) $cita['margen_minutos'] * 60;
        $minimo = max(IntervalosAgenda::fecha($cita['inicio_estimado'])->getTimestamp(), ($this->reloj)()->getTimestamp());
        foreach ($this->filas("SELECT uc.id_usuario FROM usuario_clinica uc JOIN usuarios u ON u.id_usuario=uc.id_usuario WHERE uc.id_clinica=? AND uc.id_rol=2 AND uc.estado='activo' AND u.estado=1 ORDER BY uc.id_usuario", [$this->clinica()]) as $vet) {
            $id = (int) $vet['id_usuario'];
            if ($id === (int) $cita['id_veterinario'] || in_array($id, $intentados, true)) {
                continue;
            }
            // El índice histórico de comienzo sigue siendo una defensa adicional.
            if ($this->filas("SELECT 1 FROM citas WHERE id_veterinario=? AND fecha=? AND hora=? AND estado NOT IN ('cancelada','no_asistio') AND es_sobrecupo=0 AND origen<>'urgencia' AND id_cita<>?", [$id, $cita['fecha'], $cita['hora'], $cita['id_cita']])) {
                continue;
            }
            try {
                $libres = $this->disponibilidad()->franjasParaCascada($id, (int) $cita['id_mascota'], $cita['fecha'], [(int) $cita['id_cita']]);
                foreach ($libres as [$desde, $hasta]) {
                    $inicio = max($desde, $minimo);
                    if ($inicio + $necesarios <= $hasta && substr(PoliticaTemporalAgenda::sello($inicio + $necesarios), 0, 10) === $cita['fecha']) {
                        $opciones[] = ['vet' => $id, 'inicio' => $inicio, 'fin' => $inicio + $necesarios];
                        break;
                    }
                }
            } catch (HorarioOcupado $error) {
                continue;
            }
        }
        usort($opciones, static fn ($a, $b) => [$a['inicio'], $a['vet']] <=> [$b['inicio'], $b['vet']]);
        return $opciones;
    }

    private function proponer(array $cita): void
    {
        $historial = $this->filas('SELECT * FROM reasignaciones WHERE id_clinica=? AND id_cita=? ORDER BY id_reasignacion', [$this->clinica(), $cita['id_cita']]);
        $ciclo = 1;
        $intentados = [];
        foreach ($historial as $r) {
            $ciclo = max($ciclo, (int) $r['ciclo']);
            if ((int) $r['version_cita'] === (int) $cita['version_agenda']) {
                if ($r['estado'] === 'pendiente' && $r['fecha_limite'] > ($this->reloj)()->format('Y-m-d H:i:s')) {
                    if ($this->grafo !== null) {
                        $this->grafo->emparejamientos[] = [(int) $cita['id_cita'], (int) $r['id_veterinario_destino'], (int) $r['id_reasignacion']];
                    }
                    return;
                }
                if ($r['estado'] === 'pendiente') {
                    $this->resolverFila($r, 'vencida', 'Venció el plazo de aceptación');
                }
                $intentados[] = (int) $r['id_veterinario_destino'];
            } elseif ($r['estado'] === 'pendiente') {
                $this->resolverFila($r, 'vencida', 'La cita cambió de versión');
            }
        }
        if ($historial && !$intentados) {
            $ciclo++;
        }
        $opciones = $this->candidatos($cita, $intentados);
        if (!$opciones) {
            if ($cita['estado_reajuste'] !== 'pendiente_reprogramacion') {
                $this->conn->prepare("UPDATE citas SET estado_reajuste='pendiente_reprogramacion' WHERE id_cita=? AND id_clinica=?")->execute([$cita['id_cita'], $this->clinica()]);
                $this->auditar('citas', (int) $cita['id_cita'], null, ['estado_reajuste' => 'pendiente_reprogramacion'], 'Sin candidato; ofrecer reprogramación al propietario (RE-4.14.5)');
                $this->avisar($cita, 'REPROGRAMAR_NECESARIO');
            }
            return;
        }
        $mejor = $opciones[0];
        if ($cita['estado_reajuste'] === 'pendiente_reprogramacion') {
            $this->conn->prepare("UPDATE citas SET estado_reajuste='pendiente_reasignacion' WHERE id_cita=? AND id_clinica=?")->execute([$cita['id_cita'], $this->clinica()]);
            $this->auditar('citas', (int) $cita['id_cita'], ['estado_reajuste' => 'pendiente_reprogramacion'], ['estado_reajuste' => 'pendiente_reasignacion'], 'Nueva alternativa compatible para reasignación');
        }
        $plazo = (int) $this->filas('SELECT plazo_reasignacion_min FROM clinicas WHERE id_clinica=?', [$this->clinica()])[0]['plazo_reasignacion_min'];
        $limite = ($this->reloj)()->modify('+' . $plazo . ' minutes')->format('Y-m-d H:i:s');
        $q = $this->conn->prepare('INSERT INTO reasignaciones (id_clinica,id_cita,id_veterinario_origen,id_veterinario_destino,fecha,fecha_limite,inicio_propuesto,fin_propuesto,version_cita,ciclo,motivo) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $q->execute([$this->clinica(), $cita['id_cita'], $cita['id_veterinario'], $mejor['vet'], ($this->reloj)()->format('Y-m-d H:i:s'), $limite, PoliticaTemporalAgenda::sello($mejor['inicio']), PoliticaTemporalAgenda::sello($mejor['fin']), $cita['version_agenda'], $ciclo, 'Desborde del horario compatible']);
        $id = (int) $this->conn->lastInsertId();
        if ($this->grafo !== null) {
            $this->grafo->emparejamientos[] = [(int) $cita['id_cita'], $mejor['vet'], $id];
        }
        $this->auditar('reasignaciones', $id, null, ['destino' => $mejor['vet'], 'inicio' => PoliticaTemporalAgenda::sello($mejor['inicio']), 'ciclo' => $ciclo], 'Propuesta en vivo, sin apartar hueco (RN-413)');
        // La clave contiene el ID durable de propuesta, no solo la versión de la cita.
        (new EventoAgenda($this->conn, $this->clinica()))->interno('PROPUESTA_REASIGNACION_' . $id, (int) $cita['id_cita'], null, (int) $cita['version_agenda'], $mejor['vet'], null);
    }

    public function listar(): array
    {
        $this->autorizar(true);
        return $this->filas("SELECT r.id_reasignacion,r.id_cita,r.inicio_propuesto,r.fin_propuesto,r.fecha_limite,m.nombre AS mascota FROM reasignaciones r JOIN citas c ON c.id_cita=r.id_cita AND c.id_clinica=r.id_clinica JOIN mascotas m ON m.id_mascota=c.id_mascota WHERE r.id_clinica=? AND r.id_veterinario_destino=? AND r.estado='pendiente' AND r.fecha_limite>? AND r.version_cita=c.version_agenda AND c.estado IN ('pendiente','confirmada') ORDER BY r.fecha,r.id_reasignacion", [$this->clinica(), Contexto::idUsuario(), ($this->reloj)()->format('Y-m-d H:i:s')]);
    }

    public function responder(int $id, string $respuesta, ?string $clave): array
    {
        $this->autorizar(true);
        if (!in_array($respuesta, ['aceptar', 'rechazar'], true)) {
            throw new InvalidArgumentException('Selecciona aceptar o rechazar.');
        }
        $leer = fn () => $this->filas('SELECT r.* FROM reasignaciones r WHERE r.id_reasignacion=? AND r.id_clinica=?', [$id, $this->clinica()]);
        $r = $leer()[0] ?? null;
        if (!$r || (int) $r['id_veterinario_destino'] !== Contexto::idUsuario()) {
            $this->denegarAcceso('reasignaciones', $id, 'Solo el destino responde la propuesta');
        }
        try {
            return (new CoordinadorAgenda($this->conn))->ejecutar($this->clinica(), fn () => $this->recursosDeCita((int) $r['id_cita']), function () use ($id, $respuesta, $clave, $leer): array {
                $this->autorizar(true);
                $r = $leer()[0];
                if ((int) $r['id_veterinario_destino'] !== Contexto::idUsuario()) {
                    throw new AccesoDenegado(403, 'No tienes acceso a esta propuesta.');
                }
                return (new OperacionAgenda($this->conn))->ejecutar('responder_reasignacion', $clave, [$id, $respuesta], function () use ($r, $respuesta): array {
                    $cita = $this->cita((int) $r['id_cita']);
                    if ($r['estado'] !== 'pendiente') {
                        throw new InvalidArgumentException('La propuesta ya fue resuelta.');
                    }
                    if ($r['fecha_limite'] <= ($this->reloj)()->format('Y-m-d H:i:s') || (int) $r['version_cita'] !== (int) $cita['version_agenda'] || !in_array($cita['estado'], ['pendiente', 'confirmada'], true)) {
                        $this->resolverFila($r, 'vencida', 'Venció o cambió el contexto de la propuesta');
                        if (in_array($cita['estado'], ['pendiente', 'confirmada'], true) && in_array($cita['estado_reajuste'], ['pendiente_reasignacion', 'pendiente_reprogramacion'], true)) {
                            $this->proponer($cita);
                        }
                        return ['success' => false, 'message' => 'La propuesta venció o cambió. No se trasladó la cita.'];
                    }
                    if ($respuesta === 'rechazar') {
                        $this->resolverFila($r, 'rechazada', 'Rechazada por el veterinario destino');
                        $this->proponer($cita);
                        return ['message' => 'Propuesta rechazada. Se buscó la siguiente alternativa.'];
                    }
                    $inicio = IntervalosAgenda::fecha($r['inicio_propuesto'])->getTimestamp();
                    $fin = IntervalosAgenda::fecha($r['fin_propuesto'])->getTimestamp();
                    // C5.1: si el destino acepta minutos después, el hueco se corre a «ahora» y se revalida completo.
                    $corrimiento = max(0, ($this->reloj)()->getTimestamp() - $inicio);
                    $inicio += $corrimiento;
                    $fin += $corrimiento;
                    $r['inicio_propuesto'] = PoliticaTemporalAgenda::sello($inicio);
                    try {
                        $libres = $this->disponibilidad()->franjasParaCascada((int) $r['id_veterinario_destino'], (int) $cita['id_mascota'], $cita['fecha'], [(int) $cita['id_cita']]);
                        $cabe = $inicio >= ($this->reloj)()->getTimestamp() && IntervalosAgenda::contiene($libres, $inicio, $fin);
                        $mismoComienzo = $this->filas("SELECT 1 FROM citas WHERE id_veterinario=? AND fecha=? AND hora=? AND estado NOT IN ('cancelada','no_asistio') AND es_sobrecupo=0 AND origen<>'urgencia' AND id_cita<>?", [$r['id_veterinario_destino'], $cita['fecha'], $cita['hora'], $cita['id_cita']]);
                        $cabe = $cabe && !$mismoComienzo;
                    } catch (HorarioOcupado $error) {
                        $cabe = false;
                    }
                    if (!$cabe) {
                        $this->resolverFila($r, 'vencida', 'El hueco ya no está disponible');
                        $this->proponer($cita);
                        return ['success' => false, 'message' => 'El hueco dejó de estar disponible. La cita original se conserva.'];
                    }
                    if (!$this->trasladar($cita, (int) $r['id_veterinario_destino'], $inicio, $fin)) {
                        $this->resolverFila($r, 'vencida', 'El cupo naranja ya no está disponible');
                        $this->proponer($cita);
                        return ['success' => false, 'message' => 'El cupo cambió. La cita original se conserva.'];
                    }
                    $this->resolverFila($r, 'aceptada', 'Disponibilidad revalidada y aceptada por el destino');
                    $actual = $this->cita((int) $cita['id_cita']);
                    $this->auditar('citas', (int) $cita['id_cita'], ['id_veterinario' => $cita['id_veterinario']], ['id_veterinario' => $actual['id_veterinario']], 'Reasignación en vivo aceptada (RE-4.14.4)');
                    $this->avisar($actual, 'CITA_REASIGNADA', (int) $cita['id_veterinario']);
                    return ['message' => 'Reasignación aceptada.', 'id_cita' => (int) $cita['id_cita']];
                });
            });
        } catch (AccesoDenegado $error) {
            $this->denegarAcceso('reasignaciones', $id, 'Respuesta denegada tras rollback');
        }
    }

    /** Traslado común; snapshots y pactadas permanecen intactos (RN-414/428). */
    private function trasladar(array $cita, int $destino, int $inicio, int $fin): bool
    {
        $eventos = new EventoAgenda($this->conn, $this->clinica());
        $eventos->exigirSinDespacho((int) $cita['id_cita']);
        $bloque = null;
        if ($cita['prioridad'] === 'naranja') {
            require_once __DIR__ . '/SolicitudAgenda.php';
            $bloque = (new SolicitudAgenda($this->conn, $this->reloj))->bloqueParaCambio(['id_veterinario' => (int) $destino, 'id_mascota' => (int) $cita['id_mascota'], 'fecha' => $cita['fecha']], substr(PoliticaTemporalAgenda::sello($inicio), 11), (int) $cita['id_cita']);
            if ($bloque === null) {
                return false;
            }
        }
        $orden = null;
        if ($bloque !== null) {
            $orden = (int) $this->filas('SELECT COALESCE(MAX(orden_sobrecupo),0)+1 AS siguiente FROM citas WHERE id_clinica=? AND id_bloque=?', [$this->clinica(), $bloque])[0]['siguiente'];
        }
        $finClinico = $fin - (int) $cita['margen_minutos'] * 60;
        $this->conn->prepare("UPDATE citas SET id_veterinario=?,id_bloque=?,orden_sobrecupo=?,es_sobrecupo=0,inicio_estimado=?,fin_estimado=?,estado_reajuste='ninguno',motivo_reajuste=NULL,version_agenda=version_agenda+1 WHERE id_cita=? AND id_clinica=?")->execute([$destino, $bloque, $orden, PoliticaTemporalAgenda::sello($inicio), PoliticaTemporalAgenda::sello($finClinico), $cita['id_cita'], $this->clinica()]);
        return true;
    }

    /** Solo dentro de la unidad que hace efectiva la ausencia; no hay propuesta ni veto. */
    public function cubrirAusencia(int $id): void
    {
        $this->autorizar();
        if (!$this->conn->inTransaction()) {
            throw new LogicException('La cobertura exige la unidad coordinada de ausencia.');
        }
        $cita = $this->cita($id);
        $this->retirarPropuestas($id, 'Cobertura efectiva por ausencia, sin veto');
        $demostrable = (int)$cita['duracion_minutos'] > 0 && $cita['margen_minutos'] !== null;
        if ($demostrable && in_array($cita['estado'], ['pendiente', 'confirmada'], true)) {
            $cita['inicio_estimado'] ??= $cita['fecha'] . ' ' . PoliticaTemporalAgenda::hora($cita['hora']);
            foreach ($this->candidatos($cita, []) as $opcion) {
                if (!$this->trasladar($cita, $opcion['vet'], $opcion['inicio'], $opcion['fin'])) {
                    continue;
                }
                $ahora = ($this->reloj)()->format('Y-m-d H:i:s');
                $actual = $this->cita($id);
                // Acuerdo efectivo en el historial existente: conserva el ancla temporal en recálculos.
                $this->conn->prepare("INSERT INTO reasignaciones (id_clinica,id_cita,id_veterinario_origen,id_veterinario_destino,fecha,fecha_limite,inicio_propuesto,fin_propuesto,version_cita,ciclo,estado,fecha_respuesta,motivo) VALUES (?,?,?,?,?,?,?,?,?,1,'aceptada',?,?)")->execute([$this->clinica(),$id,$cita['id_veterinario'],$opcion['vet'],$ahora,$ahora,PoliticaTemporalAgenda::sello($opcion['inicio']),PoliticaTemporalAgenda::sello($opcion['fin']),$actual['version_agenda'],$ahora,'Cobertura por ausencia; sin veto (RN-414)']);
                $this->auditar('citas', $id, ['id_veterinario' => $cita['id_veterinario']], ['id_veterinario' => $opcion['vet']], 'Cobertura efectiva sin veto (RE-4.12.3)');
                $this->avisar($actual, 'CITA_REASIGNADA', (int) $cita['id_veterinario']);
                return;
            }
        }
        $estado = $demostrable && in_array($cita['estado'], ['pendiente', 'confirmada'], true) ? 'pendiente_reprogramacion' : 'pendiente_revision';
        $motivo = 'Ausencia efectiva: requiere ' . ($estado === 'pendiente_revision' ? 'revisión clínica' : 'reprogramación con el propietario');
        if ($cita['estado_reajuste'] === $estado && $cita['motivo_reajuste'] === $motivo) {
            return;
        }
        (new EventoAgenda($this->conn, $this->clinica()))->exigirSinDespacho($id);
        $this->conn->prepare('UPDATE citas SET estado_reajuste=?,motivo_reajuste=?,version_agenda=version_agenda+1 WHERE id_cita=? AND id_clinica=?')->execute([$estado,$motivo,$id,$this->clinica()]);
        $this->auditar('citas', $id, null, ['estado_reajuste' => $estado], 'Ausencia sin cobertura (RE-4.12.4)');
        $this->avisar($this->cita($id), 'REPROGRAMAR_NECESARIO');
    }

    private function avisar(array $cita, string $tipo, ?int $origen = null): void
    {
        $eventos = new EventoAgenda($this->conn, $this->clinica());
        $id = (int) $cita['id_cita'];
        $version = (int) $cita['version_agenda'];
        foreach (array_unique(array_filter([$origen, (int) $cita['id_veterinario']])) as $vet) {
            $eventos->interno($tipo, $id, null, $version, $vet, null);
        }
        if ($cita['id_propietario']) {
            $eventos->correo($tipo, $id, $version, (int) $cita['id_propietario']);
        }
    }

    private function auditar(string $tabla, int $id, ?array $antes, array $despues, string $motivo): void
    {
        $actor = $this->clinicaSistema === null ? Contexto::idUsuario() : null;
        if (!(new Auditoria($this->conn))->log($actor, 'UPDATE', $tabla, $id, $antes, $despues, $motivo, $this->clinica())) {
            throw new RuntimeException('No se pudo auditar la cascada.');
        }
    }
}
