-- ---------------------------------------------------------------------------
-- Estado "en_curso" de las citas (iniciar / completar atención).
--
-- CitaController::iniciarAtencionAjax guarda estado = 'en_curso' y
-- completarAtencionAjax solo completa citas en ese estado, pero la columna
-- nunca tuvo ese valor en su ENUM:
--
-- · En MariaDB/MySQL sin modo estricto (XAMPP) el UPDATE no fallaba: guardaba
--   una cadena vacía en silencio. La cita desaparecía de agendas y contadores
--   y ya no se podía completar ("la atención aún no ha sido iniciada").
-- · En MySQL 8 con modo estricto (Docker) el UPDATE lanzaba un error.
--
-- Ejecutar una sola vez.
-- ---------------------------------------------------------------------------

ALTER TABLE `citas`
  MODIFY `estado` enum('pendiente','confirmada','en_curso','cancelada','completada') DEFAULT 'pendiente';

-- Las citas que quedaron con estado vacío son justamente las que se
-- intentaron iniciar: se devuelven al estado que se quiso guardar, para que
-- el veterinario pueda completarlas o cancelarlas.
UPDATE `citas` SET `estado` = 'en_curso' WHERE `estado` = '';
