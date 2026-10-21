<?php
require_once __DIR__ . '/ModeloClinica.php';
require_once __DIR__ . '/../helpers/IntervalosAgenda.php';
require_once __DIR__ . '/../helpers/PoliticaTemporalAgenda.php';
require_once __DIR__ . '/../helpers/HorarioOcupado.php';

/** HU-4.9: única proyección de capacidad; no devuelve calendarios ajenos. */
final class DisponibilidadAgenda extends ModeloClinica
{
    private Closure $reloj;
    private ?int $clinicaProcesador = null;

    /** Capacidad interna del trabajador; nunca se construye con parámetros HTTP ni cambia la sesión. */
    public static function paraProcesador(PDO $db, int $clinica, callable $reloj): self
    {
        $modelo = new self($db, $reloj);
        $modelo->clinicaProcesador = $clinica;
        return $modelo;
    }

    protected function clinica(): int
    {
        return $this->clinicaProcesador ?? parent::clinica();
    }

    public function __construct(PDO $conn, ?callable $reloj = null)
    {
        parent::__construct($conn);
        $this->reloj = Closure::fromCallable($reloj ?? static fn () => new DateTimeImmutable('now', new DateTimeZone('America/Bogota')));
    }

    /** Revalida antes de cualquier lectura global, también en sugerencias. */
    public function autorizar(int $vet, int $mascota): void
    {
        if ($this->clinicaProcesador !== null) {
            $legitimo = $this->filas("SELECT 1 FROM clinicas c JOIN usuario_clinica uc ON uc.id_clinica=c.id_clinica JOIN usuarios u ON u.id_usuario=uc.id_usuario JOIN mascota_clinica mc ON mc.id_clinica=c.id_clinica JOIN mascotas m ON m.id_mascota=mc.id_mascota WHERE c.id_clinica=? AND c.estado='activa' AND uc.id_usuario=? AND uc.id_rol=2 AND uc.estado='activo' AND u.estado=1 AND m.id_mascota=? AND m.estado=1 AND mc.estado='activo'", [$this->clinica(), $vet, $mascota]);
            if (!$legitimo) {
                throw new HorarioOcupado('La disponibilidad requiere revisión.');
            }
            return;
        }
        $actor = Contexto::idUsuario();
        $portal = (Contexto::actual()['tipo'] ?? '') === Contexto::PROPIETARIO;
        $sql = $portal
            ? "SELECT 1 FROM propietario_clinica WHERE id_clinica=? AND id_propietario=? AND estado='activo'"
            : "SELECT 1 FROM usuario_clinica WHERE id_clinica=? AND id_usuario=? AND estado='activo' AND id_rol=?";
        $parametros = [$this->clinica(), $actor];
        if (!$portal) {
            $parametros[] = Contexto::rolActivo();
            if (!in_array(Contexto::rolActivo(), [Roles::ADMIN, Roles::VETERINARIO], true)) {
                $this->denegarAcceso('citas', null, 'Disponibilidad fuera de contexto (RE-4.9.1)');
            }
        }
        $vigente = $this->filas($sql, $parametros);
        $activo = $this->filas("SELECT 1 FROM usuarios u JOIN clinicas c ON c.id_clinica=? WHERE u.id_usuario=? AND u.estado=1 AND c.estado='activa'", [$this->clinica(), $actor]);
        if (!$vigente || !$activo) {
            $this->denegarAcceso('citas', null, 'Contexto de disponibilidad no vigente (RE-T.15.2)');
        }
        if ($portal) {
            // Sugerir no vincula la mascota: el vínculo se crea atómicamente al reservar.
            $propia = $this->filas('SELECT 1 FROM mascotas WHERE id_mascota=? AND id_propietario=? AND estado=1', [$mascota, $actor]);
            if (!$propia) {
                $this->denegarAcceso('mascotas', $mascota, 'Disponibilidad de mascota ajena (RN-G02)');
            }
        } else {
            $this->exigirMascotaActiva($mascota);
        }
        $miembro = $this->filas("SELECT 1 FROM usuario_clinica uc JOIN usuarios u ON u.id_usuario=uc.id_usuario WHERE uc.id_clinica=? AND uc.id_usuario=? AND uc.id_rol=2 AND uc.estado='activo' AND u.estado=1", [$this->clinica(), $vet]);
        if (!$miembro) {
            $this->denegarAcceso('usuarios', $vet, 'Disponibilidad de veterinario fuera de la clínica activa (RN-G13, RE-T.15.2)');
        }
    }

