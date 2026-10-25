<?php
require_once __DIR__ . '/EventoWompi.php';
require_once __DIR__ . '/Suscripcion.php';
require_once __DIR__ . '/../helpers/ClienteWompi.php';
require_once __DIR__ . '/../helpers/ConfiguracionWompi.php';

/** RE-0.5.13–16/23/24: GET fuera del candado; acreditación y auditoría atómicas. */
final class ConciliadorPago
{
    public function __construct(private PDO $db, private ClienteWompi $cliente, private ConfiguracionWompi $configuracion, private mixed $reloj = null)
    {
    }

    public function ejecutar(bool $revisar = false): array
    {
        // RE-0.5.15: reintentos/leases futuros no pueden tapar eventos nuevos detrás del primer lote.
        $q = $this->db->prepare("SELECT id_evento FROM wompi_eventos WHERE entorno=? AND estado IN ('pendiente','error','procesando') ORDER BY COALESCE(proxima_revision,lease_hasta,recibido_en),id_evento LIMIT 100");
        $q->execute([$this->configuracion->entorno()]);
        $ids = $q->fetchAll(PDO::FETCH_COLUMN);
        $resultado = ['pendientes' => count($ids), 'conciliados' => 0, 'errores' => 0];
        if ($revisar) {
            return $resultado;
        }
        $this->configuracion->validar(false);
        foreach ($ids as $id) {
            try {
                $resultado['conciliados'] += (int) $this->procesar((int) $id);
                $fallo = $this->db->prepare('SELECT codigo_error FROM wompi_eventos WHERE id_evento=? AND entorno=?');
                $fallo->execute([$id, $this->configuracion->entorno()]);
                $resultado['errores'] += (int) ($fallo->fetchColumn() === 'API_NO_DISPONIBLE');
            } catch (Throwable $error) {
                $resultado['errores']++;
                error_log('Conciliación pendiente del evento #' . (int) $id);
            }
        }
        return $resultado;
    }

