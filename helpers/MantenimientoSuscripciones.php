<?php
require_once __DIR__ . '/../models/AvisoSuscripcion.php';
require_once __DIR__ . '/../models/ConciliadorPago.php';
require_once __DIR__ . '/ClienteWompiHttp.php';

/** RE-0.5.3/6: trabajador de metadatos; nunca usa una sesión de administrador. */
final class MantenimientoSuscripciones
{
    public function __construct(private PDO $db, private mixed $reloj = null, private ?ClienteWompi $cliente = null, private ?ConfiguracionWompi $configuracion = null)
    {
    }

    public function ejecutar(bool $revisar = false, ?string $solo = null, ?callable $transportar = null): array
    {
        if (!in_array($solo, [null, 'vencimientos', 'avisos', 'conciliacion'], true)) {
            throw new InvalidArgumentException('Solo se admite vencimientos, avisos o conciliacion.');
        }
        $resultado = ['transiciones' => 0, 'avisos_reservados' => 0, 'enviados' => 0, 'errores' => 0, 'inciertos' => 0, 'revision' => [], 'tarifas_personalizadas' => []];
        if ($solo === null || $solo === 'conciliacion') {
            $resultado['conciliacion'] = ['pendientes' => 0, 'conciliados' => 0, 'errores' => 0];
            if ((int) $this->db->query("SELECT COUNT(*) FROM wompi_eventos WHERE estado IN ('pendiente','procesando','error')")->fetchColumn() > 0) {
                try {
                    $config = $this->configuracion ?? new ConfiguracionWompi();
                    $cliente = $this->cliente ?? new ClienteWompiHttp($config);
                    $resultado['conciliacion'] = (new ConciliadorPago($this->db, $cliente, $config, $this->reloj))->ejecutar($revisar);
                    $resultado['errores'] += $resultado['conciliacion']['errores'];
                } catch (Throwable $error) {
                    $resultado['errores']++;
                }
            }
            if ($solo === 'conciliacion') {
                return $resultado;
            }
        }
        foreach ($this->db->query("SELECT id_plan FROM planes WHERE nombre='Profesional' AND (precio_mensual<>89990 OR precio_anual IS NULL OR precio_anual<>899900)")->fetchAll(PDO::FETCH_COLUMN) as $plan) {
            $resultado['tarifas_personalizadas'][] = (int) $plan;
        }
        $ids = $this->db->query("SELECT id_clinica FROM clinicas WHERE estado IN ('activa','suspendida') ORDER BY id_clinica")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            $s = new Suscripcion($this->db, (int) $id, $this->reloj);
            if ($revisar) {
                $estado = $s->estado();
                $resultado['revision'][] = ['id_clinica' => (int) $id, 'situacion' => $estado['situacion'], 'legado_sin_fecha' => $estado['legado_sin_fecha'], 'aviso' => AvisoSuscripcion::tipo($estado, $s->ahora())];
                continue;
            }
            try {
                $unidad = $s->bloqueada(function () use ($s, $solo, $id): array {
                    $transicion = false;
                    if ($solo !== 'avisos') {
                        $transicion = $s->sincronizar();
                    }
                    $reservados = (new AvisoSuscripcion($this->db, (int) $id, $this->reloj))->reservar($s->estado(), $solo === 'vencimientos');
                    return ['transiciones' => (int) $transicion, 'avisos_reservados' => $reservados];
                });
                $resultado['transiciones'] += $unidad['transiciones'];
                $resultado['avisos_reservados'] += $unidad['avisos_reservados'];
                if ($solo !== 'vencimientos' && $transportar !== null) {
                    $q = $this->db->prepare("SELECT id_aviso FROM avisos_suscripcion WHERE id_clinica=? AND estado IN ('pendiente','reclamado') ORDER BY id_aviso");
                    $q->execute([$id]);
                    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $aviso) {
                        $resultado['enviados'] += (int) (new AvisoSuscripcion($this->db, (int) $id, $this->reloj))->despachar((int) $aviso, $transportar);
                    }
                }
                $q = $this->db->prepare("SELECT COUNT(*) FROM avisos_suscripcion WHERE id_clinica=? AND estado='incierto'");
                $q->execute([$id]);
                $resultado['inciertos'] += (int) $q->fetchColumn();
            } catch (Throwable $error) {
                $resultado['errores']++;
                error_log('Suscripciones: fallo local en clínica #' . (int) $id);
            }
        }
        return $resultado;
    }
}
