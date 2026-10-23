<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/CoordinacionHorarios.php';
require_once __DIR__ . '/../helpers/RespuestaJson.php';
require_once __DIR__ . '/../helpers/Csrf.php';
require_once __DIR__ . '/../helpers/ValidadorClinico.php';

final class CoordinacionHorariosController
{
    private CoordinacionHorarios $modelo;

    public function __construct(?PDO $db = null)
    {
        $this->modelo = new CoordinacionHorarios($db ?? (new Database())->getConnection());
    }

    private function id(string $campo): int
    {
        $id = ValidadorClinico::id($_POST[$campo] ?? $_GET[$campo] ?? null);
        if ($id === null) {
            throw new CampoInvalido($campo, 'Identificador no válido.', 'Elige un registro válido.');
        }
        return $id;
    }

    public function leer(): void
    {
        RespuestaJson::consulta(fn () => RespuestaJson::enviar(['success' => true] + $this->modelo->leer(isset($_GET['id_veterinario']) ? $this->id('id_veterinario') : null)), 'C2 horarios');
    }

    public function escribir(string $accion): void
    {
        RespuestaJson::modificacion(function () use ($accion): array {
            if (!Csrf::validate() || (isset($_POST['id_clinica']) && (!is_scalar($_POST['id_clinica']) || (string) $_POST['id_clinica'] !== (string) Contexto::clinicaActiva()))) {
                throw new AccesoDenegado(403, 'El formulario pertenece a otro contexto o está vencido.');
            }
            $clave = $_POST['clave_operacion'] ?? null;
            if (!is_string($clave)) {
                throw new InvalidArgumentException('Falta la clave del comando. Recarga el formulario.');
            }
            $franjas = in_array($accion, ['guardar', 'proponer'], true)
                ? json_decode(is_string($_POST['franjas'] ?? null) ? $_POST['franjas'] : '', true) : [];
            if (!is_array($franjas)) {
                throw new CampoInvalido('franjas', 'Franjas no válidas.', 'Revisa las franjas.');
            }
            $resultado = match ($accion) {
                'guardar' => $this->modelo->guardarVeterinario($this->id('id_veterinario'), $franjas, $clave),
                'proponer' => $this->modelo->proponer($this->id('id_veterinario'), $franjas, $clave),
                'aprobar' => $this->modelo->resolver($this->id('id_propuesta'), true, '', $clave),
                'rechazar' => $this->modelo->resolver($this->id('id_propuesta'), false, $_POST['motivo'] ?? '', $clave),
                'cerrar' => $this->modelo->cerrar($_POST['inicio'] ?? '', $_POST['fin'] ?? '', $_POST['motivo'] ?? '', $clave),
                'retirar' => $this->modelo->retirarCierre($this->id('id_cierre'), $clave),
                default => throw new InvalidArgumentException('Comando no válido.'),
            };
            return ['resultado' => $resultado, 'message' => 'Cambio guardado. Las citas afectadas quedan pendientes de reajuste.'];
        }, 'C2 horarios');
    }
}