    public function procesar(int $id): bool
    {
        $this->configuracion->validar(false);
        $inbox = new EventoWompi($this->db, $this->reloj);
        $evento = $inbox->reclamar($id, $this->configuracion->entorno());
        if (!$evento) {
            return false;
        }
        try {
            $remoto = $this->cliente->transaccion($evento['id_transaccion_wompi']);
            $comercioConcordante = true;
            $comercioRemoto = $remoto['merchant_id'] ?? ($remoto['merchant']['id'] ?? null);
            if ($comercioRemoto !== null) {
                $comercio = $this->cliente->comercio();
                $idComercio = $comercio['id'] ?? null;
                $comercioConcordante = (is_int($idComercio) || is_string($idComercio)) && (is_int($comercioRemoto) || is_string($comercioRemoto))
                    && (string) $idComercio !== '' && (string) $idComercio === (string) $comercioRemoto;
            }
        } catch (Throwable $error) {
            Transaccion::ejecutar($this->db, fn () => $inbox->terminar($evento, 'pendiente', 'API_NO_DISPONIBLE'));
            return false;
        }
        // Solo la referencia obtenida del GET autenticado localiza al beneficiario.
        $q = $this->db->prepare('SELECT p.*,i.id_intento FROM intentos_pago i JOIN pagos_suscripcion p ON p.id_pago=i.id_pago WHERE i.entorno=? AND i.referencia=?');
        $q->execute([$evento['entorno'], is_string($remoto['reference'] ?? null) ? $remoto['reference'] : '']);
        $orden = $q->fetch(PDO::FETCH_ASSOC);
        if (!$orden) {
            Transaccion::ejecutar($this->db, fn () => $inbox->terminar($evento, 'revision', 'REFERENCIA_DESCONOCIDA'));
            return false;
        }
        $s = new Suscripcion($this->db, (int) $orden['id_clinica'], $this->reloj);
        return $s->bloqueada(function () use ($s, $inbox, $evento, $remoto, $orden, $comercioConcordante): bool {
            // Clínica primero; la reclamación inicial ya está confirmada y sin candados.
            $q = $this->db->prepare('SELECT * FROM wompi_eventos WHERE id_evento=?' . ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''));
            $q->execute([$evento['id_evento']]);
            $actual = $q->fetch(PDO::FETCH_ASSOC);
            if ($actual['lease_token'] !== $evento['lease_token']) {
                return false;
            }
            $q = $this->db->prepare('SELECT * FROM pagos_suscripcion WHERE id_pago=? AND id_clinica=?');
            $q->execute([$orden['id_pago'], $orden['id_clinica']]);
            $pago = $q->fetch(PDO::FETCH_ASSOC);
            $q = $this->db->prepare('SELECT * FROM intentos_pago WHERE id_intento=? AND id_pago=?');
            $q->execute([$orden['id_intento'], $pago['id_pago']]);
            $intento = $q->fetch(PDO::FETCH_ASSOC);
            $estados = ['PENDING' => 'pendiente', 'APPROVED' => 'aprobado', 'DECLINED' => 'declinado', 'ERROR' => 'error', 'VOIDED' => 'anulado'];
            $status = is_string($remoto['status'] ?? null) ? $remoto['status'] : '';
            if (($remoto['id'] ?? null) !== $evento['id_transaccion_wompi'] || !is_int($remoto['amount_in_cents'] ?? null)
                || $remoto['amount_in_cents'] !== (int) $pago['total_centavos'] || ($remoto['currency'] ?? null) !== $pago['moneda']
                || !isset($estados[$status]) || !$comercioConcordante || (isset($remoto['environment']) && $remoto['environment'] !== $evento['entorno'])
                || (isset($remoto['merchant']['public_key']) && $remoto['merchant']['public_key'] !== $this->configuracion->valor('LLAVE_PUBLICA'))) {
                return $this->incidencia($s, $inbox, $evento, $pago, $intento, 'DATOS_DISCORDANTES');
            }
            if ($intento['id_transaccion_wompi'] !== null && $intento['id_transaccion_wompi'] !== $remoto['id']) {
                // Evidencia de otro cargo real para la misma referencia; nunca emitir otro POST.
                return $this->duplicadoExterno($s, $inbox, $evento, $pago, $intento, $remoto);
            }
            if ($status === 'VOIDED') {
                return $this->incidencia($s, $inbox, $evento, $pago, $intento, 'TRANSACCION_ANULADA');
            }
            if ($intento['estado'] === 'aprobado' && $status !== 'APPROVED') {
                $inbox->terminar($evento, 'procesado', 'ESTADO_ANTIGUO', (int) $intento['id_intento'], (int) $pago['id_clinica']);
                return true;
            }
            $nuevo = $estados[$status];
            $this->db->prepare('UPDATE intentos_pago SET id_transaccion_wompi=?,estado=?,consultado_en=? WHERE id_intento=? AND id_pago=?')
                ->execute([$remoto['id'], $nuevo, $s->ahora()->format('Y-m-d H:i:s'), $intento['id_intento'], $pago['id_pago']]);
            if ($intento['estado'] !== $nuevo || $intento['id_transaccion_wompi'] === null) {
                $s->auditar('intentos_pago', (int) $intento['id_intento'], ['estado' => $intento['estado']], ['estado' => $nuevo, 'id_transaccion' => $remoto['id']], 'GET autenticado Wompi');
            }
            if ($status === 'APPROVED') {
                $q = $this->db->prepare("SELECT 1 FROM intentos_pago WHERE id_pago=? AND id_intento<>? AND estado='aprobado' AND id_transaccion_wompi<>? LIMIT 1");
                $q->execute([$pago['id_pago'], $intento['id_intento'], $remoto['id']]);
                if ($q->fetchColumn()) {
                    return $this->incidencia($s, $inbox, $evento, $pago, $intento, 'COBRO_DUPLICADO');
                }
                $q = $this->db->prepare('SELECT 1 FROM suscripcion_periodos WHERE id_pago=?');
                $q->execute([$pago['id_pago']]);
                if (!$q->fetchColumn()) {
                    $estado = $s->estado();
                    if ((int) $estado['suscripcion']['id_suscripcion'] !== (int) $pago['id_suscripcion']
                        || (int) $estado['suscripcion']['version_periodo'] !== (int) $pago['version_periodo_base']) {
                        return $this->incidencia($s, $inbox, $evento, $pago, $intento, 'OBLIGACION_YA_MODIFICADA');
                    }
                    $q = $this->db->prepare('SELECT 1 FROM pagos_suscripcion p JOIN suscripcion_periodos r ON r.id_pago=p.id_pago WHERE p.id_clinica=? AND p.id_suscripcion=? AND p.version_periodo_base=?');
                    $q->execute([$pago['id_clinica'], $pago['id_suscripcion'], $pago['version_periodo_base']]);
                    if ($q->fetchColumn()) {
                        return $this->incidencia($s, $inbox, $evento, $pago, $intento, 'COBRO_DUPLICADO');
                    }
                    $this->acreditar($s, $pago, $estado);
                }
            }
            $inbox->terminar($evento, $status === 'PENDING' ? 'pendiente' : 'procesado', null, (int) $intento['id_intento'], (int) $pago['id_clinica']);
            return true;
        });
    }

    private function acreditar(Suscripcion $s, array $pago, array $estado): void
    {
        $ahora = $s->ahora();
        $inicio = VigenciaSuscripcion::inicioRenovacion($estado, $ahora);
        $q = $this->db->prepare('SELECT dia_ancla,mes_ancla,ciclo FROM suscripcion_periodos WHERE id_clinica=? AND id_suscripcion=? AND fin=? ORDER BY id_periodo DESC LIMIT 1');
        $q->execute([$pago['id_clinica'], $pago['id_suscripcion'], $inicio->format('Y-m-d H:i:s')]);
        $ancla = $q->fetch(PDO::FETCH_ASSOC);
        $origenAncla = $inicio;
        if (!$ancla && in_array($estado['situacion'], ['al_dia', 'gracia'], true)) {
            $origenAncla = VigenciaSuscripcion::fecha($estado['suscripcion']['periodo_inicio'] ?? $estado['suscripcion']['fecha_inicio'] . ' 00:00:00');
        }
        $dia = $ancla ? (int) $ancla['dia_ancla'] : (int) $origenAncla->format('d');
        $mes = $ancla ? (int) $ancla['mes_ancla'] : (int) $origenAncla->format('m');
        if ($pago['ciclo'] === 'anual' && ($ancla['ciclo'] ?? $estado['ciclo']) !== 'anual') {
            // D3: el año comprado empieza en el siguiente vencimiento, no en el mes del alta original.
            $mes = (int) $inicio->format('m');
        }
        $fin = VigenciaSuscripcion::extender($inicio, $pago['ciclo'], $dia, $mes);
        if ($fin->format('Y') > '9999') {
            throw new RuntimeException('Periodo fuera del calendario permitido.');
        }
        $this->db->prepare("INSERT INTO suscripcion_periodos (id_clinica,id_suscripcion,id_plan,id_pago,origen,ciclo,inicio,fin,dia_ancla,mes_ancla,hora_ancla,total_centavos,moneda,creado_en) VALUES (?,?,?,?,'wompi',?,?,?,?,?,?,?,?,?)")
            ->execute([$pago['id_clinica'], $pago['id_suscripcion'], $pago['id_plan'], $pago['id_pago'], $pago['ciclo'], $inicio->format('Y-m-d H:i:s'), $fin->format('Y-m-d H:i:s'), $dia, $mes, $inicio->format('H:i:s'), $pago['total_centavos'], $pago['moneda'], $ahora->format('Y-m-d H:i:s')]);
        $s->auditar('suscripcion_periodos', (int) $this->db->lastInsertId(), [], ['id_pago' => $pago['id_pago'], 'inicio' => $inicio->format('Y-m-d H:i:s'), 'fin' => $fin->format('Y-m-d H:i:s')], 'Periodo acreditado por evento y GET Wompi', null, 'INSERT');
        // Una renovación anticipada no mueve el inicio efectivo al futuro ni levanta la suspensión clínica.
        $desde = $estado['suscripcion']['periodo_inicio'] ?? $ahora->format('Y-m-d H:i:s');
        if (!in_array($estado['situacion'], ['al_dia', 'gracia'], true)) {
            $desde = $inicio->format('Y-m-d H:i:s');
        }
        $this->db->prepare("UPDATE suscripciones SET id_plan=?,estado='activa',ciclo=?,gestion_cobro='gestionada',periodo_inicio=?,periodo_fin=?,fecha_inicio=?,fecha_fin=?,estado_cobro='al_dia',al_dia=1,version_periodo=version_periodo+1 WHERE id_suscripcion=? AND id_clinica=?")
            ->execute([$pago['id_plan'], $pago['ciclo'], $desde, $fin->format('Y-m-d H:i:s'), substr($desde, 0, 10), $fin->modify('-1 second')->format('Y-m-d'), $pago['id_suscripcion'], $pago['id_clinica']]);
        $this->db->prepare('UPDATE clinicas SET id_plan=? WHERE id_clinica=?')->execute([$pago['id_plan'], $pago['id_clinica']]);
        $this->db->prepare("UPDATE pagos_suscripcion SET estado='confirmado',confirmado_en=? WHERE id_pago=? AND id_clinica=?")
            ->execute([$ahora->format('Y-m-d H:i:s'), $pago['id_pago'], $pago['id_clinica']]);
        $s->auditar('suscripciones', (int) $pago['id_suscripcion'], ['version' => $pago['version_periodo_base']], ['fin' => $fin->format('Y-m-d H:i:s'), 'id_pago' => $pago['id_pago']], 'Acreditación Wompi sin cambiar suspensión administrativa');
        $s->auditar('pagos_suscripcion', (int) $pago['id_pago'], ['estado' => $pago['estado']], ['estado' => 'confirmado'], 'Pago confirmado por evento verificado');
        if ($estado['clinica']['estado'] !== 'activa') {
            $s->auditar('clinicas', (int) $pago['id_clinica'], [], ['incidencia' => 'PAGO_CON_CLINICA_NO_ACTIVA'], 'Pago conservado; suspensión/baja administrativa intacta');
        }
    }

    private function incidencia(Suscripcion $s, EventoWompi $inbox, array $evento, array $pago, array $intento, string $codigo): bool
    {
        $this->db->prepare('UPDATE intentos_pago SET codigo_error=? WHERE id_intento=? AND id_pago=?')->execute([$codigo, $intento['id_intento'], $pago['id_pago']]);
        if ($pago['estado'] !== 'confirmado') {
            $this->db->prepare("UPDATE pagos_suscripcion SET estado='revision' WHERE id_pago=? AND id_clinica=?")->execute([$pago['id_pago'], $pago['id_clinica']]);
        }
        $s->auditar('pagos_suscripcion', (int) $pago['id_pago'], ['estado' => $pago['estado']], ['incidencia' => $codigo], 'Incidencia financiera, revisión en S4 (D5)');
        $inbox->terminar($evento, 'revision', $codigo, (int) $intento['id_intento'], (int) $pago['id_clinica']);
        return false;
    }

    private function duplicadoExterno(Suscripcion $s, EventoWompi $inbox, array $evento, array $pago, array $intento, array $remoto): bool
    {
        $q = $this->db->prepare('SELECT id_intento FROM intentos_pago WHERE entorno=? AND id_transaccion_wompi=?');
        $q->execute([$evento['entorno'], $remoto['id']]);
        $duplicado = $q->fetchColumn();
        if (!$duplicado) {
            $q = $this->db->prepare('SELECT MAX(numero) FROM intentos_pago WHERE id_pago=?');
            $q->execute([$pago['id_pago']]);
            $numero = (int) $q->fetchColumn() + 1;
            $this->db->prepare("INSERT INTO intentos_pago (id_pago,numero,entorno,referencia,id_transaccion_wompi,origen,estado,codigo_error,creado_en) VALUES (?,?,?,?,?,'boton','incierto','COBRO_DUPLICADO',?)")
                ->execute([$pago['id_pago'], $numero, $evento['entorno'], 'evidencia_' . bin2hex(random_bytes(24)), $remoto['id'], $s->ahora()->format('Y-m-d H:i:s')]);
            $duplicado = (int) $this->db->lastInsertId();
            $s->auditar('intentos_pago', $duplicado, [], ['incidencia' => 'COBRO_DUPLICADO', 'id_transaccion' => $remoto['id']], 'Evidencia externa, sin emitir cargo', null, 'INSERT');
        }
        $inbox->terminar($evento, 'revision', 'COBRO_DUPLICADO', (int) $duplicado, (int) $pago['id_clinica']);
        return false;
    }
}
