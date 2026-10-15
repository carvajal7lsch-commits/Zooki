<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/GrafoConocimiento.php';

/**
 * HU-2.12 — Mapa del conocimiento clínico en el panel de plataforma.
 * Solo lectura (RE-2.12.5); el permiso lo decide la matriz de Security (RE-2.12.1).
 */
final class GrafoConocimientoController
{
    private GrafoConocimiento $modelo;

    public function __construct(?PDO $db = null, ?string $manifiesto = null)
    {
        $this->modelo = new GrafoConocimiento($db ?? (new Database())->getConnection(), $manifiesto);
    }

    public function vista(): void
    {
        require __DIR__ . '/../views/plataforma/grafo.php';
    }

    public function datosAjax(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'El mapa solo se consulta.']);
            return;
        }
        try {
            echo json_encode(['success' => true, 'datos' => $this->modelo->mapa()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            error_log('C6 mapa del conocimiento: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'No se pudo leer el conocimiento. Inténtalo nuevamente.']);
        }
    }
}
