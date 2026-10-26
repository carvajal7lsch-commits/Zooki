<?php
require_once __DIR__ . '/FuentePago.php';
require_once __DIR__ . '/PagoSuscripcion.php';

/** RN-016: reserva durable antes del único POST; la incertidumbre jamás libera otro. */
final class CobroAutomatico
{
    public function __construct(private PDO $db, private ClienteWompi $cliente, private ConfiguracionWompi $configuracion, private mixed $reloj = null)
    {
    }

    public function ejecutar(bool $revisar = false): array
    {
        $resultado = ['enviados' => 0, 'errores' => 0, 'inciertos' => 0, 'candidatas' => 0, 'habilitado' => false];
        try {
            $this->configuracion->validarAutomaticos();
        } catch (RuntimeException $error) {
            return $resultado;
        }
        $resultado['habilitado'] = true;
        $ids = $this->db->query('SELECT DISTINCT id_clinica FROM suscripciones WHERE cobro_automatico=1 ORDER BY id_clinica')->fetchAll(PDO::FETCH_COLUMN);
        $resultado['candidatas'] = count($ids);
        if ($revisar) {
            return $resultado;
        }
        foreach ($ids as $id) {
            try {
                $resultado['enviados'] += (int) $this->procesar((int) $id);
            } catch (Throwable $error) {
                $resultado['errores']++;
                error_log('Cobro automático pendiente en clínica #' . (int) $id);
            }
        }
        $q = $this->db->prepare("SELECT COUNT(*) FROM intentos_pago WHERE entorno=? AND origen='automatico' AND estado IN ('incierto','enviando')");
        $q->execute([$this->configuracion->entorno()]);
        $resultado['inciertos'] = (int) $q->fetchColumn();
        return $resultado;
    }

