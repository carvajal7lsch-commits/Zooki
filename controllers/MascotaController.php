<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Mascota.php';
require_once __DIR__ . '/../models/PropietarioClinica.php';
require_once __DIR__ . '/../models/AvisoFichaMascota.php';
require_once __DIR__ . '/../helpers/ValidadorTelefono.php';
require_once __DIR__ . '/../helpers/FotoMascota.php';

/** C3: el controlador valida la petición; los modelos resuelven el contexto. */
class MascotaController
{
    private PDO $db;
    private Mascota $mascotaModel;
    private PropietarioClinica $propietarioModel;
    public function __construct(?PDO $db=null,private $enviarAviso=null)
    {
        $this->db=$db ?? (new Database())->getConnection();
        $this->mascotaModel=new Mascota($this->db);
        $this->propietarioModel=new PropietarioClinica($this->db);
    }
    private function json($datos): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos,JSON_UNESCAPED_UNICODE);
    }
    private function modificar(callable $accion): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); $this->json(['success'=>false,'message'=>'Método no permitido.']); return; }
        try { $this->json(['success'=>true]+($accion() ?? [])); }
        catch (AccesoDenegado $e) { throw $e; }
        catch (InvalidArgumentException $e) { http_response_code(422); $this->json(['success'=>false,'message'=>$e->getMessage()]); }
        catch (Throwable $e) { error_log('C3: ' . $e->getMessage()); http_response_code(500); $this->json(['success'=>false,'message'=>'No se pudo guardar. Intenta nuevamente.']); }
    }
    private function id($valor): int
    {
        if (!ctype_digit((string)$valor) || (int)$valor<1) throw new InvalidArgumentException('Identificador no válido.');
        return (int)$valor;
    }
    public function listar(): array { return $this->mascotaModel->getAll(true); }
    public function listarMascotasAjax(): void { $this->json($this->listar()); }
    public function buscar(): void { $this->json($this->mascotaModel->search(trim((string)($_GET['query'] ?? '')))); }
    public function getMascotaAjax(): void
    {
        $this->json($this->mascotaModel->getById($this->id($_GET['id'] ?? '')));
    }
    private function datos(array $entrada): array
    {
        $datos=array_intersect_key($entrada,array_flip(['nombre','fecha_nacimiento','peso','sexo','estado','esterilizado']));
        if (isset($entrada['id_propietario'])) $datos['id_propietario']=$this->id($entrada['id_propietario']);
        if (array_key_exists('especie',$entrada)) $datos['id_especie']=$this->id($entrada['especie']);
        if (array_key_exists('raza',$entrada)) {
            $otra=in_array($entrada['raza'],['Otra','otra'],true);
            $datos['id_raza']=$otra ? null : $this->id($entrada['raza']);
            $datos['raza_indicada']=$otra ? trim((string)($entrada['raza_indicada'] ?? $entrada['nueva_raza'] ?? '')) : null;
            if ($otra && $datos['raza_indicada'] === '') throw new InvalidArgumentException('Indica la raza por confirmar.');
        }
        if (array_key_exists('colores',$entrada)) $datos['colores']=$entrada['colores'];
        return $datos;
    }
    public function registrarAjax(): void
    {
        $this->modificar(function () {
            $datos=$this->datos($_POST);
            $foto=FotoMascota::guardar($_FILES['foto'] ?? null,$datos['nombre'] ?? '',$error);
            if ($foto === false) throw new InvalidArgumentException($error);
            $datos['url_foto']=$foto;
            try { $id=$this->mascotaModel->insert($datos); }
            catch (Throwable $e) { if ($foto !== null) FotoMascota::eliminarAnterior($foto,''); throw $e; }
            return ['id_mascota'=>$id];
        });
    }
    public function actualizarAjax(): void
    {
        $this->modificar(function () {
            $id=$this->id($_POST['id_mascota'] ?? '');
            $actual=$this->mascotaModel->getById($id);
            $datos=$this->datos($_POST)+['id_mascota'=>$id];
            // Rechazar campos protegidos antes de guardar archivos.
            $this->mascotaModel->comprobarEdicion($actual,$datos);
            $foto=FotoMascota::guardar($_FILES['foto'] ?? null,$datos['nombre'] ?? $actual['nombre'],$error);
            if ($foto === false) throw new InvalidArgumentException($error);
            if ($foto !== null) $datos['url_foto']=$foto;
            try { $this->mascotaModel->update($datos); }
            catch (Throwable $e) { if ($foto !== null) FotoMascota::eliminarAnterior($foto,''); throw $e; }
            if ($foto !== null) FotoMascota::eliminarAnterior($actual['url_foto'],$foto);
            (new AvisoFichaMascota($this->db))->enviarPendientes($id,$this->enviarAviso);
            return [];
        });
    }
    public function actualizar(): void { $this->actualizarAjax(); }
    public function cambiarEstadoAjax(): void
    {
        $this->modificar(function () {
            $id=$this->id($_POST['id_mascota'] ?? '');
            $this->mascotaModel->updateStatus($id,$_POST['estado'] ?? '');
            (new AvisoFichaMascota($this->db))->enviarPendientes($id,$this->enviarAviso);
            return [];
        });
    }
    public function vincularMascotaAjax(): void
    {
        $this->modificar(function () {
            $this->mascotaModel->vincular($this->id($_POST['id_mascota'] ?? ''),$this->id($_POST['id_propietario'] ?? ''));
            return [];
        });
    }
    public function listarPropietariosAjax(): void { $this->json($this->propietarioModel->listar()); }
    public function buscarPropietarioExactoAjax(): void
    {
        $this->json(['success'=>true,'propietario'=>$this->propietarioModel->buscarExacto((string)($_GET['identificador'] ?? ''))]);
    }
    public function listarMascotasPorPropietarioAjax(): void
    {
        $id=$this->id($_GET['id_usuario'] ?? '');
        $this->propietarioModel->obtener($id);
        $this->json($this->mascotaModel->getByPropietario($id));
    }
    public function getPropietarioAjax(): void { $this->json($this->propietarioModel->obtener($this->id($_GET['id_usuario'] ?? ''))); }
    public function actualizarPropietarioAjax(): void
    {
        $this->modificar(function () {
            $nombre=trim((string)($_POST['nombre_completo'] ?? ''));
            // D1: la misma regla de teléfono en todo el sistema (ValidadorTelefono).
            $telefono=ValidadorTelefono::normalizar((string)($_POST['telefono'] ?? ''));
            if (!ValidadorTelefono::esValido($telefono)) throw new InvalidArgumentException(ValidadorTelefono::MENSAJE);
            if (mb_strlen($nombre)<3 || mb_strlen($nombre)>100
                || !in_array((string)($_POST['estado'] ?? ''),['0','1'],true)) throw new InvalidArgumentException('Revisa nombre y estado.');
            $this->propietarioModel->actualizar($this->id($_POST['id_usuario'] ?? ''),array_replace($_POST,['nombre_completo'=>$nombre,'telefono'=>$telefono]));
            return [];
        });
    }
    public function listarEspeciesAjax(): void { $this->json($this->mascotaModel->getEspecies()); }
    public function listarRazasAjax(): void { $this->json($this->mascotaModel->getRazasByEspecie($_GET['id_especie'] ?? 0)); }
    public function listarColoresAjax(): void { $this->json($this->mascotaModel->getColoresBase()); }
}
