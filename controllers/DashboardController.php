<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Panel.php';
require_once __DIR__ . '/../models/Vacuna.php';
require_once __DIR__ . '/../models/Desparasitacion.php';
require_once __DIR__ . '/../helpers/ReglaAtencion.php';
require_once __DIR__ . '/../helpers/RespuestaJson.php';

/**
 * Pendientes del día para el aviso del navegador (extras.js). Todo es de la
 * clínica activa (RNF-11) y el veterinario ve solo sus citas (RE-6.1.4).
 *
 * C7: las estadísticas, gráficas y la línea de tiempo sin uso se
 * retiraron; los paneles salen de PanelController.
 */
class DashboardController
{
    /** Citas que aún esperan atención en el día. */
    private const POR_ATENDER = ['pendiente', 'confirmada', 'en_curso'];

    private PDO $db;

    /** @var callable(): DateTimeImmutable */
    private $reloj;

    public function __construct(?PDO $db = null, ?callable $reloj = null)
    {
        $this->db = $db ?? (new Database())->getConnection();
        $this->reloj = $reloj ?? fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone(ReglaAtencion::ZONA));
    }

    public function getPendientesAjax(): void
    {
        RespuestaJson::consulta(function (): void {
            RespuestaJson::enviar(['success' => true, 'pendientes' => $this->pendientes()]);
        }, 'C7 pendientes del día');
    }

    private function pendientes(): array
    {
        $hoy = ($this->reloj)();
        $desde = $hoy->format('Y-m-d');
        $hasta = $hoy->modify('+7 days')->format('Y-m-d');
        $idVeterinario = Contexto::rolActivo() === Roles::VETERINARIO ? Contexto::idUsuario() : null;

        $citas = (new Panel($this->db))->citasDelDia($desde, $idVeterinario);

        return [
            'citas_hoy' => array_values(array_filter($citas, fn ($c) => in_array($c['estado'], self::POR_ATENDER, true))),
            'vacunas_proximas' => (new Vacuna($this->db))->pendientesEntre($desde, $hasta),
            'desparasitaciones_proximas' => (new Desparasitacion($this->db))->pendientesEntre($desde, $hasta),
        ];
    }
}
