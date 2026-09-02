<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/IntentoLogin.php';

/**
 * HU-38 (VD-SEG-05) — El limite de intentos debe vivir del lado del servidor.
 *
 * El contador anterior estaba en $_SESSION: bastaba con no enviar la cookie
 * para que cada intento empezara en cero, asi que el limite de 5 intentos no
 * frenaba a ningun script. Estas pruebas ejercitan el almacen que lo sustituye.
 */
class IntentoLoginTest extends TestCase
{
    private $db;
    private $intentos;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->db->exec("
            CREATE TABLE intentos_login (
                id_intento INTEGER PRIMARY KEY AUTOINCREMENT,
                identificador TEXT NOT NULL UNIQUE,
                intentos INTEGER NOT NULL DEFAULT 0,
                bloqueado_hasta TEXT DEFAULT NULL,
                primer_intento TEXT NOT NULL,
                ultimo_intento TEXT NOT NULL
            )
        ");

        IntentoLogin::olvidarEstadoDeTabla();
        $this->intentos = new IntentoLogin($this->db);
    }

    protected function tearDown(): void
    {
        IntentoLogin::olvidarEstadoDeTabla();
    }

    public function testSinIntentosNoHayBloqueo()
    {
        $this->assertSame(0, $this->intentos->segundosDeBloqueo('ip:1.2.3.4'));
    }

    public function testBloqueaAlLlegarAlMaximo()
    {
        for ($i = 1; $i < 5; $i++) {
            $this->intentos->registrarFallo('cuenta:123', 5, 900, 900);
            $this->assertSame(0, $this->intentos->segundosDeBloqueo('cuenta:123'), "no deberia bloquear en el intento $i");
        }

        $this->intentos->registrarFallo('cuenta:123', 5, 900, 900);
        $this->assertGreaterThan(0, $this->intentos->segundosDeBloqueo('cuenta:123'), 'el quinto intento debe bloquear');
    }

    /**
     * El punto de toda la HU: el contador no depende de nada que mande el
     * cliente, asi que "empezar una sesion nueva" no lo reinicia.
     */
    public function testElContadorSobreviveAUnClienteQueDescartaSuSesion()
    {
        // Cada llamada simula una peticion distinta, sin estado compartido:
        // un objeto nuevo, como si fuera otro proceso PHP.
        for ($i = 0; $i < 5; $i++) {
            $almacenNuevo = new IntentoLogin($this->db);
            $almacenNuevo->registrarFallo('ip:9.9.9.9', 5, 900, 900);
        }

        $otroMas = new IntentoLogin($this->db);
        $this->assertGreaterThan(0, $otroMas->segundosDeBloqueo('ip:9.9.9.9'));
    }

    /** Los contadores no se pisan entre si. */
    public function testCadaIdentificadorLlevaSuPropioContador()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->intentos->registrarFallo('cuenta:111', 5, 900, 900);
        }

        $this->assertGreaterThan(0, $this->intentos->segundosDeBloqueo('cuenta:111'));
        $this->assertSame(0, $this->intentos->segundosDeBloqueo('cuenta:222'), 'otra cuenta no debe verse afectada');
        $this->assertSame(0, $this->intentos->segundosDeBloqueo('ip:1.1.1.1'), 'la IP lleva contador aparte');
    }

    /** Un inicio de sesion correcto limpia el contador de esa cuenta. */
    public function testLimpiarBorraElBloqueo()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->intentos->registrarFallo('cuenta:123', 5, 900, 900);
        }
        $this->assertGreaterThan(0, $this->intentos->segundosDeBloqueo('cuenta:123'));

        $this->intentos->limpiar('cuenta:123');
        $this->assertSame(0, $this->intentos->segundosDeBloqueo('cuenta:123'));
    }

    /** Ventana deslizante: pasado el periodo, la cuenta arranca de nuevo. */
    public function testLaVentanaVencidaReiniciaElContador()
    {
        // Ventana de 1 segundo: cuatro intentos no alcanzan a bloquear.
        for ($i = 0; $i < 4; $i++) {
            $this->intentos->registrarFallo('ip:5.5.5.5', 5, 1, 900);
        }
        $this->assertSame(0, $this->intentos->segundosDeBloqueo('ip:5.5.5.5'));

        // Se envejece el registro a mano en vez de dormir el test.
        $this->db->exec("UPDATE intentos_login SET primer_intento = '2020-01-01 00:00:00' WHERE identificador = 'ip:5.5.5.5'");

        // Con la ventana vencida el contador vuelve a 1, no a 5.
        $this->intentos->registrarFallo('ip:5.5.5.5', 5, 1, 900);
        $this->assertSame(0, $this->intentos->segundosDeBloqueo('ip:5.5.5.5'));
        $this->assertSame(1, (int) $this->db->query("SELECT intentos FROM intentos_login WHERE identificador='ip:5.5.5.5'")->fetchColumn());
    }

    /** El bloqueo se levanta solo cuando pasa el castigo. */
    public function testElBloqueoExpira()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->intentos->registrarFallo('cuenta:777', 5, 900, 900);
        }
        $this->assertGreaterThan(0, $this->intentos->segundosDeBloqueo('cuenta:777'));

        $this->db->exec("UPDATE intentos_login SET bloqueado_hasta = '2020-01-01 00:00:00' WHERE identificador = 'cuenta:777'");
        $this->assertSame(0, $this->intentos->segundosDeBloqueo('cuenta:777'), 'un bloqueo vencido ya no cuenta');
    }

    /** El mantenimiento no debe borrar un bloqueo que sigue vigente. */
    public function testPurgarConservaLosBloqueosActivos()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->intentos->registrarFallo('cuenta:888', 5, 900, 900);
        }
        $this->db->exec("UPDATE intentos_login SET ultimo_intento = '2020-01-01 00:00:00' WHERE identificador = 'cuenta:888'");

        $this->intentos->purgar(3600);

        $this->assertGreaterThan(0, $this->intentos->segundosDeBloqueo('cuenta:888'), 'un bloqueo vigente no se purga aunque sea viejo');
    }

    public function testPurgarEliminaContadoresViejosYaLiberados()
    {
        $this->intentos->registrarFallo('ip:3.3.3.3', 5, 900, 900);
        $this->db->exec("UPDATE intentos_login SET ultimo_intento = '2020-01-01 00:00:00' WHERE identificador = 'ip:3.3.3.3'");

        $this->intentos->purgar(3600);

        $this->assertSame(0, (int) $this->db->query("SELECT COUNT(*) FROM intentos_login WHERE identificador='ip:3.3.3.3'")->fetchColumn());
    }
}
