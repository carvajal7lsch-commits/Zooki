<?php
require_once __DIR__ . '/ConciliadorPago.php';

/** RE-0.5.15: recuperar reservas manuales vencidas sin emitir cargos ni liberar por tiempo solamente. */
final class RecuperadorCheckout
{
    public function __construct(private PDO $db, private ClienteWompi $cliente, private ConfiguracionWompi $configuracion, private mixed $reloj = null)
    {
    }

    public function ejecutar(bool $revisar = false, ?int $clinica = null, ?int $actor = null): array
    {
        if ($this->db->inTransaction()) {
            throw new LogicException('La recuperación de checkout requiere HTTP fuera del candado.');
        }
        $ahora = ($this->reloj ? ($this->reloj)()->setTimezone(new DateTimeZone(PoliticaSuscripcion::ZONA))
            : new DateTimeImmutable('now', new DateTimeZone(PoliticaSuscripcion::ZONA)))->format('Y-m-d H:i:s');
        $resultado = ['pendientes' => 0, 'cerradas' => 0, 'errores' => 0];
        if (!(int) $this->db->query("SELECT COUNT(*) FROM pagos_suscripcion WHERE estado='pendiente'")->fetchColumn()) {
            return $resultado;
        }
        $sql = "SELECT p.id_pago,p.id_clinica FROM pagos_suscripcion p JOIN intentos_pago i ON i.id_pago=p.id_pago WHERE p.estado='pendiente' AND p.cotizacion_hasta<=? AND i.entorno=? AND i.origen='boton' AND i.estado IN ('creado','incierto','pendiente') AND i.numero=(SELECT MAX(ultimo.numero) FROM intentos_pago ultimo WHERE ultimo.id_pago=p.id_pago)";
        $parametros = [$ahora, $this->configuracion->entorno()];
        if ($clinica !== null) {
            $sql .= ' AND p.id_clinica=?';
            $parametros[] = $clinica;
        }
        $q = $this->db->prepare($sql . ' ORDER BY p.id_pago LIMIT 100');
        $q->execute($parametros);
        $candidatas = $q->fetchAll(PDO::FETCH_ASSOC);
        $resultado = ['pendientes' => count($candidatas), 'cerradas' => 0, 'errores' => 0];
        if ($revisar || !$candidatas) {
            return $resultado;
        }
        $this->configuracion->validar(false);
        foreach ($candidatas as $candidata) {
            try {
                $resultado['cerradas'] += (int) $this->recuperar((int) $candidata['id_pago'], (int) $candidata['id_clinica'], $actor);
            } catch (Throwable $error) {
                $resultado['errores']++;
                error_log('Checkout: comprobación pendiente de la orden #' . (int) $candidata['id_pago']);
            }
        }
        return $resultado;
    }

    private function instantanea(int $pago, int $clinica): array
    {
        $q = $this->db->prepare('SELECT * FROM pagos_suscripcion WHERE id_pago=? AND id_clinica=?');
        $q->execute([$pago, $clinica]);
        $orden = $q->fetch(PDO::FETCH_ASSOC);
        $q = $this->db->prepare('SELECT i.* FROM intentos_pago i JOIN pagos_suscripcion p ON p.id_pago=i.id_pago WHERE p.id_pago=? AND p.id_clinica=? ORDER BY i.numero');
        $q->execute([$pago, $clinica]);
        return ['orden' => $orden, 'intentos' => $q->fetchAll(PDO::FETCH_ASSOC)];
    }

    private function recuperar(int $pago, int $clinica, ?int $actor): bool
    {
        $s = new Suscripcion($this->db, $clinica, $this->reloj);
        $antes = $this->instantanea($pago, $clinica);
        $orden = $antes['orden'];
        $intentos = $antes['intentos'];
        if (!$orden || $orden['estado'] !== 'pendiente' || !$intentos
            || VigenciaSuscripcion::fecha($orden['cotizacion_hasta']) > $s->ahora()) {
            return false;
        }
        $ultimo = array_pop($intentos);
        foreach ($antes['intentos'] as $intento) {
            if ($intento['origen'] !== 'boton' || $intento['id_fuente'] !== null || $intento['entorno'] !== $this->configuracion->entorno()) {
                return false;
            }
        }
        foreach ($intentos as $previo) {
            if (!in_array($previo['estado'], ['declinado', 'error'], true)) {
                return false;
            }
        }
        $conciliador = new ConciliadorPago($this->db, $this->cliente, $this->configuracion, $this->reloj);
        if ($ultimo['id_transaccion_wompi'] !== null) {
            $conciliador->recuperar($ultimo['id_transaccion_wompi'], $clinica);
            return false;
        }
        if (!in_array($ultimo['estado'], ['creado', 'incierto'], true)) {
            return false;
        }
        $transacciones = $this->cliente->transaccionesPorReferencia($ultimo['referencia']);
        if (!array_is_list($transacciones)) {
            throw new RuntimeException('Lista de transacciones inválida.');
        }
        if ($transacciones) {
            foreach ($transacciones as $transaccion) {
                if (($transaccion['reference'] ?? null) !== $ultimo['referencia'] || !FirmaWompi::idValido($transaccion['id'] ?? null)) {
                    throw new RuntimeException('Resultado de búsqueda discordante.');
                }
                $conciliador->recuperar($transaccion['id'], $clinica);
            }
            return false;
        }
        return $s->bloqueada(function () use ($s, $antes, $orden, $ultimo, $pago, $clinica, $actor): bool {
            // El HTTP pudo coincidir con un webhook, otro intento o un cierre; nunca usar una lectura anterior al candado.
            if ($this->instantanea($pago, $clinica) !== $antes
                || VigenciaSuscripcion::fecha($orden['cotizacion_hasta']) > $s->ahora()) {
                return false;
            }
            $this->db->prepare("UPDATE pagos_suscripcion SET estado='cancelado',clave_operacion=?,clave_obligacion=? WHERE id_pago=? AND id_clinica=?")
                ->execute([PagoSuscripcion::claveCerrada($orden['clave_operacion'], $pago), PagoSuscripcion::claveCerrada($orden['clave_obligacion'], $pago), $pago, $clinica]);
            $s->auditar('pagos_suscripcion', $pago, ['estado' => 'pendiente'], ['estado' => 'cancelado', 'referencia' => $ultimo['referencia'],
                'entorno' => $ultimo['entorno'], 'comprobacion' => 'GET privado por referencia: lista completa vacía', 'consultado_en' => $s->ahora()->format('Y-m-d H:i:s')], 'Checkout vencido sin transacción en Wompi (RE-0.5.15)', $actor);
            return true;
        });
    }
}