    public function procesar(int $clinica): bool
    {
        $this->configuracion->validarAutomaticos();
        if ($this->db->inTransaction()) {
            throw new LogicException('El cobro requiere una transacción independiente.');
        }
        $s = new Suscripcion($this->db, $clinica, $this->reloj);
        $prelectura = $s->estado();
        if (empty($prelectura['suscripcion']['cobro_automatico'])) {
            return false;
        }
        // Tokens actuales fuera de la transacción; solo se usan si las condiciones aceptadas coinciden.
        $contratos = AutorizacionCobro::contratos($this->cliente->comercio());
        $envio = $s->bloqueada(function () use ($s, $clinica, $contratos): ?array {
            $estado = $s->estado();
            $contrato = $estado['suscripcion'];
            if (!$contrato || !(int) $contrato['cobro_automatico'] || $estado['clinica']['estado'] !== 'activa'
                || $contrato['estado'] !== 'activa' || $estado['ciclo'] !== 'mensual' || $estado['situacion'] !== 'gracia'
                || $estado['legado_sin_fecha'] || $estado['periodo_fin'] === null
                || (int) $estado['plan_contratado']['id_plan'] === (int) $estado['plan_gratuito']['id_plan']) {
                return null;
            }
            $q = $this->db->prepare("SELECT f.*,u.email FROM fuentes_pago f JOIN usuarios u ON u.id_usuario=? JOIN usuario_clinica uc ON uc.id_usuario=u.id_usuario AND uc.id_clinica=f.id_clinica AND uc.id_rol=? AND uc.estado='activo' WHERE f.id_clinica=? AND f.id_fuente=? AND f.estado='disponible' AND f.entorno=? AND u.estado=1");
            $q->execute([$contrato['autorizado_por'], Roles::ADMIN, $clinica, $contrato['id_fuente_activa'], $this->configuracion->entorno()]);
            $fuente = $q->fetch(PDO::FETCH_ASSOC);
            if (!$fuente) {
                return null;
            }
            $tarifa = TarifaSuscripcion::calcular($s->profesional(), 'mensual');
            if ((int) $fuente['importe_mensual_autorizado_centavos'] !== $tarifa['total_centavos']
                || $fuente['version_autorizacion'] !== AutorizacionCobro::VERSION || !hash_equals($fuente['version_contratos'], $contratos['version'])) {
                $precioCambio = (int) $fuente['importe_mensual_autorizado_centavos'] !== $tarifa['total_centavos'];
                $condicionesCambio = $fuente['version_autorizacion'] !== AutorizacionCobro::VERSION || !hash_equals($fuente['version_contratos'], $contratos['version']);
                $motivo = $precioCambio ? ($condicionesCambio ? 'precio_y_condiciones' : 'precio') : 'condiciones';
                $this->db->prepare('UPDATE suscripciones SET cobro_automatico=0,retirado_en=?,motivo_automatico_apagado=? WHERE id_clinica=? AND id_suscripcion=?')
                    ->execute([$s->ahora()->format('Y-m-d H:i:s'), $motivo, $clinica, $contrato['id_suscripcion']]);
                $s->auditar('suscripciones', (int) $contrato['id_suscripcion'], ['automatico' => true], ['automatico' => false, 'requiere_aceptacion' => true, 'motivo_automatico_apagado' => $motivo], 'Importe o condiciones cambiaron (RE-0.5.26)');
                return null;
            }
            $q = $this->db->prepare('SELECT i.*,p.estado AS estado_pago,p.total_centavos,p.ciclo,p.cotizacion_hasta FROM pagos_suscripcion p JOIN intentos_pago i ON i.id_pago=p.id_pago WHERE p.id_clinica=? AND p.id_suscripcion=? AND p.version_periodo_base=? ORDER BY i.id_intento DESC');
            $q->execute([$clinica, $contrato['id_suscripcion'], $contrato['version_periodo']]);
            $intentos = $q->fetchAll(PDO::FETCH_ASSOC);
            $automaticos = 0;
            foreach ($intentos as $intento) {
                $automaticos += (int) ($intento['origen'] === 'automatico');
                if ($intento['estado'] === 'enviando' && $intento['lease_hasta'] !== null && VigenciaSuscripcion::fecha($intento['lease_hasta']) <= $s->ahora()) {
                    $this->db->prepare("UPDATE intentos_pago SET estado='incierto' WHERE id_intento=? AND id_pago=?")
                        ->execute([$intento['id_intento'], $intento['id_pago']]);
                    $s->auditar('intentos_pago', (int) $intento['id_intento'], ['estado' => 'enviando'], ['estado' => 'incierto'], 'Envío interrumpido; nunca repetir POST (RE-0.5.15)');
                }
                if (!in_array($intento['estado'], ['declinado', 'error'], true) || $intento['estado_pago'] === 'revision') {
                    return null;
                }
                if ($intento['estado_pago'] === 'pendiente' && VigenciaSuscripcion::fecha($intento['cotizacion_hasta']) > $s->ahora()
                    && ((int) $intento['total_centavos'] !== $tarifa['total_centavos'] || $intento['ciclo'] !== 'mensual')) {
                    // Una nueva aceptación no permite cargar el importe de una cotización anterior distinta.
                    return null;
                }
            }
            if ($automaticos >= count(PoliticaSuscripcion::REINTENTOS_HORAS)) {
                return null;
            }
            $desde = VigenciaSuscripcion::fecha($estado['periodo_fin']);
            if ($s->ahora() < $desde->modify('+' . PoliticaSuscripcion::REINTENTOS_HORAS[$automaticos] . ' hours')) {
                return null;
            }
            (new PagoSuscripcion($this->db, $clinica, $this->configuracion, $this->reloj))->reservarAutomatico((int) $contrato['version_periodo'], (int) $fuente['id_fuente']);
            $q = $this->db->prepare("SELECT i.*,p.total_centavos,p.moneda,p.id_clinica FROM intentos_pago i JOIN pagos_suscripcion p ON p.id_pago=i.id_pago WHERE p.id_clinica=? AND p.id_suscripcion=? AND p.version_periodo_base=? AND i.origen='automatico' AND i.estado='creado' ORDER BY i.id_intento DESC LIMIT 1");
            $q->execute([$clinica, $contrato['id_suscripcion'], $contrato['version_periodo']]);
            $intento = $q->fetch(PDO::FETCH_ASSOC);
            if (!$intento) {
                return null;
            }
            $this->db->prepare("UPDATE intentos_pago SET estado='enviando',enviado_en=?,lease_hasta=? WHERE id_intento=? AND id_pago=?")
                ->execute([$s->ahora()->format('Y-m-d H:i:s'), $s->ahora()->modify('+' . PoliticaSuscripcion::RECLAMACION_SEGUNDOS . ' seconds')->format('Y-m-d H:i:s'), $intento['id_intento'], $intento['id_pago']]);
            $s->auditar('intentos_pago', (int) $intento['id_intento'], ['estado' => 'creado'], ['estado' => 'enviando'], 'Cargo en curso; reserva confirmada antes de POST (RN-016)');
            return $intento + ['fuente' => $fuente];
        });
        if (!$envio) {
            return false;
        }
        $datos = ['amount_in_cents' => (int) $envio['total_centavos'], 'currency' => $envio['moneda'], 'reference' => $envio['referencia'],
            'payment_source_id' => (int) $envio['fuente']['id_fuente_wompi'], 'customer_email' => $envio['fuente']['email'],
            'signature' => FirmaWompi::integridad($envio['referencia'], (int) $envio['total_centavos'], $envio['moneda'], $this->configuracion->valor('SECRETO_INTEGRIDAD'))] + $contratos['tokens'];
        if ($envio['fuente']['tipo'] === 'CARD') {
            $datos['payment_method'] = ['installments' => 1];
            $datos['recurrent'] = true;
        }
        try {
            $respuesta = $this->cliente->crearTransaccion($datos);
        } catch (Throwable $error) {
            $this->registrarRespuesta($s, $envio, null);
            return false;
        }
        $this->registrarRespuesta($s, $envio, $respuesta);
        return true;
    }

    private function registrarRespuesta(Suscripcion $s, array $envio, ?array $respuesta): void
    {
        $s->bloqueada(function () use ($s, $envio, $respuesta): void {
            $q = $this->db->prepare('SELECT * FROM intentos_pago WHERE id_intento=? AND id_pago=?');
            $q->execute([$envio['id_intento'], $envio['id_pago']]);
            $actual = $q->fetch(PDO::FETCH_ASSOC);
            if ($actual['estado'] !== 'enviando') {
                // Un evento puede aprobar antes de que vuelva el POST; nunca degradarlo.
                return;
            }
            $id = $respuesta['id'] ?? null;
            $valida = is_string($id) && preg_match('/^[a-zA-Z0-9_-]{1,128}$/D', $id)
                && ($respuesta['reference'] ?? null) === $envio['referencia'] && ($respuesta['amount_in_cents'] ?? null) === (int) $envio['total_centavos']
                && ($respuesta['currency'] ?? null) === $envio['moneda'];
            $nuevo = $valida ? 'pendiente' : 'incierto';
            $this->db->prepare('UPDATE intentos_pago SET estado=?,id_transaccion_wompi=?,lease_hasta=NULL WHERE id_intento=? AND id_pago=?')
                ->execute([$nuevo, $valida ? $id : null, $envio['id_intento'], $envio['id_pago']]);
            $s->auditar('intentos_pago', (int) $envio['id_intento'], ['estado' => 'enviando'], ['estado' => $nuevo], 'Respuesta de creación no acredita ni confirma rechazo (RE-0.5.15)');
        });
    }
}
