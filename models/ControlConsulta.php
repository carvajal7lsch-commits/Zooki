<?php
require_once __DIR__ . '/ModeloHistoria.php';
require_once __DIR__ . '/Consulta.php';
require_once __DIR__ . '/SolicitudAgenda.php';
require_once __DIR__ . '/MedicacionVigente.php';
require_once __DIR__ . '/../helpers/RangosVitales.php';

/** RN-431: esta unidad se inicia después del commit clínico; nunca revierte la consulta. */
final class ControlConsulta extends ModeloHistoria
{
    public const DIAS_CALENDARIO_MAX = 62;
    private Closure $reloj;

    public function __construct(PDO $db, ?callable $reloj = null)
    {
        parent::__construct($db);
        $this->reloj = Closure::fromCallable($reloj ?? static fn () => new DateTimeImmutable('now', new DateTimeZone('America/Bogota')));
    }

    public function configuracion(): array
    {
        $q = $this->conn->prepare('SELECT control_busqueda_dias, control_dias_sin_duracion FROM clinicas WHERE id_clinica = ?');
        $q->execute([$this->clinica()]);
        $fila = $q->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            throw new AccesoDenegado(403, 'Clínica no disponible.');
        }
        return array_map('intval', $fila);
    }

    public function contexto(int $idMascota): array
    {
        $this->exigirVeterinario();
        $this->exigirMascotaActiva($idMascota);
        $grafo = (new GrafoConocimiento($this->conn))->instantanea();
        $paciente = (new PacienteDiagnostico($this->conn))->contexto($idMascota, $grafo, ($this->reloj)(), []);
        $ficha = (new Mascota($this->conn))->getById($idMascota);
        $especie = (string) ($ficha['nombre_especie'] ?? $paciente['especie'] ?? '');
        $tamanio = $paciente['raza']['atributos']['tamanio'] ?? null;
        $ultimoPeso = $ficha['peso'] ?? null;
        foreach ((new Consulta($this->conn))->findByMascota($idMascota) as $consulta) {
            if ($consulta['peso'] !== null) {
                $ultimoPeso = $consulta['peso'];
                break;
            }
        }
        $horarios = $this->conn->prepare('SELECT 1 FROM horarios_veterinario WHERE id_clinica=? AND id_veterinario=? AND activo=1 LIMIT 1');
        $horarios->execute([$this->clinica(), Contexto::idUsuario()]);
        return $this->configuracion() + [
            'tiene_horario' => (bool) $horarios->fetchColumn(),
            'id_clinica' => $this->clinica(),
            'id_veterinario' => Contexto::idUsuario(),
            'referencias' => RangosVitales::para($especie, $tamanio),
            'aviso_referencias' => RangosVitales::AVISO,
            'ultimo_peso' => $ultimoPeso,
            'cambio_peso' => RangosVitales::CAMBIO_PESO,
            'ficha' => array_intersect_key($ficha, array_flip(['sexo', 'esterilizado', 'fecha_nacimiento', 'identidad_editable'])),
        ];
    }

    private function exigirVeterinario(): void
    {
        if (Contexto::rolActivo() !== Roles::VETERINARIO) {
            $this->denegarAcceso('consultas', null, 'Próximo control reservado al veterinario (RN-431)');
        }
    }

    public function diasAbiertos(int $idMascota, string $desde, string $hasta): array
    {
        $this->exigirVeterinario();
        $this->exigirMascotaActiva($idMascota);
        $inicio = ValidadorClinico::fecha($desde);
        $fin = ValidadorClinico::fecha($hasta);
        if ($inicio === null || $fin === null || $inicio > $fin) {
            throw new InvalidArgumentException('Indica un intervalo válido para el calendario.');
        }
        $primero = IntervalosAgenda::fecha($inicio . ' 00:00:00');
        $ultimo = IntervalosAgenda::fecha($fin . ' 00:00:00');
        if ((int) $primero->diff($ultimo)->days >= self::DIAS_CALENDARIO_MAX) {
            throw new InvalidArgumentException('Consulta como máximo dos meses del calendario.');
        }
        $disponibilidad = new DisponibilidadAgenda($this->conn, $this->reloj);
        $disponibilidad->autorizar(Contexto::idUsuario(), $idMascota);
        $dias = [];
        for ($dia = $primero; $dia <= $ultimo; $dia = $dia->modify('+1 day')) {
            if ($dia->format('Y-m-d') < ($this->reloj)()->format('Y-m-d')) {
                continue;
            }
            $lectura = $disponibilidad->diaDelVeterinario(Contexto::idUsuario(), $dia->format('Y-m-d'));
            $ocupados = array_merge($lectura['ausencias'], array_map(static fn (array $c): array => [$c[0], $c[1]], $lectura['cierres']));
            if (IntervalosAgenda::restar($lectura['jornada'], $ocupados) !== []) {
                $dias[] = $dia->format('Y-m-d');
            }
        }
        return $dias;
    }

    public function programar(int $idConsulta, ?string $fechaSolicitada): array
    {
        $this->exigirVeterinario();
        if ($this->conn->inTransaction()) {
            throw new LogicException('El control debe comenzar después del commit de la consulta.');
        }
        $q = $this->conn->prepare('SELECT id_consulta, id_mascota, id_veterinario FROM consultas WHERE id_consulta = ? AND id_clinica = ?');
        $q->execute([$idConsulta, $this->clinica()]);
        $consulta = $q->fetch(PDO::FETCH_ASSOC);
        if (!$consulta || (int) $consulta['id_veterinario'] !== Contexto::idUsuario()) {
            $this->denegarAcceso('consultas', $idConsulta, 'Control de una consulta ajena (RN-407, RN-431)');
        }
        $idMascota = (int) $consulta['id_mascota'];
        $this->exigirMascotaActiva($idMascota);
        $configuracion = $this->configuracion();
        $fecha = $fechaSolicitada !== null ? ValidadorClinico::fecha($fechaSolicitada) : null;
        if ($fechaSolicitada !== null && $fecha === null) {
            throw new InvalidArgumentException('Fecha de control no válida.');
        }
        if ($fecha === null) {
            $q = $this->conn->prepare('SELECT MAX(t.fecha_fin) FROM tratamientos t JOIN consultas c ON c.id_consulta=t.id_consulta WHERE t.id_consulta=? AND c.id_clinica=?');
            $q->execute([$idConsulta, $this->clinica()]);
            $fecha = $q->fetchColumn() ?: ($this->reloj)()->modify('+' . $configuracion['control_dias_sin_duracion'] . ' days')->format('Y-m-d');
        }
        if ($fecha < ($this->reloj)()->format('Y-m-d')) {
            throw new InvalidArgumentException('El control no puede quedar en una fecha pasada.');
        }
        $propuesta = $fecha;
        $veterinario = Contexto::idUsuario();
        return (new CoordinadorAgenda($this->conn))->ejecutar($this->clinica(), static fn (): array => ['usuarios' => [$veterinario], 'mascotas' => [$idMascota]], function () use ($idConsulta, $idMascota, $veterinario, $propuesta, $configuracion): array {
            // RN-407/B1.1: una conciliación o desvinculación pudo ganar mientras esperábamos la clínica.
            $q = $this->conn->prepare('SELECT id_mascota, id_veterinario FROM consultas WHERE id_consulta=? AND id_clinica=?');
            $q->execute([$idConsulta, $this->clinica()]);
            $actual = $q->fetch(PDO::FETCH_ASSOC);
            if (!$actual || (int) $actual['id_mascota'] !== $idMascota || (int) $actual['id_veterinario'] !== $veterinario) {
                throw new InvalidArgumentException('La identidad de la consulta cambió. Abre su ficha actual para agendar el control.');
            }
            $this->exigirMascotaActiva($idMascota);
            $clave = hash('sha256', 'control_consulta:' . $idConsulta);
            return (new OperacionAgenda($this->conn))->ejecutar('control_consulta', $clave, ['consulta' => $idConsulta, 'fecha' => $propuesta], function () use ($idConsulta, $idMascota, $veterinario, $propuesta, $configuracion, $clave): array {
                $q = $this->conn->prepare("SELECT id_tipo_cita FROM tipos_cita WHERE id_clinica=? AND activo=1 AND (LOWER(nombre_tipo) LIKE '%control%' OR LOWER(nombre_tipo)='consulta general') ORDER BY CASE WHEN LOWER(nombre_tipo) LIKE '%control%' THEN 0 ELSE 1 END, id_tipo_cita LIMIT 1");
                $q->execute([$this->clinica()]);
                $tipo = $q->fetchColumn();
                if (!$tipo) {
                    throw new InvalidArgumentException('No hay tipo Control ni Consulta general activo.');
                }
                $citas = new Cita($this->conn, $this->reloj);
                for ($desplazamiento = 0; $desplazamiento < $configuracion['control_busqueda_dias']; $desplazamiento++) {
                    $fecha = IntervalosAgenda::fecha($propuesta . ' 00:00:00')->modify('+' . $desplazamiento . ' days')->format('Y-m-d');
                    $horas = $citas->sugerencias($veterinario, $idMascota, (int) $tipo, $fecha);
                    if (!$horas) {
                        continue;
                    }
                    $entrada = ['id_mascota' => $idMascota, 'id_veterinario' => $veterinario, 'id_tipo_cita' => (int) $tipo, 'fecha' => $fecha, 'sintomas' => [['codigo' => 'sintoma.control_preventivo', 'tiempo' => 'no_aplica']]];
                    $solicitudes = new SolicitudAgenda($this->conn, $this->reloj);
                    $evaluacion = $solicitudes->evaluar($entrada + ['clave_operacion' => hash('sha256', $clave . ':evaluar')]);
                    if ($evaluacion['nivel'] !== 'verde' || !$evaluacion['reservable']) {
                        throw new InvalidArgumentException('El control requiere valoración antes de reservar; abre Nueva cita.');
                    }
                    $reserva = $solicitudes->reservar($entrada + ['id_solicitud' => $evaluacion['id_solicitud'], 'hora' => $horas[0], 'motivo' => 'Control de consulta #' . $idConsulta, 'ignore_warning' => '1', 'clave_operacion' => hash('sha256', $clave . ':reservar')]);
                    if (!isset($reserva['id_cita'])) {
                        throw new InvalidArgumentException('No se pudo reservar el control; abre Nueva cita.');
                    }
                    return ['creado' => true, 'id_cita' => $reserva['id_cita'], 'fecha' => $fecha, 'hora' => $reserva['hora'], 'fecha_ajustada' => $fecha !== $propuesta, 'message' => ($fecha !== $propuesta ? 'El día propuesto no tenía hueco. ' : '') . 'Control agendado para ' . $fecha . ' a las ' . substr($reserva['hora'], 0, 5) . '.'];
                }
                throw new InvalidArgumentException('No encontramos horario para el control dentro del plazo configurado.');
            });
        });
    }
}
