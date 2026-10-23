<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/AusenciaAgenda.php';
require_once __DIR__ . '/../helpers/RespuestaJson.php';
require_once __DIR__ . '/../helpers/Csrf.php';
require_once __DIR__ . '/../helpers/ValidadorClinico.php';

final class AusenciaAgendaController
{
    private AusenciaAgenda $modelo;

    public function __construct(?PDO $db = null)
    {
        $this->modelo = new AusenciaAgenda($db ?? (new Database())->getConnection());
    }

    public function listar(): void
    {
        RespuestaJson::consulta(fn () => RespuestaJson::enviar(['success' => true, 'ausencias' => $this->modelo->listar()]), 'C6 ausencias');
    }

    public function escribir(string $accion): void
    {
        RespuestaJson::modificacion(function () use ($accion): array {
            if (!Csrf::validate() || (string) ($_POST['id_clinica'] ?? '') !== (string) Contexto::clinicaActiva()) {
                throw new AccesoDenegado(403, 'El formulario pertenece a otro contexto o está vencido.');
            }
            if (!is_string($_POST['clave_operacion'] ?? null)) {
                throw new InvalidArgumentException('Falta la clave del comando. Recarga el formulario.');
            }
            if ($accion === 'registrar') {
                $resultado = $this->modelo->registrar($_POST);
            } else {
                $id = ValidadorClinico::id($_POST['id_ausencia'] ?? null);
                if ($id === null) {
                    throw new InvalidArgumentException('Selecciona una ausencia válida.');
                }
                $resultado = $this->modelo->resolver($id, $accion === 'aprobar', (string) ($_POST['motivo'] ?? ''), $_POST['clave_operacion']);
            }
            return ['resultado' => $resultado, 'message' => $resultado['message']];
        }, 'C6 ausencias');
    }
}
