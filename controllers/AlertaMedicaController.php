<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/AlertaMedica.php';
require_once __DIR__ . '/../models/NodosEnlazables.php';
require_once __DIR__ . '/../helpers/RespuestaJson.php';
require_once __DIR__ . '/../helpers/ValidadorClinico.php';

/**
 * HU-2.1, RE-2.1.5 y RE-2.10.1 — Alergias y alertas médicas en la clínica.
 * Los permisos por rol están en la matriz de Security; el alcance por clínica
 * (RN-112, RN-113, RN-115) lo aplica el modelo. Lo que escribe devuelve la
 * lista actualizada para pintarla sin recargar.
 */
final class AlertaMedicaController
{
    private AlertaMedica $alertas;
    private NodosEnlazables $nodos;

    public function __construct(?PDO $db = null)
    {
        $db ??= (new Database())->getConnection();
        $this->alertas = new AlertaMedica($db);
        $this->nodos = new NodosEnlazables($db);
    }

    public function listarAjax(): void
    {
        RespuestaJson::consulta(function (): void {
            $idMascota = ValidadorClinico::id($_GET['id_mascota'] ?? null);
            if ($idMascota === null) {
                throw new InvalidArgumentException('Mascota no válida.');
            }
            RespuestaJson::enviar(['success' => true, 'alertas' => $this->alertas->deMascota($idMascota)]);
        }, 'C1 alertas médicas');
    }

    public function registrarAjax(): void
    {
        RespuestaJson::modificacion(function (): array {
            $this->alertas->registrar($_POST);
            $idMascota = (int) ValidadorClinico::id($_POST['id_mascota'] ?? null);
            return ['message' => 'Alerta registrada. Ya la ven las clínicas vinculadas.', 'alertas' => $this->alertas->deMascota($idMascota)];
        }, 'C1 registrar alerta');
    }

    public function desactivarAjax(): void
    {
        RespuestaJson::modificacion(function (): array {
            $idMascota = $this->alertas->desactivar($_POST['id_alerta'] ?? null);
            return ['message' => 'Alerta desactivada. Queda en el historial.', 'alertas' => $this->alertas->deMascota($idMascota)];
        }, 'C1 desactivar alerta');
    }

    /** A.2: nodos activos del tipo que admite la alerta, por etiqueta o sinónimo. */
    public function buscarNodosAjax(): void
    {
        RespuestaJson::consulta(function (): void {
            $tipo = ValidadorAlertaMedica::tipo($_GET['tipo'] ?? null);
            RespuestaJson::enviar(['success' => true, 'nodos' => $this->nodos->buscar($tipo, (string) ($_GET['q'] ?? ''))]);
        }, 'C1 buscar nodos');
    }
}
