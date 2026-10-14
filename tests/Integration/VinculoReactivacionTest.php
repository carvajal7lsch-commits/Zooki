<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/PropietarioClinica.php';
require_once __DIR__ . '/../../controllers/MascotaController.php';
require_once __DIR__ . '/../../helpers/Security.php';

/**
 * D1 (RN-109, revisión de C9.1) — La clínica desactiva el vínculo de un
 * propietario, pero no lo reactiva por su cuenta: se reactiva solo cuando el
 * titular confirma por correo.
 */
final class VinculoReactivacionTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        // La solicitud muestra las mascotas básicas: necesita la taxonomía de C3.
        DosClinicas::crearMascotasSqlite($this->db);
        // C1.1: desactivar el vínculo revisa las citas sin resolver de la clínica.
        DosClinicas::crearHistoriaSqlite($this->db);
        Security::definirAuditoria(new Auditoria($this->db));
        $_SESSION = ['id_usuario' => DosClinicas::ADMIN_NORTE];
        Contexto::activar(Contexto::deClinica(DosClinicas::NORTE, 'Clínica Norte', Roles::ADMIN), 1);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_POST = [];
    }

    protected function tearDown(): void
    {
        Security::definirAuditoria(false);
        $_SESSION = [];
        $_POST = [];
        http_response_code(200);
    }

    private function vinculo(int $idPropietario, int $idClinica): ?string
    {
        $stmt = $this->db->prepare('SELECT estado FROM propietario_clinica WHERE id_propietario = ? AND id_clinica = ?');
        $stmt->execute([$idPropietario, $idClinica]);
        $estado = $stmt->fetchColumn();
        return $estado === false ? null : $estado;
    }

    private function datosFabio(string $estado): array
    {
        return ['nombre_completo' => 'Fabio Dueño', 'telefono' => '3001234567', 'estado' => $estado];
    }

    public function testLaClinicaPuedeDesactivarElVinculo(): void
    {
        (new PropietarioClinica($this->db))->actualizar(DosClinicas::PROPIETARIO, $this->datosFabio('0'));

        $this->assertSame('inactivo', $this->vinculo(DosClinicas::PROPIETARIO, DosClinicas::NORTE));
    }

    public function testElModeloNoReactivaUnVinculoInactivo(): void
    {
        $this->db->exec("UPDATE propietario_clinica SET estado='inactivo' WHERE id_propietario=6 AND id_clinica=1");

        try {
            (new PropietarioClinica($this->db))->actualizar(DosClinicas::PROPIETARIO, $this->datosFabio('1'));
            $this->fail('La clínica reactivó el vínculo sin la confirmación del titular.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('confirma', $e->getMessage());
        }
        $this->assertSame('inactivo', $this->vinculo(DosClinicas::PROPIETARIO, DosClinicas::NORTE));
    }

    public function testUnaPeticionDirectaAlControladorNoLoReactiva(): void
    {
        $this->db->exec("UPDATE propietario_clinica SET estado='inactivo' WHERE id_propietario=6 AND id_clinica=1");
        $_POST = ['id_usuario' => '6'] + $this->datosFabio('1');

        ob_start();
        (new MascotaController($this->db))->actualizarPropietarioAjax();
        $respuesta = json_decode((string) ob_get_clean(), true);

        $this->assertFalse($respuesta['success']);
        $this->assertSame(422, http_response_code());
        $this->assertSame('inactivo', $this->vinculo(DosClinicas::PROPIETARIO, DosClinicas::NORTE));
    }

    /** RN-115 (decisión del usuario, M5 C1): la clínica no sobrescribe los datos de un cliente desvinculado. */
    public function testUnVinculoInactivoNoSeEditaDesdeLaClinica(): void
    {
        $this->db->exec("UPDATE propietario_clinica SET estado='inactivo' WHERE id_propietario=6 AND id_clinica=1");

        try {
            (new PropietarioClinica($this->db))->actualizar(DosClinicas::PROPIETARIO, ['nombre_completo' => 'Fabio Corregido', 'telefono' => '3009999999', 'estado' => '0']);
            $this->fail('La clínica editó a un cliente desvinculado.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(PropietarioClinica::MENSAJE_DESVINCULADO, $e->getMessage());
        }

        $this->assertSame('Fabio Dueño', $this->db->query('SELECT nombre_completo FROM usuarios WHERE id_usuario = 6')->fetchColumn());
        $this->assertNull($this->db->query('SELECT telefono FROM usuarios WHERE id_usuario = 6')->fetchColumn());
        $this->assertSame('inactivo', $this->vinculo(DosClinicas::PROPIETARIO, DosClinicas::NORTE));
    }

    public function testSoloLaConfirmacionDelTitularReactivaElVinculo(): void
    {
        $this->db->exec("UPDATE propietario_clinica SET estado='inactivo' WHERE id_propietario=6 AND id_clinica=1");
        $modelo = new PropietarioClinica($this->db);

        $solicitud = $modelo->solicitarVinculo('fabio@zooki.test');
        $this->assertSame('inactivo', $this->vinculo(DosClinicas::PROPIETARIO, DosClinicas::NORTE));

        $this->assertTrue($modelo->confirmarVinculo($solicitud['id_enlace'], $solicitud['token']));
        $this->assertSame('activo', $this->vinculo(DosClinicas::PROPIETARIO, DosClinicas::NORTE));
    }
}
