<?php
require_once __DIR__ . '/Suscripcion.php';
require_once __DIR__ . '/../helpers/FirmaWompi.php';
require_once __DIR__ . '/../helpers/ConfiguracionWompi.php';
require_once __DIR__ . '/../helpers/EnlaceCuenta.php';
require_once __DIR__ . '/../helpers/Security.php';

/** RE-0.5.10/14: la obligación no depende del ciclo, pestaña ni medio. */
final class PagoSuscripcion
{
    public function __construct(private PDO $db, private int $clinica, private ConfiguracionWompi $configuracion, private mixed $reloj = null)
    {
    }

    public function reservar(string $ciclo, int $version, int $actor): array
    {
        $this->configuracion->validar();
        if (!in_array($ciclo, ['mensual', 'anual'], true) || $version < 0) {
            throw new InvalidArgumentException('Revisa el ciclo y actualiza la pantalla.');
        }
        $base = EnlaceCuenta::base();
        $s = new Suscripcion($this->db, $this->clinica, $this->reloj);
        return $s->bloqueada(function () use ($s, $ciclo, $version, $actor, $base): array {
            $estado = $s->estado();
            if ($estado['clinica']['estado'] !== 'activa' || $estado['legado_sin_fecha']) {
                throw new InvalidArgumentException('La clínica requiere regularización administrativa antes de pagar.');
            }
            $q = $this->db->prepare("SELECT 1 FROM usuario_clinica WHERE id_clinica=? AND id_usuario=? AND id_rol=? AND estado='activo'");
            $q->execute([$this->clinica, $actor, Roles::ADMIN]);
            if (!$q->fetchColumn()) {
                throw new AccesoDenegado(403, 'Solo el administrador de la clínica puede pagar.');
            }
            $contrato = $estado['suscripcion'];
            if (!$contrato) {
                // Sin contrato previo, la reserva jamás puede aparentar un legado al día.
                $this->db->prepare("INSERT INTO suscripciones (id_clinica,id_plan,fecha_inicio,gestion_cobro,al_dia) VALUES (?,?,?,'gestionada',0)")
                    ->execute([$this->clinica, $estado['plan_contratado']['id_plan'], $s->ahora()->format('Y-m-d')]);
                $id = (int) $this->db->lastInsertId();
                $s->auditar('suscripciones', $id, [], ['origen' => 'reserva_sin_beneficios'], 'Reserva de pago (RE-0.5.10)', $actor, 'INSERT');
                $contrato = $s->estado()['suscripcion'];
            }
            $operacion = hash('sha256', json_encode([$this->clinica, (int) $contrato['id_suscripcion'], $version], JSON_THROW_ON_ERROR));
            $q = $this->db->prepare('SELECT * FROM pagos_suscripcion WHERE id_clinica=? AND clave_operacion=?');
            $q->execute([$this->clinica, $operacion]);
            $orden = $q->fetch(PDO::FETCH_ASSOC);
            if ($orden) {
                $intento = $this->ultimoIntento((int) $orden['id_pago']);
                if ((int) $contrato['version_periodo'] !== $version || $orden['estado'] !== 'pendiente') {
                    return $this->respuesta($orden, $intento, $base, $s->ahora());
                }
                if (in_array($intento['estado'], ['declinado', 'error'], true) && VigenciaSuscripcion::fecha($orden['cotizacion_hasta']) <= $s->ahora()) {
                    // Decisión del dueño: solo un fallo terminal confirmado libera una cotización vencida.
                    $cerrada = hash('sha256', $operacion . ':cerrada:' . $orden['id_pago']);
                    $this->db->prepare("UPDATE pagos_suscripcion SET estado='cancelado',clave_operacion=?,clave_obligacion=? WHERE id_pago=? AND id_clinica=?")
                        ->execute([$cerrada, hash('sha256', $orden['clave_obligacion'] . ':cerrada:' . $orden['id_pago']), $orden['id_pago'], $this->clinica]);
                    $s->auditar('pagos_suscripcion', (int) $orden['id_pago'], ['estado' => 'pendiente'], ['estado' => 'cancelado'], 'Cotización vencida con fallo terminal confirmado', $actor);
                    $orden = false;
                } else {
                    if (in_array($intento['estado'], ['declinado', 'error'], true)) {
                        $intento = $this->crearIntento($orden, (int) $intento['numero'] + 1, $s, $actor);
                    } elseif ($intento['estado'] === 'creado' && VigenciaSuscripcion::fecha($orden['cotizacion_hasta']) <= $s->ahora()) {
                        $this->db->prepare("UPDATE intentos_pago SET estado='incierto' WHERE id_intento=? AND id_pago=?")->execute([$intento['id_intento'], $orden['id_pago']]);
                        $s->auditar('intentos_pago', (int) $intento['id_intento'], ['estado' => 'creado'], ['estado' => 'incierto'], 'Cotización vencida sin resultado confirmado', $actor);
                        $intento['estado'] = 'incierto';
                    }
                    return $this->respuesta($orden, $intento, $base, $s->ahora());
                }
            }
            if ((int) $contrato['version_periodo'] !== $version) {
                throw new InvalidArgumentException('La vigencia cambió. Actualiza la pantalla antes de renovar.');
            }
            $plan = $s->profesional();
            $tarifa = TarifaSuscripcion::calcular($plan, $ciclo);
            if (!(int) $plan['estado'] || $tarifa['total_centavos'] <= 0) {
                throw new InvalidArgumentException('La tarifa requiere revisión administrativa.');
            }
            $obligacion = hash('sha256', json_encode([$this->clinica, (int) $contrato['id_suscripcion'], $version, $estado['periodo_fin']], JSON_THROW_ON_ERROR));
            $hasta = $s->ahora()->modify('+' . PoliticaSuscripcion::COTIZACION_MINUTOS . ' minutes')->format('Y-m-d H:i:s');
            $this->db->prepare('INSERT INTO pagos_suscripcion (id_clinica,id_suscripcion,id_plan,clave_obligacion,version_periodo_base,ciclo,moneda,subtotal_centavos,impuesto_centavos,total_centavos,tarifa_version,clave_operacion,cotizacion_hasta,id_usuario_solicitante,creado_en) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$this->clinica, $contrato['id_suscripcion'], $plan['id_plan'], $obligacion, $version, $ciclo, $tarifa['moneda'], $tarifa['subtotal_centavos'], 0, $tarifa['total_centavos'], substr(hash('sha256', (string) $tarifa['total_centavos']), 0, 40), $operacion, $hasta, $actor, $s->ahora()->format('Y-m-d H:i:s')]);
            $id = (int) $this->db->lastInsertId();
            $s->auditar('pagos_suscripcion', $id, [], ['estado' => 'pendiente', 'total_centavos' => $tarifa['total_centavos'], 'ciclo' => $ciclo], 'Cotización de suscripción', $actor, 'INSERT');
            $q = $this->db->prepare('SELECT * FROM pagos_suscripcion WHERE id_pago=? AND id_clinica=?');
            $q->execute([$id, $this->clinica]);
            $orden = $q->fetch(PDO::FETCH_ASSOC);
            return $this->respuesta($orden, $this->crearIntento($orden, 1, $s, $actor), $base, $s->ahora());
        });
    }

    private function crearIntento(array $orden, int $numero, Suscripcion $s, int $actor): array
    {
        $referencia = 'zk_' . bin2hex(random_bytes(24));
        $this->db->prepare("INSERT INTO intentos_pago (id_pago,numero,entorno,referencia,origen,estado,creado_en) VALUES (?,?,?,?,'boton','creado',?)")
            ->execute([$orden['id_pago'], $numero, $this->configuracion->entorno(), $referencia, $s->ahora()->format('Y-m-d H:i:s')]);
        $s->auditar('intentos_pago', (int) $this->db->lastInsertId(), [], ['estado' => 'creado', 'referencia' => $referencia], 'Reserva única para Widget', $actor, 'INSERT');
        return $this->ultimoIntento((int) $orden['id_pago']);
    }

    private function ultimoIntento(int $orden): array
    {
        $q = $this->db->prepare('SELECT i.* FROM intentos_pago i JOIN pagos_suscripcion p ON p.id_pago=i.id_pago WHERE p.id_clinica=? AND p.id_pago=? ORDER BY i.numero DESC LIMIT 1');
        $q->execute([$this->clinica, $orden]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: throw new RuntimeException('Falta el intento de la orden.');
    }

    private function respuesta(array $orden, array $intento, string $base, DateTimeImmutable $ahora): array
    {
        if ($intento['entorno'] !== $this->configuracion->entorno()) {
            throw new RuntimeException('La orden pertenece a otro entorno. Revisa el despliegue antes de pagar.');
        }
        $expiracion = VigenciaSuscripcion::fecha($orden['cotizacion_hasta'])->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.000\Z');
        $abrir = $orden['estado'] === 'pendiente' && $intento['estado'] === 'creado' && VigenciaSuscripcion::fecha($orden['cotizacion_hasta']) > $ahora;
        $resumen = 'Orden ' . $orden['ciclo'] . ' por $' . number_format(intdiv((int) $orden['total_centavos'], 100), 0, ',', '.') . ' COP. ';
        $resultado = ['orden' => (int) $orden['id_pago'], 'estado' => $orden['estado'], 'intento' => $intento['estado'], 'ciclo' => $orden['ciclo'],
            'total_centavos' => (int) $orden['total_centavos'], 'abrir' => $abrir,
            'mensaje' => $resumen . ($abrir ? 'Completa el pago en Wompi.' : 'Consulta el estado. Una ventana cerrada o cotización vencida no descarta un cobro en curso.')];
        if ($abrir) {
            $resultado['checkout'] = ['currency' => $orden['moneda'], 'amountInCents' => (int) $orden['total_centavos'], 'reference' => $intento['referencia'],
                'publicKey' => $this->configuracion->valor('LLAVE_PUBLICA'), 'expirationTime' => $expiracion,
                'signature' => ['integrity' => FirmaWompi::integridad($intento['referencia'], (int) $orden['total_centavos'], $orden['moneda'], $this->configuracion->valor('SECRETO_INTEGRIDAD'), $expiracion)],
                'redirectUrl' => $base . '?' . http_build_query(['action' => 'suscripcion_retorno', 'referencia' => $intento['referencia']])];
        }
        return $resultado;
    }

    public function historial(int $pagina = 1): array
    {
        $pagina = max(1, min(100000, $pagina));
        $q = $this->db->prepare('SELECT p.id_pago,p.ciclo,p.moneda,p.total_centavos,p.estado,p.creado_en,i.referencia,i.estado AS estado_intento,i.codigo_error,r.inicio,r.fin FROM pagos_suscripcion p LEFT JOIN intentos_pago i ON i.id_pago=p.id_pago LEFT JOIN suscripcion_periodos r ON r.id_pago=p.id_pago WHERE p.id_clinica=? ORDER BY p.id_pago DESC,i.numero DESC LIMIT 21 OFFSET ?');
        $q->bindValue(1, $this->clinica, PDO::PARAM_INT);
        $q->bindValue(2, ($pagina - 1) * 20, PDO::PARAM_INT);
        $q->execute();
        $filas = $q->fetchAll(PDO::FETCH_ASSOC);
        return ['filas' => array_slice($filas, 0, 20), 'pagina' => $pagina, 'mas' => count($filas) > 20];
    }

    public function estadoLocal(?string $referencia = null): array
    {
        $s = (new Suscripcion($this->db, $this->clinica, $this->reloj))->estado();
        $resultado = ['situacion' => $s['situacion'], 'plan' => $s['plan_efectivo']['nombre'], 'fin' => $s['periodo_fin']];
        if ($referencia !== null) {
            $q = $this->db->prepare('SELECT p.estado,i.estado AS intento FROM intentos_pago i JOIN pagos_suscripcion p ON p.id_pago=i.id_pago WHERE p.id_clinica=? AND i.referencia=?');
            $q->execute([$this->clinica, $referencia]);
            $resultado['pago'] = $q->fetch(PDO::FETCH_ASSOC) ?: throw new AccesoDenegado(403, 'Pago no disponible en esta clínica.');
        }
        return $resultado;
    }
}
