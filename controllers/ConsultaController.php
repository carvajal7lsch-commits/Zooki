<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Consulta.php';
require_once __DIR__ . '/../models/Mascota.php';
require_once __DIR__ . '/../models/Vacuna.php';
require_once __DIR__ . '/../models/Desparasitacion.php';
require_once __DIR__ . '/../helpers/ValidadorClinico.php';
require_once __DIR__ . '/../helpers/RespuestaJson.php';

/**
 * C4: el controlador lee la petición y responde; los modelos aplican la
 * clínica activa y la visibilidad de la historia (RN-112, RN-113).
 */
class ConsultaController
{
    private const CARPETA_ADJUNTOS = __DIR__ . '/../public/uploads/clinicos/';
    private const TAMANO_MAXIMO_ADJUNTO = 10 * 1024 * 1024;

    private PDO $db;
    private Consulta $consultaModel;
    private Mascota $mascotaModel;
    private Vacuna $vacunaModel;
    private Desparasitacion $desparasitacionModel;

    /** @var callable(string, string): bool mueve un archivo subido; las pruebas lo reemplazan */
    private $moverArchivo;
    private string $carpetaAdjuntos;

    /** $moverArchivo y $carpetaAdjuntos existen para las pruebas, que no escriben en public/uploads. */
    public function __construct(?PDO $db = null, ?callable $moverArchivo = null, ?string $carpetaAdjuntos = null)
    {
        $this->db = $db ?? (new Database())->getConnection();
        $this->consultaModel = new Consulta($this->db);
        $this->mascotaModel = new Mascota($this->db);
        $this->vacunaModel = new Vacuna($this->db);
        $this->desparasitacionModel = new Desparasitacion($this->db);
        $this->moverArchivo = $moverArchivo ?? 'move_uploaded_file';
        $this->carpetaAdjuntos = rtrim($carpetaAdjuntos ?? self::CARPETA_ADJUNTOS, '/\\') . '/';
    }

    /** HU-2.2: listado de consultas de la clínica activa. */
    public function listar(): array
    {
        return $this->consultaModel->listarDeLaClinica();
    }

    /** HU-2.1, HU-2.2 y HU-2.6: registra la consulta con adjuntos y tratamientos, todo o nada. */
    public function registrarAjax(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            RespuestaJson::error(405, 'Método no permitido.');
            return;
        }

        // RE-2.6.2: los adjuntos se revisan antes de escribir nada. Si alguno
        // no sirve no se guarda la consulta y se dice cuál y por qué.
        [$adjuntos, $rechazados] = $this->revisarAdjuntos();
        if ($rechazados !== []) {
            $mensaje = 'No se guardó la consulta porque hay adjuntos que no se pueden aceptar. Corrígelos y vuelve a enviarla.';
            RespuestaJson::error(422, $mensaje, ['adjuntos_rechazados' => $rechazados]);
            return;
        }

        $tratamientos = $this->tratamientosDelFormulario();
        $movidos = [];
        $guardarAdjunto = function (int $idConsulta, array $adjunto, int $indice) use (&$movidos): array {
            return $this->guardarAdjunto($idConsulta, $adjunto, $indice, $movidos);
        };

        try {
            $idConsulta = $this->consultaModel->registrar($_POST, $tratamientos, $adjuntos, $guardarAdjunto);
        } catch (AccesoDenegado $e) {
            $this->borrarArchivos($movidos);
            throw $e;
        } catch (InvalidArgumentException $e) {
            $this->borrarArchivos($movidos);
            RespuestaJson::error(422, $e->getMessage());
            return;
        } catch (Throwable $e) {
            // RE-2.6.3: nunca se reporta éxito con datos parciales.
            $this->borrarArchivos($movidos);
            error_log('C4: error al registrar la consulta: ' . $e->getMessage());
            RespuestaJson::error(500, 'No se pudo guardar la consulta. No se registró nada; revisa los datos e inténtalo de nuevo.');
            return;
        }

