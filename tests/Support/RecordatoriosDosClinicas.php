<?php
require_once __DIR__ . '/DosClinicas.php';

/** C8: datos portables para SQLite y el esquema real de MySQL. */
final class RecordatoriosDosClinicas
{
    public static function poblar(PDO $db): void
    {
        DosClinicas::vincularLunaASur($db);
        $db->exec("INSERT INTO vacunas (id_vacuna,id_clinica,id_mascota,nombre_vacuna,fecha_aplicacion,fecha_proxima_dosis) VALUES
            (1,1,1,'Rabia','2025-09-25','2026-09-25'),
            (2,1,1,'Parvovirus','2025-09-23','2026-09-23'),
            (3,2,1,'Moquillo','2025-09-29','2026-09-29'),
            (4,2,1,'Fuera de ventana','2025-10-10','2026-10-10')");
        $db->exec("INSERT INTO desparasitaciones (id_desparasitacion,id_clinica,id_mascota,tipo,producto,periodicidad,fecha_aplicacion,fecha_proxima) VALUES
            (1,1,1,'interna','Drontal','trimestral','2026-06-22','2026-09-22'),
            (2,2,1,'externa','Bravecto','trimestral','2026-06-25','2026-09-25')");
        $db->exec("INSERT INTO citas (id_cita,id_clinica,id_mascota,id_veterinario,fecha,hora,motivo,estado) VALUES
            (1,1,1,2,'2026-09-23','10:00:00','Control Norte','pendiente'),
            (2,2,1,4,'2026-09-23','11:00:00','Control Sur','confirmada'),
            (3,2,1,4,'2026-09-23','12:00:00','Cancelada','cancelada')");
    }
}

/** Doble del transporte: guarda correos y nunca abre una conexión SMTP. */
final class CorreoRecordatorioSimulado
{
    public array $envios = [];
    public bool $resultado = true;
    public bool $lanzarError = false;

    public function limpiarDirecciones(): void
    {
    }

    public function obtenerPlantillaBaseHTML(string $nombre, string $titulo, string $contenido, string $cta, string $url): string
    {
        return $contenido;
    }

    public function enviarCorreoPersonalizado(string $email, string $nombre, string $asunto, string $cuerpo): bool
    {
        $this->envios[] = compact('email', 'nombre', 'asunto', 'cuerpo');
        if ($this->lanzarError) {
            throw new RuntimeException('SMTP simulado sin conexión');
        }
        return $this->resultado;
    }
}
