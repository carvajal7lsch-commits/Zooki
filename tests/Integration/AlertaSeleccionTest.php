<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/AlertaMedica.php';

final class AlertaSeleccionTest extends TestCase
{
    private PDO $db;
    private AlertaMedica $modelo;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        $_SESSION = ['id_usuario' => DosClinicas::VET_NORTE];
        Contexto::activar(Contexto::deClinica(DosClinicas::NORTE, 'Norte', Roles::VETERINARIO), 1);
        $this->modelo = new AlertaMedica($this->db);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function entrada(array $cambios = []): array
    {
        return $cambios + ['id_mascota' => 1, 'tipo' => 'alergia', 'seleccion_estructurada' => '1', 'nodo' => 'farmaco.paracetamol', 'nombre_alerta' => 'Paracetamol (acetaminofén)', 'descripcion' => 'Sin reacción grave, solo picazón'];
    }

    public function testLosDetallesLibresNoIdentificanNiInvalidanLaSeleccion(): void
    {
        $id = $this->modelo->registrar($this->entrada());
        $fila = $this->db->query('SELECT id_nodo, descripcion FROM alertas_medicas WHERE id_alerta = ' . $id)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, (int) $fila['id_nodo']);
        $this->assertSame('Paracetamol (acetaminofén): Sin reacción grave, solo picazón', $fila['descripcion']);
    }

    public function testEscribirUnNombreSinElegirNoCreaUnaAsociacion(): void
    {
        $this->expectException(CampoInvalido::class);
        $this->modelo->registrar($this->entrada(['nodo' => '', 'nombre_alerta' => 'acetaminofén']));
    }

    public function testElNombreEscritoSeConservaCuandoNoEstaEnElCatalogo(): void
    {
        $id = $this->modelo->registrar($this->entrada(['nodo' => '', 'nombre_alerta' => 'Acetaminofén WN', 'no_encuentro_nombre' => '1', 'descripcion' => 'Ronchas']));
        $fila = $this->db->query('SELECT id_nodo, descripcion FROM alertas_medicas WHERE id_alerta = ' . $id)->fetch(PDO::FETCH_ASSOC);
        $this->assertNull($fila['id_nodo']);
        $this->assertSame('Acetaminofén WN: Ronchas', $fila['descripcion']);
    }

    public function testNoAceptaUnaSeleccionAnteriorConOtroNombre(): void
    {
        $this->expectException(CampoInvalido::class);
        $this->modelo->registrar($this->entrada(['nombre_alerta' => 'Meloxicam']));
    }

    public function testElCodigoDebeCorresponderAlTipoDeAlerta(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->modelo->registrar($this->entrada(['tipo' => 'condicion_cronica']));
    }

    public function testNoAceptaUnNodoInactivo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->modelo->registrar($this->entrada(['nodo' => 'farmaco.retirado', 'nombre_alerta' => 'Fármaco retirado']));
    }

    public function testNombreYDetallesCompartenElLimiteExistenteSinTruncar(): void
    {
        $this->expectException(CampoInvalido::class);
        $this->modelo->registrar($this->entrada(['descripcion' => str_repeat('a', ValidadorAlertaMedica::DESCRIPCION_MAX)]));
    }

    public function testOtraAlertaSoloNecesitaSuDescripcion(): void
    {
        $id = $this->modelo->registrar($this->entrada(['tipo' => 'otra', 'nodo' => '', 'nombre_alerta' => '', 'descripcion' => 'Revisar la piel']));
        $fila = $this->db->query('SELECT id_nodo, descripcion FROM alertas_medicas WHERE id_alerta = ' . $id)->fetch(PDO::FETCH_ASSOC);
        $this->assertNull($fila['id_nodo']);
        $this->assertSame('Revisar la piel', $fila['descripcion']);
    }
}
