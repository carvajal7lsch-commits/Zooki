<?php
require_once __DIR__ . '/ServicioAgendaClinica.php';
require_once __DIR__ . '/SolicitudAgenda.php';
require_once __DIR__ . '/CascadaAgenda.php';

/** C8: hechos de llegada, decisiones veterinarias y espera; nunca inicia por registrar presencia. */
final class LlegadaAgenda extends ServicioAgendaClinica
{
    private function cita(int $id, bool $asignado = true): array
    {
        $this->autorizar([Roles::VETERINARIO]);
        $cita = (new Cita($this->conn, $this->reloj))->getById($id);
        (new DisponibilidadAgenda($this->conn, $this->reloj))->autorizar((int) $cita['id_veterinario'], (int) $cita['id_mascota']);
        if ($asignado && (int) $cita['id_veterinario'] !== Contexto::idUsuario()) {
            $this->denegarAcceso('citas', $id, 'Decisión reservada al veterinario asignado (RN-408)');
        }
        return $cita;
    }

    private function abierta(array $cita): void
    {
        if (!in_array($cita['estado'], Cita::ESTADOS_ABIERTOS, true)) {
            throw new InvalidArgumentException('La cita ya no admite esta decisión. Recarga la agenda.');
        }
    }

    private function tiempos(array $cita): array
    {
        if ((int) $cita['duracion_minutos'] <= 0 || $cita['margen_minutos'] === null) {
            throw new InvalidArgumentException('La duración de esta cita requiere revisión.');
        }
        return [(int) $cita['duracion_minutos'], (int) $cita['margen_minutos']];
    }

    private function tolerancia(array $cita): DateTimeImmutable
    {
        $minutos = (int) $this->filas('SELECT tolerancia_llegada_min FROM clinicas WHERE id_clinica=?', [$this->clinica()])[0]['tolerancia_llegada_min'];
        return ReglaAtencion::inicio($cita['fecha'], $cita['hora'])->modify('+' . $minutos . ' minutes');
    }

    private function comando(int $id, string $accion, array $entrada, ?string $clave, callable $escribir, bool $asignado = true): array
    {
        $cita = $this->cita($id, $asignado);
        return $this->unidad([Roles::VETERINARIO], $accion, [$id, $entrada], $clave,
            fn () => $this->recursos([(int) $cita['id_mascota']], [], [$cita['fecha'], $cita['fecha']]),
            function () use ($id, $escribir, $asignado): array {
                $actual = $this->cita($id, $asignado);
                $this->abierta($actual);
                (new EventoAgenda($this->conn, $this->clinica()))->exigirSinDespacho($id);
                return $escribir($actual);
            });
    }

    private function recalcular(array $cita, string $motivo): void
    {
        $this->conn->prepare("UPDATE citas SET version_agenda=version_agenda+1,estado_reajuste='pendiente_calculo',motivo_reajuste=? WHERE id_cita=? AND id_clinica=?")
            ->execute([$motivo, $cita['id_cita'], $this->clinica()]);
        (new CascadaAgenda($this->conn, $this->reloj))->procesar($cita['fecha']);
    }

    public function noAsistio(int $id, ?string $clave): array
    {
        return $this->comando($id, 'inasistencia_c8', [], $clave, function (array $cita) use ($id): array {
            if (($this->reloj)() <= $this->tolerancia($cita)) {
                throw new InvalidArgumentException('La inasistencia exige haber superado la tolerancia.');
            }
            $this->conn->prepare("UPDATE citas SET estado='no_asistio' WHERE id_cita=? AND id_clinica=?")->execute([$id, $this->clinica()]);
            $this->auditar('citas', $id, ['estado' => $cita['estado']], ['estado' => 'no_asistio'], 'Inasistencia tras tolerancia (RE-4.7.1/2)');
            $this->recalcular($cita, 'Inasistencia libera capacidad');
            return ['id_cita' => $id, 'message' => 'Inasistencia registrada; agenda recalculada.'];
        });
    }

