<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../helpers/Contexto.php';

/**
 * HU-T.8 — Registro de auditoría del administrador. Solo muestra lo ocurrido
 * en la clínica del contexto activo (RN-G13); la matriz de Security ya exige
 * el rol administrador. Es de solo lectura (RE-T.8.4).
 */
class AuditoriaController
{
    private const POR_PAGINA = 50;

    private Auditoria $auditoria;

    public function __construct($db = null)
    {
        if ($db === null) {
            $db = (new Database())->getConnection();
        }
        $this->auditoria = new Auditoria($db);
    }

    /** RE-T.8.3: filtros por persona (nombre, documento o correo), acción, tabla y fechas. */
    private function filtros(): array
    {
        return [
            'usuario' => trim((string) ($_GET['usuario'] ?? '')),
            'accion' => (string) ($_GET['accion'] ?? ''),
            'tabla' => (string) ($_GET['tabla'] ?? ''),
            'fecha_desde' => (string) ($_GET['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($_GET['fecha_hasta'] ?? ''),
        ];
    }

    public function listar(): void
    {
        $idClinica = (int) Contexto::clinicaActiva();
        $filtros = $this->filtros();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = self::POR_PAGINA;

        $logs = $this->auditoria->getLogs($idClinica, $filtros, $perPage, ($page - 1) * $perPage);
        $total = $this->auditoria->countLogs($idClinica, $filtros);
        $acciones = $this->auditoria->getDistinctAcciones($idClinica);
        $tablas = $this->auditoria->getDistinctTablas($idClinica);
        $stats_hoy = $this->auditoria->getStats($idClinica, 1);

        $content_view = __DIR__ . '/../views/admin/auditoria.php';
        require __DIR__ . '/../views/admin/layout.php';
    }

    public function listarAjax(): void
    {
        header('Content-Type: application/json');

        $idClinica = (int) Contexto::clinicaActiva();
        $filtros = $this->filtros();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = $this->auditoria->countLogs($idClinica, $filtros);

        echo json_encode([
            'success' => true,
            'logs' => $this->auditoria->getLogs($idClinica, $filtros, self::POR_PAGINA, ($page - 1) * self::POR_PAGINA),
            'total' => $total,
            'page' => $page,
            'perPage' => self::POR_PAGINA,
            'totalPages' => (int) ceil($total / self::POR_PAGINA),
        ]);
    }
}
