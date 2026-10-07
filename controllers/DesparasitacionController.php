<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Desparasitacion.php';
require_once __DIR__ . '/../models/CatalogoClinica.php';
require_once __DIR__ . '/../helpers/RespuestaJson.php';

/** C4: desparasitaciones en la clínica activa (HU-3.4, RN-112, RN-207). */
class DesparasitacionController
{
    private PDO $db;
    private Desparasitacion $modelo;
    private CatalogoClinica $catalogo;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? (new Database())->getConnection();
        $this->modelo = new Desparasitacion($this->db);
        $this->catalogo = new CatalogoClinica($this->db);
    }

    public function registrarAjax(): void
    {
        RespuestaJson::modificacion(function () {
            $idDesparasitacion = $this->modelo->registrar($_POST);
            return [
                'id_desparasitacion' => $idDesparasitacion,
                'message' => 'Desparasitación registrada. Próxima dosis calculada.',
            ];
        }, 'C4 desparasitaciones');
    }

    /** «Otro producto»: se agrega al catálogo de la clínica activa. */
    public function registrarNuevoProductoAjax(): void
    {
        RespuestaJson::modificacion(function () {
            $nombre = trim((string) ($_POST['nombre_producto'] ?? ''));
            $tipo = trim((string) ($_POST['tipo'] ?? 'interna'));
            $idProducto = $this->catalogo->agregarProducto($nombre, $tipo);
            return [
                'message' => 'Producto agregado al catálogo de la clínica.',
                'id_producto' => $idProducto,
                'nombre_producto' => $nombre,
            ];
        }, 'C4 desparasitaciones');
    }

    public function getProductosAjax(): void
    {
        RespuestaJson::enviar(['success' => true, 'productos' => $this->catalogo->productos()]);
    }
}