    public function llegada(int $id, ?string $clave): array
    {
        $resultado = $this->comando($id, 'llegada_c8', [], $clave, function (array $cita) use ($id): array {
            if ($cita['fecha'] !== ($this->reloj)()->format('Y-m-d')) {
                throw new InvalidArgumentException('La llegada se registra en la fecha de la cita.');
            }
            if ($cita['hora_llegada'] === null) {
                $sello = ($this->reloj)()->format('Y-m-d H:i:s');
                $this->conn->prepare('UPDATE citas SET hora_llegada=? WHERE id_cita=? AND id_clinica=?')->execute([$sello, $id, $this->clinica()]);
                $this->auditar('citas', $id, null, ['hora_llegada' => $sello], 'Llegada física, sin iniciar atención (RE-4.19.1)');
                $this->recalcular($cita, 'Llegada registrada');
            }
            return ['id_cita' => $id, 'message' => 'Llegada registrada. Elige cómo atender.'];
        });
        $actual = $this->cita($id);
        return $resultado + ['opciones' => in_array($actual['estado'], Cita::ESTADOS_ABIERTOS, true) ? $this->opciones($id) : null];
    }

    public function opciones(int $id): array
    {
        $cita = $this->cita($id);
        $this->abierta($cita);
        [$duracion, $margen] = $this->tiempos($cita);
        $ahora = ($this->reloj)();
        $llegada = $cita['hora_llegada'] === null ? $ahora : IntervalosAgenda::fecha($cita['hora_llegada']);
        $fin = ReglaAtencion::inicio($cita['fecha'], $cita['hora'])->modify('+' . $duracion . ' minutes');
        $restante = max(0, $fin->getTimestamp() - $ahora->getTimestamp());
        $reducida = false;
        if ($restante > 0) {
            try {
                (new DisponibilidadAgenda($this->conn, $this->reloj))->exigirSegundos((int) $cita['id_veterinario'], (int) $cita['id_mascota'], $ahora->format('Y-m-d'), $ahora->format('H:i:s'), $restante, $margen * 60, $id);
                $reducida = true;
            } catch (HorarioOcupado $error) {
                $reducida = false;
            }
        }
        return ['dentro_tolerancia' => $llegada <= $this->tolerancia($cita), 'segundos_restantes' => $restante,
            'reducida' => $reducida, 'reprogramar' => true, 'otro_veterinario' => $this->alternativas($cita),
            'siguiente_hueco' => (new DisponibilidadAgenda($this->conn, $this->reloj))->sugerir((int) $cita['id_veterinario'], (int) $cita['id_mascota'], $cita['fecha'], $duracion, $margen, $id)];
    }

    private function alternativas(array $cita): array
    {
        [$duracion, $margen] = $this->tiempos($cita);
        $resultado = [];
        foreach ((new Cita($this->conn, $this->reloj))->veterinariosActivos() as $vet) {
            $id = (int) $vet['id_usuario'];
            if ($id === (int) $cita['id_veterinario']) {
                continue;
            }
            $horas = (new DisponibilidadAgenda($this->conn, $this->reloj))->sugerir($id, (int) $cita['id_mascota'], $cita['fecha'], $duracion, $margen, isset($cita['id_cita']) ? (int) $cita['id_cita'] : null);
            if ($horas) {
                $resultado[] = ['id_veterinario' => $id, 'nombre' => $vet['nombre_completo'], 'hora' => $horas[0]];
            }
        }
        return $resultado;
    }