    /** Las franjas base se conservan para anclar el paso de presentación existente. */
    private function jornada(int $vet, string $fecha): array
    {
        $dia = IntervalosAgenda::fecha($fecha . ' 00:00:00');
        $apertura = $this->apertura($fecha);
        $jornada = [];
        foreach ($this->filas('SELECT hora_inicio,hora_fin FROM horarios_veterinario WHERE id_clinica=? AND id_veterinario=? AND dia_semana=? AND activo=1', [$this->clinica(), $vet, (int) $dia->format('N')]) as $fila) {
            $jornada[] = $this->intervalo($fecha, $fila['hora_inicio'], $fila['hora_fin']);
        }
        return IntervalosAgenda::intersecar(IntervalosAgenda::unir($apertura), IntervalosAgenda::unir($jornada));
    }

    private function apertura(string $fecha): array
    {
        $dia = IntervalosAgenda::fecha($fecha . ' 00:00:00');
        $apertura = [];
        foreach ($this->filas('SELECT * FROM horarios_clinica WHERE id_clinica=? AND dia_semana=? AND activo=1', [$this->clinica(), (int) $dia->format('N')]) as $fila) {
            foreach (['morning', 'afternoon'] as $bloque) {
                if ((int) $fila['bloque_' . $bloque . '_activo'] === 1) {
                    $apertura[] = $this->intervalo($fecha, $fila['bloque_' . $bloque . '_inicio'], $fila['bloque_' . $bloque . '_fin']);
                }
            }
        }
        return IntervalosAgenda::unir($apertura);
    }

    /**
     * Pruebas de usuario (2026-10-21): sin horas, el aviso dice por qué. RN-415: sin horario del
     * veterinario no hay disponibilidad, y eso se explica en vez de un «no quedan horarios».
     */
    public function motivoSinHoras(int $vet, string $fecha): string
    {
        if ($this->aperturaEfectiva($fecha) === []) {
            return 'La clínica no atiende ese día (horario de la clínica o cierre puntual). Prueba con otro día.';
        }
        $dia = (int) IntervalosAgenda::fecha($fecha . ' 00:00:00')->format('N');
        $franjas = $this->filas('SELECT 1 FROM horarios_veterinario WHERE id_clinica=? AND id_veterinario=? AND dia_semana=? AND activo=1', [$this->clinica(), $vet, $dia]);
        if ($franjas === []) {
            $nombres = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábados', 7 => 'domingos'];
            return 'Este veterinario no tiene horario de atención los ' . $nombres[$dia] . '. El administrador lo configura en Configuración › Horarios › Horario del veterinario.';
        }
        if ($this->jornada($vet, $fecha) === []) {
            return 'El horario de este veterinario no coincide con el de la clínica ese día. Revísalo en Configuración › Horarios.';
        }
        return 'No quedan horarios libres este día. Prueba con otro día.';
    }

    public function clinicaAbiertaEnFecha(string $fecha): bool
    {
        return $this->aperturaEfectiva($fecha) !== [];
    }

    public function clinicaAbiertaAhora(DateTimeImmutable $ahora): bool
    {
        foreach ($this->aperturaEfectiva($ahora->format('Y-m-d')) as [$desde, $hasta]) {
            if ($desde <= $ahora->getTimestamp() && $ahora->getTimestamp() < $hasta) {
                return true;
            }
        }
        return false;
    }

    private function aperturaEfectiva(string $fecha): array
    {
        $desde = $fecha . ' 00:00:00';
        $hasta = IntervalosAgenda::fecha($desde)->modify('+1 day')->format('Y-m-d H:i:s');
        $cierres = [];
        foreach ($this->filas('SELECT inicio,fin FROM cierres_clinica WHERE id_clinica=? AND activo=1 AND inicio<? AND fin>?', [$this->clinica(), $hasta, $desde]) as $fila) {
            $cierres[] = [IntervalosAgenda::fecha($fila['inicio'])->getTimestamp(), IntervalosAgenda::fecha($fila['fin'])->getTimestamp()];
        }
        return IntervalosAgenda::restar($this->apertura($fecha), $cierres);
    }