        RespuestaJson::enviar([
            'success' => true,
            'id_consulta' => $idConsulta,
            'adjuntos_guardados' => count($adjuntos),
            'tratamientos_guardados' => count($tratamientos),
            'message' => $this->resumenDelRegistro(count($adjuntos), count($tratamientos)),
        ]);
    }

    /**
     * HU-2.5 y HU-2.10: historial de la mascota según lo que la clínica
     * activa puede ver. Una mascota no vinculada da 403 auditado.
     */
    public function listarHistorialAjax(): void
    {
        $idMascota = ValidadorClinico::id($_GET['id_mascota'] ?? null);
        if ($idMascota === null) {
            RespuestaJson::error(422, 'Mascota no válida.');
            return;
        }

        try {
            $respuesta = [
                'success' => true,
                'mascota' => $this->mascotaModel->getById($idMascota),
                'consultas' => $this->consultaModel->historialDeMascota($idMascota),
                'vacunas' => $this->vacunaModel->findByMascota($idMascota),
                'desparasitaciones' => $this->desparasitacionModel->findByMascota($idMascota),
            ];
        } catch (AccesoDenegado $e) {
            throw $e;
        } catch (Throwable $e) {
            error_log('C4: error al cargar el historial clínico: ' . $e->getMessage());
            RespuestaJson::error(500, 'No se pudo cargar el historial.');
            return;
        }

        RespuestaJson::enviar($respuesta);
    }

    /**
     * RE-2.3.1: revisa los adjuntos antes de tocar la base. Devuelve
     * [aceptados, rechazados]; los rechazados llevan el archivo y el motivo.
     * El tipo sale del contenido, no del nombre ni del Content-Type.
     */
    private function revisarAdjuntos(): array
    {
        $aceptados = [];
        $rechazados = [];

        $archivos = $_FILES['archivos'] ?? null;
        if (!is_array($archivos) || !is_array($archivos['name'] ?? null)) {
            return [$aceptados, $rechazados];
        }

        $motivosDeSubida = [
            UPLOAD_ERR_INI_SIZE => 'supera el tamaño máximo que admite el servidor',
            UPLOAD_ERR_FORM_SIZE => 'supera el tamaño máximo permitido',
            UPLOAD_ERR_PARTIAL => 'se subió incompleto',
            UPLOAD_ERR_NO_TMP_DIR => 'no se pudo guardar: falta la carpeta temporal del servidor',
            UPLOAD_ERR_CANT_WRITE => 'no se pudo escribir en el disco del servidor',
            UPLOAD_ERR_EXTENSION => 'fue bloqueado por una extensión del servidor',
        ];

        $total = count($archivos['name']);
        for ($i = 0; $i < $total; $i++) {
            $nombre = (string) $archivos['name'][$i];
            $error = (int) $archivos['error'][$i];

            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                $rechazados[] = ['archivo' => $nombre, 'motivo' => $motivosDeSubida[$error] ?? 'no se pudo subir'];
                continue;
            }

            $temporal = $archivos['tmp_name'][$i];
            $tamano = (int) $archivos['size'][$i];
            if ($tamano <= 0) {
                $rechazados[] = ['archivo' => $nombre, 'motivo' => 'está vacío'];
                continue;
            }
            if ($tamano > self::TAMANO_MAXIMO_ADJUNTO) {
                $rechazados[] = ['archivo' => $nombre, 'motivo' => 'supera los 10 MB permitidos'];
                continue;
            }

            $extension = $this->extensionPorContenido($temporal);
            if ($extension === null) {
                $rechazados[] = ['archivo' => $nombre, 'motivo' => 'no es un JPG, PNG ni PDF válido'];
                continue;
            }

            $aceptados[] = [
                'nombre_original' => mb_substr($nombre, 0, 255),
                'temporal' => $temporal,
                'tamano' => $tamano,
                'extension' => $extension,
            ];
        }

        return [$aceptados, $rechazados];
    }

    private function extensionPorContenido(string $temporal): ?string
    {
        $imagenes = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
        $imagen = @getimagesize($temporal);
        if ($imagen !== false && isset($imagenes[$imagen[2]])) {
            return $imagenes[$imagen[2]];
        }
        if (@file_get_contents($temporal, false, null, 0, 5) === '%PDF-') {
            return 'pdf';
        }
        return null;
    }

    /**
     * Mueve un adjunto aceptado a la carpeta protegida y devuelve sus
     * metadatos. El nombre lleva una parte aleatoria para no ser adivinable;
     * la descarga va siempre por ver_archivo.php (RE-2.3.2).
     */
    private function guardarAdjunto(int $idConsulta, array $adjunto, int $indice, array &$movidos): array
    {
        $carpeta = $this->carpetaAdjuntos;
        if (!is_dir($carpeta) && !mkdir($carpeta, 0755, true) && !is_dir($carpeta)) {
            throw new RuntimeException('No se pudo preparar la carpeta de adjuntos.');
        }

        $nombreServidor = sprintf('CLI_%d_%s_%d.%s', $idConsulta, bin2hex(random_bytes(8)), $indice, $adjunto['extension']);
        $destino = $carpeta . $nombreServidor;
        if (!($this->moverArchivo)($adjunto['temporal'], $destino)) {
            throw new RuntimeException('No se pudo guardar el adjunto ' . $adjunto['nombre_original']);
        }
        $movidos[] = $destino;

        $tipos = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg'];
        return [
            'nombre_original' => $adjunto['nombre_original'],
            'nombre_servidor' => $nombreServidor,
            'ruta_archivo' => 'uploads/clinicos/' . $nombreServidor,
            'tipo_archivo' => $tipos[$adjunto['extension']],
            'extension' => $adjunto['extension'],
            'tamano_bytes' => $adjunto['tamano'],
            'descripcion' => 'Adjunto de consulta',
        ];
    }

    /** El disco no participa del rollback: lo movido se borra a mano. */
    private function borrarArchivos(array $rutas): void
    {
        foreach ($rutas as $ruta) {
            if (is_file($ruta)) {
                @unlink($ruta);
            }
        }
    }

    /**
     * HU-2.4: filas de medicamentos del formulario. Una fila sin medicamento
     * se ignora; las demás las valida Tratamiento::validar (con fecha_inicio).
     */
    private function tratamientosDelFormulario(): array
    {
        $medicamentos = $_POST['med_nombre'] ?? null;
        if (!is_array($medicamentos)) {
            return [];
        }

        $tratamientos = [];
        foreach ($medicamentos as $i => $medicamento) {
            if (trim((string) $medicamento) === '') {
                continue;
            }
            $tratamientos[] = [
                'medicamento' => (string) $medicamento,
                'dosis' => $_POST['med_dosis'][$i] ?? null,
                'via_administracion' => $_POST['med_via'][$i] ?? null,
                'duracion' => $_POST['med_duracion'][$i] ?? null,
                'fecha_inicio' => $_POST['med_inicio'][$i] ?? null,
                'observaciones' => $_POST['med_obs'][$i] ?? null,
            ];
        }
        return $tratamientos;
    }

    private function resumenDelRegistro(int $adjuntos, int $tratamientos): string
    {
        $resumen = 'Consulta registrada correctamente';
        if ($adjuntos > 0) {
            $resumen .= sprintf(' con %d adjunto%s', $adjuntos, $adjuntos === 1 ? '' : 's');
        }
        if ($tratamientos > 0) {
            $resumen .= sprintf(' y %d tratamiento%s', $tratamientos, $tratamientos === 1 ? '' : 's');
        }
        return $resumen . '.';
    }
}
