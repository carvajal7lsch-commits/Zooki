SET NAMES utf8mb4;

-- HU-2.9 / RN-211: catálogo global, IDs nuevos sin modificar registros existentes.
INSERT IGNORE INTO `razas` (`id_raza`, `id_especie`, `nombre_raza`) VALUES
(56, 1, 'Collie'),
(57, 1, 'Border Collie'),
(58, 1, 'Pastor Australiano'),
(59, 1, 'Pastor de Shetland');
