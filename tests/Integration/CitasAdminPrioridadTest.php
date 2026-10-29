<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../controllers/CitaController.php';

/** RE-6.2.5 / RE-4.13.6 / RNF-11: prioridad existente y aislamiento de la respuesta administrativa. */
final class CitasAdminPrioridadTest extends TestCase
{
    public function testListaPrioridadSinIncluirCitasDeLaOtraClinica(): void
    {
        $db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($db);
        DosClinicas::crearCatalogosSqlite($db);
        DosClinicas::crearHistoriaSqlite($db);
        DosClinicas::poblarAgenda($db);
        $insertar = $db->prepare("INSERT INTO citas (id_clinica,id_mascota,id_veterinario,id_tipo_cita,fecha,hora,motivo,prioridad) VALUES (?,1,?,1,'2030-01-07','09:00:00','Revisión',?)");
        $insertar->execute([1, 2, 'naranja']);
        $propia = (int) $db->lastInsertId();
        $insertar->execute([2, 3, 'rojo']);
        $_SESSION = ['id_usuario' => 1];
        Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::ADMIN), 1);
        ob_start();
        try {
            (new CitaController($db))->listarTodasCitasAjax();
        } finally {
            $json = ob_get_clean();
        }
        $respuesta = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($respuesta['success']);
        self::assertCount(1, $respuesta['citas']);
        self::assertSame($propia, $respuesta['citas'][0]['id_cita']);
        self::assertSame('naranja', $respuesta['citas'][0]['prioridad']);
        self::assertSame('Revisión', $respuesta['citas'][0]['motivo']);
    }
}
