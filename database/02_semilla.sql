-- ---------------------------------------------------------------------------
-- Zooki v2 — Datos semilla: roles, planes y catálogos taxonómicos globales.
--
-- Repetible (plan M0, D-5): el migrador la aplica en cada arranque. Solo
-- inserta lo que falta y NUNCA sobrescribe: INSERT IGNORE con la clave
-- primaria explícita y con los índices únicos por nombre de 01_schema.sql.
-- Así, un precio de plan que cambie el super-administrador (RN-008) no vuelve
-- al valor inicial, y una fila que ya exista con otro id no se duplica.
-- No usar ON DUPLICATE KEY UPDATE ni REPLACE en este archivo.
--
-- No incluye usuarios, clínicas ni contraseñas. Los catálogos por clínica
-- (tipos de cita, horarios, vacunas, laboratorios, productos) se copian a
-- cada clínica al activarla (RE-0.2.5, etapa D).
--
-- Para agregar una fila: usar el siguiente id libre del bloque y no cambiar
-- ni reutilizar los existentes, porque otras tablas los referencian.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

-- Roles (MER §2). El 3, recepcionista, ya no existe en la v2. Los nombres van
-- en minúscula porque el código compara así el rol de la sesión.
INSERT IGNORE INTO `roles` (`id_rol`, `nombre_rol`) VALUES
(1, 'administrador'),
(2, 'veterinario'),
(4, 'propietario'),
(5, 'super-administrador');

-- Planes (RN-003, RN-008). NULL en un límite = sin límite.
INSERT IGNORE INTO `planes` (`id_plan`, `nombre`, `precio_mensual`, `precio_anual`, `limite_mascotas`, `limite_citas_mes`, `limite_personal`, `estado`) VALUES
(1, 'Gratuito', 0, NULL, 5, 30, 2, 1),
(2, 'Profesional', 100000, 960000, NULL, NULL, NULL, 1);

-- Especies (sin la especie de prueba «PAN», D-4).
INSERT IGNORE INTO `especies` (`id_especie`, `nombre_especie`) VALUES
(1, 'Canino'),
(2, 'Felino'),
(3, 'Roedor'),
(4, 'Ave'),
(5, 'Reptil'),
(6, 'Exótico');

-- Razas: las 48 de la v1 con sus mismos ids, más «Sin raza definida» por
-- especie, que usa el portal cuando el propietario no conoce la raza.
INSERT IGNORE INTO `razas` (`id_raza`, `id_especie`, `nombre_raza`) VALUES
(1, 1, 'Labrador Retriever'),
(2, 1, 'Pastor Alemán'),
(3, 1, 'Golden Retriever'),
(4, 1, 'Bulldog Inglés'),
(5, 1, 'Bulldog Francés'),
(6, 1, 'Poodle (Caniche)'),
(7, 1, 'Beagle'),
(8, 1, 'Chihuahua'),
(9, 1, 'Boxer'),
(10, 1, 'Rottweiler'),
(11, 1, 'Husky Siberiano'),
(12, 1, 'Pinscher'),
(13, 1, 'Shih Tzu'),
(14, 1, 'Pug'),
(15, 1, 'Yorkshire Terrier'),
(16, 1, 'Dóberman'),
(17, 1, 'Dálmata'),
(18, 1, 'Criollo (Mestizo)'),
(19, 2, 'Persa'),
(20, 2, 'Siamés'),
(21, 2, 'Maine Coon'),
(22, 2, 'Angora'),
(23, 2, 'Azul Ruso'),
(24, 2, 'Bengala'),
(25, 2, 'Ragdoll'),
(26, 2, 'Sphynx'),
(27, 2, 'Criollo'),
(28, 3, 'Conejo Enano'),
(29, 3, 'Hámster Sirio'),
(30, 3, 'Hámster Ruso'),
(31, 3, 'Cobaya (Cuy)'),
(32, 3, 'Hurón'),
(33, 3, 'Chinchilla'),
(34, 4, 'Canario'),
(35, 4, 'Periquito Australiano'),
(36, 4, 'Loro Amazónico'),
(37, 4, 'Cacatúa'),
(38, 4, 'Agapornis'),
(39, 4, 'Ninfa'),
(40, 5, 'Tortuga Morrocoy'),
(41, 5, 'Tortuga Jicotea'),
(42, 5, 'Iguana Verde'),
(43, 5, 'Dragón Barbudo'),
(44, 5, 'Gecko'),
(45, 6, 'Erizo de Tierra'),
(46, 6, 'Mini Pig'),
(47, 6, 'Serpiente del Maíz'),
(48, 1, 'Pitbull'),
(49, 1, 'Sin raza definida'),
(50, 2, 'Sin raza definida'),
(51, 3, 'Sin raza definida'),
(52, 4, 'Sin raza definida'),
(53, 5, 'Sin raza definida'),
(54, 6, 'Sin raza definida');

-- Colores (sin «blancoo»; «Verde» con mayúscula, D-4).
INSERT IGNORE INTO `colores_base` (`id_color`, `nombre_color`) VALUES
(1, 'Blanco'),
(2, 'Negro'),
(3, 'Café'),
(4, 'Gris'),
(5, 'Canela'),
(6, 'Crema'),
(7, 'Naranja'),
(8, 'Chocolate'),
(9, 'Verde');

-- especialidades queda vacía hasta HU-7.3 (D-9).