    public function decidir(int $id, array $datos): array
    {
        $decision = (string) ($datos['decision'] ?? '');
        $segundos = filter_var($datos['segundos'] ?? null, FILTER_VALIDATE_INT);
        $vet = (int) ($datos['id_veterinario'] ?? 0);
        $fecha = (string) ($datos['fecha'] ?? '');
        $hora = (string) ($datos['hora'] ?? '');
        return $this->comando($id, 'decidir_llegada_c8', [$decision, $segundos, $vet, $fecha, $hora], $datos['clave_operacion'] ?? null, function (array $cita) use ($id, $decision, $segundos, $vet, $fecha, $hora): array {
            if ($cita['hora_llegada'] === null || $cita['fecha'] !== ($this->reloj)()->format('Y-m-d')) {
                throw new InvalidArgumentException('Registra primero la llegada de hoy.');
            }
            $opciones = $this->opciones($id);
            if (in_array($decision, ['completa', 'reducida'], true)) {
                if (!$opciones['dentro_tolerancia']) {
                    throw new InvalidArgumentException('Pasada la tolerancia elige siguiente hueco o reprogramación.');
                }
                [$duracion, $margen] = $this->tiempos($cita);
                $objetivo = $duracion * 60;
                if ($decision === 'reducida') {
                    if (!$opciones['reducida'] || !$segundos || $segundos < 1 || $segundos > $opciones['segundos_restantes']) {
                        throw new InvalidArgumentException('Indica la duración activa reducida que cabe, en segundos.');
                    }
                    $objetivo = $segundos;
                    $ahora = ($this->reloj)();
                    (new DisponibilidadAgenda($this->conn, $this->reloj))->exigirSegundos((int) $cita['id_veterinario'], (int) $cita['id_mascota'], $cita['fecha'], $ahora->format('H:i:s'), $objetivo, $margen * 60, $id);
                }
                $this->conn->prepare('UPDATE citas SET duracion_activa_objetivo_segundos=? WHERE id_cita=? AND id_clinica=?')->execute([$objetivo, $id, $this->clinica()]);
                if (!(new Cita($this->conn, $this->reloj))->iniciarAtencion($cita, ($this->reloj)()->format('Y-m-d H:i:s'))) {
                    throw new InvalidArgumentException('La cita cambió; recarga.');
                }
            } elseif (in_array($decision, ['reprogramar', 'otro_veterinario', 'siguiente_hueco'], true)) {
                if ($decision === 'otro_veterinario' && $vet === (int) $cita['id_veterinario']) {
                    throw new InvalidArgumentException('Elige otro veterinario disponible.');
                }
                if ($decision === 'siguiente_hueco') {
                    $vet = (int) $cita['id_veterinario'];
                    $fecha = $cita['fecha'];
                    $hora = $opciones['siguiente_hueco'][0] ?? '';
                }
                // RN-430: decisión clínica de una llegada acreditada, nunca un parámetro HTTP de excepción.
                (new Cita($this->conn, $this->reloj))->reprogramar($cita, $vet, $fecha, $hora, null, true);
                $this->recalcular($cita, 'Decisión por llegada tarde');
            } else {
                throw new InvalidArgumentException('Selecciona una decisión válida.');
            }
            $this->auditar('citas', $id, ['hora' => $cita['hora'], 'duracion_minutos' => $cita['duracion_minutos']], ['decision' => $decision, 'segundos' => $segundos], 'Decisión veterinaria por llegada (RE-4.19.4)');
            return ['id_cita' => $id, 'message' => 'Decisión registrada y agenda recalculada.'];
        });
    }

    /** RN-419: revisión y reclasificación comparten los recursos de la cascada y una sola transacción. */
    public function conRevisionDeSolicitud(array $entrada, callable $revisar): array
    {
        $this->autorizar([Roles::VETERINARIO]);
        try {
            return (new CoordinadorAgenda($this->conn))->ejecutar($this->clinica(),
                fn () => $this->recursos([(int) $entrada['id_mascota']], [], [$entrada['fecha'], $entrada['fecha']]),
                $revisar);
        } catch (AccesoDenegado $error) {
            $this->denegarAcceso('solicitudes_agenda', null, 'Revisión denegada tras rollback');
        }
    }

