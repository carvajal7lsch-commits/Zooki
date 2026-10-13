<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../controllers/PortalController.php';

/**
 * M5 C2b — Agendar con la clínica única (RE-5.9.3, hueco 8; un solo formulario desde C3),
 * Clínicas en tarjetas, Perfil plegable y los formularios de mascota con las
 * reglas y los avisos de ValidadorMascota (validación en tiempo real y
 * restricción de entrada; el servidor dice el campo que rechaza).
 */
final class M5C2bPortalTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
        // Csrf::token abre la sesión de PHP si no lo está y reemplazaría $_SESSION (como en M01AccesoTest).
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = ['id_usuario' => DosClinicas::PROPIETARIO, 'usuario_nombre' => 'Fabio Dueño'];
        Contexto::activar(Contexto::dePropietario(), 1);
        $_GET = [];
        $_POST = [];
        $_FILES = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_FILES = [];
        http_response_code(200);
    }

    private function portal(): string
    {
        ob_start();
        try {
            (new PortalController($this->db))->index();
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }

    private function trozo(string $html, string $desde, string $hasta): string
    {
        $inicio = strpos($html, $desde);
        $this->assertNotFalse($inicio, "No está «{$desde}».");
        return substr($html, $inicio, strpos($html, $hasta, $inicio) - $inicio);
    }

    public function testConUnaSolaClinicaAgendarMuestraSuNombreYElIdOculto(): void
    {
        $paso = $this->trozo($this->portal(), 'id="bookingEleccionClinica"', 'id="bookingEleccionMascota"');

        $this->assertStringContainsString('Clínica Norte', $paso);
        $this->assertMatchesRegularExpression('/<input type="hidden" name="id_clinica" value="1" data-nombre="Clínica Norte">/', $paso);
        $this->assertStringNotContainsString('type="radio"', $paso, 'RE-5.9.3: con una sola no hay nada que elegir.');
    }

    public function testConVariasClinicasSonTarjetasDeEleccionSoloDeLasVinculadas(): void
    {
        $this->db->exec("INSERT INTO propietario_clinica (id_propietario, id_clinica, estado) VALUES (6, 2, 'activo')");

        $paso = $this->trozo($this->portal(), 'id="bookingEleccionClinica"', 'id="bookingEleccionMascota"');

        preg_match_all('/<input type="radio" name="id_clinica" value="(\d+)"/', $paso, $opciones);
        $this->assertSame(['1', '2'], $opciones[1]);
        $this->assertStringNotContainsString('type="hidden" name="id_clinica"', $paso);
        $this->assertStringNotContainsString('Clínica Pausada', $paso, 'Nunca las demás clínicas de la plataforma.');
    }

    /** M5 C3 (decisión del usuario): Agendar vuelve a un solo formulario, todo a la vista, con el pie del modal. */
    public function testAgendarEsUnSoloFormularioConElPieDelModal(): void
    {
        $modal = $this->trozo($this->portal(), 'id="portalBookingModal"', '<!-- Ventana: registrar mascota');

        $this->assertStringNotContainsString('data-paso', $modal, 'Sin pasos.');
        foreach (['bookingEleccionClinica', 'bookingEleccionMascota', 'booking_tipo_cita', 'booking_veterinario', 'booking_fecha', 'booking_slots'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $modal, "{$id} a la vista.");
        }
        $this->assertStringContainsString('users-modal__footer portal-modal__pie', $modal);
        $this->assertMatchesRegularExpression('/<button type="submit" class="btn-primary" id="booking_confirmar" disabled>\s*<span>Agendar<\/span>/', $modal);
    }

    public function testLosCamposDeMascotaLlevanLasReglasYElAvisoDelServidor(): void
    {
        $formulario = $this->trozo($this->portal(), 'id="portalAddPetForm"', '</form>');

        $this->assertStringContainsString('data-validacion-mascota', $formulario);
        $this->assertStringContainsString('novalidate', $formulario);
        $this->assertStringContainsString('data-aviso-obligatorio="' . ValidadorMascota::AVISO_OBLIGATORIO . '"', $formulario);
        $nombre = $this->trozo($formulario, 'name="nombre"', '>');
        $this->assertStringContainsString('maxlength="' . ValidadorMascota::NOMBRE_MAX . '"', $nombre);
        $this->assertStringContainsString('data-caracteres="', $nombre, 'Restricción de entrada (C9.1).');
        $peso = $this->trozo($formulario, 'name="peso"', '>');
        $this->assertStringContainsString('inputmode="decimal"', $peso);
        $this->assertStringContainsString('data-maximo="' . ValidadorMascota::PESO_MAX_KG . '"', $peso);
        foreach (['add_pet_nombre', 'add_pet_especie', 'add_pet_raza', 'add_pet_raza_indicada', 'add_pet_sexo', 'add_pet_peso', 'add_pet_nacimiento'] as $id) {
            $this->assertStringContainsString('id="' . $id . '_aviso"', $formulario, "Línea de aviso reservada para {$id}.");
        }
        $this->assertStringContainsString('data-resumen-mascota hidden', $formulario);
    }

    public function testElServidorDiceElCampoYSuAvisoCortoAlRechazarUnaMascota(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['nombre' => 'Toby', 'especie' => '1', 'raza' => '49', 'sexo' => 'Macho', 'peso' => '999'];

        ob_start();
        (new PortalController($this->db))->registrarMascotaAjax();
        $respuesta = json_decode((string) ob_get_clean(), true);

        $this->assertSame(422, http_response_code());
        $this->assertFalse($respuesta['success']);
        $this->assertSame('peso', $respuesta['campo']);
        $this->assertSame(ValidadorMascota::AVISOS['peso'], $respuesta['aviso']);
        $this->assertStringContainsString('150 kg', $respuesta['message'], 'El detalle completo va al resumen.');
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM mascotas')->fetchColumn());
    }

    public function testClinicasEnTarjetasConInterruptorAvisoYBuscador(): void
    {
        $clinicas = $this->trozo($this->portal(), 'id="screen-clinicas"', '<!-- ══ PERFIL');

        $tarjeta = $this->trozo($clinicas, 'class="person-card portal-clinica" data-id-clinica="1"', '</article>');
        $this->assertStringContainsString('class="toggle-switch"', $tarjeta);
        $this->assertStringContainsString('data-autorizar-clinica="1"', $tarjeta);
        $this->assertStringContainsString('data-desvincular-clinica="1"', $tarjeta);
        $this->assertStringContainsString('data-aviso-clinica', $tarjeta, 'RE-5.13.3: el rechazo sale en la tarjeta.');
        $this->assertStringContainsString('data-buscar-clinica', $clinicas);
        $this->assertStringContainsString('data-vincular-clinica="2"', $clinicas, 'Sur aparece en «Otras clínicas».');
        $this->assertStringNotContainsString('data-vincular-clinica="3"', $clinicas, 'Una clínica suspendida no se ofrece.');
    }

    public function testPerfilEnTarjetasPlegables(): void
    {
        $perfil = $this->trozo($this->portal(), 'id="screen-account"', '<!-- ══ MASCOTA');

        $this->assertSame(4, substr_count($perfil, '<details class="'), 'Mis datos, contacto, datos de acceso y contraseña.');
        $this->assertStringContainsString('<details class="profile-card portal-plegable" id="contactEditSection">', $perfil);
        $this->assertStringContainsString('<details class="profile-card portal-plegable" id="passwordChangeSection">', $perfil);
        $this->assertStringContainsString('cuenta-identidad profile-card portal-plegable', $perfil);
        $this->assertStringContainsString('portal-plegable--enlace', $perfil, 'Salir.');
        $this->assertStringNotContainsString('data-ui-accion="editar-contacto"', $perfil);
    }
}
