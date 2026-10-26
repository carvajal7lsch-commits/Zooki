<?php
require_once __DIR__ . '/DosClinicas.php';
require_once __DIR__ . '/ClienteWompiFalso.php';
require_once __DIR__ . '/../../models/PagoSuscripcion.php';
require_once __DIR__ . '/../../models/ConciliadorPago.php';
require_once __DIR__ . '/../../models/Plataforma.php';

/** S4: ningún paciente ni historia; API falsa incluso para preparar evidencia. */
final class FinanzasFixture
{
    public static function datos(PDO $db): void
    {
        $db->exec("UPDATE suscripciones SET gestion_cobro='gestionada',periodo_inicio='2026-09-25 00:00:00',periodo_fin='2026-10-25 00:00:00' WHERE id_clinica=1");
        $config = ClienteWompiFalso::configuracion();
        $reloj = static fn () => VigenciaSuscripcion::fecha('2026-10-26 08:00:00');
        $reserva = (new PagoSuscripcion($db, 1, $config, $reloj))->reservar('mensual', 0, 1);
        $cliente = new ClienteWompiFalso();
        $tx = ['id' => 's4_tx', 'reference' => $reserva['checkout']['reference'], 'status' => 'APPROVED', 'amount_in_cents' => 8999000, 'currency' => 'COP'];
        foreach (['APPROVED', 'VOIDED'] as $n => $estado) {
            $tx['status'] = $estado;
            $cliente->respuestas['s4_tx'] = $tx;
            (new EventoWompi($db, $reloj))->recibir(FirmaWompi::evento(ClienteWompiFalso::cuerpo($tx, 'test', $n + 1), '', 'test', 'test_events_falso'));
            (new ConciliadorPago($db, $cliente, $config, $reloj))->procesar((int) $db->query('SELECT MAX(id_evento) FROM wompi_eventos')->fetchColumn());
        }
        $tx['id'] = 's4_sin_clinica';
        $tx['reference'] = 'referencia_desconocida';
        $cliente->respuestas[$tx['id']] = $tx;
        (new EventoWompi($db, $reloj))->recibir(FirmaWompi::evento(ClienteWompiFalso::cuerpo($tx, 'test', 3), '', 'test', 'test_events_falso'));
        (new ConciliadorPago($db, $cliente, $config, $reloj))->procesar((int) $db->query('SELECT MAX(id_evento) FROM wompi_eventos')->fetchColumn());
        $db->exec("UPDATE suscripciones SET motivo_automatico_apagado='condiciones' WHERE id_clinica=1");
    }

    public static function plataforma(): void
    {
        $_SESSION = ['id_usuario' => DosClinicas::SUPER_ADMIN, 'usuario_nombre' => 'Plataforma'];
        Contexto::activar(Contexto::dePlataforma(), 1);
    }
}
