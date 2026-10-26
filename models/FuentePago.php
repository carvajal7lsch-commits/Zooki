<?php
require_once __DIR__ . '/Suscripcion.php';
require_once __DIR__ . '/../helpers/Security.php';
require_once __DIR__ . '/../helpers/AutorizacionCobro.php';
require_once __DIR__ . '/../helpers/ClienteWompiHttp.php';

/** RE-0.5.18/19/26: la fuente y el mandato mensual son decisiones distintas. */
final class FuentePago
{
    public function __construct(private PDO $db, private int $clinica, private ClienteWompi $cliente, private ConfiguracionWompi $configuracion, private mixed $reloj = null)
    {
    }

    private function servicio(): Suscripcion
    {
        return new Suscripcion($this->db, $this->clinica, $this->reloj);
    }

    public function administrador(int $actor): array
    {
        $q = $this->db->prepare("SELECT u.email FROM usuarios u JOIN usuario_clinica uc ON uc.id_usuario=u.id_usuario JOIN clinicas c ON c.id_clinica=uc.id_clinica WHERE uc.id_clinica=? AND uc.id_usuario=? AND uc.id_rol=? AND uc.estado='activo' AND u.estado=1 AND c.estado='activa'");
        $q->execute([$this->clinica, $actor, Roles::ADMIN]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: throw new AccesoDenegado(403, 'Solo el administrador activo de esta clínica puede gestionar el medio de pago.');
    }

    public function preparar(int $actor): array
    {
        $this->administrador($actor);
        $this->configuracion->validar();
        $contratos = AutorizacionCobro::contratos($this->cliente->comercio());
        $tarifa = TarifaSuscripcion::calcular($this->servicio()->profesional(), 'mensual');
        return ['contratos' => $contratos['prueba'], 'version' => $contratos['version'], 'importe' => $tarifa['total_centavos'],
            'publicKey' => $this->configuracion->valor('LLAVE_PUBLICA')];
    }

    public function actual(): ?array
    {
        $q = $this->db->prepare('SELECT f.id_fuente,f.tipo,f.marca,f.ultimos_cuatro,f.estado,f.entorno,f.importe_mensual_autorizado_centavos,s.cobro_automatico,s.autorizado_en FROM suscripciones s JOIN fuentes_pago f ON f.id_fuente=s.id_fuente_activa AND f.id_clinica=s.id_clinica WHERE s.id_clinica=? AND s.id_suscripcion=(SELECT MAX(id_suscripcion) FROM suscripciones WHERE id_clinica=?)');
        $q->execute([$this->clinica, $this->clinica]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function guardar(string $tipo, string $token, bool $aceptaPolitica, bool $aceptaDatos, string $version, int $actor): array
    {
        $this->configuracion->validar();
        $usuario = $this->administrador($actor);
        $prefijo = $tipo === 'CARD' ? 'tok_' : 'nequi_';
        if (!in_array($tipo, ['CARD', 'NEQUI'], true) || !$aceptaPolitica || !$aceptaDatos
            || !preg_match('/^' . $prefijo . $this->configuracion->entorno() . '_[a-zA-Z0-9_-]{1,180}$/D', $token)) {
            throw new InvalidArgumentException('Acepta las dos condiciones y completa el medio en Wompi.');
        }
        $contratos = AutorizacionCobro::contratos($this->cliente->comercio());
        if (!hash_equals($contratos['version'], $version)) {
            throw new InvalidArgumentException('Las condiciones cambiaron. Actualiza y vuelve a aceptarlas.');
        }
        if ($tipo === 'NEQUI') {
            $nequi = $this->cliente->tokenNequi($token);
            if (($nequi['id'] ?? null) !== $token) {
                throw new RuntimeException('Wompi no confirmó este medio de pago.');
            }
            if (($nequi['status'] ?? null) === 'PENDING') {
                return ['pendiente' => true, 'mensaje' => 'Aprueba la solicitud en tu celular y pulsa Comprobar Nequi.'];
            }
            if (($nequi['status'] ?? null) !== 'APPROVED') {
                throw new InvalidArgumentException('Nequi no aprobó la solicitud. Vuelve a iniciar el guardado.');
            }
        }
        // RN-018: el token solo vive en esta llamada. No guardar ni auditar el payload remoto.
        $remota = $this->cliente->crearFuente(['type' => $tipo, 'token' => $token, 'customer_email' => $usuario['email']] + $contratos['tokens']);
        $id = $remota['id'] ?? null;
        if ((!is_string($id) && !is_int($id)) || !preg_match('/^[1-9][0-9]{0,17}$/D', (string) $id)
            || ($remota['type'] ?? null) !== $tipo || ($remota['status'] ?? null) !== 'AVAILABLE'
            || (isset($remota['environment']) && $remota['environment'] !== $this->configuracion->entorno())) {
            throw new RuntimeException('Wompi no confirmó una fuente disponible. No se autorizó ningún cobro.');
        }
        return $this->servicio()->bloqueada(function () use ($actor, $remota, $tipo, $id, $contratos): array {
            $this->administrador($actor);
            $s = $this->servicio();
            $estado = $s->estado();
            $contrato = $estado['suscripcion'];
            if (!$contrato) {
                $this->db->prepare("INSERT INTO suscripciones (id_clinica,id_plan,fecha_inicio,gestion_cobro,al_dia) VALUES (?,?,?,'gestionada',0)")
                    ->execute([$this->clinica, $estado['plan_contratado']['id_plan'], $s->ahora()->format('Y-m-d')]);
                $s->auditar('suscripciones', (int) $this->db->lastInsertId(), [], ['origen' => 'fuente_sin_beneficios'], 'Fuente sin contratación', $actor, 'INSERT');
                $contrato = $s->estado()['suscripcion'];
            }
            $q = $this->db->prepare('SELECT * FROM fuentes_pago WHERE entorno=? AND id_fuente_wompi=?');
            $q->execute([$this->configuracion->entorno(), (string) $id]);
            $existente = $q->fetch(PDO::FETCH_ASSOC);
            if ($existente) {
                if ((int) $existente['id_clinica'] !== $this->clinica || $existente['estado'] === 'retirada') {
                    throw new AccesoDenegado(403, 'La fuente no está disponible para esta clínica.');
                }
                // Repetir la respuesta de Wompi no reinicia un mandato existente.
                return ['pendiente' => false, 'mensaje' => 'El medio ya estaba guardado.'];
            }
            $publica = $remota['public_data'] ?? [];
            $marca = $tipo === 'CARD' && is_string($publica['brand'] ?? null) && preg_match('/^[A-Za-z0-9 -]{1,30}$/D', $publica['brand']) ? $publica['brand'] : null;
            $cuatro = $tipo === 'CARD' && is_string($publica['last_four'] ?? null) && preg_match('/^[0-9]{4}$/D', $publica['last_four']) ? $publica['last_four'] : null;
            $this->db->prepare("INSERT INTO fuentes_pago (id_clinica,entorno,id_fuente_wompi,tipo,marca,ultimos_cuatro,estado,creada_por,creada_en,consentida_en,version_contratos,contratos_json) VALUES (?,?,?,?,?,?,'disponible',?,?,?,?,?)")
                ->execute([$this->clinica, $this->configuracion->entorno(), (string) $id, $tipo, $marca, $cuatro, $actor, $s->ahora()->format('Y-m-d H:i:s'), $s->ahora()->format('Y-m-d H:i:s'), $contratos['version'], json_encode($contratos['prueba'], JSON_THROW_ON_ERROR)]);
            $local = (int) $this->db->lastInsertId();
            $s->auditar('fuentes_pago', $local, [], ['estado' => 'disponible', 'tipo' => $tipo, 'contratos' => $contratos['prueba']], 'Fuente guardada; sin autorización automática', $actor, 'INSERT');
            $this->db->prepare('UPDATE suscripciones SET id_fuente_activa=?,motivo_automatico_apagado=NULL,cobro_automatico=0,retirado_en=? WHERE id_suscripcion=? AND id_clinica=?')
                ->execute([$local, $s->ahora()->format('Y-m-d H:i:s'), $contrato['id_suscripcion'], $this->clinica]);
            $s->auditar('suscripciones', (int) $contrato['id_suscripcion'], ['fuente' => $contrato['id_fuente_activa']], ['fuente' => $local, 'automatico' => false], 'Guardar no autoriza (RN-016)', $actor);
            return ['pendiente' => false, 'mensaje' => 'Medio guardado. El cobro automático sigue desactivado.'];
        });
    }

    public function automatico(bool $autorizar, string $version, int $importe, int $actor): array
    {
        $actuales = null;
        if ($autorizar) {
            $this->configuracion->validarAutomaticos();
            $this->administrador($actor);
            $actuales = AutorizacionCobro::contratos($this->cliente->comercio());
            if (!hash_equals($actuales['version'], $version)) {
                throw new InvalidArgumentException('Actualiza las condiciones antes de autorizar.');
            }
        }
        return $this->servicio()->bloqueada(function () use ($autorizar, $actor, $importe, $actuales): array {
            $this->administrador($actor);
            $s = $this->servicio();
            $estado = $s->estado();
            $contrato = $estado['suscripcion'];
            if (!$contrato) {
                throw new InvalidArgumentException('No hay una suscripción que modificar.');
            }
            if ($autorizar) {
                $fuente = $this->actual();
                $tarifa = TarifaSuscripcion::calcular($s->profesional(), 'mensual');
                if (!$fuente || $fuente['estado'] !== 'disponible' || $fuente['entorno'] !== $this->configuracion->entorno()
                    || $estado['ciclo'] !== 'mensual' || $estado['legado_sin_fecha'] || $estado['periodo_fin'] === null
                    || (int) $estado['plan_contratado']['id_plan'] === (int) $estado['plan_gratuito']['id_plan']
                    || $contrato['estado'] !== 'activa' || $importe !== $tarifa['total_centavos'] || $importe <= 0) {
                    throw new InvalidArgumentException('El automático requiere plan mensual con vencimiento, medio disponible e importe vigente.');
                }
                $this->db->prepare('UPDATE fuentes_pago SET version_contratos=?,contratos_json=?,consentida_en=?,version_autorizacion=?,importe_mensual_autorizado_centavos=? WHERE id_fuente=? AND id_clinica=?')
                    ->execute([$actuales['version'], json_encode($actuales['prueba'], JSON_THROW_ON_ERROR), $s->ahora()->format('Y-m-d H:i:s'), AutorizacionCobro::VERSION, $importe, $fuente['id_fuente'], $this->clinica]);
                $s->auditar('fuentes_pago', (int) $fuente['id_fuente'], [], ['contratos' => $actuales['prueba'], 'importe_centavos' => $importe, 'periodicidad' => 'mensual', 'version' => AutorizacionCobro::VERSION], 'Autorización mensual explícita (RE-0.5.26)', $actor);
            }
            $ahora = $s->ahora()->format('Y-m-d H:i:s');
            $this->db->prepare('UPDATE suscripciones SET motivo_automatico_apagado=NULL,cobro_automatico=?,autorizado_por=?,autorizado_en=?,retirado_en=? WHERE id_suscripcion=? AND id_clinica=?')
                ->execute([(int) $autorizar, $autorizar ? $actor : $contrato['autorizado_por'], $autorizar ? $ahora : $contrato['autorizado_en'], $autorizar ? null : $ahora, $contrato['id_suscripcion'], $this->clinica]);
            $s->auditar('suscripciones', (int) $contrato['id_suscripcion'], ['automatico' => (int) $contrato['cobro_automatico']], ['automatico' => $autorizar, 'importe_centavos' => $autorizar ? $importe : null], $autorizar ? 'Autorizar mensual' : 'Retirar automático sin acortar lo pagado', $actor);
            return ['mensaje' => $autorizar ? 'Cobro mensual autorizado.' : $this->mensajeRetiro()];
        });
    }

    public function quitar(int $actor): array
    {
        return $this->servicio()->bloqueada(function () use ($actor): array {
            $this->automatico(false, '', 0, $actor);
            $s = $this->servicio();
            $fuente = $this->actual();
            if ($fuente) {
                $this->db->prepare("UPDATE fuentes_pago SET estado='retirada',retirada_en=? WHERE id_fuente=? AND id_clinica=?")
                    ->execute([$s->ahora()->format('Y-m-d H:i:s'), $fuente['id_fuente'], $this->clinica]);
                $this->db->prepare('UPDATE suscripciones SET id_fuente_activa=NULL WHERE id_clinica=? AND id_suscripcion=?')
                    ->execute([$this->clinica, $s->estado()['suscripcion']['id_suscripcion']]);
                $s->auditar('fuentes_pago', (int) $fuente['id_fuente'], ['estado' => $fuente['estado']], ['estado' => 'retirada'], 'Quitar fuente local; no promete borrado en Wompi', $actor);
            }
            return ['mensaje' => 'Medio quitado. ' . $this->mensajeRetiro()];
        });
    }

    private function mensajeRetiro(): string
    {
        $q = $this->db->prepare("SELECT 1 FROM intentos_pago i JOIN pagos_suscripcion p ON p.id_pago=i.id_pago WHERE p.id_clinica=? AND p.estado='pendiente' AND i.origen='automatico' AND i.estado IN ('enviando','pendiente','incierto') LIMIT 1");
        $q->execute([$this->clinica]);
        return $q->fetchColumn() ? 'Automático retirado. Hay un cargo en curso que aún puede confirmarse; los siguientes se detienen.' : 'Automático retirado. Conservas el periodo ya pagado.';
    }
}
