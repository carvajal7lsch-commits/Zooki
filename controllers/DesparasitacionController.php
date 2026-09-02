<?php
require_once '../config/Database.php';
require_once '../models/Desparasitacion.php';
require_once '../models/Mascota.php';
require_once '../helpers/ValidadorClinico.php';

class DesparasitacionController {
    private $db;
    private $model;
    private $mascotaModel;

    /** Valores admitidos por los ENUM de la tabla `desparasitaciones`. */
    private const TIPOS = ['interna', 'externa'];
    private const PERIODICIDADES = ['mensual', 'trimestral', 'semestral'];

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->model = new Desparasitacion($this->db);
        $this->mascotaModel = new Mascota($this->db);
    }

    public function registrarAjax() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');

            // HU-35: sin esta comprobación se podía registrar una
            // desparasitación contra un id_mascota inexistente.
            $idMascota = ValidadorClinico::id($_POST['id_mascota'] ?? null);
            if ($idMascota === null || $this->mascotaModel->getPropietarioSiActiva($idMascota) === null) {
                echo json_encode(['success' => false, 'message' => 'La mascota indicada no existe o está inactiva']);
                exit;
            }

            // Los ENUM de MySQL rechazan en silencio (guardan ''), así que el
            // valor se valida aquí contra la lista real de la columna.
            $tipo = ValidadorClinico::opcion($_POST['tipo'] ?? null, self::TIPOS);
            if ($tipo === null) {
                echo json_encode(['success' => false, 'message' => 'El tipo de desparasitación debe ser interna o externa']);
                exit;
            }

            $periodicidad = ValidadorClinico::opcion($_POST['periodicidad'] ?? null, self::PERIODICIDADES);
            if ($periodicidad === null) {
                echo json_encode(['success' => false, 'message' => 'La periodicidad debe ser mensual, trimestral o semestral']);
                exit;
            }

            $producto = ValidadorClinico::textoRequerido($_POST['producto'] ?? null, 150);
            if ($producto === null) {
                echo json_encode(['success' => false, 'message' => 'El producto es obligatorio']);
                exit;
            }

            $fechaAplicacion = ValidadorClinico::fechaNoFutura($_POST['fecha_aplicacion'] ?? null);
            if ($fechaAplicacion === null) {
                echo json_encode(['success' => false, 'message' => 'La fecha de aplicación no es válida o está en el futuro']);
                exit;
            }

            $data = [
                'id_mascota' => $idMascota,
                'tipo' => $tipo,
                'producto' => $producto,
                'periodicidad' => $periodicidad,
                'fecha_aplicacion' => $fechaAplicacion,
                'observaciones' => ValidadorClinico::textoOpcional($_POST['observaciones'] ?? null, 5000)
            ];

            if ($this->model->insert($data)) {
                echo json_encode(['success' => true, 'message' => 'Desparasitación registrada. Próxima dosis calculada.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al registrar la desparasitación']);
            }
            exit;
        }
    }

    public function listarPendientesAjax() {
        $pendientes = $this->model->getPendientesSemana();
        header('Content-Type: application/json');
        echo json_encode($pendientes);
        exit;
    }

    public function registrarNuevoProductoAjax() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre_producto = trim($_POST['nombre_producto']);
            $tipo = isset($_POST['tipo']) ? trim($_POST['tipo']) : 'interna';

            if (empty($nombre_producto)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'El nombre del producto es requerido']);
                exit;
            }

            $id_producto = $this->model->insertarNuevoProducto($nombre_producto, $tipo);

            if ($id_producto) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Producto registrado exitosamente',
                    'id_producto' => $id_producto,
                    'nombre_producto' => $nombre_producto
                ]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Error al registrar el producto']);
            }
            exit;
        }
    }

    public function getProductosAjax() {
        $query = "SELECT id_producto, nombre_producto, tipo FROM productos_desparasitacion_base WHERE estado = 1 ORDER BY nombre_producto";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'productos' => $productos]);
        exit;
    }
}
?>
