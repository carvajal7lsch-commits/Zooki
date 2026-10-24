-- RN-505: cuenta también tokens inválidos, sin relacionarlos con una mascota.
CREATE TABLE IF NOT EXISTS `carnet_accesos` (
  `id_acceso` INT NOT NULL AUTO_INCREMENT,
  `ip_hash` CHAR(64) NOT NULL,
  `fecha` DATETIME NOT NULL,
  PRIMARY KEY (`id_acceso`),
  KEY `idx_carnet_accesos_ip_fecha` (`ip_hash`, `fecha`),
  -- La limpieza de más de 24 horas filtra solo por fecha.
  KEY `idx_carnet_accesos_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
