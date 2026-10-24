<?php
require_once __DIR__ . '/../helpers/ReglaAtencion.php';

/**
 * RN-503: proyección cerrada reutilizable por el portal y por la futura B.
 * Es global (RN-110/113), sin contexto clínico. El llamador debe autorizar
 * al dueño o resolver un token antes de invocarla; nunca exponer ids por HTTP.
 */
final class CarnetMascota
{
    public function __construct(private PDO $db) {}

    /** RN-502 y RE-4.15.3: ni la inactiva ni la provisional tienen carnet. */
    public static function motivoNoDisponible(array $mascota): ?string
    {
        if (empty($mascota['id_propietario'])) {
            return 'El carnet estará disponible cuando se verifique el titular de la ficha provisional.';
        }
        if ((int) $mascota['estado'] !== 1) {
            return 'El carnet no está disponible porque la mascota está inactiva.';
        }
        if ((int) $mascota['carnet_activo'] !== 1) {
            return 'El carnet no está activo. La clínica debe completar o consolidar la ficha.';
        }
        return null;
    }

    public function datosEmergencia(int $idMascota): ?array
    {
        $consulta = $this->db->prepare('SELECT m.nombre, m.url_foto AS foto, e.nombre_especie AS especie,
            COALESCE(NULLIF(m.raza_indicada, \'\'), r.nombre_raza) AS raza, u.telefono AS telefono_propietario
            FROM mascotas m JOIN usuarios u ON u.id_usuario = m.id_propietario
            LEFT JOIN especies e ON e.id_especie = m.id_especie
            LEFT JOIN razas r ON r.id_raza = m.id_raza
            WHERE m.id_mascota = ? AND m.estado = 1 AND m.carnet_activo = 1');
        $consulta->execute([$idMascota]);
        $datos = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($datos === false) {
            return null;
        }
        // RN-113: todas las activas, aunque no haya autorización de consultas.
        $alertas = $this->db->prepare('SELECT tipo, descripcion FROM alertas_medicas WHERE id_mascota = ? AND activa = 1 ORDER BY tipo, id_alerta');
        $alertas->execute([$idMascota]);
        $datos['alertas'] = $alertas->fetchAll(PDO::FETCH_ASSOC);
        $clinicas = $this->db->prepare("SELECT c.nombre, COALESCE(NULLIF(c.telefono_urgencias, ''), c.telefono) AS telefono
            FROM mascota_clinica mc JOIN clinicas c ON c.id_clinica = mc.id_clinica
            WHERE mc.id_mascota = ? AND mc.estado = 'activo' AND c.estado <> 'baja' ORDER BY c.nombre");
        $clinicas->execute([$idMascota]);
        $datos['clinicas'] = $clinicas->fetchAll(PDO::FETCH_ASSOC);
        $datos['vacunas'] = $this->vacunasVigentes($idMascota);
        return $datos;
    }

    private function vacunasVigentes(int $idMascota): array
    {
        // Una dosis posterior reemplaza la anterior incluso si su vigencia no se conoce.
        $consulta = $this->db->prepare('SELECT v.nombre_vacuna, v.fecha_aplicacion, v.fecha_proxima_dosis, c.nombre AS clinica
            FROM vacunas v JOIN clinicas c ON c.id_clinica = v.id_clinica
            WHERE v.id_mascota = ? ORDER BY v.fecha_aplicacion DESC, v.id_vacuna DESC');
        $consulta->execute([$idMascota]);
        $hoy = (new DateTimeImmutable('now', new DateTimeZone(ReglaAtencion::ZONA)))->format('Y-m-d');
        $vistas = [];
        $vigentes = [];
        foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $vacuna) {
            $clave = mb_strtolower(trim($vacuna['nombre_vacuna']));
            if (isset($vistas[$clave]) || $vacuna['fecha_aplicacion'] > $hoy) {
                continue;
            }
            $vistas[$clave] = true;
            if (!empty($vacuna['fecha_proxima_dosis']) && $vacuna['fecha_proxima_dosis'] >= $hoy) {
                $vigentes[] = $vacuna;
            }
        }
        return $vigentes;
    }
}
