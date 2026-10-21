<?php
require_once __DIR__ . '/Cita.php';
require_once __DIR__ . '/GrafoConocimiento.php';
require_once __DIR__ . '/PacienteDiagnostico.php';
require_once __DIR__ . '/EventoAgenda.php';
require_once __DIR__ . '/Mascota.php';
require_once __DIR__ . '/../helpers/MotorTriage.php';
require_once __DIR__ . '/../helpers/ValidadorTriage.php';
require_once __DIR__ . '/../helpers/ValidadorContextoClinico.php';

/** C4: evaluación/revisión con huella vigente, sin confundir solicitud con presencia. */
final class SolicitudAgenda extends ModeloClinica
{
    private Closure $reloj;

    public function __construct(PDO $db, ?callable $reloj = null)
    {
        parent::__construct($db);
        $this->reloj = Closure::fromCallable($reloj ?? static fn () => new DateTimeImmutable('now', new DateTimeZone('America/Bogota')));
    }

    private function disponibilidad(): DisponibilidadAgenda
    {
        return $this->conMismoAlcance(new DisponibilidadAgenda($this->conn, $this->reloj));
    }

    private function autorizar(array $entrada, bool $reserva = false): void
    {
        if (!in_array(Contexto::rolActivo(), [Roles::VETERINARIO, Roles::PROPIETARIO], true)) {
            $this->denegarAcceso('solicitudes_agenda', null, 'Solo veterinario y propietario agendan (HU-4.13)');
        }
        $this->autorizarContexto((int) ($entrada['id_mascota'] ?? 0));
        if (!$reserva) {
            return;
        }
        $this->disponibilidad()->autorizar((int) ($entrada['id_veterinario'] ?? 0), (int) ($entrada['id_mascota'] ?? 0));
        if (Contexto::rolActivo() === Roles::VETERINARIO && (int) $entrada['id_veterinario'] !== Contexto::idUsuario()) {
            $this->denegarAcceso('solicitudes_agenda', null, 'Reserva de otro veterinario');
        }
    }

    private function autorizarContexto(int $mascota): void
    {
        $portal = Contexto::rolActivo() === Roles::PROPIETARIO;
        $sql = $portal
            ? "SELECT 1 FROM propietario_clinica WHERE id_clinica=? AND id_propietario=? AND estado='activo'"
            : "SELECT 1 FROM usuario_clinica WHERE id_clinica=? AND id_usuario=? AND estado='activo' AND id_rol=?";
        $parametros = [$this->clinica(), Contexto::idUsuario()];
        if (!$portal) $parametros[] = Contexto::rolActivo();
        $q = $this->conn->prepare($sql);
        $q->execute($parametros);
        $miembro = (bool) $q->fetchColumn();
        $q = $this->conn->prepare("SELECT 1 FROM usuarios u JOIN clinicas c ON c.id_clinica=? WHERE u.id_usuario=? AND u.estado=1 AND c.estado='activa'");
        $q->execute([$this->clinica(), Contexto::idUsuario()]);
        if (!$miembro || !$q->fetchColumn() || !in_array(Contexto::rolActivo(), [Roles::VETERINARIO, Roles::ADMIN, Roles::PROPIETARIO], true)) {
            $this->denegarAcceso('solicitudes_agenda', null, 'Contexto no vigente');
        }
        if ($mascota < 1) return;
        if ($portal) {
            $q = $this->conn->prepare('SELECT 1 FROM mascotas WHERE id_mascota=? AND id_propietario=? AND estado=1');
            $q->execute([$mascota, Contexto::idUsuario()]);
            if (!$q->fetchColumn()) $this->denegarAcceso('mascotas', $mascota, 'Solicitud de mascota ajena');
        } else {
            $this->exigirMascotaVinculada($mascota);
        }
    }

