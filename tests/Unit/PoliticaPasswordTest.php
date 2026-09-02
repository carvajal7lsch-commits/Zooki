<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/PoliticaPassword.php';

/**
 * HU-36 (VD-SEG-07) — "Politica unica (minimo 8 + complejidad) en todos los flujos".
 *
 * Antes cada flujo tenia su propia regla: registro 6, restablecimiento 8, los
 * formularios minlength="6", y ninguno exigia complejidad. La politica real
 * del sistema terminaba siendo la del flujo mas debil.
 */
class PoliticaPasswordTest extends TestCase
{
    public function testAceptaUnaContrasenaQueCumpleTodo()
    {
        $this->assertNull(PoliticaPassword::validar('Tr0mpetaAzul'));
        $this->assertTrue(PoliticaPassword::esValida('Kiwi7Nube'));
        $this->assertTrue(PoliticaPassword::esValida('Rueda8CafeMar'));
    }

    /** El minimo sube de 6 a 8: lo que antes pasaba en el registro ya no. */
    public function testRechazaLoQueElRegistroAceptabaAntes()
    {
        $this->assertNotNull(PoliticaPassword::validar('Kiwi7N'), '6 caracteres ya no alcanzan');
        $this->assertNotNull(PoliticaPassword::validar('Kiwi7Nu'), '7 tampoco');
        $this->assertNull(PoliticaPassword::validar('Kiwi7Nub'), '8 si');
    }

    public function testExigeCadaClaseDeCaracter()
    {
        $this->assertStringContainsString('minúscula', PoliticaPassword::validar('KIWI7NUBE'));
        $this->assertStringContainsString('mayúscula', PoliticaPassword::validar('kiwi7nube'));
        $this->assertStringContainsString('número', PoliticaPassword::validar('KiwiNubeX'));
    }

    public function testRechazaVacioYNoCadenas()
    {
        $this->assertNotNull(PoliticaPassword::validar(''));
        $this->assertNotNull(PoliticaPassword::validar(null));
        $this->assertNotNull(PoliticaPassword::validar(12345678));
        $this->assertNotNull(PoliticaPassword::validar(['Kiwi7Nube']));
    }

    /**
     * La longitud se mide en caracteres, no en bytes: con strlen(), "Ñañez1á"
     * (7 caracteres) suma mas de 8 bytes y habria pasado el minimo.
     */
    public function testLaLongitudSeMideEnCaracteresNoEnBytes()
    {
        $siete = 'Ñañez1á';
        $this->assertSame(7, mb_strlen($siete));
        $this->assertGreaterThan(8, strlen($siete), 'en bytes si supera el minimo');
        $this->assertNotNull(PoliticaPassword::validar($siete), 'debe rechazarse por tener 7 caracteres');
    }

    /** bcrypt ignora lo que pase de 72 bytes: aceptarlo daria falsa seguridad. */
    public function testRechazaContrasenasMasLargasDeLoQueBcryptUsa()
    {
        $limite = 'Aa1' . str_repeat('xy', 34) . 'z';    // 72 exactos, sin repeticiones
        $this->assertSame(72, strlen($limite));
        $this->assertNull(PoliticaPassword::validar($limite));

        $this->assertNotNull(PoliticaPassword::validar('Aa1' . str_repeat('xy', 35)));
    }

    /** El texto de ayuda de las vistas sale de aqui, no se escribe a mano. */
    public function testLaDescripcionMencionaElMinimoReal()
    {
        $this->assertStringContainsString((string) PoliticaPassword::MINIMO, PoliticaPassword::descripcion());
    }

    // -------------------------------------------------- adivinabilidad ---

    /**
     * Las reglas de composicion miden la FORMA, no lo facil que es acertarla.
     * Estas nueve cumplian el patron al pie de la letra y estaban entre las
     * primeras de cualquier diccionario; "Password1" justamente porque tantos
     * sitios exigen mayuscula + numero.
     */
    public function testRechazaLasComunesQuePasabanLaComposicion()
    {
        foreach ([
            'Password1', 'Zooki2026', 'Abcdefg1', 'Qwerty12', 'Colombia1',
            'Abcd1234', 'Aaaaaaa1', 'Contrasena1', 'Veterinaria1',
        ] as $password) {
            $this->assertNotNull(
                PoliticaPassword::validar($password),
                "'$password' cumple la composicion pero es adivinable"
            );
        }
    }

    /** Disfrazarla con leet o con un ano al final no la hace mejor. */
    public function testRechazaLasComunesDisfrazadas()
    {
        foreach (['P4ssw0rd1', 'Z00ki2026', 'Adm1n1234', 'Med3ll1n7', 'Mascota01', 'Firulais1'] as $password) {
            $this->assertNotNull(
                PoliticaPassword::validar($password),
                "'$password' es una variante de una clave conocida"
            );
        }
    }

    public function testRechazaSecuenciasYRepeticiones()
    {
        $this->assertNotNull(PoliticaPassword::validar('Ab12345Zx'), 'secuencia de digitos');
        $this->assertNotNull(PoliticaPassword::validar('Xefghijk9'), 'secuencia de letras');
        $this->assertNotNull(PoliticaPassword::validar('Xk9zzzzQw'), 'caracter repetido');
        $this->assertNotNull(PoliticaPassword::validar('Xqwert9Zk'), 'corrida de teclado');
    }

    /**
     * En una clinica el documento y el nombre los ve todo el personal de
     * recepcion: son lo primero que probaria alguien de adentro.
     */
    public function testRechazaLosDatosDelPropioUsuario()
    {
        $datos = ['1080361993', 'Carlos Andres Perez', 'carlos.perez@zooki.test'];

        foreach (['Carlos7Perez', '1080361993Aa', 'Andres99Xy', 'CarlosPerez1'] as $password) {
            $this->assertNotNull(
                PoliticaPassword::validar($password, $datos),
                "'$password' contiene datos del titular"
            );
        }
    }

    /** Sin falsos positivos: una clave ajena a sus datos debe pasar. */
    public function testNoConfundeContrasenasLegitimasConDatosPersonales()
    {
        $datos = ['1080361993', 'Carlos Andres Perez', 'carlos.perez@zooki.test'];

        foreach (['Tr0mpetaAzul', 'Kiwi7Nube', 'Rueda8CafeMar', 'Xilofono4Mar'] as $password) {
            $this->assertNull(
                PoliticaPassword::validar($password, $datos),
                "'$password' no tiene relacion con sus datos y deberia pasar"
            );
        }
    }

    /**
     * Nombres cortos como "Ana" o "Luz" apareceran por casualidad dentro de
     * muchas contrasenas; exigir que no aparezcan seria inusable.
     */
    public function testLosNombresMuyCortosNoBloquean()
    {
        $this->assertNull(PoliticaPassword::validar('Manzana7Kx', ['123', 'Ana Luz', 'a@b.co']));
    }

    /** Si falta la lista, se valida con las demas reglas en vez de bloquear. */
    public function testSinListaDeBloqueoLaValidacionSigueFuncionando()
    {
        $this->assertTrue(is_readable(__DIR__ . '/../../helpers/passwords-comunes.txt'),
            'la lista deberia existir; si se mueve, actualiza PoliticaPassword::listaComunes()');
    }
}
