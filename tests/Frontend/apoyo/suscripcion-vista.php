<?php
require __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../Support/DosClinicas.php';
require_once __DIR__ . '/../../../controllers/SuscripcionController.php';
require_once __DIR__ . '/../../Support/ClienteWompiFalso.php';
require_once __DIR__ . '/../../../models/ConciliadorPago.php';
session_set_save_handler(static fn () => true, static fn () => true, static fn () => '', static fn () => true, static fn () => true, static fn () => 0);
session_start();
$db = DosClinicas::sqlite();
$_SESSION = ['id_usuario' => 1, 'usuario_nombre' => 'Ana Norte'];
$contexto = Contexto::deClinica(1, 'Clínica Norte', Roles::ADMIN);
Contexto::activar($contexto, 1);
Security::definirFuenteDeContextos(static fn (): array => [$contexto]);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['action' => 'admin_suscripcion'] + (($argv[2] ?? '') !== '' ? ['desde' => $argv[2]] : []);
$db->exec("UPDATE clinicas SET nombre='Clínica Norte <prueba>' WHERE id_clinica=1");
$escenario = $argv[1] ?? 'gracia';
if ($escenario !== 'legado') {
    $db->exec("UPDATE suscripciones SET gestion_cobro='gestionada',periodo_inicio='2026-09-25 00:00:00',periodo_fin='2026-10-25 00:00:00' WHERE id_clinica=1");
}
$ahora = VigenciaSuscripcion::fecha($escenario === 'congelada' ? '2026-10-28 00:00:00' : '2026-10-26 12:00:00');
$config = in_array($escenario, ['activa', 'historial', 'fuente', 'automatico', 'nequi'], true) ? ClienteWompiFalso::configuracion('test', '1', '1') : new ConfiguracionWompi([]);
$cliente = new ClienteWompiFalso();
$cliente->datosComercio = ClienteWompiFalso::contratos();
if (in_array($escenario, ['fuente', 'automatico', 'nequi'], true)) {
    $fuentes = new FuentePago($db, 1, $cliente, $config, static fn () => $ahora);
    $version = AutorizacionCobro::contratos($cliente->datosComercio)['version'];
    if ($escenario === 'nequi') {
        $cliente->fuente = ['id' => 99, 'type' => 'NEQUI', 'status' => 'AVAILABLE'];
        $fuentes->guardar('NEQUI', 'nequi_test_visual', true, true, $version, 1);
    } else {
        $fuentes->guardar('CARD', 'tok_test_visual', true, true, $version, 1);
    }
    if ($escenario === 'automatico') {
        $fuentes->automatico(true, $version, 8999000, 1);
    }
}
if ($escenario === 'historial') {
    putenv('APP_URL=http://localhost/Zooki/public');
    $pago = (new PagoSuscripcion($db, 1, $config, static fn () => $ahora))->reservar('mensual', 0, 1);
    $tx = ['id' => 'tx_visual', 'reference' => $pago['checkout']['reference'], 'amount_in_cents' => 8999000, 'currency' => 'COP', 'status' => 'APPROVED'];
    $cliente = new ClienteWompiFalso();
    $cliente->respuestas['tx_visual'] = $tx;
    (new EventoWompi($db))->recibir(FirmaWompi::evento(ClienteWompiFalso::cuerpo($tx), '', 'test', 'test_events_falso'));
    (new ConciliadorPago($db, $cliente, $config, static fn () => $ahora))->procesar(1);
}
(new SuscripcionController($db, static fn () => $ahora, $config, $cliente))->vista();
