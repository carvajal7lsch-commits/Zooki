<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/CascadaAgenda.php';
require_once __DIR__ . '/../helpers/RespuestaJson.php';

/** RE-4.14.4: el ID de destino se toma de la sesión, nunca del formulario. */
final class ReasignacionController
{
    private CascadaAgenda $agenda;

    public function __construct(?PDO $db = null, ?callable $reloj = null)
    {
        $this->agenda = new CascadaAgenda($db ?? (new Database())->getConnection(), $reloj);
    }

    public function listarAjax(): void
    {
        RespuestaJson::consulta(fn () => RespuestaJson::enviar(['success' => true, 'propuestas' => $this->agenda->listar()]), 'C5 propuestas');
    }

    public function responderAjax(): void
    {
        RespuestaJson::modificacion(fn () => $this->agenda->responder((int) ($_POST['id_reasignacion'] ?? 0), (string) ($_POST['respuesta'] ?? ''), $_POST['clave_operacion'] ?? null), 'C5 respuesta');
    }
}