    private function unidad(array $entrada, callable $accion): mixed
    {
        try {
            return (new CoordinadorAgenda($this->conn))->ejecutar($this->clinica(), static fn () => [
                'usuarios' => [(int) $entrada['id_veterinario']], 'mascotas' => [(int) $entrada['id_mascota']]
            ], $accion);
        } catch (AccesoDenegado $error) {
            $this->denegarAcceso('solicitudes_agenda', $entrada['id_solicitud'] ?? null, 'Comando de agenda denegado tras rollback');
        } catch (LimiteAlcanzado $error) {
            if (!$this->conn->inTransaction()) {
                (new LimitePlan($this->conn, $this->clinica()))->registrarRechazo($error);
            }
            throw $error;
        }
    }

    public static function huella(array $datos): string
    {
        $normalizar = static function (array $valor) use (&$normalizar): array {
            foreach ($valor as &$parte) {
                if (is_array($parte)) {
                    $parte = $normalizar($parte);
                }
            }
            unset($parte);
            if (!array_is_list($valor)) {
                ksort($valor, SORT_STRING);
            }
            return $valor;
        };
        return hash('sha256', json_encode($normalizar($datos), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function calcular(array $entrada, ?string $fechaEvaluacion = null): array
    {
        $grafo = (new GrafoConocimiento($this->conn))->instantanea();
        $declarados = ValidadorContextoClinico::validar($entrada);
        $paciente = $this->conMismoAlcance(new PacienteDiagnostico($this->conn))->contexto((int) $entrada['id_mascota'], $grafo, ($this->reloj)(), $declarados);
        $sintomas = ValidadorTriage::episodios($entrada['sintomas'] ?? [], false, $grafo);
        $exposiciones = ValidadorTriage::episodios($entrada['exposiciones'] ?? [], true, $grafo);
        if (!$sintomas && !$exposiciones) {
            throw new InvalidArgumentException('Confirma una marca del catálogo u Otro / no sé. La nota no calcula.');
        }
        $ahora = ($this->reloj)();
        if ($fechaEvaluacion !== null) {
            $transcurridos = max(0, $ahora->getTimestamp() - IntervalosAgenda::fecha($fechaEvaluacion)->getTimestamp()) / 60;
            foreach ($sintomas as &$episodio) {
                if ($episodio['tiempo'] === 'conocido') $episodio['minutos'] += $transcurridos;
            }
            unset($episodio);
            foreach ($exposiciones as &$episodio) {
                if ($episodio['tiempo'] === 'conocido') $episodio['minutos'] += $transcurridos;
            }
            unset($episodio);
        }
        $resultado = MotorTriage::calcular($sintomas, $exposiciones, $paciente, $grafo, $ahora);
        $contexto = ['entrada' => $entrada, 'fecha_evaluacion' => $ahora->format('Y-m-d H:i:s'), 'paciente' => $paciente, 'declarados' => $declarados, 'resultado' => $resultado];
        return [$contexto, self::huella([$entrada, $paciente, $grafo->version])];
    }

    public function evaluar(array $datos): array
    {
        $entrada = [];
        foreach (['id_mascota', 'id_veterinario', 'id_tipo_cita'] as $campo) {
            $entrada[$campo] = ValidadorClinico::id($datos[$campo] ?? null);
            if ($campo === 'id_mascota' && $entrada[$campo] === null) {
                throw new InvalidArgumentException('Selecciona mascota, veterinario y tipo de cita.');
            }
        }
        $entrada['fecha'] = ValidadorClinico::fecha($datos['fecha'] ?? null);
        if ($entrada['fecha'] === null) {
            throw new InvalidArgumentException('Selecciona una fecha válida.');
        }
        foreach (['sintomas', 'exposiciones', 'vacunacion', 'dias_desde_celo'] as $campo) {
            if (array_key_exists($campo, $datos)) {
                $entrada[$campo] = $datos[$campo];
            }
        }
        $this->autorizar($entrada);
        return $this->unidad($entrada, function () use ($entrada, $datos): array {
            $this->autorizar($entrada);
            $op = $this->conMismoAlcance(new OperacionAgenda($this->conn));
            $referencia = $op->ejecutar('evaluar_agenda', $datos['clave_operacion'] ?? null, $entrada, function () use ($entrada): array {
                [$contexto, $huella] = $this->calcular($entrada);
                $r = $contexto['resultado'];
                $revision = $r['revision'] ? 'pendiente' : 'no_requerida';
                $estado = $r['nivel'] === 'rojo' ? 'derivada' : 'abierta';
                $origen = Contexto::rolActivo() === Roles::PROPIETARIO ? 'portal' : 'interno';
                $q = $this->conn->prepare('INSERT INTO solicitudes_agenda (id_clinica,id_mascota,id_actor,origen,estado,estado_revision,nivel_calculado,contexto_triage,version_conocimiento,huella_entrada,contagio,fuente_nivel) VALUES (?,?,?,?,?,?,?,?,?,?,?,\'grafo\')');
                $q->execute([$this->clinica(), $entrada['id_mascota'], Contexto::idUsuario(), $origen, $estado, $revision, $r['nivel'], json_encode($contexto, JSON_THROW_ON_ERROR), $r['version'], $huella, (int) $r['contagio']]);
                $id = (int) $this->conn->lastInsertId();
                $eventos = new EventoAgenda($this->conn, $this->clinica());
                if ($estado === 'derivada' || $revision === 'pendiente') {
                    $eventos->interno($estado === 'derivada' ? 'DERIVACION_ROJA' : 'REVISION_TRIAGE', null, $id, 0, null, Roles::VETERINARIO);
                }
                if ($r['contagio']) {
                    $eventos->interno('AISLAMIENTO', null, $id, 0, null, Roles::VETERINARIO);
                }
                $this->auditar($id, null, ['nivel_calculado' => $r['nivel'], 'revision' => $revision], 'Evaluación para agenda (RE-4.13.1)');
                return ['id_solicitud' => $id];
            });
            return $this->respuesta($this->obtener((int) $referencia['id_solicitud']));
        });
    }

    public function obtener(int $id): array
    {
        $q = $this->conn->prepare('SELECT * FROM solicitudes_agenda WHERE id_solicitud=? AND id_clinica=?');
        $q->execute([$id, $this->clinica()]);
        $fila = $q->fetch(PDO::FETCH_ASSOC);
        if (!$fila || (Contexto::rolActivo() === Roles::PROPIETARIO && (int) $fila['id_actor'] !== Contexto::idUsuario())) {
            $this->denegarAcceso('solicitudes_agenda', $id, 'Solicitud ajena');
        }
        $contexto = json_decode($fila['contexto_triage'], true, 512, JSON_THROW_ON_ERROR);
        $this->autorizarContexto((int) $fila['id_mascota']);
        return $fila;
    }

    public function vigente(int $id, array $seleccion): array
    {
        $fila = $this->obtener($id);
        $contexto = json_decode($fila['contexto_triage'], true, 512, JSON_THROW_ON_ERROR);
        $this->autorizar($contexto['entrada'], true);
        foreach (['id_mascota', 'id_veterinario', 'id_tipo_cita', 'fecha'] as $campo) {
            if ((string) ($seleccion[$campo] ?? '') !== (string) $contexto['entrada'][$campo]) {
                throw new InvalidArgumentException('La selección cambió. Vuelve a calcular el triage.');
            }
        }
        [$vigente, $huella] = $this->calcular($contexto['entrada'], $contexto['fecha_evaluacion']);
        foreach (['nivel', 'revision', 'contagio', 'reglas_aplicadas'] as $campo) {
            if ($vigente['resultado'][$campo] !== $contexto['resultado'][$campo]) {
                throw new InvalidArgumentException('La evaluación cambió con los datos vigentes. Vuelve a calcular y revisar.');
            }
        }
        if (!hash_equals($fila['huella_entrada'], $huella) || ($fila['estado_revision'] === 'resuelta' && !hash_equals($huella, $fila['huella_revision']))) {
            throw new InvalidArgumentException('La ficha o el conocimiento cambiaron. Se necesita una nueva evaluación y revisión.');
        }
        if ($fila['estado_revision'] === 'pendiente') {
            throw new InvalidArgumentException('Se requiere revisión veterinaria antes de ofrecer horarios.');
        }
        return $fila;
    }

    public function revisar(int $id, string $nivel, string $motivo, string $huella, ?string $clave): array
    {
        if (Contexto::rolActivo() !== Roles::VETERINARIO) {
            $this->denegarAcceso('solicitudes_agenda', $id, 'Revisión solo veterinaria');
        }
        $motivo = trim($motivo);
        if (!in_array($nivel, ['verde', 'amarillo', 'naranja', 'rojo'], true) || $motivo === '' || mb_strlen($motivo) > 255) {
            throw new InvalidArgumentException('Indica nivel y motivo de revisión de 1 a 255 caracteres.');
        }
        $fila = $this->obtener($id);
        $e = json_decode($fila['contexto_triage'], true, 512, JSON_THROW_ON_ERROR)['entrada'];
        return $this->unidad($e, function () use ($id, $nivel, $motivo, $huella, $clave): array {
            $fila = $this->obtener($id);
            $op = $this->conMismoAlcance(new OperacionAgenda($this->conn));
            $op->ejecutar('revisar_agenda', $clave, [$id, $nivel, $motivo, $huella], function () use ($fila, $id, $nivel, $motivo, $huella): array {
                $contexto = json_decode($fila['contexto_triage'], true, 512, JSON_THROW_ON_ERROR);
                $entrada = $contexto['entrada'];
                [$vigente, $actual] = $this->calcular($entrada, $contexto['fecha_evaluacion']);
                if ($fila['estado_revision'] !== 'pendiente' || !hash_equals($actual, $huella) || !hash_equals($actual, $fila['huella_entrada'])) {
                    throw new InvalidArgumentException('La revisión está obsoleta o ya fue resuelta. Vuelve a evaluar.');
                }
                foreach (['nivel', 'revision', 'contagio', 'reglas_aplicadas'] as $campo) {
                    if ($vigente['resultado'][$campo] !== $contexto['resultado'][$campo]) {
                        throw new InvalidArgumentException('La evaluación cambió. Vuelve a calcular antes de revisar.');
                    }
                }
                $estado = $nivel === 'rojo' ? 'derivada' : 'abierta';
                $this->conn->prepare("UPDATE solicitudes_agenda SET nivel_aprobado=?,estado_revision='resuelta',huella_revision=?,id_revisor=?,fecha_revision=?,motivo_revision=?,estado=?,version_solicitud=version_solicitud+1 WHERE id_solicitud=? AND id_clinica=?")
                    ->execute([$nivel, $actual, Contexto::idUsuario(), ($this->reloj)()->format('Y-m-d H:i:s'), $motivo, $estado, $id, $this->clinica()]);
                if ($nivel === 'rojo') {
                    (new EventoAgenda($this->conn, $this->clinica()))->interno('DERIVACION_ROJA', null, $id, (int) $fila['version_solicitud'] + 1, null, Roles::VETERINARIO);
                }
                $this->auditar($id, ['nivel_calculado' => $fila['nivel_calculado']], ['nivel_aprobado' => $nivel, 'motivo' => $motivo], 'Revisión veterinaria (RE-4.18.1/2)');
                return ['id_solicitud' => $id];
            });
            return $this->respuesta($this->obtener($id));
        });
    }

    public function respuesta(array $fila): array
    {
        $contexto = json_decode($fila['contexto_triage'], true, 512, JSON_THROW_ON_ERROR);
        $nivel = $fila['nivel_aprobado'] ?? $fila['nivel_calculado'];
        $q = $this->conn->prepare('SELECT telefono_urgencias FROM clinicas WHERE id_clinica=?');
        $q->execute([$this->clinica()]);
        $cerrada = $nivel === 'rojo' ? !$this->disponibilidad()->clinicaAbiertaAhora(($this->reloj)())
            : !$this->disponibilidad()->clinicaAbiertaEnFecha($contexto['entrada']['fecha']);
        return ['id_solicitud' => (int) $fila['id_solicitud'], 'nivel' => $nivel, 'nivel_calculado' => $fila['nivel_calculado'],
            'revision' => $fila['estado_revision'] === 'pendiente', 'estado_revision' => $fila['estado_revision'], 'huella' => $fila['huella_entrada'],
            'contagio' => (bool) $fila['contagio'], 'clinica_cerrada' => $cerrada, 'telefono_urgencias' => $q->fetchColumn() ?: '',
            'reservable' => !$cerrada && !empty($contexto['entrada']['id_veterinario']) && !empty($contexto['entrada']['id_tipo_cita']) && $nivel !== 'rojo' && $fila['estado_revision'] !== 'pendiente', 'resultado' => $contexto['resultado'],
            'message' => ($cerrada ? 'Clínica cerrada. Contacta al teléfono de urgencias o a un servicio 24 horas. ' : '')
                . ($nivel === 'rojo' ? 'Emergencia: contacta de inmediato a la clínica o a un servicio de urgencias 24 horas. Este aviso no es una reserva ni acredita llegada.' : ($fila['estado_revision'] === 'pendiente' ? 'Se requiere revisión veterinaria antes de ver horarios.' : 'Evaluación lista para consultar disponibilidad.'))];
    }

    public function horas(int $id, array $seleccion): array
    {
        $fila = $this->vigente($id, $seleccion);
        if (($fila['nivel_aprobado'] ?? $fila['nivel_calculado']) === 'rojo') {
            return [];
        }
        $estado = $this->respuesta($fila);
        if ($estado['clinica_cerrada']) {
            throw new HorarioOcupado($estado['message'] . ' ' . $estado['telefono_urgencias']);
        }
        $citas = $this->conMismoAlcance(new Cita($this->conn, $this->reloj));
        $horas = $citas->sugerencias((int) $seleccion['id_veterinario'], (int) $seleccion['id_mascota'], (int) $seleccion['id_tipo_cita'], $seleccion['fecha']);
        if (!$horas && ($fila['nivel_aprobado'] ?? $fila['nivel_calculado']) === 'naranja') {
            $tipo = $this->conMismoAlcance(new CatalogoClinica($this->conn))->tipoCita((int) $seleccion['id_tipo_cita']);
            if (!$tipo || !(int) $tipo['activo']) {
                throw new InvalidArgumentException('Tipo de cita no disponible.');
            }
            $horas = $this->disponibilidad()->sugerir((int) $seleccion['id_veterinario'], (int) $seleccion['id_mascota'], $seleccion['fecha'], (int) $tipo['duracion_minutos'], (int) $tipo['margen_minutos'], null, true);
        }
        return ($fila['nivel_aprobado'] ?? $fila['nivel_calculado']) === 'verde' ? $horas : array_slice($horas, 0, 1);
    }

    public function reservar(array $datos): array
    {
        $fila = $this->obtener((int) ($datos['id_solicitud'] ?? 0));
        $entrada = json_decode($fila['contexto_triage'], true, 512, JSON_THROW_ON_ERROR)['entrada'];
        $this->autorizar($entrada, true);
        return $this->unidad($entrada, function () use ($fila, $entrada, $datos): array {
            $this->autorizar($entrada, true);
            $comando = array_intersect_key($datos, array_flip(['id_solicitud','id_mascota','id_veterinario','id_tipo_cita','fecha','hora','motivo']));
            $operacion = $this->conMismoAlcance(new OperacionAgenda($this->conn));
            if (!$operacion->tieneResultado('confirmar_agenda', $datos['clave_operacion'] ?? null) && ($datos['ignore_warning'] ?? '') !== '1') {
                $actual = $this->vigente((int) $fila['id_solicitud'], $datos);
                $horas = $this->horas((int) $fila['id_solicitud'], $datos);
                $hora = Cita::normalizarHora((string) ($datos['hora'] ?? ''));
                $compatible = in_array($hora, array_map([Cita::class, 'normalizarHora'], $horas), true);
                $otra = $this->conMismoAlcance(new Cita($this->conn, $this->reloj))->horaDeOtraCitaDelDia($entrada['id_mascota'], $entrada['fecha']);
                if ($actual['estado'] === 'abierta' && $compatible && $otra !== null) {
                    return ['success' => false, 'has_warning' => true, 'message' => 'La mascota ya tiene otra cita ese día a las ' . $otra . '. ¿Deseas continuar?'];
                }
            }
            return $operacion->ejecutar('confirmar_agenda', $datos['clave_operacion'] ?? null, $comando, function () use ($fila, $entrada, $datos): array {
                $actual = $this->vigente((int) $fila['id_solicitud'], $datos);
                if ($actual['estado'] !== 'abierta') {
                    throw new InvalidArgumentException('Esta solicitud ya no admite una nueva reserva.');
                }
                $nivel = $actual['nivel_aprobado'] ?? $actual['nivel_calculado'];
                if ($nivel === 'rojo') {
                    return $this->respuesta($actual);
                }
                if ($entrada['fecha'] < ($this->reloj)()->format('Y-m-d') || ($nivel === 'verde' && IntervalosAgenda::fecha($entrada['fecha'] . ' ' . Cita::normalizarHora((string) ($datos['hora'] ?? ''))) <= ($this->reloj)())) {
                    throw new InvalidArgumentException('La fecha y hora seleccionadas ya pasaron.');
                }
                $citas = $this->conMismoAlcance(new Cita($this->conn, $this->reloj));
                $horas = $citas->sugerencias($entrada['id_veterinario'], $entrada['id_mascota'], $entrada['id_tipo_cita'], $entrada['fecha']);
                $sobrecupo = $nivel === 'naranja' && !$horas;
                $bloque = null;
                if ($nivel === 'naranja') {
                    if ($sobrecupo) {
                        $tipo = $this->conMismoAlcance(new CatalogoClinica($this->conn))->tipoCita((int) $entrada['id_tipo_cita']);
                        if (!$tipo || !(int) $tipo['activo']) {
                            throw new InvalidArgumentException('Tipo de cita no disponible.');
                        }
                        $horas = $this->disponibilidad()->sugerir($entrada['id_veterinario'], $entrada['id_mascota'], $entrada['fecha'], (int) $tipo['duracion_minutos'], (int) $tipo['margen_minutos'], null, true);
                    }
                    $bloque = $horas ? $this->bloqueDisponible($entrada, $horas[0]) : null;
                    if ($bloque === null) {
                        (new EventoAgenda($this->conn, $this->clinica()))->interno('TOPE_NARANJA', null, (int) $actual['id_solicitud'], (int) $actual['version_solicitud'], null, Roles::VETERINARIO);
                        $this->auditar((int) $actual['id_solicitud'], null, ['sin_cupo' => true], 'Tope naranja: requiere decisión del personal (RN-422)');
                        return ['success' => false, 'sin_cupo' => true, 'message' => 'Sin cupo naranja disponible. Se avisó al personal para que decida; no se creó una cita.'];
                    }
                }
                if (!$horas) {
                    throw new HorarioOcupado('No hay horarios compatibles. Elige otra fecha o veterinario.');
                }
                $hora = $nivel === 'verde' ? Cita::normalizarHora((string) ($datos['hora'] ?? '')) : Cita::normalizarHora($horas[0]);
                $motivo = trim((string) ($datos['motivo'] ?? ''));
                if (mb_strlen($motivo) > 255) {
                    throw new InvalidArgumentException('El motivo admite hasta 255 caracteres.');
                }
                if (Contexto::rolActivo() === Roles::PROPIETARIO) {
                    $this->conMismoAlcance(new Mascota($this->conn))->vincular((int) $entrada['id_mascota'], (int) Contexto::idUsuario());
                }
                $id = $citas->registrarClasificada($entrada + ['hora' => $hora, 'motivo' => $motivo, 'estado' => 'confirmada'], $nivel, $sobrecupo);
                $this->conn->prepare('UPDATE citas SET contexto_triage=?,prioridad_calculada=?,motivo_ajuste_prioridad=?,id_bloque=? WHERE id_cita=? AND id_clinica=?')
                    ->execute([$actual['contexto_triage'], $actual['nivel_calculado'], $actual['motivo_revision'], $bloque, $id, $this->clinica()]);
                if ($nivel === 'naranja') {
                    $q = $this->conn->prepare('SELECT COALESCE(MAX(orden_sobrecupo),0)+1 FROM citas WHERE id_bloque=? AND id_clinica=? AND id_cita<>?');
                    $q->execute([$bloque, $this->clinica(), $id]);
                    $this->conn->prepare('UPDATE citas SET orden_sobrecupo=? WHERE id_cita=? AND id_clinica=?')->execute([(int) $q->fetchColumn(), $id, $this->clinica()]);
                }
                $contexto = json_decode($actual['contexto_triage'], true, 512, JSON_THROW_ON_ERROR);
                foreach ($contexto['entrada']['sintomas'] ?? [] as $episodio) {
                    $q = $this->conn->prepare("SELECT id_nodo FROM grafo_nodos WHERE codigo=? AND tipo='sintoma' AND activo=1");
                    $q->execute([$episodio['codigo']]);
                    $nodo = $q->fetchColumn();
                    if (!$nodo) {
                        throw new InvalidArgumentException('El catálogo cambió. Vuelve a evaluar.');
                    }
                    $this->conn->prepare('INSERT INTO cita_sintomas (id_cita,id_nodo,detalles) VALUES (?,?,?)')->execute([$id, $nodo, json_encode($episodio, JSON_THROW_ON_ERROR)]);
                }
                $this->conn->prepare("UPDATE solicitudes_agenda SET estado='asignada',id_cita=?,version_solicitud=version_solicitud+1 WHERE id_solicitud=? AND id_clinica=?")->execute([$id, $actual['id_solicitud'], $this->clinica()]);
                $eventos = new EventoAgenda($this->conn, $this->clinica());
                if ($entrada['id_veterinario'] !== Contexto::idUsuario()) {
                    $eventos->interno('NUEVA_CITA', $id, null, 0, $entrada['id_veterinario'], null);
                }
                $cita = $citas->getById($id);
                if ($cita['id_propietario']) {
                    $eventos->correo('NUEVA_CITA', $id, 0, (int) $cita['id_propietario']);
                }
                $this->auditar((int) $actual['id_solicitud'], ['estado' => 'abierta'], ['estado' => 'asignada', 'id_cita' => $id], 'Reserva con síntomas (RE-4.13.6)');
                return ['id_cita' => $id, 'hora' => $hora, 'es_sobrecupo' => $sobrecupo, 'message' => 'Cita agendada correctamente.'];
            });
        });
    }

    public function bloqueParaCambio(array $entrada, string $hora, int $excluir): ?int
    {
        return $this->bloqueDisponible($entrada, $hora, $excluir);
    }

    private function bloqueDisponible(array $entrada, string $hora, ?int $excluir = null): ?int
    {
        $inicio = IntervalosAgenda::fecha($entrada['fecha'] . ' ' . Cita::normalizarHora($hora))->getTimestamp();
        foreach ($this->disponibilidad()->bloquesOriginales($entrada['id_veterinario'], $entrada['id_mascota'], $entrada['fecha']) as [$desde, $hasta]) {
            if ($inicio < $desde || $inicio >= $hasta) {
                continue;
            }
            // Un bloque usado conserva su identidad aunque se recorte o edite la jornada.
            $q = $this->conn->prepare('SELECT id_bloque FROM bloques_agenda WHERE id_clinica=? AND id_veterinario=? AND fecha=? AND inicio_original<? AND fin_original>? ORDER BY id_bloque LIMIT 1');
            $fin = (new DateTimeImmutable('@' . $hasta))->setTimezone(new DateTimeZone('America/Bogota'))->format('Y-m-d H:i:s');
            $comienzo = (new DateTimeImmutable('@' . $desde))->setTimezone(new DateTimeZone('America/Bogota'))->format('Y-m-d H:i:s');
            $q->execute([$this->clinica(), $entrada['id_veterinario'], $entrada['fecha'], $fin, $comienzo]);
            $id = $q->fetchColumn();
            if (!$id) {
                $this->conn->prepare('INSERT INTO bloques_agenda (id_clinica,id_veterinario,fecha,franja_origen,inicio_original,fin_original) VALUES (?,?,?,?,?,?)')
                    ->execute([$this->clinica(), $entrada['id_veterinario'], $entrada['fecha'], self::huella([$entrada['id_veterinario'], $entrada['fecha'], $desde, $hasta]), $comienzo, $fin]);
                $id = (int) $this->conn->lastInsertId();
            }
            $q = $this->conn->prepare("SELECT COUNT(*) FROM citas WHERE id_bloque=? AND id_clinica=? AND prioridad='naranja' AND estado NOT IN ('cancelada','no_asistio') AND id_cita<>?");
            $q->execute([$id, $this->clinica(), $excluir ?? 0]);
            $consumo = (int) $q->fetchColumn();
            $q = $this->conn->prepare('SELECT tope_sobrecupos FROM clinicas WHERE id_clinica=?');
            $q->execute([$this->clinica()]);
            return $consumo < (int) $q->fetchColumn() ? (int) $id : null;
        }
        return null;
    }

    private function auditar(int $id, ?array $antes, array $despues, string $motivo): void
    {
        if (!(new Auditoria($this->conn))->log(Contexto::idUsuario(), 'UPDATE', 'solicitudes_agenda', $id, $antes, $despues, $motivo, $this->clinica())) {
            throw new RuntimeException('No se pudo auditar la solicitud.');
        }
    }

    public function listar(?int $id = null, bool $historial = false): array
    {
        $this->autorizarContexto(0);
        if ($id !== null) {
            $fila = $this->obtener($id);
            $respuesta = $this->respuesta($fila);
            if (Contexto::rolActivo() !== Roles::PROPIETARIO) {
                $q = $this->conn->prepare("SELECT id_usuario,fecha_hora,datos_anteriores,datos_nuevos,descripcion FROM auditoria_sistema WHERE id_clinica=? AND tabla_afectada='solicitudes_agenda' AND registro_id=? ORDER BY id_auditoria");
                $q->execute([$this->clinica(), (string) $id]);
                $respuesta['historial'] = $q->fetchAll(PDO::FETCH_ASSOC);
            }
            return $respuesta;
        }
        if (!in_array(Contexto::rolActivo(), [Roles::VETERINARIO, Roles::ADMIN], true)) {
            $this->denegarAcceso('solicitudes_agenda', null, 'Bandeja clínica no disponible en el portal');
        }
        $condicion = $historial ? "s.estado_revision='resuelta'" : "s.estado_revision='pendiente' AND s.estado='abierta'";
        $q = $this->conn->prepare("SELECT s.id_solicitud,m.nombre,s.nivel_calculado,s.nivel_aprobado,s.huella_entrada,s.contexto_triage FROM solicitudes_agenda s JOIN mascotas m ON m.id_mascota=s.id_mascota WHERE s.id_clinica=? AND $condicion ORDER BY s.fecha_registro,s.id_solicitud");
        $q->execute([$this->clinica()]);
        $solicitudes = $q->fetchAll(PDO::FETCH_ASSOC);
        return ['solicitudes' => $solicitudes, 'etiquetas' => $this->etiquetasTriage($solicitudes)];
    }

    /** Pulido de interfaz: nombre legible de cada síntoma o exposición para la bandeja (sin códigos). */
    private function etiquetasTriage(array $solicitudes): array
    {
        $codigos = [];
        foreach ($solicitudes as $fila) {
            $contexto = json_decode((string) $fila['contexto_triage'], true);
            foreach (['sintomas', 'exposiciones'] as $grupo) {
                foreach ((array) ($contexto['entrada'][$grupo] ?? []) as $episodio) {
                    if (is_array($episodio) && is_string($episodio['codigo'] ?? null)) $codigos[$episodio['codigo']] = true;
                }
            }
        }
        if ($codigos === []) return [];
        $lista = array_keys($codigos);
        $q = $this->conn->prepare('SELECT codigo, etiqueta FROM grafo_nodos WHERE codigo IN (' . implode(',', array_fill(0, count($lista), '?')) . ')');
        $q->execute($lista);
        return $q->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