    private function intervalo(string $fecha, string $inicio, string $fin): array
    {
        return [IntervalosAgenda::fecha($fecha . ' ' . self::hora($inicio))->getTimestamp(), IntervalosAgenda::fecha($fecha . ' ' . self::hora($fin))->getTimestamp()];
    }

    private static function hora(string $hora): string
    {
        return strlen($hora) === 5 ? $hora . ':00' : $hora;
    }

    private function franjas(int $vet, int $mascota, string $fecha, ?int $excluir, int $corte, bool $sobrecupo = false, array $secuencia = []): array
    {
        $this->autorizar($vet, $mascota);
        if ($this->filas('SELECT 1 FROM mascotas WHERE id_mascota=? AND ficha_por_completar=1 AND id_propietario IS NULL',[$mascota])) {
            throw new HorarioOcupado('La ficha requiere resolver la identidad antes de una reserva ordinaria.');
        }
        if ($excluir !== null) {
            $cita = $this->filas("SELECT id_mascota,id_veterinario FROM citas WHERE id_cita=? AND id_clinica=? AND estado IN ('pendiente','confirmada')", [$excluir, $this->clinica()]);
            if (!$cita || (int) $cita[0]['id_mascota'] !== $mascota
                || (Contexto::rolActivo() === Roles::VETERINARIO && (int) $cita[0]['id_veterinario'] !== Contexto::idUsuario())
                || ((Contexto::actual()['tipo'] ?? '') === Contexto::PROPIETARIO
                    && !$this->filas('SELECT 1 FROM mascotas WHERE id_mascota=? AND id_propietario=?', [$mascota, Contexto::idUsuario()]))) {
                $this->denegarAcceso('citas', $excluir, 'Exclusión no autorizada (RE-4.9.3)');
            }
        }
        $inicioDia = IntervalosAgenda::fecha($fecha . ' 00:00:00');
        $finDia = $inicioDia->modify('+1 day');
        $ocupaciones = [];
        $desde = $inicioDia->format('Y-m-d H:i:s');
        $hasta = $finDia->format('Y-m-d H:i:s');
        foreach ($this->filas('SELECT inicio,fin FROM cierres_clinica WHERE id_clinica=? AND activo=1 AND inicio<? AND fin>?', [$this->clinica(), $hasta, $desde]) as $fila) {
            $ocupaciones[] = [IntervalosAgenda::fecha($fila['inicio'])->getTimestamp(), IntervalosAgenda::fecha($fila['fin'])->getTimestamp()];
        }
        foreach ($this->filas("SELECT fecha_hora_inicio,fecha_hora_fin FROM ausencias_veterinario WHERE id_clinica=? AND id_veterinario=? AND estado='aprobada' AND fecha_hora_inicio<? AND fecha_hora_fin>?", [$this->clinica(), $vet, $hasta, $desde]) as $fila) {
            $ocupaciones[] = [IntervalosAgenda::fecha($fila['fecha_hora_inicio'])->getTimestamp(), IntervalosAgenda::fecha($fila['fecha_hora_fin'])->getTimestamp()];
        }
        foreach ($this->ocupacionGlobal($vet, $mascota, $fecha, $excluir, $corte, $sobrecupo, $secuencia) as $intervalo) {
            $ocupaciones[] = $intervalo;
        }
        return IntervalosAgenda::restar($this->jornada($vet, $fecha), $ocupaciones);
    }

