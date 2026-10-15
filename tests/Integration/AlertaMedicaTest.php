<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/AlertaMedica.php';
require_once __DIR__ . '/../../models/NodosEnlazables.php';
require_once __DIR__ . '/../../models/HistoriaPropietario.php';
require_once __DIR__ . '/../../controllers/AlertaMedicaController.php';
require_once __DIR__ . '/../../models/Usuario.php';
require_once __DIR__ . '/../../helpers/Security.php';

/**
 * M2 C1 — HU-2.1, RE-2.1.5 y RE-2.10.1: alergias y alertas médicas.
 *
 * Luna es de Fabio y está vinculada a Norte (A) y a Sur (B).
 * - Un veterinario de A la registra; B la ve con el nombre de A y no puede desactivarla (RN-112, RN-113).
 * - Una clínica sin vínculo recibe 403 auditado; con el vínculo inactivo solo lee lo suyo (RN-115).
 * - Desactivar no borra: la fila sigue y quién y cuándo queda en la auditoría.
 * - El propietario ve las activas solo de sus mascotas (RN-G02).
 */
final class AlertaMedicaTest extends TestCase
{
    private const LUNA = 1;

    private PDO $db;

    protected function setUp(): void
    {
        // Csrf::validate inicia la sesión si no hay una y reemplazaría $_SESSION.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::vincularLunaASur($this->db);
        $_POST = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        http_response_code(200);
    }

    public function testRegistrarYDesactivarEnLaPropiaClinicaSinBorrarYConAuditoria(): void
    {
        $this->comoVeterinario(DosClinicas::NORTE, DosClinicas::VET_NORTE);
        $modelo = new AlertaMedica($this->db);
        $id = $modelo->registrar(['id_mascota' => self::LUNA, 'tipo' => 'alergia', 'descripcion' => '  Reacción   cutánea a la amoxicilina ', 'nodo' => 'farmaco.paracetamol']);
        $alertas = $modelo->deMascota(self::LUNA);
        $this->assertCount(1, $alertas['activas']);
        $activa = $alertas['activas'][0];
        $this->assertSame('Reacción cutánea a la amoxicilina', $activa['descripcion']);
        $this->assertSame('Clínica Norte', $activa['clinica_nombre']);
        $this->assertSame('Beto Norte', $activa['veterinario']);
        $this->assertSame('farmaco.paracetamol', $activa['nodo_codigo']);
        $this->assertSame('Alergia', $activa['tipo_nombre']);
        $this->assertTrue($activa['es_propia']);
        $this->assertTrue($activa['puede_desactivar']);
        $this->assertSame(1, $this->auditorias('INSERT', $id));

        $this->assertSame(self::LUNA, $modelo->desactivar((string) $id));
        $despues = $modelo->deMascota(self::LUNA);
        $this->assertSame([], $despues['activas']);
        $this->assertCount(1, $despues['historial']);
        $this->assertSame('Beto Norte', $despues['historial'][0]['desactivada_por']);
        $this->assertNotNull($despues['historial'][0]['desactivada_en']);
        $this->assertSame(0, (int) $this->db->query('SELECT activa FROM alertas_medicas WHERE id_alerta = ' . $id)->fetchColumn(), 'Sigue en la base');
        $this->assertSame(1, $this->auditorias('UPDATE', $id));
        $this->expectException(InvalidArgumentException::class);
        $modelo->desactivar($id);
    }

