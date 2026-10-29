<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/PropietarioClinica.php';

final class PropietarioResumenMascotasTest extends TestCase
{
    public function testResumenLimitadoALaClinicaSinDatosDeUnDesvinculado(): void
    {
        $db = DosClinicas::sqlite();
        $db->exec("INSERT INTO propietario_clinica (id_propietario,id_clinica,estado) VALUES (6,2,'activo')");
        foreach ([2 => 'Max', 3 => 'Kira', 4 => 'Toby', 5 => 'Privada Sur', 6 => 'Inactiva', 7 => 'Vínculo cerrado'] as $id => $nombre) {
            $db->prepare('INSERT INTO mascotas (id_mascota,id_propietario,id_clinica_registro,token_carnet,nombre,estado) VALUES (?,6,1,?,?,?)')->execute([$id, str_repeat((string) $id, 43), $nombre, $id === 6 ? 0 : 1]);
            $db->prepare('INSERT INTO mascota_clinica (id_mascota,id_clinica,estado) VALUES (?,?,?)')->execute([$id, $id === 5 ? 2 : 1, $id === 7 ? 'inactivo' : 'activo']);
        }
        Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::VETERINARIO), 2);
        $modelo = new PropietarioClinica($db);
        $norte = $modelo->obtener(6);
        $this->assertSame(['Luna', 'Max', 'Kira'], $norte['mascotas_resumen']);
        $this->assertCount(3, $norte['mascotas_resumen']);
        $this->assertArrayNotHasKey('mascota_resumen_0', $norte);
        Contexto::activar(Contexto::deClinica(2, 'Sur', Roles::VETERINARIO), 4);
        $sur = (new PropietarioClinica($db))->obtener(6);
        $this->assertSame(['Privada Sur'], $sur['mascotas_resumen']);
        $db->exec("UPDATE propietario_clinica SET estado='inactivo' WHERE id_propietario=6 AND id_clinica=2");
        $desvinculado = (new PropietarioClinica($db))->obtener(6);
        $this->assertSame([], $desvinculado['mascotas_resumen']);
        $this->assertEmpty($desvinculado['email']);
        $this->assertEmpty($desvinculado['telefono']);
    }
}
