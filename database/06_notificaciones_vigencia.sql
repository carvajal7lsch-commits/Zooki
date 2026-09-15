-- ---------------------------------------------------------------------------
-- Vigencia de las notificaciones internas.
--
-- Las notificaciones de citas (NUEVA_CITA, CITA_CANCELADA) no caducaban nunca:
-- el veterinario y el administrador seguian viendo como "nuevas" citas que ya
-- habian pasado, que se habian cancelado o que ya se habian atendido.
--
-- · id_cita        enlaza la notificacion con su cita, para retirarla cuando
--                  la cita se cancela, se reprograma o se atiende.
-- · vigente_hasta  momento a partir del cual deja de mostrarse (para una cita,
--                  su fecha y hora). NULL = no caduca.
--
-- Ejecutar una sola vez (MySQL 8 no admite ADD COLUMN IF NOT EXISTS).
-- ---------------------------------------------------------------------------

ALTER TABLE `notificaciones_internas`
  ADD COLUMN `id_cita` int(11) DEFAULT NULL AFTER `enlace`,
  ADD COLUMN `vigente_hasta` datetime DEFAULT NULL AFTER `id_cita`,
  ADD KEY `idx_notif_cita` (`id_cita`),
  ADD KEY `idx_notif_vigencia` (`vigente_hasta`);

-- Las notificaciones de citas que ya existen no tienen id_cita, pero su
-- mensaje trae la fecha ("... para el 21/07/2026 a las 08:30"): de ahi sale
-- la vigencia, y las de citas pasadas dejan de aparecer.
UPDATE `notificaciones_internas`
   SET `vigente_hasta` = STR_TO_DATE(
         REGEXP_SUBSTR(`mensaje`, '[0-9]{2}/[0-9]{2}/[0-9]{4} a las [0-9]{2}:[0-9]{2}'),
         '%d/%m/%Y a las %H:%i'
       )
 WHERE `tipo` IN ('NUEVA_CITA', 'CITA_CANCELADA')
   AND `vigente_hasta` IS NULL;