    /** RN-401: excepción global mínima; nunca nombres, clínica, motivo o contactos. */
    private function ocupacionGlobal(int $vet, int $mascota, string $fecha, ?int $excluir, int $ahora, bool $sobrecupo = false, array $secuencia = [], bool $rojoPresente = false): array
    {
        $sql = "SELECT (c.id_clinica=?) AS misma_clinica,(c.id_mascota=?) AS mismo_paciente,c.id_cita,(SELECT m.nombre FROM mascotas m WHERE m.id_mascota=c.id_mascota) AS mascota,c.fecha,c.hora,c.hora_fin,c.duracion_minutos,c.margen_minutos,c.estado,
            c.hora_inicio_real,c.hora_fin_real,c.inicio_estimado,c.fin_estimado,c.duracion_activa_objetivo_segundos,
            p.inicio AS pausa_inicio,p.fin AS pausa_fin
            FROM citas c LEFT JOIN pausas_cita p ON p.id_cita=c.id_cita
            WHERE (c.id_veterinario=? OR c.id_mascota=?) AND c.estado NOT IN ('cancelada','no_asistio')
            AND (c.fecha=? OR (c.fecha<=? AND c.duracion_minutos>0 AND c.margen_minutos IS NOT NULL AND c.estado IN ('pendiente','confirmada'))
                OR (c.hora_inicio_real<? AND (c.hora_fin_real IS NULL OR c.hora_fin_real>=?))
                OR (c.inicio_estimado<? AND c.fin_estimado>=?))";
        $finDia = IntervalosAgenda::fecha($fecha . ' 00:00:00')->modify('+1 day')->format('Y-m-d H:i:s');
        $parametros = [$this->clinica(), $mascota, $vet, $mascota, $fecha, $fecha, $finDia, $fecha . ' 00:00:00', $finDia, $fecha . ' 00:00:00'];
        if ($excluir !== null) {
            $sql .= ' AND c.id_cita<>?';
            $parametros[] = $excluir;
        }
        if ($secuencia) {
            // Solo el procesador sustituye esta secuencia local por su proyección en memoria.
            $marcas = implode(',', array_fill(0, count($secuencia), '?'));
            $sql .= " AND NOT (c.id_clinica=? AND c.id_cita IN ($marcas))";
            $parametros = [...$parametros, $this->clinica(), ...$secuencia];
        }
        $sql .= ' ORDER BY c.id_cita,p.inicio';
        $citas = [];
        foreach ($this->filas($sql, $parametros) as $fila) {
            $id = (int) $fila['id_cita'];
            $citas[$id] ??= ['fila' => $fila, 'pausas' => []];
            if ($fila['pausa_inicio'] !== null) {
                $citas[$id]['pausas'][] = [$fila['pausa_inicio'], $fila['pausa_fin']];
            }
        }
        $resultado = [];
        foreach ($citas as $datos) {
            $c = $datos['fila'];
            // La atención roja presente puede interrumpir solo ocupación local de otro paciente.
            // Ausencias y ocupación del paciente o de otra clínica siguen bloqueando.
            if ($rojoPresente && (bool) $c['misma_clinica'] && !(bool) $c['mismo_paciente']) {
                continue;
            }
            $ignorar = $sobrecupo && (bool) $c['misma_clinica'] && !(bool) $c['mismo_paciente'];
            $s = IntervalosAgenda::fecha($c['fecha'] . ' ' . self::hora($c['hora']))->getTimestamp();
            $duracion = (int) $c['duracion_minutos'];
            $margen = $c['margen_minutos'] === null ? null : (int) $c['margen_minutos'] * 60;
            // Decisión C3 del dueño: incertidumbre no equivale a capacidad libre.
            if ($margen === null || $margen < 0) {
                $this->requiereRevision($c, 'tiene el margen entre citas sin definir');
            }
            if (in_array($c['estado'], ['pendiente', 'confirmada'], true)) {
                if ($duracion > 0) {
                    $fin = $s + $duracion * 60;
                } elseif ($c['hora_fin'] !== null) {
                    $fin = IntervalosAgenda::fecha($c['fecha'] . ' ' . self::hora($c['hora_fin']))->getTimestamp();
                    if ($fin <= $s) {
                        $this->requiereRevision($c, 'termina antes de empezar');
                    }
                } else {
                    $this->requiereRevision($c, 'no tiene duración');
                }
                $inicio = $c['inicio_estimado'] === null ? $s : IntervalosAgenda::fecha($c['inicio_estimado'])->getTimestamp();
                $fin = $c['fin_estimado'] === null ? $inicio + ($fin - $s) : IntervalosAgenda::fecha($c['fin_estimado'])->getTimestamp();
                if (!$ignorar) {
                    $resultado[] = [min($s, $inicio), max($s + $duracion * 60, $fin) + $margen];
                }
                continue;
            }
            // Pruebas de usuario (2026-10-21): una atención de un día anterior que nunca se cerró
            // (olvido o dato de antes de M4) no ocupa un día posterior; se resuelve como «sin cerrar»
            // en su propia fecha. Una que empezó tarde la noche anterior (menos de 12 h antes de
            // este día) sí sigue ocupando, por si cruza la medianoche.
            if (in_array($c['estado'], ['en_curso', 'pausada', 'sin_cerrar'], true) && $c['hora_fin_real'] === null && $c['fecha'] < $fecha) {
                $limite = IntervalosAgenda::fecha($fecha . ' 00:00:00')->getTimestamp() - 12 * 3600;
                if ($c['hora_inicio_real'] === null || IntervalosAgenda::fecha($c['hora_inicio_real'])->getTimestamp() < $limite) {
                    continue;
                }
            }
            if ($c['hora_inicio_real'] === null || (int) $c['duracion_activa_objetivo_segundos'] <= 0) {
                // Pruebas de usuario (2026-10-21): una cita ya completada de antes de M4 no tiene
                // inicio real ni objetivo. Ya terminó: ocupa lo que se agendó (y hasta su fin real),
                // sin frenar el día entero. Si ni eso se sabe, sigue pidiendo revisión.
                if ($c['estado'] === 'completada' && $duracion > 0) {
                    $finReal = $c['hora_fin_real'] === null ? 0 : IntervalosAgenda::fecha($c['hora_fin_real'])->getTimestamp();
                    if (!$ignorar) {
                        $resultado[] = [$s, max($s + $duracion * 60, $finReal) + $margen];
                    }
                    continue;
                }
                $this->requiereRevision($c, $c['estado'] === 'completada' ? 'está completada pero sin duración registrada' : 'está en atención sin hora de inicio o sin duración registrada');
            }
            try {
                $pausas = array_map(static fn ($p) => ['inicio' => $p[0], 'fin' => $p[1]], $datos['pausas']);
                $e = PoliticaTemporalAgenda::calcular($c, $pausas, IntervalosAgenda::fecha(PoliticaTemporalAgenda::sello($ahora)));
            } catch (InvalidArgumentException $error) {
                $this->requiereRevision($c, 'tiene una pausa o un tiempo de atención inconsistente');
            }
            // A.3: una atención abierta no libera capacidad por agotar su estimación.
            if ($c['hora_fin_real'] === null && $ahora >= $e['extremo']) {
                $this->requiereRevision($c, 'sigue en atención y ya pasó su tiempo estimado: termínala para liberar la agenda');
            }
            if (!$ignorar) {
                $resultado[] = [min($s, $e['inicio']), $e['extremo']];
            }
        }
        return $resultado;
    }

