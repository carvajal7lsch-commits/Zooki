<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Panel.php';
require_once __DIR__ . '/../helpers/ResumenPanel.php';

/**
 * Datos de los paneles de inicio. Se calculan en el servidor y la vista los
 * pinta directamente: sin peticiones AJAX al cargar, sin datos de ejemplo y
 * con el "hoy" de la clínica, no el del servidor.
 */
class PanelController
{
    private PDO $db;
    private Panel $panel;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? (new Database())->getConnection();
        $this->panel = new Panel($this->db);
    }

    /** HU-18 / HU-20: el día del veterinario. */
    public function datosVeterinario(string $doc, ?DateTimeImmutable $ahora = null): array
    {
        $ahora = $ahora ?? $this->ahora();
        $this->revisarAtencionesAbiertas($ahora);
        $hoy = $ahora->format('Y-m-d');

        $agenda = $this->conAccion($this->panel->citasDelDia($hoy, $doc), $ahora);
        $siguiente = ResumenPanel::siguiente($agenda, $ahora);

        // Las atenciones de hoy ya se ven en la agenda con su botón; aquí solo
        // se listan las que exigen algo aparte: las abiertas de días anteriores
        // y las que quedaron sin cerrar.
        $pendientes = array_values(array_filter(
            $this->conAccion($this->panel->atencionesAbiertas($doc), $ahora),
            fn($c) => $c['estado'] === 'sin_cerrar' || $c['fecha'] !== $hoy
        ));

        $recordatorios = $this->panel->recordatoriosDeSusPacientes(
            $doc,
            $hoy,
            $ahora->modify('+7 days')->format('Y-m-d')
        );

        return [
            'fecha' => ResumenPanel::fechaLarga($ahora),
            'saludo' => $this->saludo($ahora),
            'contadores' => ResumenPanel::contadores($agenda),
            'siguiente' => $siguiente,
            'agenda' => $agenda,
            'pendientes' => array_map(fn($c) => $c + ['dia' => ResumenPanel::etiquetaDia($c['fecha'], $ahora)], $pendientes),
            'recordatorios' => ResumenPanel::agruparPorDia($recordatorios, $ahora),
            'total_recordatorios' => count($recordatorios),
        ];
    }

    /** HU-57: la operación de la clínica para el administrador. */
    public function datosAdministrador(?DateTimeImmutable $ahora = null): array
    {
        $ahora = $ahora ?? $this->ahora();
        $this->revisarAtencionesAbiertas($ahora);
        $hoy = $ahora->format('Y-m-d');

        $citas = $this->panel->citasDelDia($hoy);

        // El mes en curso se compara con el mismo tramo del mes anterior (del 1
        // al día de hoy), no con el mes completo, que siempre saldría mayor.
        $inicioMes = $ahora->modify('first day of this month')->setTime(0, 0);
        $manana = $ahora->modify('+1 day')->setTime(0, 0);
        $inicioAnterior = $inicioMes->modify('-1 month');
        $diasTranscurridos = (int) $ahora->format('j');
        $finAnterior = min(
            $inicioAnterior->modify("+$diasTranscurridos days"),
            $inicioMes
        );
        $consultasMes = $this->panel->contarConsultas($inicioMes->format('Y-m-d H:i:s'), $manana->format('Y-m-d H:i:s'));
        $consultasAnterior = $this->panel->contarConsultas($inicioAnterior->format('Y-m-d H:i:s'), $finAnterior->format('Y-m-d H:i:s'));

        $abiertas = $this->panel->atencionesAbiertas();
        $desdeTendencia = $inicioMes->modify('-5 months')->format('Y-m-d');

        return [
            'fecha' => ResumenPanel::fechaLarga($ahora),
            'contadores' => ResumenPanel::contadores($citas),
            'consultas_mes' => $consultasMes,
            'variacion_consultas' => ResumenPanel::variacion($consultasMes, $consultasAnterior),
            'mes_anterior' => $this->nombreMes($inicioAnterior),
            'citas' => $citas,
            'carga' => $this->panel->cargaPorVeterinario($hoy),
            'pendientes' => [
                'sin_cerrar' => $this->porVeterinario(array_filter($abiertas, fn($c) => $c['estado'] === 'sin_cerrar')),
                'en_curso_otro_dia' => $this->porVeterinario(array_filter($abiertas, fn($c) => $c['estado'] === 'en_curso' && $c['fecha'] !== $hoy)),
                'por_confirmar' => $this->panel->contarPorConfirmar($hoy, $ahora->modify('+7 days')->format('Y-m-d')),
                'sin_marcar' => $this->panel->contarSinMarcar(
                    $ahora->modify('-30 days')->format('Y-m-d'),
                    $hoy,
                    $ahora->format('H:i:s')
                ),
            ],
            'tendencia' => ResumenPanel::serieMensual(
                $this->panel->tendenciaMensual($desdeTendencia, $hoy),
                $ahora
            ),
        ];
    }

    /** Cuenta citas por veterinario: ['Ana Gómez' => 2, ...], de más a menos. */
    private function porVeterinario(array $citas): array
    {
        $conteo = [];
        foreach ($citas as $cita) {
            $nombre = $cita['veterinario'];
            $conteo[$nombre] = ($conteo[$nombre] ?? 0) + 1;
        }
        arsort($conteo);
        return $conteo;
    }

    private function conAccion(array $citas, DateTimeImmutable $ahora): array
    {
        return array_map(fn($c) => $c + ['accion' => ResumenPanel::accion($c, $ahora)], $citas);
    }

    // RN-410: el panel es la primera pantalla del día, así que también revisa
    // las atenciones abiertas, igual que el calendario. Un fallo no lo tumba.
    private function revisarAtencionesAbiertas(DateTimeImmutable $ahora): void
    {
        try {
            require_once __DIR__ . '/../helpers/VigilanteAtenciones.php';
            (new VigilanteAtenciones($this->db))->revisar($ahora);
        } catch (Throwable $e) {
            error_log('No se pudieron revisar las atenciones abiertas: ' . $e->getMessage());
        }
    }

    private function saludo(DateTimeImmutable $ahora): string
    {
        $hora = (int) $ahora->format('G');
        if ($hora < 12) {
            return 'Buenos días';
        }
        return $hora < 19 ? 'Buenas tardes' : 'Buenas noches';
    }

    private function nombreMes(DateTimeImmutable $mes): string
    {
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        return $meses[(int) $mes->format('n') - 1];
    }

    private function ahora(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(ReglaAtencion::ZONA));
    }
}