    public function ajustar(int $id, string $nivel, string $motivo, ?string $clave): array
    {
        $motivo = trim($motivo);
        if (!in_array($nivel, ['verde', 'amarillo', 'naranja', 'rojo'], true) || $motivo === '' || mb_strlen($motivo) > 255 || preg_match('/\p{C}/u', $motivo)) {
            throw new InvalidArgumentException('Selecciona nivel y motivo de 1 a 255 caracteres.');
        }
        return $this->comando($id, 'ajustar_c8', [$nivel, $motivo], $clave, function (array $cita) use ($id, $nivel, $motivo): array {
            if ($nivel === 'rojo') {
                return $this->derivarARojo($cita, $motivo);
            }
            [$duracion, $margen] = $this->tiempos($cita);
            $hora = $cita['hora'];
            $orden = ['verde' => 0, 'amarillo' => 1, 'naranja' => 2, 'rojo' => 3];
            // RN-423: confirmar o bajar conserva exactamente el espacio, incluso si era un sobrecupo.
            $conservar = $orden[$nivel] <= $orden[$cita['prioridad']];
            $bloque = $conservar ? $cita['id_bloque'] : null;
            $sobrecupo = $conservar && (bool) $cita['es_sobrecupo'];
            if (!$conservar && in_array($nivel, ['amarillo', 'naranja'], true)) {
                $disponibilidad = new DisponibilidadAgenda($this->conn, $this->reloj);
                $horas = $disponibilidad->sugerir((int) $cita['id_veterinario'], (int) $cita['id_mascota'], $cita['fecha'], $duracion, $margen, $id);
                if ($nivel === 'naranja') {
                    $sobrecupo = !$horas;
                    if ($sobrecupo) {
                        $horas = $disponibilidad->sugerir((int) $cita['id_veterinario'], (int) $cita['id_mascota'], $cita['fecha'], $duracion, $margen, $id, true);
                    }
                    foreach ($horas as $opcion) {
                        $bloque = (new SolicitudAgenda($this->conn, $this->reloj))->bloqueParaCambio($cita, $opcion, $id);
                        if ($bloque !== null) {
                            $hora = Cita::normalizarHora($opcion);
                            break;
                        }
                    }
                    if ($bloque === null) {
                        throw new HorarioOcupado('Sin cupo naranja; requiere decisión del personal.');
                    }
                } else {
                    if (!$horas) {
                        throw new HorarioOcupado('Sin primer espacio compatible; reprograma con el propietario.');
                    }
                    $hora = Cita::normalizarHora($horas[0]);
                }
            }
            $fin = ReglaAtencion::inicio($cita['fecha'], $hora)->modify('+' . $duracion . ' minutes')->format('H:i:s');
            $this->conn->prepare('UPDATE citas SET prioridad_calculada=COALESCE(prioridad_calculada,prioridad),prioridad=?,motivo_ajuste_prioridad=?,hora=?,hora_fin=?,es_sobrecupo=?,id_bloque=? WHERE id_cita=? AND id_clinica=?')
                ->execute([$nivel, $motivo, $hora, $fin, (int) $sobrecupo, $bloque, $id, $this->clinica()]);
            $this->auditar('citas', $id, ['nivel_calculado' => $cita['prioridad_calculada'] ?? $cita['prioridad'], 'nivel_anterior' => $cita['prioridad']], ['nivel_ajustado' => $nivel, 'motivo' => $motivo], 'Reclasificación veterinaria (RE-4.18.1/4)');
            $this->recalcular($cita, 'Reclasificación veterinaria');
            $actual = (new Cita($this->conn, $this->reloj))->getById($id);
            $eventos = new EventoAgenda($this->conn, $this->clinica());
            $eventos->interno('CITA_REPROGRAMADA', $id, null, (int) $actual['version_agenda'], (int) $cita['id_veterinario'], null);
            if ($actual['id_propietario']) {
                $eventos->correo('CITA_REPROGRAMADA', $id, (int) $actual['version_agenda'], (int) $actual['id_propietario']);
            }
            return ['id_cita' => $id, 'message' => 'Nivel ajustado y ubicación recalculada.'];
        }, false);
    }

    /**
     * D (decisión del dueño, 2026-10-21): reclasificar a rojo saca la cita de la agenda ordinaria y la pone en Urgencias.
     * La cita ordinaria se cancela (no se borra) para liberar su espacio; la atención crea su propia cita de urgencia.
     */
    private function derivarARojo(array $cita, string $motivo): array
    {
        if (in_array($cita['estado'], ['en_curso', 'pausada', 'sin_cerrar'], true)) {
            throw new InvalidArgumentException('La atención ya está en curso; continúa la consulta en lugar de derivarla.');
        }
        require_once __DIR__ . '/UrgenciaAgenda.php';
        $id = (int) $cita['id_cita'];
        $solicitud = (new UrgenciaAgenda($this->conn, $this->reloj))->derivarCita($cita, $motivo);
        $this->conn->prepare("UPDATE citas SET prioridad_calculada=COALESCE(prioridad_calculada,prioridad),prioridad='rojo',motivo_ajuste_prioridad=?,estado='cancelada',version_agenda=version_agenda+1 WHERE id_cita=? AND id_clinica=?")
            ->execute([$motivo, $id, $this->clinica()]);
        $this->auditar('citas', $id, ['nivel_calculado' => $cita['prioridad_calculada'] ?? $cita['prioridad'], 'nivel_anterior' => $cita['prioridad']], ['nivel_ajustado' => 'rojo', 'motivo' => $motivo, 'id_solicitud_urgencia' => $solicitud], 'Reclasificación veterinaria (RE-4.18.1/4)');
        $this->recalcular($cita, 'Reclasificación a rojo: pasa a Urgencias');
        $mensaje = $cita['hora_llegada'] !== null
            ? 'Reclasificada a rojo: el paciente pasa a la cola de Urgencias.'
            : 'Reclasificada a rojo: queda derivada a Urgencias y se avisó a la clínica. Registra su llegada en Urgencias.';
        return ['id_cita' => $id, 'id_solicitud' => $solicitud, 'message' => $mensaje];
    }

