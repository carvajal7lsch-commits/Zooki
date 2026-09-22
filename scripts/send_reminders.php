<?php
/**
 * Recordatorios automáticos por correo (HU-10, HU-37): citas de mañana y
 * vacunas o desparasitaciones próximas a vencer.
 *
 * Corre una vez al día a las 7:00 hora de la clínica: en producción con un
 * Schedule de Dokploy, en un servidor propio con scripts/zooki.cron.
 * Si un día no corre, al siguiente se recuperan los avisos que quedaron dentro
 * de su ventana, y los envíos fallidos se reintentan (RE-37.1, RE-37.2).
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/Database.php';
require_once dirname(__DIR__) . '/config/EmailService.php';
require_once dirname(__DIR__) . '/models/Recordatorio.php';

$database = new Database();
$db = $database->getConnection();
$emailService = new EmailService();
$recordatorios = new Recordatorio($db);

// RE-37.3: hoy y mañana en la zona de la clínica, no en la del servidor.
$hoy = VentanaRecordatorio::hoy(new DateTimeImmutable('now'));
$manana = (new DateTimeImmutable($hoy))->modify('+1 day')->format('Y-m-d');

$appUrl = 'https://zooki.secarvajal.com/index.php';
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile);
    if (isset($env['APP_URL'])) {
        $appUrl = rtrim($env['APP_URL'], '/') . '/index.php';
    }
}

echo "Iniciando envío de recordatorios ($hoy)...\n";

// ── 1. Recordatorio de citas para mañana ──
$citasManana = $recordatorios->citasDeManana($manana);

foreach ($citasManana as $c) {
    $tipo_notificacion = 'recordatorio_cita_24h';
    if (!$recordatorios->puedeEnviar('cita', (int) $c['id_cita'], $tipo_notificacion)) {
        continue;
    }

    $emailService->limpiarDirecciones();
    $fechaStr = date('d/m/Y', strtotime($c['fecha']));
    $horaStr = substr($c['hora'], 0, 5);
    $asunto = "Recordatorio de cita: {$c['mascota_nombre']} - {$fechaStr} {$horaStr}";

    $contenidoHtml = '
    <p style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;">
      Te recordamos que tienes una cita programada para <strong>mañana</strong> con tu mascota <strong>' . htmlspecialchars($c['mascota_nombre']) . '</strong>.
    </p>

    <div style="background-color:#f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 24px 0;">
        <p style="margin: 0 0 8px 0; font-size: 15px; color: #1d1c1d;"><strong>📅 Fecha:</strong> ' . htmlspecialchars($fechaStr) . '</p>
        <p style="margin: 0 0 8px 0; font-size: 15px; color: #1d1c1d;"><strong>⏰ Hora:</strong> ' . htmlspecialchars($horaStr) . '</p>
        <p style="margin: 0 0 8px 0; font-size: 15px; color: #1d1c1d;"><strong>❓ Motivo:</strong> ' . htmlspecialchars($c['motivo']) . '</p>
        <p style="margin: 0; font-size: 15px; color: #1d1c1d;"><strong>🩺 Veterinario:</strong> Dr(a). ' . htmlspecialchars($c['vet_nombre']) . '</p>
    </div>

    <p style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;">
      Por favor llega puntual. Si necesitas reprogramar, contáctanos con anticipación.
    </p>';

    $cuerpo = $emailService->obtenerPlantillaBaseHTML($c['prop_nombre'], 'Recordatorio de Cita', $contenidoHtml, 'Ver mis citas', $appUrl);

    $enviado = $emailService->enviarCorreoPersonalizado($c['email'], $c['prop_nombre'], $asunto, $cuerpo);
    echo $enviado
        ? "[CITA] Enviado a {$c['email']} para {$c['mascota_nombre']}\n"
        : "[CITA] Error al enviar a {$c['email']}\n";

    $recordatorios->registrar([
        'doc_propietario' => $c['doc_propietario'],
        'tipo_entidad' => 'cita',
        'id_entidad' => $c['id_cita'],
        'email' => $c['email'],
        'tipo_notificacion' => $tipo_notificacion,
        'asunto' => $asunto,
        'mensaje' => 'Recordatorio cita 24h',
        'enviado' => $enviado,
    ]);
}

// ── 2. Recordatorio de vacunas y desparasitaciones ──
$dosis = $recordatorios->dosisPorRecordar($hoy);

if (count($dosis) === 0 && count($citasManana) === 0) {
    echo "No hay recordatorios pendientes para hoy.\n";
    exit;
}

foreach ($dosis as $v) {
    if (!$recordatorios->puedeEnviar($v['tipo_entidad'], (int) $v['id_entidad'], $v['tipo_notificacion'])) {
        continue;
    }

    $emailService->limpiarDirecciones();

    $fecha_prox = new DateTime($v['fecha_proxima']);
    $tipo_texto = $v['tipo_entidad'] == 'vacuna' ? 'Vacunación' : 'Desparasitación';
    $asunto = "Recordatorio de $tipo_texto: " . $v['mascota_nombre'];

    $contenidoHtml = '
    <p style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;">
      Te recordamos que el próximo procedimiento médico para tu mascota <strong>' . htmlspecialchars($v['mascota_nombre']) . '</strong> está programado para pronto.
    </p>

    <div style="background-color:#f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 24px 0;">
        <p style="margin: 0 0 8px 0; font-size: 15px; color: #1d1c1d;"><strong>💉 Procedimiento:</strong> ' . htmlspecialchars($v['nombre_item']) . '</p>
        <p style="margin: 0; font-size: 15px; color: #1d1c1d;"><strong>📅 Fecha Programada:</strong> ' . htmlspecialchars($fecha_prox->format('d/m/Y')) . '</p>
    </div>

    <p style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;">
      Por favor, comunícate con nosotros para agendar la cita y mantener a ' . htmlspecialchars($v['mascota_nombre']) . ' protegido(a).
    </p>';

    $cuerpo = $emailService->obtenerPlantillaBaseHTML($v['prop_nombre'], 'Recordatorio de ' . $tipo_texto, $contenidoHtml, 'Agendar Cita', $appUrl);

    $enviado = $emailService->enviarCorreoPersonalizado($v['email'], $v['prop_nombre'], $asunto, $cuerpo);
    echo $enviado
        ? "Enviado a {$v['email']} para {$v['mascota_nombre']} ({$v['tipo_entidad']})\n"
        : "Error al enviar a {$v['email']}\n";

    $recordatorios->registrar([
        'doc_propietario' => $v['doc_propietario'],
        'tipo_entidad' => $v['tipo_entidad'],
        'id_entidad' => $v['id_entidad'],
        'email' => $v['email'],
        'tipo_notificacion' => $v['tipo_notificacion'],
        'asunto' => $asunto,
        'mensaje' => 'Cuerpo del correo guardado',
        'enviado' => $enviado,
    ]);
}

echo "Proceso finalizado.\n";