    /**
     * Pruebas de usuario (2026-10-21): el aviso dice qué cita frena el día y por qué, para que
     * el veterinario la resuelva. De otra clínica no se revela nada (RN-401).
     */
    private function requiereRevision(array $c, string $razon): never
    {
        if (!(bool) $c['misma_clinica']) {
            throw new HorarioOcupado('No se pueden ofrecer horas este día: hay una ocupación en otra clínica con datos incompletos.');
        }
        $hora = substr((string) $c['hora'], 0, 5);
        $mascota = trim((string) ($c['mascota'] ?? ''));
        throw new HorarioOcupado(sprintf('No se pueden ofrecer horas este día: la cita #%d%s del %s a las %s %s.', (int) $c['id_cita'], $mascota !== '' ? ' de ' . $mascota : '', (string) $c['fecha'], $hora, $razon));
    }

    /** RE-4.15.2: capacidad global mínima; cierre no impide atender a un rojo ya presente. */
    public function exigirCapacidadUrgente(int $vet, int $mascota, int $segundos): void
    {
        $this->autorizar($vet, $mascota);
        $ahora = ($this->reloj)();
        $inicio = $ahora->format('Y-m-d H:i:s');
        $fin = PoliticaTemporalAgenda::sello($ahora->getTimestamp() + $segundos);
        if ($this->filas("SELECT 1 FROM ausencias_veterinario WHERE id_veterinario=? AND id_clinica=? AND estado='aprobada' AND fecha_hora_inicio<? AND fecha_hora_fin>?", [$vet,$this->clinica(),$fin,$inicio])) {
            throw new HorarioOcupado('No hay un veterinario disponible para esta atención.');
        }
        $ocupadas = $this->ocupacionGlobal($vet,$mascota,$ahora->format('Y-m-d'),null,$ahora->getTimestamp(),false,[],true);
        foreach ($ocupadas as [$desde,$hasta]) {
            if ($desde < $ahora->getTimestamp() + $segundos && $hasta > $ahora->getTimestamp()) {
                throw new HorarioOcupado('No hay disponibilidad para esta atención.');
            }
        }
    }