    /** RE-2.2.5: orden clínico confirmado; ninguna notificación acredita presencia. */
    public function espera(): array
    {
        $this->autorizar([Roles::VETERINARIO]);
        return $this->filas("SELECT id_solicitud,id_mascota,(SELECT m.nombre FROM mascotas m WHERE m.id_mascota=solicitudes_agenda.id_mascota) AS mascota,fecha_llegada,nivel_calculado,nivel_aprobado,estado_revision FROM solicitudes_agenda WHERE id_clinica=? AND origen='llegada' AND estado='espera_presencial' AND COALESCE(nivel_aprobado,nivel_calculado)<>'rojo' ORDER BY CASE COALESCE(nivel_aprobado,nivel_calculado) WHEN 'naranja' THEN 0 WHEN 'amarillo' THEN 1 ELSE 2 END,fecha_llegada,id_solicitud", [$this->clinica()]);
    }

    private function solicitud(int $id): array
    {
        $this->autorizar([Roles::VETERINARIO]);
        return (new SolicitudAgenda($this->conn, $this->reloj))->obtener($id);
    }

    private function entrada(array $solicitud): array
    {
        return json_decode($solicitud['contexto_triage'], true, 512, JSON_THROW_ON_ERROR)['entrada'];
    }

    public function presentar(int $id, ?string $clave): array
    {
        $solicitud = $this->solicitud($id);
        $r = $this->unidad([Roles::VETERINARIO], 'presentar_c8', [$id], $clave,
            fn () => $this->recursos([(int) $solicitud['id_mascota']]), function () use ($id): array {
                $s = $this->solicitud($id);
                $entrada = $this->entrada($s);
                if ((int) $entrada['id_veterinario'] !== Contexto::idUsuario() || $entrada['fecha'] !== ($this->reloj)()->format('Y-m-d')) {
                    throw new InvalidArgumentException('Evalúa la llegada con tu contexto y la fecha de hoy.');
                }
                if (($s['nivel_aprobado'] ?? $s['nivel_calculado']) === 'rojo') {
                    throw new InvalidArgumentException('El rojo se recibe por Urgencias; no por llegada ordinaria.');
                }
                if ($s['id_cita'] !== null || !in_array($s['estado'], ['abierta', 'espera_presencial'], true)) {
                    throw new InvalidArgumentException('Esta solicitud no admite otra llegada.');
                }
                if ($s['fecha_llegada'] === null) {
                    $sello = ($this->reloj)()->format('Y-m-d H:i:s');
                    $this->conn->prepare("UPDATE solicitudes_agenda SET origen='llegada',estado='espera_presencial',fecha_llegada=?,id_receptor=?,version_solicitud=version_solicitud+1 WHERE id_solicitud=? AND id_clinica=?")
                        ->execute([$sello, Contexto::idUsuario(), $id, $this->clinica()]);
                    $this->auditar('solicitudes_agenda', $id, null, ['fecha_llegada' => $sello], 'Llegada sin reserva, sin consulta ni sobrecupo automático (RE-2.2.5)');
                }
                return ['id_solicitud' => $id, 'message' => 'Llegada registrada. Revisa disponibilidad antes de asignar.'];
            });
        return $r;
    }

    public function alternativasLlegada(int $id): array
    {
        $s = $this->solicitud($id);
        $e = $this->entrada($s);
        (new SolicitudAgenda($this->conn, $this->reloj))->vigente($id, $e);
        $tipo = (new CatalogoClinica($this->conn))->tipoCita((int) $e['id_tipo_cita']);
        if (!$tipo || !(int) $tipo['activo']) {
            throw new InvalidArgumentException('Selecciona un tipo vigente y vuelve a evaluar.');
        }
        $cita = $e + ['duracion_minutos' => (int) $tipo['duracion_minutos'], 'margen_minutos' => (int) $tipo['margen_minutos']];
        return ['horas' => (new SolicitudAgenda($this->conn, $this->reloj))->horas($id, $e), 'otros' => $this->alternativas($cita)];
    }

