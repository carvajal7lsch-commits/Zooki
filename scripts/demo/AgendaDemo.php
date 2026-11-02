<?php
require_once __DIR__ . '/../../models/LlegadaAgenda.php';
require_once __DIR__ . '/../../models/CascadaAgenda.php';

/** Agenda de demostración: los niveles pertenecen al motor y las transiciones a los servicios existentes. */
final class AgendaDemo
{
    public function __construct(private PDO $db, private ConocimientoClinico $grafo, private array $clinicas, private array $usuarios, private array $mascotas, private Closure $reloj, private Closure $historia)
    {
    }

    private function filas(string $sql, array $parametros = []): array
    {
        $q = $this->db->prepare($sql);
        $q->execute($parametros);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    private function unidad(callable $accion): array
    {
        SemillaDemo::como($this->usuarios['sanroque-vet-1'], $this->clinicas['sanroque'], Roles::VETERINARIO);
        $usuarios = array_values($this->usuarios);
        $mascotas = array_column($this->mascotas, 'id');
        return (new CoordinadorAgenda($this->db))->ejecutar($this->clinicas['sanroque'], static fn (): array => ['usuarios' => $usuarios, 'mascotas' => $mascotas], $accion);
    }

    private function clave(string $fase, string $dia, string $operacion): string
    {
        return hash('sha256', 'semilla-demo:' . $fase . ':' . $dia . ':' . $operacion);
    }

    private function tipo(string $nombre = 'Consulta general/Enfermedad'): int
    {
        $fila = $this->filas('SELECT id_tipo_cita FROM tipos_cita WHERE id_clinica=? AND nombre_tipo=? AND activo=1', [$this->clinicas['sanroque'], $nombre]);
        if ($fila === []) {
            throw new RuntimeException('Falta el tipo de cita ' . $nombre . '; vuelve a crear la demo.');
        }
        return (int) $fila[0]['id_tipo_cita'];
    }

    public function preparar(DateTimeImmutable $dia): array
    {
        return $this->unidad(function () use ($dia): array {
            $reloj = static fn (): DateTimeImmutable => $dia->setTime(6, 55);
            $nombres = array_values(array_diff(array_keys(array_filter($this->mascotas, static fn (array $m): bool => ($m['clinica'] ?? '') === 'sanroque')), ['Mila', 'Rocco', 'Max', 'Mishi', 'Toby', 'Bruno', 'Nina', 'Apolo', 'Lola']));
            $tipos = ['Consulta general/Enfermedad', 'Vacunación/Revisión rápida', 'Desparasitación', 'Control post-operatorio', 'Primera visita/Mascota nueva'];
            $fecha = $dia;
            foreach (range(0, 5) as $jornada) {
                if ($fecha->format('N') === '7') {
                    $fecha = $fecha->modify('+1 day');
                }
                $cantidad = $jornada === 0 ? 6 : 5;
                foreach (range(1, 3) as $numeroVet) {
                    foreach (range(0, $cantidad - 1) as $turno) {
                        $i = ($numeroVet - 1) * $cantidad + $turno;
                        $this->reservar($nombres[$i], ['control_preventivo'], $fecha, $reloj, $this->usuarios['sanroque-vet-' . $numeroVet], $this->tipo($tipos[$turno % count($tipos)]), 'futuro', $numeroVet . '-' . $turno);
                    }
                }
                $fecha = $fecha->modify('+1 day');
            }
            $this->omitirCorreos();
            return $this->solicitudesGuion($dia, $reloj, 'base');
        });
    }

    private function solicitud(string $nombre, array $sintomas, array $declarados, DateTimeImmutable $dia, Closure $reloj, int $vet, int $tipo, string $fase, string $etiqueta, bool $portal): array
    {
        $mascota = $this->mascotas[$nombre];
        SemillaDemo::como($portal ? $mascota['propietario'] : $vet, $this->clinicas['sanroque'], $portal ? Roles::PROPIETARIO : Roles::VETERINARIO);
        $modelo = new SolicitudAgenda($this->db, $reloj);
        if ($portal) {
            $modelo = $modelo->enClinicaDelPropietario($this->clinicas['sanroque']);
        }
        $entrada = ['id_mascota' => $mascota['id'], 'id_veterinario' => $vet, 'id_tipo_cita' => $tipo, 'fecha' => $dia->format('Y-m-d'), 'sintomas' => GuionDemo::episodios($sintomas), 'clave_operacion' => $this->clave($fase, $dia->format('Y-m-d'), 'evaluar-' . $etiqueta)] + $declarados;
        return [$modelo, $entrada, $modelo->evaluar($entrada)];
    }

    private function reservar(string $nombre, array $sintomas, DateTimeImmutable $dia, Closure $reloj, int $vet, int $tipo, string $fase, string $etiqueta): int
    {
        [$modelo, $entrada, $resultado] = $this->solicitud($nombre, $sintomas, [], $dia, $reloj, $vet, $tipo, $fase, $etiqueta, false);
        $horas = $modelo->horas($resultado['id_solicitud'], $entrada);
        if ($resultado['nivel'] !== 'naranja') {
            $ahora = ($reloj)();
            $horas = array_values(array_filter($horas, static fn (string $hora): bool => IntervalosAgenda::fecha($dia->format('Y-m-d') . ' ' . Cita::normalizarHora($hora)) > $ahora));
        }
        if ($horas === []) {
            throw new RuntimeException('No cabe la cita demo de ' . $nombre . ' el ' . $dia->format('Y-m-d') . '; no se fuerza ni se modifica la agenda.');
        }
        unset($entrada['clave_operacion']);
        $respuesta = $modelo->reservar($entrada + ['id_solicitud' => $resultado['id_solicitud'], 'hora' => $horas[0], 'motivo' => 'Demo: ' . $nombre, 'clave_operacion' => $this->clave($fase, $dia->format('Y-m-d'), 'reservar-' . $etiqueta)]);
        if (!isset($respuesta['id_cita'])) {
            throw new RuntimeException('La reserva requiere una decisión adicional; no se fuerza el guion de ' . $nombre . '.');
        }
        return (int) $respuesta['id_cita'];
    }

    private function solicitudesGuion(DateTimeImmutable $dia, Closure $reloj, string $fase): array
    {
        $salida = [];
        foreach (GuionDemo::casos() as $nombre => [$sintomas, $esperado, $declarados]) {
            [, , $respuesta] = $this->solicitud($nombre, $sintomas, $declarados, $dia, $reloj, $this->usuarios['sanroque-vet-1'], $this->tipo(), $fase, $nombre, true);
            $salida[$nombre] = ['nivel' => $respuesta['nivel'], 'revision' => $respuesta['revision'], 'id_solicitud' => $respuesta['id_solicitud']];
            if ($respuesta['nivel'] !== $esperado || ($nombre === 'Kira' && $respuesta['revision'])) {
                throw new RuntimeException('El motor devuelve ' . $respuesta['nivel'] . ' para ' . $nombre . ', esperado ' . $esperado . '. No se altera el resultado clínico.');
            }
        }
        return $salida;
    }

    /** S.1: jornada continua aprobada, con reserva de capacidad hasta las 20:00. */
    private function horarioEscena(DateTimeImmutable $h): void
    {
        $apertura = $this->filas('SELECT * FROM horarios_clinica WHERE id_clinica=?', [$this->clinicas['sanroque']]);
        $dia = (int) $h->format('N');
        if ($dia === 7 || $h->format('H:i:s') < '08:00:00' || $h->format('H:i:s') > '18:00:00') {
            throw new RuntimeException('La escena solo se permite de lunes a sábado, entre 8:00 y 18:00, dentro del horario aprobado 7:00–20:00.');
        }
        foreach ([[-35, -5], [0, 120]] as [$desde, $hasta]) {
            $inicio = $h->modify($desde . ' minutes');
            $fin = $h->modify($hasta . ' minutes');
            if ($inicio->format('Y-m-d') !== $h->format('Y-m-d') || !CoordinacionHorarios::contiene($apertura, $dia, $inicio->format('H:i:s'), $fin->format('H:i:s'))) {
                throw new RuntimeException('La escena relativa a esta hora no cabe en el horario aprobado. Ejecútala en jornada compatible de lunes a sábado; no se abre una excepción.');
            }
        }
    }

    public function escena(): array
    {
        $ahora = ($this->reloj)();
        $this->horarioEscena($ahora);
        $h = $ahora->setTime((int) $ahora->format('H'), intdiv((int) $ahora->format('i'), 5) * 5);
        $this->horarioEscena($h);
        return $this->unidad(function () use ($ahora, $h): array {
            $this->horarioEscena($h);
            $this->limpiarDia($h->format('Y-m-d'));
            $clinica = $this->clinicas['sanroque'];
            $vet = $this->usuarios['sanroque-vet-1'];
            $this->completarJornadaAnterior($ahora);
            SemillaDemo::como($vet, $clinica, Roles::VETERINARIO);
            foreach (['Nina', 'Apolo', 'Lola'] as $i => $nombre) {
                $fecha = $h->modify('-' . (120 - 30 * $i) . ' minutes');
                if ($fecha->format('H:i') >= '07:00') {
                    ($this->historia)($this->mascotas[$nombre], $clinica, $vet, $fecha, 'diagnostico.gastritis_aguda', false, 25);
                }
            }
            $inicio = $h->modify('-35 minutes');
            $relojPasado = static fn (): DateTimeImmutable => $inicio->modify('-5 minutes');
            $id = $this->reservar('Mila', ['control_preventivo'], $h, $relojPasado, $vet, $this->tipo(), 'escena', 'en-curso');
            // La reserva normal respeta la grilla; el inicio real expresa el hecho simulado aprobado por el dueño.
            $citas = new Cita($this->db, $this->reloj);
            $citas->iniciarAtencion($citas->getById($id), $inicio->format('Y-m-d H:i:s'));
            $rocco = 0;
            $posteriores = ['Rocco' => ['picazon', 'costras_piel', 'perdida_pelo'], 'Max' => ['lame_patas', 'picazon'], 'Mishi' => ['vomito'], 'Toby' => ['control_preventivo']];
            if ($h->format('H:i') >= '17:00') {
                $posteriores = array_slice($posteriores, 0, 2, true);
            }
            foreach ($posteriores as $nombre => $sintomas) {
                $relojReserva = $nombre === 'Rocco' ? static fn (): DateTimeImmutable => $h->modify('-5 minutes') : $this->reloj;
                $tipo = $this->tipo($nombre === 'Rocco' ? 'Consulta general/Enfermedad' : 'Vacunación/Revisión rápida');
                $cita = $this->reservar($nombre, $sintomas, $h, $relojReserva, $vet, $tipo, 'escena', $nombre);
                if ($nombre === 'Rocco') {
                    $rocco = $cita;
                }
            }
            SemillaDemo::como($vet, $clinica, Roles::VETERINARIO);
            $paciente = (new PacienteDiagnostico($this->db))->contexto($this->mascotas['Bruno']['id'], $this->grafo, $ahora, []);
            $episodios = GuionDemo::episodios(['vomito', 'decaimiento']);
            $marcas = ValidadorTriage::episodios($episodios, false, $this->grafo);
            $nivel = MotorTriage::calcular($marcas, [], $paciente, $this->grafo, $ahora)['nivel'];
            if ($nivel !== 'naranja') {
                throw new RuntimeException('El motor no da naranja al sobrecupo demo. No se fuerza.');
            }
            $horas = (new DisponibilidadAgenda($this->db, $this->reloj))->sugerir($vet, $this->mascotas['Bruno']['id'], $h->format('Y-m-d'), 30, 10, null, true);
            if ($horas === []) {
                throw new RuntimeException('No cabe el sobrecupo demo en la jornada.');
            }
            $citas->registrarClasificada(['id_mascota' => $this->mascotas['Bruno']['id'], 'id_veterinario' => $vet, 'id_tipo_cita' => $this->tipo(), 'fecha' => $h->format('Y-m-d'), 'hora' => $horas[0], 'motivo' => 'Demo: sobrecupo naranja calculado', 'clave_operacion' => $this->clave('escena', $h->format('Y-m-d'), 'sobrecupo')], $nivel, true);
            (new LlegadaAgenda($this->db, $this->reloj))->llegada($rocco, $this->clave('escena', $h->format('Y-m-d'), 'llegada-Rocco'));
            $niveles = $this->solicitudesGuion($h, $this->reloj, 'escena');
            SemillaDemo::como($vet, $clinica, Roles::VETERINARIO);
            $cascada = (new CascadaAgenda($this->db, $this->reloj))->procesar($h->format('Y-m-d'));
            $fuera = $this->filas("SELECT id_cita FROM citas WHERE id_clinica=? AND fecha=? AND estado IN ('pendiente','confirmada') AND (inicio_estimado IS NULL OR ADDTIME(fin_estimado,SEC_TO_TIME(margen_minutos*60))>CONCAT(fecha,' 20:00:00'))", [$clinica, $h->format('Y-m-d')]);
            $revision = $this->filas("SELECT id_cita FROM citas WHERE id_clinica=? AND fecha=? AND estado_reajuste IN ('pendiente_revision','pendiente_reasignacion','pendiente_reprogramacion')", [$clinica, $h->format('Y-m-d')]);
            $propuestas = $this->filas("SELECT r.id_reasignacion FROM reasignaciones r JOIN citas c ON c.id_cita=r.id_cita AND c.id_clinica=r.id_clinica WHERE r.id_clinica=? AND c.fecha=? AND r.estado='pendiente'", [$clinica, $h->format('Y-m-d')]);
            if ($fuera !== [] || $revision !== [] || $propuestas !== []) {
                throw new RuntimeException('La jornada de San Roque no queda resuelta antes del cierre de las 20:00; se conserva la agenda anterior.');
            }
            $this->omitirCorreos($rocco);
            return ['dia' => $h->format('Y-m-d'), 'hora' => $h->format('H:i'), 'en_curso' => $id, 'rocco' => $rocco, 'niveles' => $niveles, 'cascada' => $cascada];
        });
    }

    private function completarJornadaAnterior(DateTimeImmutable $ahora): void
    {
        $clinica = $this->clinicas['sanroque'];
        $principal = $this->usuarios['sanroque-vet-1'];
        $citas = $this->filas("SELECT * FROM citas WHERE id_clinica=? AND fecha=? AND id_veterinario<>? AND estado IN ('pendiente','confirmada') AND hora<? ORDER BY id_veterinario,hora,id_cita", [$clinica, $ahora->format('Y-m-d'), $principal, $ahora->format('H:i:s')]);
        foreach ($citas as $cita) {
            // Decisión del dueño S.1: conservar las reservas y acreditar la actividad anterior, sin forzar reajustes.
            $inicio = IntervalosAgenda::fecha($cita['fecha'] . ' ' . $cita['hora']);
            $fin = min($inicio->modify('+' . $cita['duracion_minutos'] . ' minutes'), $ahora);
            $vet = (int) $cita['id_veterinario'];
            SemillaDemo::como($vet, $clinica, Roles::VETERINARIO);
            $modelo = new Cita($this->db, $this->reloj);
            $modelo->iniciarAtencion($cita, $inicio->format('Y-m-d H:i:s'));
            $modelo->completarAtencion($modelo->getById((int) $cita['id_cita']), $fin->format('Y-m-d H:i:s'));
        }
    }

    private function omitirCorreos(?int $rocco = null): void
    {
        // S.1: datos ficticios sin ráfaga de correos; los recordatorios diarios conservan su comportamiento real.
        $sql = "UPDATE eventos_agenda SET estado='omitido',error_tecnico='Demo: correo omitido al preparar datos ficticios',fecha_resultado=CURRENT_TIMESTAMP WHERE id_clinica=? AND canal='correo' AND estado IN ('pendiente','error')";
        $parametros = [$this->clinicas['sanroque']];
        if ($rocco !== null) {
            $sql .= ' AND (id_cita IS NULL OR id_cita<>?)';
            $parametros[] = $rocco;
        }
        $this->db->prepare($sql)->execute($parametros);
    }

    private function limpiarDia(string $dia): void
    {
        $clinica = $this->clinicas['sanroque'];
        $vet = $this->usuarios['sanroque-vet-1'];
        $citas = $this->filas('SELECT id_cita FROM citas WHERE id_clinica=? AND id_veterinario=? AND fecha=?', [$clinica, $vet, $dia]);
        $solicitudes = $this->filas("SELECT id_solicitud FROM solicitudes_agenda WHERE id_clinica=? AND JSON_UNQUOTE(JSON_EXTRACT(contexto_triage,'$.entrada.fecha'))=? AND JSON_UNQUOTE(JSON_EXTRACT(contexto_triage,'$.entrada.id_veterinario'))=?", [$clinica, $dia, (string) $vet]);
        $operaciones = [];
        foreach (['en-curso', 'Rocco', 'Max', 'Mishi', 'Toby', 'Simón', 'Luna', 'Kira', 'Nala'] as $etiqueta) {
            foreach (['evaluar-', 'reservar-'] as $accion) {
                $operaciones[] = $this->clave('escena', $dia, $accion . $etiqueta);
                $operaciones[] = $this->clave('base', $dia, $accion . $etiqueta);
            }
        }
        foreach (['sobrecupo', 'llegada-Rocco'] as $etiqueta) {
            $operaciones[] = $this->clave('escena', $dia, $etiqueta);
        }
        $raices = ['citas' => $citas, 'solicitudes_agenda' => $solicitudes, 'operaciones_agenda' => $this->filas('SELECT id_operacion FROM operaciones_agenda WHERE id_clinica=? AND clave_operacion IN (' . implode(',', array_fill(0, count($operaciones), '?')) . ')', [$clinica, ...$operaciones])];
        (new LimpiezaDemo($this->db))->eliminarDependencias($raices, [$clinica]);
    }
}