    public function exigir(int $vet, int $mascota, string $fecha, string $hora, int $duracion, int $margen, ?int $excluir = null): void
    {
        $this->exigirSegundos($vet, $mascota, $fecha, $hora, $duracion * 60, $margen * 60, $excluir);
    }

    /** RE-4.19: la duración reducida explícita conserva segundos, sin redondear ni alterar snapshots. */
    public function exigirSegundos(int $vet, int $mascota, string $fecha, string $hora, int $duracion, int $margen, ?int $excluir = null): void
    {
        $inicio = IntervalosAgenda::fecha($fecha . ' ' . self::hora($hora));
        $fin = $inicio->modify('+' . ($duracion + $margen) . ' seconds');
        $corte = ($this->reloj)()->getTimestamp();
        if ($duracion <= 0 || $margen < 0 || $inicio->getTimestamp() < $corte || $fin->format('Y-m-d') !== $fecha
            || !IntervalosAgenda::contiene($this->franjas($vet, $mascota, $fecha, $excluir, $corte), $inicio->getTimestamp(), $fin->getTimestamp())) {
            throw new HorarioOcupado('No hay disponibilidad para el intervalo completo. Elige otro horario.');
        }
    }

    public function sugerir(int $vet, int $mascota, string $fecha, int $duracion, int $margen, ?int $excluir = null, bool $sobrecupo = false): array
    {
        $ahora = ($this->reloj)()->getTimestamp();
        $libres = $this->franjas($vet, $mascota, $fecha, $excluir, $ahora, $sobrecupo);
        $horas = [];
        foreach ($this->jornada($vet, $fecha) as [$desde, $hasta]) {
            for ($inicio = $desde; $inicio + ($duracion + $margen) * 60 <= $hasta; $inicio += $duracion * 60) {
                if ($inicio >= $ahora && IntervalosAgenda::contiene($libres, $inicio, $inicio + ($duracion + $margen) * 60)) {
                    $instante = (new DateTimeImmutable('@' . $inicio))->setTimezone(new DateTimeZone('America/Bogota'));
                    // RE-4.9.1/2: HH:mm solo abrevia segundos cero, nunca redondea la sugerencia.
                    $horas[] = $instante->format($instante->format('s') === '00' ? 'H:i' : 'H:i:s');
                }
            }
        }
        return array_values(array_unique($horas));
    }

    public function bloquesOriginales(int $vet, int $mascota, string $fecha): array
    {
        $this->autorizar($vet, $mascota);
        return $this->jornada($vet, $fecha);
    }

    /** C5: usa la misma resta global; la secuencia excluida debe ser propia, vigente y releída. */
    public function franjasParaCascada(int $vet, int $mascota, string $fecha, array $secuencia): array
    {
        if ($this->clinicaProcesador === null) {
            throw new LogicException('La proyección de secuencia pertenece al procesador.');
        }
        foreach ($secuencia as $id) {
            if (!$this->filas('SELECT 1 FROM citas WHERE id_cita=? AND id_clinica=?', [$id, $this->clinica()])) {
                throw new LogicException('La secuencia no pertenece a la clínica procesada.');
            }
        }
        return $this->franjas($vet, $mascota, $fecha, null, ($this->reloj)()->getTimestamp(), false, $secuencia);
    }

    private function filas(string $sql, array $parametros): array
    {
        $consulta = $this->conn->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }
}