    public function asignar(int $id, string $hora, ?string $clave): array
    {
        $s = $this->solicitud($id);
        // La identidad se valida también en replay; el resultado durable solo conserva referencias.
        return $this->unidad([Roles::VETERINARIO], 'asignar_llegada_c8', [$id, $hora], $clave,
            fn () => $this->recursos([(int) $s['id_mascota']]), function () use ($id, $hora): array {
                $s = $this->solicitud($id);
                $e = $this->entrada($s);
                $solicitudes = new SolicitudAgenda($this->conn, $this->reloj);
                $s = $solicitudes->vigente($id, $e);
                if ($s['estado'] !== 'espera_presencial' || $s['fecha_llegada'] === null || ($s['nivel_aprobado'] ?? $s['nivel_calculado']) === 'rojo') {
                    throw new InvalidArgumentException('La llegada no está disponible para asignar.');
                }
                // Se adelantan solo quienes pueden ocupar este recurso; no bloquea una espera incompatible.
                foreach ($this->espera() as $anterior) {
                    if ((int) $anterior['id_solicitud'] === $id) {
                        break;
                    }
                    $otra = $this->solicitud((int) $anterior['id_solicitud']);
                    $oe = $this->entrada($otra);
                    if ((int) $oe['id_veterinario'] !== Contexto::idUsuario() || $oe['fecha'] !== $e['fecha'] || $otra['estado_revision'] === 'pendiente') {
                        continue;
                    }
                    try {
                        $solicitudes->vigente((int) $otra['id_solicitud'], $oe);
                        $compatible = (bool) $solicitudes->horas((int) $otra['id_solicitud'], $oe);
                    } catch (InvalidArgumentException $error) {
                        continue;
                    }
                    // C8 revisión: mensaje de dominio (422), no LogicException que el usuario vería como error genérico.
                    if ($compatible) {
                        throw new InvalidArgumentException('Hay una llegada anterior compatible; asígnala primero.');
                    }
                }
                $this->conn->prepare("UPDATE solicitudes_agenda SET estado='abierta' WHERE id_solicitud=? AND id_clinica=?")->execute([$id, $this->clinica()]);
                $r = $solicitudes->reservar($e + ['id_solicitud' => $id, 'hora' => $hora, 'motivo' => 'Llegada sin reserva', 'ignore_warning' => '1']);
                if (empty($r['id_cita'])) {
                    throw new HorarioOcupado('Sin capacidad; la llegada permanece en espera.');
                }
                $cita = (int) $r['id_cita'];
                $this->conn->prepare("UPDATE citas SET origen='llegada_sin_reserva',hora_llegada=? WHERE id_cita=? AND id_clinica=?")->execute([$s['fecha_llegada'], $cita, $this->clinica()]);
                $this->auditar('citas', $cita, null, ['id_solicitud' => $id, 'origen' => 'llegada_sin_reserva'], 'Cita obligatoria antes de consultar (RE-2.2.1/4)');
                $actual = (new Cita($this->conn, $this->reloj))->getById($cita);
                $this->recalcular($actual, 'Llegada sin reserva asignada');
                return ['id_cita' => $cita, 'id_solicitud' => $id, 'message' => 'Cita de llegada creada. Inicia la atención desde la agenda.'];
            });
    }

    public function leer(): array
    {
        $this->autorizar([Roles::VETERINARIO]);
        $citas = $this->filas("SELECT id_cita,(SELECT m.nombre FROM mascotas m WHERE m.id_mascota=citas.id_mascota) AS mascota,fecha,hora,estado,origen,prioridad,prioridad_calculada,hora_llegada FROM citas WHERE id_clinica=? AND id_veterinario=? AND fecha=? AND estado IN ('pendiente','confirmada') ORDER BY hora,id_cita", [$this->clinica(), Contexto::idUsuario(), ($this->reloj)()->format('Y-m-d')]);
        $historial = $this->filas("SELECT a.registro_id,a.id_usuario,a.fecha_hora,a.datos_anteriores,a.datos_nuevos FROM auditoria_sistema a WHERE a.id_clinica=? AND a.tabla_afectada='citas' AND a.descripcion='Reclasificación veterinaria (RE-4.18.1/4)' ORDER BY a.id_auditoria DESC", [$this->clinica()]);
        $solicitudes = $this->filas("SELECT id_solicitud,id_mascota,(SELECT m.nombre FROM mascotas m WHERE m.id_mascota=solicitudes_agenda.id_mascota) AS mascota,nivel_calculado,nivel_aprobado,estado_revision FROM solicitudes_agenda WHERE id_clinica=? AND id_actor=? AND estado='abierta' AND id_cita IS NULL ORDER BY id_solicitud DESC", [$this->clinica(), Contexto::idUsuario()]);
        return ['citas' => $citas, 'solicitudes' => $solicitudes, 'espera' => $this->espera(), 'historial' => $historial];
    }
}