    public function testOtraClinicaVinculadaLaVeConLaClinicaDeOrigenYNoLaDesactiva(): void
    {
        $id = $this->registrarEnNorte();
        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR);
        $modelo = new AlertaMedica($this->db);
        $activas = $modelo->deMascota(self::LUNA)['activas'];
        $this->assertSame('Clínica Norte', $activas[0]['clinica_nombre']);
        $this->assertFalse($activas[0]['es_propia']);
        $this->assertFalse($activas[0]['puede_desactivar']);
        $this->assertDenegado(fn () => $modelo->desactivar($id));
        $this->assertSame(1, (int) $this->db->query('SELECT activa FROM alertas_medicas WHERE id_alerta = ' . $id)->fetchColumn());
        $this->assertSame(1, $this->auditoriasDenegadas(DosClinicas::SUR, 'alertas_medicas'));
        // Sur también registra la suya; cada clínica desactiva solo lo propio.
        $propia = $modelo->registrar(['id_mascota' => self::LUNA, 'tipo' => 'condicion_cronica', 'descripcion' => 'Epilepsia en control', 'nodo' => 'condicion.epilepsia']);
        $activas = array_column($modelo->deMascota(self::LUNA)['activas'], null, 'id_alerta');
        $this->assertTrue($activas[$propia]['puede_desactivar']);
        $this->assertSame('Clínica Sur', $activas[$propia]['clinica_nombre']);
    }

    public function testClinicaSinVinculoNoLaVeNiRegistraY403Auditado(): void
    {
        $id = $this->registrarEnNorte();
        $this->comoVeterinario(DosClinicas::SUSPENDIDA, DosClinicas::ADMIN_SUSPENDIDA);
        $modelo = new AlertaMedica($this->db);
        $this->assertDenegado(fn () => $modelo->deMascota(self::LUNA));
        $this->assertDenegado(fn () => $modelo->registrar(['id_mascota' => self::LUNA, 'tipo' => 'otra', 'descripcion' => 'Intento']));
        $this->assertDenegado(fn () => $modelo->desactivar($id));
        $this->assertSame(3, $this->auditoriasDenegadas(DosClinicas::SUSPENDIDA));
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM alertas_medicas')->fetchColumn());
    }

    public function testConElVinculoInactivoSoloLeeLoPropioYNadaSeEscribe(): void
    {
        $this->registrarEnNorte();
        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR);
        $modelo = new AlertaMedica($this->db);
        $propia = $modelo->registrar(['id_mascota' => self::LUNA, 'tipo' => 'otra', 'descripcion' => 'Muerde al examinarlo']);
        $this->db->exec("UPDATE mascota_clinica SET estado = 'inactivo' WHERE id_mascota = 1 AND id_clinica = 2");
        $alertas = $modelo->deMascota(self::LUNA);
        $this->assertTrue($alertas['solo_lectura']);
        $this->assertSame([$propia], array_map('intval', array_column($alertas['activas'], 'id_alerta')));
        $this->assertFalse($alertas['activas'][0]['puede_desactivar']);
        $this->assertDenegado(fn () => $modelo->desactivar($propia));
        $this->assertDenegado(fn () => $modelo->registrar(['id_mascota' => self::LUNA, 'tipo' => 'otra', 'descripcion' => 'Otra']));
    }

    public function testTipoYNodoIncompatiblesNodoInactivoYDescripcionInvalidaSeRechazan(): void
    {
        $this->comoVeterinario(DosClinicas::NORTE, DosClinicas::VET_NORTE);
        $modelo = new AlertaMedica($this->db);
        $base = ['id_mascota' => self::LUNA, 'tipo' => 'alergia', 'descripcion' => 'Alergia al fármaco'];
        $invalidas = [
            'condición enlazada a un fármaco' => ['tipo' => 'condicion_cronica', 'nodo' => 'farmaco.paracetamol'],
            'medicación enlazada a una sustancia' => ['tipo' => 'medicacion_continua', 'nodo' => 'sustancia.lilium'],
            'alergia enlazada a un síntoma' => ['nodo' => 'sintoma.vomito'],
            '«otra» con nodo' => ['tipo' => 'otra', 'nodo' => 'farmaco.paracetamol'],
            'nodo inactivo' => ['nodo' => 'farmaco.retirado'],
            'nodo inexistente' => ['nodo' => 'farmaco.no_existe'],
            'nodo por id numérico' => ['nodo' => 1],
            'descripción vacía' => ['descripcion' => '   '],
            'descripción larga' => ['descripcion' => str_repeat('a', ValidadorAlertaMedica::DESCRIPCION_MAX + 1)],
            'descripción con caracteres de control' => ['descripcion' => "Alergia\x07"],
            'tipo desconocido' => ['tipo' => 'grave'],
        ];
        foreach ($invalidas as $caso => $cambio) {
            try {
                $modelo->registrar($cambio + $base);
                $this->fail('Se aceptó: ' . $caso);
            } catch (InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage(), $caso);
            }
        }
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM alertas_medicas')->fetchColumn());
        $limite = $modelo->registrar(['descripcion' => str_repeat('a', ValidadorAlertaMedica::DESCRIPCION_MAX)] + $base);
        $sinNodo = $modelo->registrar(['tipo' => 'alergia', 'descripcion' => 'Alergia a un champú'] + $base);
        $sustancia = $modelo->registrar(['nodo' => 'sustancia.lilium'] + $base);
        $medicacion = $modelo->registrar(['tipo' => 'medicacion_continua', 'nodo' => 'farmaco.meloxicam', 'descripcion' => 'Meloxicam diario'] + $base);
        $this->assertCount(4, array_filter([$limite, $sinNodo, $sustancia, $medicacion]));
        $this->assertNull($this->db->query('SELECT id_nodo FROM alertas_medicas WHERE id_alerta = ' . $sinNodo)->fetchColumn() ?: null);
    }

    public function testBuscadorDeNodosPorEtiquetaYSinonimoFiltradoPorTipo(): void
    {
        $nodos = new NodosEnlazables($this->db);
        $this->assertSame(['farmaco.paracetamol'], array_column($nodos->buscar('alergia', 'ACETAMINOFEN'), 'codigo'));
        $this->assertSame('acetaminofén', $nodos->buscar('alergia', 'acetaminofen')[0]['coincidencia']);
        $this->assertSame(['sustancia.lilium'], array_column($nodos->buscar('alergia', 'lirios'), 'codigo'));
        $this->assertSame([], $nodos->buscar('medicacion_continua', 'lirio'), 'Una medicación no se enlaza a una sustancia');
        $this->assertSame(['condicion.epilepsia'], array_column($nodos->buscar('condicion_cronica', 'epilep'), 'codigo'));
        $this->assertSame([], $nodos->buscar('otra', 'paracetamol'));
        $this->assertSame([], $nodos->buscar('medicacion_continua', 'retirado'), 'Solo nodos activos');
        $this->assertSame([], $nodos->buscar('alergia', 'dolex'), 'Solo sinónimos activos');
        $this->assertSame([], $nodos->buscar('alergia', 'p'), 'Una letra no busca');
        $this->assertSame([], $nodos->buscar('alergia', 'vomito'), 'Un síntoma no es enlazable');
    }

    public function testElPropietarioVeLasActivasDeSusMascotasConSuClinicaYNoLasDeOtro(): void
    {
        $id = $this->registrarEnNorte();
        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR);
        $sur = (new AlertaMedica($this->db))->registrar(['id_mascota' => self::LUNA, 'tipo' => 'otra', 'descripcion' => 'Se estresa en consulta']);
        (new AlertaMedica($this->db))->desactivar($sur);
        $this->comoPropietario(DosClinicas::PROPIETARIO);
        $alertas = (new HistoriaPropietario($this->db))->alertas(self::LUNA);
        $this->assertSame([$id], array_map('intval', array_column($alertas, 'id_alerta')), 'Solo activas');
        $this->assertSame('Clínica Norte', $alertas[0]['clinica_nombre']);
        $this->assertSame('Alergia', $alertas[0]['tipo_nombre']);
        $this->assertArrayNotHasKey('veterinario', $alertas[0]);
        $this->comoPropietario(DosClinicas::DOBLE);
        $this->assertDenegado(fn () => (new HistoriaPropietario($this->db))->alertas(self::LUNA));
    }

    public function testControladorDevuelveLaListaActualizadaYRechazaSinPost(): void
    {
        $this->comoVeterinario(DosClinicas::NORTE, DosClinicas::VET_NORTE);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['id_mascota' => (string) self::LUNA, 'tipo' => 'alergia', 'descripcion' => 'Penicilina', 'nodo' => ''];
        $registro = $this->llamar('registrarAjax');
        $this->assertTrue($registro['success']);
        $this->assertSame('Penicilina', $registro['alertas']['activas'][0]['descripcion']);
        $_POST = ['id_mascota' => (string) self::LUNA, 'tipo' => 'alergia', 'descripcion' => ''];
        $error = $this->llamar('registrarAjax');
        $this->assertSame(422, http_response_code());
        $this->assertFalse($error['success']);
        $this->assertSame('Describe la alergia o la alerta.', $error['message']);
        http_response_code(200);
        $_POST = ['id_alerta' => (string) $registro['alertas']['activas'][0]['id_alerta']];
        $desactivada = $this->llamar('desactivarAjax');
        $this->assertSame([], $desactivada['alertas']['activas']);
        $this->assertCount(1, $desactivada['alertas']['historial']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->assertSame(405, $this->llamarCodigo('desactivarAjax'));
        $_GET = ['tipo' => 'alergia', 'q' => 'paracet'];
        $this->assertSame('farmaco.paracetamol', $this->llamar('buscarNodosAjax')['nodos'][0]['codigo']);
        $_GET = ['id_mascota' => (string) self::LUNA];
        $this->assertCount(1, $this->llamar('listarAjax')['alertas']['historial']);
    }

    public function testEscribirExigeCsrfYLaPlataformaNoEntra(): void
    {
        $usuarios = new Usuario($this->db);
        Security::definirFuenteDeContextos(fn (int $id): ?array => $usuarios->contextosDe($id));
        Security::definirFuenteDeVersionSesion(null);
        try {
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_SESSION = ['id_usuario' => DosClinicas::VET_NORTE];
            Contexto::activar(Contexto::deClinica(DosClinicas::NORTE, 'Clínica Norte', Roles::VETERINARIO), 1);
            foreach (['registrar_alerta_medica_ajax', 'desactivar_alerta_medica_ajax'] as $accion) {
                try {
                    Security::autorizar($accion);
                    $this->fail('Sin CSRF pasó ' . $accion);
                } catch (AccesoDenegado $e) {
                    $this->assertSame(403, $e->codigo());
                }
                $_SESSION['csrf_default'] = 'token';
                $_POST['csrf_token'] = 'token';
                Security::autorizar($accion);
                $_POST = [];
            }
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SESSION = ['id_usuario' => DosClinicas::SUPER_ADMIN];
            Contexto::activar(Contexto::dePlataforma(), 1);
            foreach (['listar_alertas_medicas_ajax', 'registrar_alerta_medica_ajax', 'buscar_nodo_alerta_ajax'] as $accion) {
                try {
                    Security::autorizar($accion);
                    $this->fail('La plataforma entró a ' . $accion);
                } catch (AccesoDenegado $e) {
                    $this->assertSame(403, $e->codigo());
                }
            }
        } finally {
            Security::definirFuenteDeContextos(null);
        }
    }

    public function testElModalUsaLosLimitesYTiposDelServidorSinCodigoEnLinea(): void
    {
        ob_start();
        require __DIR__ . '/../../views/partials/modal_alerta_medica.php';
        $html = (string) ob_get_clean();
        $this->assertStringContainsString('maxlength="' . ValidadorAlertaMedica::DESCRIPCION_MAX . '"', $html);
        $this->assertStringContainsString('data-caracteres="' . ValidadorAlertaMedica::CARACTERES . '"', $html);
        $this->assertStringContainsString(htmlspecialchars(json_encode(ValidadorAlertaMedica::NODOS_POR_TIPO), ENT_QUOTES, 'UTF-8'), $html);
        foreach (array_keys(ValidadorAlertaMedica::TIPOS) as $tipo) {
            $this->assertStringContainsString('value="' . $tipo . '"', $html);
        }
        $this->assertDoesNotMatchRegularExpression('/\sstyle=|\son[a-z]+=|<script/i', $html);
    }

    private function registrarEnNorte(): int
    {
        $this->comoVeterinario(DosClinicas::NORTE, DosClinicas::VET_NORTE);
        return (new AlertaMedica($this->db))->registrar(['id_mascota' => self::LUNA, 'tipo' => 'alergia', 'descripcion' => 'Alergia al paracetamol', 'nodo' => 'farmaco.paracetamol']);
    }

    private function llamar(string $metodo): array
    {
        ob_start();
        try {
            (new AlertaMedicaController($this->db))->$metodo();
        } finally {
            $salida = ob_get_clean();
        }
        return json_decode($salida, true);
    }

    private function llamarCodigo(string $metodo): int
    {
        $this->llamar($metodo);
        $codigo = http_response_code();
        http_response_code(200);
        return $codigo;
    }

    private function auditorias(string $accion, int $id): int
    {
        $consulta = $this->db->prepare("SELECT COUNT(*) FROM auditoria_sistema WHERE tabla_afectada = 'alertas_medicas' AND accion = ? AND registro_id = ?");
        $consulta->execute([$accion, (string) $id]);
        return (int) $consulta->fetchColumn();
    }

    private function auditoriasDenegadas(int $clinica, ?string $tabla = null): int
    {
        $sql = "SELECT COUNT(*) FROM auditoria_sistema WHERE accion = 'OTHER' AND id_clinica = ?";
        $parametros = [$clinica];
        if ($tabla !== null) {
            $sql .= ' AND tabla_afectada = ?';
            $parametros[] = $tabla;
        }
        $consulta = $this->db->prepare($sql);
        $consulta->execute($parametros);
        return (int) $consulta->fetchColumn();
    }

    private function assertDenegado(callable $accion): void
    {
        try {
            $accion();
            $this->fail('Se esperaba 403.');
        } catch (AccesoDenegado $e) {
            $this->assertSame(403, $e->codigo());
        }
    }

    private function comoVeterinario(int $clinica, int $idUsuario): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::deClinica($clinica, 'Clínica', Roles::VETERINARIO), 1);
    }

    private function comoPropietario(int $idUsuario): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::dePropietario(), 1);
    }
}
