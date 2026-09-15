-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 02-07-2026 a las 04:04:15
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `zooki_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `archivos_clinicos`
--

CREATE TABLE `archivos_clinicos` (
  `id_archivo` int(11) NOT NULL,
  `id_consulta` int(11) NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `nombre_servidor` varchar(255) NOT NULL,
  `ruta_archivo` varchar(255) NOT NULL,
  `tipo_archivo` varchar(255) NOT NULL,
  `extension` varchar(20) NOT NULL,
  `tamano_bytes` int(11) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `fecha_subida` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `archivos_clinicos`
--

INSERT INTO `archivos_clinicos` (`id_archivo`, `id_consulta`, `nombre_original`, `nombre_servidor`, `ruta_archivo`, `tipo_archivo`, `extension`, `tamano_bytes`, `descripcion`, `fecha_subida`) VALUES
(1, 3, 'Captura de pantalla 2025-05-16 231426.png', 'CLI_3_1779594579_0.png', 'uploads/clinicos/CLI_3_1779594579_0.png', 'image/png', 'png', 30532, 'Adjunto de consulta', '2026-05-23 22:49:39');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria_mascotas`
--

CREATE TABLE `auditoria_mascotas` (
  `id_auditoria` int(11) NOT NULL,
  `id_mascota` int(11) NOT NULL,
  `usuario_doc` varchar(20) NOT NULL,
  `campo_modificado` varchar(100) DEFAULT NULL,
  `valor_anterior` text DEFAULT NULL,
  `valor_nuevo` text DEFAULT NULL,
  `fecha_cambio` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `auditoria_mascotas`
--

INSERT INTO `auditoria_mascotas` (`id_auditoria`, `id_mascota`, `usuario_doc`, `campo_modificado`, `valor_anterior`, `valor_nuevo`, `fecha_cambio`) VALUES
(1, 1, '1080361991', 'estado', '1', '0', '2026-05-19 23:48:30'),
(2, 1, '1080361991', 'estado', '0', '1', '2026-05-19 23:48:56');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria_sistema`
--

CREATE TABLE `auditoria_sistema` (
  `id_auditoria` int(11) NOT NULL,
  `usuario_doc` varchar(20) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `fecha_hora` datetime DEFAULT current_timestamp(),
  `accion` enum('LOGIN','LOGIN_FAIL','LOGOUT','INSERT','UPDATE','DELETE','VIEW','OTHER') NOT NULL,
  `tabla_afectada` varchar(50) DEFAULT NULL,
  `registro_id` varchar(50) DEFAULT NULL,
  `datos_anteriores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos_anteriores`)),
  `datos_nuevos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos_nuevos`)),
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `auditoria_sistema`
--

INSERT INTO `auditoria_sistema` (`id_auditoria`, `usuario_doc`, `ip_address`, `fecha_hora`, `accion`, `tabla_afectada`, `registro_id`, `datos_anteriores`, `datos_nuevos`, `descripcion`) VALUES
(1, '1080361991', '::1', '2026-06-07 21:12:49', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(2, '12345', '::1', '2026-06-07 21:12:54', 'LOGIN', 'usuarios', '12345', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(3, '12345', '::1', '2026-06-07 21:16:32', 'LOGOUT', 'usuarios', '12345', NULL, NULL, 'Cierre de sesión'),
(4, '1080361991', '::1', '2026-06-07 21:16:34', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(5, '1080361991', '::1', '2026-06-07 21:18:20', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(6, '1080361991', '::1', '2026-06-07 21:18:25', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(7, '1080361991', '::1', '2026-06-07 21:18:28', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(8, '12345', '::1', '2026-06-07 21:18:31', 'LOGIN', 'usuarios', '12345', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(9, '12345', '::1', '2026-06-08 17:30:51', 'LOGOUT', 'usuarios', '12345', NULL, NULL, 'Cierre de sesión'),
(10, '12345', '127.0.0.1', '2026-06-08 17:31:50', 'LOGIN_FAIL', 'usuarios', '12345', NULL, NULL, 'Intento de login fallido: credenciales incorrectas'),
(11, '123456', '127.0.0.1', '2026-06-08 17:32:01', 'LOGIN_FAIL', 'usuarios', '123456', NULL, NULL, 'Intento de login fallido: credenciales incorrectas'),
(12, '12345', '127.0.0.1', '2026-06-08 17:32:11', 'LOGIN', 'usuarios', '12345', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(13, '12345', '127.0.0.1', '2026-06-08 17:32:58', 'LOGIN', 'usuarios', '12345', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(14, '12345', '::1', '2026-06-08 17:34:08', 'LOGIN', 'usuarios', '12345', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(15, '1080361991', '::1', '2026-06-10 13:58:51', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(16, '1080361991', '::1', '2026-06-10 13:58:56', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(17, '0123456', '::1', '2026-06-10 13:58:59', 'LOGIN', 'usuarios', '0123456', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(18, '0123456', '::1', '2026-06-10 13:59:14', 'LOGOUT', 'usuarios', '0123456', NULL, NULL, 'Cierre de sesión'),
(19, '0123456', '::1', '2026-06-10 13:59:18', 'LOGIN', 'usuarios', '0123456', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(20, '0123456', '::1', '2026-06-10 13:59:28', 'LOGOUT', 'usuarios', '0123456', NULL, NULL, 'Cierre de sesión'),
(21, '1080361992', '::1', '2026-06-10 13:59:35', 'LOGIN', 'usuarios', '1080361992', NULL, '{\"rol\":\"propietario\",\"id_rol\":4}', 'Inicio de sesión exitoso'),
(22, '1080361992', '::1', '2026-06-10 13:59:37', 'LOGOUT', 'usuarios', '1080361992', NULL, NULL, 'Cierre de sesión'),
(23, '0123456', '::1', '2026-06-10 13:59:41', 'LOGIN', 'usuarios', '0123456', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(24, '0123456', '::1', '2026-06-10 13:59:44', 'LOGOUT', 'usuarios', '0123456', NULL, NULL, 'Cierre de sesión'),
(25, '12345', '::1', '2026-06-10 13:59:48', 'LOGIN', 'usuarios', '12345', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(26, '12345', '::1', '2026-06-10 22:14:39', 'LOGOUT', 'usuarios', '12345', NULL, NULL, 'Cierre de sesión'),
(27, '1080361991', '::1', '2026-06-10 22:14:43', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(28, '1080361991', '::1', '2026-06-10 22:15:05', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(29, '0123456', '::1', '2026-06-10 22:15:09', 'LOGIN', 'usuarios', '0123456', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(30, '0123456', '::1', '2026-06-10 23:10:39', 'LOGOUT', 'usuarios', '0123456', NULL, NULL, 'Cierre de sesión'),
(31, '1080361991', '::1', '2026-06-10 23:10:43', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(32, '1080361991', '::1', '2026-06-10 23:11:41', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(33, '12345', '::1', '2026-06-10 23:11:45', 'LOGIN', 'usuarios', '12345', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(34, '12345', '::1', '2026-06-10 23:18:19', 'LOGOUT', 'usuarios', '12345', NULL, NULL, 'Cierre de sesión'),
(35, '1080361992', '::1', '2026-06-10 23:18:23', 'LOGIN', 'usuarios', '1080361992', NULL, '{\"rol\":\"propietario\",\"id_rol\":4}', 'Inicio de sesión exitoso'),
(36, '1080361992', '::1', '2026-06-10 23:18:51', 'LOGOUT', 'usuarios', '1080361992', NULL, NULL, 'Cierre de sesión'),
(37, '1080361991', '::1', '2026-06-10 23:19:49', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(38, '1080361991', '::1', '2026-06-10 23:19:52', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(39, '12345', '::1', '2026-06-10 23:19:55', 'LOGIN', 'usuarios', '12345', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(40, '12345', '::1', '2026-06-11 00:27:21', 'LOGOUT', 'usuarios', '12345', NULL, NULL, 'Cierre de sesión'),
(41, '1080361991', '::1', '2026-06-11 00:27:25', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(42, '1080361991', '::1', '2026-06-11 00:27:47', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(43, '12345', '::1', '2026-06-11 00:27:54', 'LOGIN', 'usuarios', '12345', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(44, '12345', '::1', '2026-06-11 00:37:45', 'LOGOUT', 'usuarios', '12345', NULL, NULL, 'Cierre de sesión'),
(45, '1080361991', '::1', '2026-06-11 00:37:48', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(46, '1080361991', '::1', '2026-06-11 00:38:12', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(47, '0123456', '::1', '2026-06-11 00:38:16', 'LOGIN', 'usuarios', '0123456', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(48, '0123456', '::1', '2026-06-11 00:49:12', 'LOGOUT', 'usuarios', '0123456', NULL, NULL, 'Cierre de sesión'),
(49, '12345', '::1', '2026-06-11 00:49:17', 'LOGIN', 'usuarios', '12345', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(50, '12345', '::1', '2026-06-11 10:02:27', 'LOGIN', 'usuarios', '12345', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(51, '1080361991', '::1', '2026-06-18 23:39:23', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(52, '1080361991', '::1', '2026-06-18 23:40:17', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(53, '1080361991', '::1', '2026-06-19 16:43:00', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(54, '1080361991', '::1', '2026-06-19 16:43:43', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(55, '1080361992', '::1', '2026-06-19 16:43:46', 'LOGIN', 'usuarios', '1080361992', NULL, '{\"rol\":\"propietario\",\"id_rol\":4}', 'Inicio de sesión exitoso'),
(56, '1080361992', '::1', '2026-06-19 16:43:49', 'LOGOUT', 'usuarios', '1080361992', NULL, NULL, 'Cierre de sesión'),
(57, '12345', '::1', '2026-06-19 16:43:52', 'LOGIN', 'usuarios', '12345', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(58, '12345', '::1', '2026-06-21 12:09:00', 'LOGOUT', 'usuarios', '12345', NULL, NULL, 'Cierre de sesión'),
(59, '1080361991', '::1', '2026-06-21 12:09:04', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(60, '1080361991', '::1', '2026-06-21 13:04:10', 'UPDATE', 'usuarios', '012345', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"0\"}', 'Estado de usuario cambiado a 0'),
(61, '1080361991', '::1', '2026-06-21 13:04:11', 'UPDATE', 'usuarios', '012345', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"1\"}', 'Estado de usuario cambiado a 1'),
(62, '1080361991', '::1', '2026-06-21 13:04:12', 'UPDATE', 'usuarios', '12345', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"0\"}', 'Estado de usuario cambiado a 0'),
(63, '1080361991', '::1', '2026-06-21 13:04:13', 'UPDATE', 'usuarios', '12345', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"1\"}', 'Estado de usuario cambiado a 1'),
(64, '1080361991', '::1', '2026-06-21 13:13:31', 'UPDATE', 'usuarios', '012345', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"0\"}', 'Estado de usuario cambiado a 0'),
(65, '1080361991', '::1', '2026-06-21 13:13:31', 'UPDATE', 'usuarios', '012345', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"1\"}', 'Estado de usuario cambiado a 1'),
(66, '1080361991', '::1', '2026-06-21 13:13:33', 'UPDATE', 'usuarios', '12345', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"0\"}', 'Estado de usuario cambiado a 0'),
(67, '1080361991', '::1', '2026-06-21 13:13:34', 'UPDATE', 'usuarios', '12345', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"1\"}', 'Estado de usuario cambiado a 1'),
(68, '1080361991', '::1', '2026-06-21 13:13:35', 'UPDATE', 'usuarios', '0123456', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"0\"}', 'Estado de usuario cambiado a 0'),
(69, '1080361991', '::1', '2026-06-21 13:13:36', 'UPDATE', 'usuarios', '0123456', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"1\"}', 'Estado de usuario cambiado a 1'),
(70, '1080361991', '::1', '2026-06-21 13:13:40', 'UPDATE', 'usuarios', '0123456', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"0\"}', 'Estado de usuario cambiado a 0'),
(71, '1080361991', '::1', '2026-06-21 13:13:42', 'UPDATE', 'usuarios', '0123456', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"1\"}', 'Estado de usuario cambiado a 1'),
(72, '1080361991', '::1', '2026-06-21 13:13:46', 'UPDATE', 'usuarios', '0123456', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"0\"}', 'Estado de usuario cambiado a 0'),
(73, '1080361991', '::1', '2026-06-21 13:13:47', 'UPDATE', 'usuarios', '0123456', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"1\"}', 'Estado de usuario cambiado a 1'),
(74, '1080361991', '::1', '2026-06-21 13:13:50', 'UPDATE', 'usuarios', '12345', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"0\"}', 'Estado de usuario cambiado a 0'),
(75, '1080361991', '::1', '2026-06-21 13:13:51', 'UPDATE', 'usuarios', '12345', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"1\"}', 'Estado de usuario cambiado a 1'),
(76, '1080361991', '::1', '2026-06-21 13:18:43', 'UPDATE', 'usuarios', '012345', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"0\"}', 'Estado de usuario cambiado a 0'),
(77, '1080361991', '::1', '2026-06-21 13:18:44', 'UPDATE', 'usuarios', '012345', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"1\"}', 'Estado de usuario cambiado a 1'),
(78, '1080361991', '::1', '2026-06-21 13:26:45', 'UPDATE', 'usuarios', '11178583838', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"1\"}', 'Estado de usuario cambiado a 1'),
(79, '1080361991', '::1', '2026-06-21 13:26:46', 'UPDATE', 'usuarios', '11178583838', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"0\"}', 'Estado de usuario cambiado a 0'),
(80, '1080361991', '::1', '2026-06-21 14:39:49', 'UPDATE', 'usuarios', '11178583838', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"1\"}', 'Estado de usuario cambiado a 1'),
(81, '1080361991', '::1', '2026-06-21 14:39:50', 'UPDATE', 'usuarios', '11178583838', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"0\"}', 'Estado de usuario cambiado a 0'),
(82, '1080361991', '::1', '2026-06-21 14:55:57', 'UPDATE', 'usuarios', '1080361991', '{\"original_doc\":\"1080361991\"}', '{\"nombre_completo\":\"Sebastian Carvajal \",\"email\":\"carvajal7lsch@gmail.com\",\"id_rol\":\"1\",\"estado\":\"1\"}', 'Usuario actualizado'),
(83, '1080361991', '::1', '2026-06-21 15:02:02', 'UPDATE', 'usuarios', '11178583838', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"1\"}', 'Estado de usuario cambiado a 1'),
(84, '1080361991', '::1', '2026-06-21 15:02:03', 'UPDATE', 'usuarios', '11178583838', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"0\"}', 'Estado de usuario cambiado a 0'),
(85, '1080361991', '::1', '2026-06-21 15:10:29', 'UPDATE', 'usuarios', '11178583838', '{\"original_doc\":\"11178583838\"}', '{\"nombre_completo\":\"Juan perez\",\"email\":\"carvajal7lsch@gmail.comdf\",\"id_rol\":\"1\",\"estado\":\"0\"}', 'Usuario actualizado'),
(86, '1080361991', '::1', '2026-06-21 15:10:50', 'UPDATE', 'usuarios', '11178583838', '{\"original_doc\":\"11178583838\"}', '{\"nombre_completo\":\"Juan perez\",\"email\":\"carvajal7lsch@gmail.comdf\",\"id_rol\":\"1\",\"estado\":\"0\"}', 'Usuario actualizado'),
(87, '1080361991', '::1', '2026-06-21 15:11:04', 'UPDATE', 'usuarios', '11178583838', '{\"original_doc\":\"11178583838\"}', '{\"nombre_completo\":\"Juan perez\",\"email\":\"carvajal.2@gmail.com\",\"id_rol\":\"1\",\"estado\":\"0\"}', 'Usuario actualizado'),
(88, '1080361991', '::1', '2026-06-21 15:15:24', 'UPDATE', 'usuarios', '012345', '{\"original_doc\":\"012345\"}', '{\"nombre_completo\":\"Luisa Fernanda\",\"email\":\"Luisa@gmail.com\",\"id_rol\":\"2\",\"estado\":\"0\"}', 'Usuario actualizado'),
(89, '1080361991', '::1', '2026-06-21 15:15:27', 'UPDATE', 'usuarios', '012345', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"1\"}', 'Estado de usuario cambiado a 1'),
(90, '1080361991', '::1', '2026-06-21 15:16:54', 'UPDATE', 'usuarios', '12345', '{\"original_doc\":\"12345\"}', '{\"nombre_completo\":\"Maria Lopez\",\"email\":\"maria.vet@zooki.com\",\"id_rol\":\"2\",\"estado\":\"1\"}', 'Usuario actualizado'),
(91, '1080361991', '::1', '2026-06-21 15:16:58', 'UPDATE', 'usuarios', '12345', '{\"original_doc\":\"12345\"}', '{\"nombre_completo\":\"Maria Lopez\",\"email\":\"maria.vet@zooki.com\",\"id_rol\":\"2\",\"estado\":\"1\"}', 'Usuario actualizado'),
(92, '1080361991', '::1', '2026-06-21 15:20:33', 'UPDATE', 'usuarios', '11178583838', '{\"original_doc\":\"11178583838\"}', '{\"nombre_completo\":\"Juan perez\",\"email\":\"carvajal.2@gmail.comfd\",\"id_rol\":\"1\",\"estado\":\"0\"}', 'Usuario actualizado'),
(93, '1080361991', '::1', '2026-06-21 15:20:39', 'UPDATE', 'usuarios', '11178583838', '{\"original_doc\":\"11178583838\"}', '{\"nombre_completo\":\"Juan perez\",\"email\":\"carvajal.2@gmail.com\",\"id_rol\":\"1\",\"estado\":\"0\"}', 'Usuario actualizado'),
(94, '1080361991', '::1', '2026-06-21 15:21:09', 'UPDATE', 'usuarios', '11178583838', '{\"original_doc\":\"11178583838\"}', '{\"nombre_completo\":\"Juan perez\",\"email\":\"carvajal.2@gmail.com\",\"id_rol\":\"1\",\"estado\":\"0\"}', 'Usuario actualizado'),
(95, '1080361991', '::1', '2026-06-21 15:22:50', 'UPDATE', 'usuarios', '1080361991', '{\"original_doc\":\"1080361991\"}', '{\"nombre_completo\":\"Sebastian Carvajal \",\"email\":\"carvajal7lsch@gmail.com\",\"id_rol\":\"1\",\"estado\":\"0\"}', 'Usuario actualizado'),
(96, '1080361991', '::1', '2026-06-21 15:22:55', 'UPDATE', 'usuarios', '1080361991', '{\"original_doc\":\"1080361991\"}', '{\"nombre_completo\":\"Sebastian Carvajal \",\"email\":\"carvajal7lsch@gmail.com\",\"id_rol\":\"1\",\"estado\":\"1\"}', 'Usuario actualizado'),
(97, '1080361991', '::1', '2026-06-21 15:23:13', 'UPDATE', 'usuarios', '11178583838', '{\"original_doc\":\"11178583838\"}', '{\"nombre_completo\":\"Juan perez\",\"email\":\"carvajal.2@gmail.coms\",\"id_rol\":\"1\",\"estado\":\"0\"}', 'Usuario actualizado'),
(98, '1080361991', '::1', '2026-06-21 15:23:17', 'UPDATE', 'usuarios', '11178583838', '{\"original_doc\":\"11178583838\"}', '{\"nombre_completo\":\"Juan perez\",\"email\":\"carvajal.2@gmail.com\",\"id_rol\":\"1\",\"estado\":\"0\"}', 'Usuario actualizado'),
(99, '1080361991', '::1', '2026-06-21 15:23:51', 'UPDATE', 'usuarios', '11178583838', '{\"original_doc\":\"11178583838\"}', '{\"nombre_completo\":\"Juan perez\",\"email\":\"carvajal.2@gmail.com\",\"id_rol\":\"1\",\"estado\":\"0\"}', 'Usuario actualizado'),
(100, '1080361991', '::1', '2026-06-21 15:25:02', 'UPDATE', 'usuarios', '11178583838', '{\"original_doc\":\"11178583838\"}', '{\"nombre_completo\":\"Juan perez\",\"email\":\"carvajal.2@gmail.com\",\"id_rol\":\"1\",\"estado\":\"0\"}', 'Usuario actualizado'),
(101, '1080361991', '::1', '2026-06-21 15:55:21', 'UPDATE', 'usuarios', '111111777', '{\"original_doc\":\"111111777\"}', '{\"nombre_completo\":\"Santiago Lizcano\",\"email\":\"santilizcanoaliasbombi@gmail.com\",\"id_rol\":\"4\",\"estado\":\"1\"}', 'Usuario actualizado'),
(102, '1080361991', '::1', '2026-06-21 16:10:57', 'UPDATE', 'usuarios', '11178583838', '{\"original_doc\":\"11178583838\"}', '{\"nombre_completo\":\"Juan perez\",\"email\":\"carvajal.2@gmail.com\",\"id_rol\":\"1\",\"estado\":\"0\"}', 'Usuario actualizado'),
(103, '1080361991', '::1', '2026-06-21 16:19:44', 'UPDATE', 'usuarios', '12345', '{\"original_doc\":\"12345\"}', '{\"nombre_completo\":\"Maria Lopez\",\"email\":\"maria.vet@zooki.com\",\"id_rol\":\"2\",\"estado\":\"1\"}', 'Usuario actualizado'),
(104, '1080361991', '::1', '2026-06-21 16:21:02', 'UPDATE', 'usuarios', '12345', '{\"original_doc\":\"12345\"}', '{\"nombre_completo\":\"Maria Lopez\",\"email\":\"maria.vet@zooki.com\",\"id_rol\":\"2\",\"estado\":\"1\"}', 'Usuario actualizado'),
(105, '1080361991', '::1', '2026-06-21 16:29:01', 'UPDATE', 'usuarios', '12345', '{\"original_doc\":\"12345\"}', '{\"nombre_completo\":\"Maria Lopez\",\"email\":\"maria.vet@zooki.com\",\"id_rol\":\"2\",\"estado\":\"1\"}', 'Usuario actualizado'),
(106, '1080361991', '::1', '2026-06-21 16:29:14', 'UPDATE', 'usuarios', '12345', '{\"original_doc\":\"12345\"}', '{\"nombre_completo\":\"Maria Lopez\",\"email\":\"maria.vet@zooki.com\",\"id_rol\":\"2\",\"estado\":\"1\"}', 'Usuario actualizado'),
(107, '1080361991', '::1', '2026-06-21 16:44:00', 'UPDATE', 'usuarios', '1080361992', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"0\"}', 'Estado de usuario cambiado a 0'),
(108, '1080361991', '::1', '2026-06-21 16:44:02', 'UPDATE', 'usuarios', '1080361992', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"0\"}', 'Estado de usuario cambiado a 0'),
(109, '1080361991', '::1', '2026-06-21 16:44:04', 'UPDATE', 'usuarios', '1080361992', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"0\"}', 'Estado de usuario cambiado a 0'),
(110, '1080361991', '::1', '2026-06-21 16:44:09', 'UPDATE', 'usuarios', '1080361992', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"1\"}', 'Estado de usuario cambiado a 1'),
(111, '1080361991', '::1', '2026-06-21 18:49:06', 'INSERT', 'usuarios', '1080361997', NULL, '{\"nombre_completo\":\"Cristian GPT\",\"email\":\"cristi@gmail.com\",\"id_rol\":\"2\"}', 'Usuario creado'),
(112, '1080361991', '::1', '2026-06-22 17:21:49', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(113, '1080361991', '::1', '2026-06-22 21:13:27', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(114, '1080361992', '::1', '2026-06-22 21:13:31', 'LOGIN', 'usuarios', '1080361992', NULL, '{\"rol\":\"propietario\",\"id_rol\":4}', 'Inicio de sesión exitoso'),
(115, '1080361992', '::1', '2026-06-22 21:13:33', 'LOGOUT', 'usuarios', '1080361992', NULL, NULL, 'Cierre de sesión'),
(116, '12345', '::1', '2026-06-22 21:13:36', 'LOGIN', 'usuarios', '12345', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(117, '12345', '::1', '2026-06-22 21:14:16', 'LOGOUT', 'usuarios', '12345', NULL, NULL, 'Cierre de sesión'),
(118, '1080361991', '::1', '2026-06-22 21:14:19', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(119, '1080361991', '::1', '2026-06-22 21:36:53', 'UPDATE', 'usuarios', '012345', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"0\"}', 'Estado de usuario cambiado a 0'),
(120, '1080361991', '::1', '2026-06-22 21:36:54', 'UPDATE', 'usuarios', '012345', '{\"estado_anterior\":\"desconocido\"}', '{\"estado_nuevo\":\"1\"}', 'Estado de usuario cambiado a 1'),
(121, '1080361991', '::1', '2026-06-22 21:40:56', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(122, '0123456', '::1', '2026-06-22 21:41:01', 'LOGIN', 'usuarios', '0123456', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(123, '0123456', '::1', '2026-06-22 21:41:10', 'LOGOUT', 'usuarios', '0123456', NULL, NULL, 'Cierre de sesión'),
(124, '1080361991', '::1', '2026-06-22 21:41:13', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(125, '1080361991', '::1', '2026-06-22 21:47:51', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(126, '12345', '::1', '2026-06-22 21:47:56', 'LOGIN', 'usuarios', '12345', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(127, '12345', '::1', '2026-06-22 21:47:59', 'LOGOUT', 'usuarios', '12345', NULL, NULL, 'Cierre de sesión'),
(128, '1080361991', '::1', '2026-06-22 21:48:06', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(129, '1080361991', '::1', '2026-06-22 22:47:15', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(130, '1080361992', '::1', '2026-06-22 22:47:20', 'LOGIN', 'usuarios', '1080361992', NULL, '{\"rol\":\"propietario\",\"id_rol\":4}', 'Inicio de sesión exitoso'),
(131, '1080361992', '::1', '2026-06-22 22:47:22', 'LOGOUT', 'usuarios', '1080361992', NULL, NULL, 'Cierre de sesión'),
(132, '12345', '::1', '2026-06-22 22:47:29', 'LOGIN', 'usuarios', '12345', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(133, '12345', '::1', '2026-06-22 23:38:54', 'LOGOUT', 'usuarios', '12345', NULL, NULL, 'Cierre de sesión'),
(134, '1080361991', '::1', '2026-06-22 23:38:59', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(135, '1080361991', '::1', '2026-06-22 23:39:09', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(136, '12345', '::1', '2026-06-22 23:39:15', 'LOGIN', 'usuarios', '12345', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(137, '12345', '::1', '2026-06-22 23:39:27', 'LOGOUT', 'usuarios', '12345', NULL, NULL, 'Cierre de sesión'),
(138, '1080361991', '::1', '2026-06-22 23:39:31', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(139, '1080361991', '::1', '2026-06-22 23:39:52', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(140, '12345', '::1', '2026-06-22 23:39:58', 'LOGIN', 'usuarios', '12345', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(141, '12345', '::1', '2026-06-22 23:57:46', 'LOGOUT', 'usuarios', '12345', NULL, NULL, 'Cierre de sesión'),
(142, '1080361991', '::1', '2026-06-22 23:57:50', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(143, '1080361991', '::1', '2026-06-22 23:58:40', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(144, '12345', '::1', '2026-06-22 23:58:46', 'LOGIN', 'usuarios', '12345', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(145, '12345', '::1', '2026-06-23 00:19:27', 'LOGOUT', 'usuarios', '12345', NULL, NULL, 'Cierre de sesión'),
(146, '1080361991', '::1', '2026-06-23 00:19:31', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(147, '1080361991', '::1', '2026-06-23 00:20:36', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(148, '12345', '::1', '2026-06-23 00:20:42', 'LOGIN', 'usuarios', '12345', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(149, '1080361991', '::1', '2026-06-23 12:35:35', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(150, '1080361991', '::1', '2026-06-23 13:21:22', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(151, '12345', '::1', '2026-06-23 13:21:26', 'LOGIN', 'usuarios', '12345', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(152, '1080361991', '::1', '2026-06-26 13:32:22', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(153, '1080361991', '::1', '2026-06-26 13:32:29', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(154, '12345', '::1', '2026-06-26 13:32:34', 'LOGIN', 'usuarios', '12345', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(155, '12345', '::1', '2026-06-29 19:03:41', 'LOGOUT', 'usuarios', '12345', NULL, NULL, 'Cierre de sesión'),
(156, '12345', '::1', '2026-06-30 15:47:22', 'LOGIN', 'usuarios', '12345', NULL, '{\"rol\":\"veterinario\",\"id_rol\":2}', 'Inicio de sesión exitoso'),
(157, '12345', '::1', '2026-06-30 15:47:24', 'LOGOUT', 'usuarios', '12345', NULL, NULL, 'Cierre de sesión'),
(158, '1080361991', '::1', '2026-06-30 15:48:07', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(159, '1080361991', '::1', '2026-06-30 15:49:15', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(160, '1080361991', '::1', '2026-06-30 16:07:10', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(161, '1080361991', '::1', '2026-06-30 16:07:12', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(162, '1080361991', '::1', '2026-06-30 16:13:36', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(163, '1080361991', '::1', '2026-06-30 16:13:38', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(164, '1080361991', '::1', '2026-06-30 19:02:33', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(165, '1080361991', '::1', '2026-06-30 19:02:42', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(166, '0123456', '::1', '2026-06-30 19:22:38', 'LOGOUT', 'usuarios', '0123456', NULL, NULL, 'Cierre de sesión'),
(167, '1080361991', '::1', '2026-06-30 19:22:46', '', 'Usuario', '1080361991', NULL, NULL, NULL),
(168, '1080361991', '::1', '2026-06-30 19:22:50', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(169, '1080361992', '::1', '2026-06-30 19:23:01', '', 'Usuario', '1080361992', NULL, NULL, NULL),
(170, '1080361992', '::1', '2026-06-30 19:23:06', 'LOGOUT', 'usuarios', '1080361992', NULL, NULL, 'Cierre de sesión'),
(171, '1080361991', '::1', '2026-06-30 19:23:52', '', 'Usuario', '1080361991', NULL, NULL, NULL),
(172, '1080361991', '::1', '2026-06-30 19:23:55', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(173, '0123456', '::1', '2026-06-30 19:24:06', '', 'Usuario', '0123456', NULL, NULL, NULL),
(174, '0123456', '::1', '2026-06-30 19:24:12', 'LOGOUT', 'usuarios', '0123456', NULL, NULL, 'Cierre de sesión'),
(175, '1080361991', '::1', '2026-06-30 19:35:34', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(176, '1080361991', '::1', '2026-06-30 19:35:36', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(177, '0123456', '::1', '2026-06-30 19:37:52', '', 'Usuario', '0123456', NULL, NULL, NULL),
(178, '0123456', '::1', '2026-06-30 19:37:55', 'LOGOUT', 'usuarios', '0123456', NULL, NULL, 'Cierre de sesión'),
(179, '1080361991', '::1', '2026-06-30 20:28:29', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(180, '1080361991', '::1', '2026-06-30 20:29:16', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(181, '1080361991', '::1', '2026-06-30 20:36:08', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(182, '1080361991', '::1', '2026-06-30 20:36:20', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(183, '1080361991', '::1', '2026-06-30 20:36:23', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(184, '1080361991', '::1', '2026-06-30 20:36:25', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(185, '1080361991', '::1', '2026-06-30 22:19:27', 'LOGIN', 'usuarios', '1080361991', NULL, '{\"rol\":\"administrador\",\"id_rol\":1}', 'Inicio de sesión exitoso'),
(186, '1080361991', '::1', '2026-06-30 22:19:30', 'LOGOUT', 'usuarios', '1080361991', NULL, NULL, 'Cierre de sesión'),
(187, '0123456', '::1', '2026-06-30 22:20:43', '', 'Usuario', '0123456', NULL, NULL, NULL),
(188, '0123456', '::1', '2026-06-30 22:20:45', 'LOGOUT', 'usuarios', '0123456', NULL, NULL, 'Cierre de sesión');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `citas`
--

CREATE TABLE `citas` (
  `id_cita` int(11) NOT NULL,
  `id_mascota` int(11) NOT NULL,
  `doc_veterinario` varchar(20) NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `hora_fin` time DEFAULT NULL,
  `motivo` varchar(255) NOT NULL,
  `id_tipo_cita` int(11) DEFAULT NULL,
  `duracion_minutos` int(11) DEFAULT NULL,
  `estado` enum('pendiente','confirmada','en_curso','cancelada','completada','no_asistio','sin_cerrar','cerrada_sin_consulta') DEFAULT 'pendiente',
  `slot_activo` tinyint(1) GENERATED ALWAYS AS (CASE WHEN `estado` IN ('cancelada','no_asistio','cerrada_sin_consulta') THEN NULL ELSE 1 END) STORED,
  `hora_inicio_real` datetime DEFAULT NULL,
  `hora_fin_real` datetime DEFAULT NULL,
  `aviso_atencion_abierta` datetime DEFAULT NULL,
  `motivo_cierre` varchar(255) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `citas`
--

INSERT INTO `citas` (`id_cita`, `id_mascota`, `doc_veterinario`, `fecha`, `hora`, `hora_fin`, `motivo`, `id_tipo_cita`, `duracion_minutos`, `estado`, `observaciones`, `fecha_registro`) VALUES
(6, 5, '12345', '2026-05-23', '12:00:00', '12:15:00', '', 1, 15, 'pendiente', NULL, '2026-05-22 10:16:41'),
(7, 1, '12345', '2026-05-24', '09:30:00', '10:15:00', 'le duele una patica', 3, 45, 'cancelada', NULL, '2026-05-23 22:51:16'),
(8, 3, '12345', '2026-05-24', '17:45:00', '18:00:00', '', 5, 15, 'pendiente', NULL, '2026-05-24 17:33:22'),
(9, 3, '012345', '2026-05-29', '11:00:00', '11:20:00', '', 6, 20, 'pendiente', NULL, '2026-05-28 12:54:20'),
(10, 3, '12345', '2026-06-01', '10:30:00', '10:45:00', '', 5, 15, 'cancelada', NULL, '2026-06-01 10:29:08'),
(11, 2, '12345', '2026-06-08', '08:00:00', '08:15:00', '', 1, 15, 'completada', NULL, '2026-06-07 21:13:57'),
(12, 2, '12345', '2026-06-08', '08:15:00', '08:30:00', '', 5, 15, 'cancelada', NULL, '2026-06-07 21:18:56'),
(13, 3, '12345', '2026-06-09', '08:15:00', '08:30:00', '', 1, 15, '', NULL, '2026-06-08 17:54:30'),
(14, 5, '12345', '2026-06-09', '09:00:00', '09:20:00', '', 6, 20, 'confirmada', NULL, '2026-06-08 19:43:37'),
(15, 1, '12345', '2026-06-11', '08:00:00', '08:15:00', '', 1, 15, '', NULL, '2026-06-10 23:38:48'),
(16, 4, '12345', '2026-06-11', '08:15:00', '08:30:00', '', 1, 15, 'confirmada', NULL, '2026-06-10 23:45:17'),
(17, 5, '12345', '2026-06-11', '08:30:00', '09:00:00', '', 2, 30, 'confirmada', NULL, '2026-06-10 23:50:32'),
(19, 1, '12345', '2026-06-12', '08:30:00', '08:45:00', '', 1, 15, '', NULL, '2026-06-11 00:09:34');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `colores_base`
--

CREATE TABLE `colores_base` (
  `id_color` int(11) NOT NULL,
  `nombre_color` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `colores_base`
--

INSERT INTO `colores_base` (`id_color`, `nombre_color`) VALUES
(1, 'Blanco'),
(2, 'Negro'),
(3, 'Café'),
(4, 'Gris'),
(5, 'Canela'),
(6, 'Crema'),
(7, 'Naranja'),
(8, 'Chocolate'),
(9, 'verde'),
(10, 'blancoo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `consultas`
--

CREATE TABLE `consultas` (
  `id_consulta` int(11) NOT NULL,
  `id_cita` int(11) DEFAULT NULL,
  `id_mascota` int(11) NOT NULL,
  `doc_veterinario` varchar(20) NOT NULL,
  `fecha_hora` datetime NOT NULL,
  `motivo_consulta` text NOT NULL,
  `anamnesis` text NOT NULL,
  `peso` decimal(5,2) DEFAULT NULL,
  `temperatura` decimal(4,1) DEFAULT NULL,
  `frecuencia_cardiaca` int(11) DEFAULT NULL,
  `frecuencia_respiratoria` int(11) DEFAULT NULL,
  `diagnostico` text NOT NULL,
  `plan_tratamiento` text NOT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `consultas`
--

INSERT INTO `consultas` (`id_consulta`, `id_cita`, `id_mascota`, `doc_veterinario`, `fecha_hora`, `motivo_consulta`, `anamnesis`, `peso`, `temperatura`, `frecuencia_cardiaca`, `diagnostico`, `plan_tratamiento`) VALUES
(1, NULL, 1, '1080361991', '2026-05-10 19:51:22', 'le duele una patica', 'lo cogio un carro ayer  y siguio malo', 5.00, 36.0, 140, 'se partio la pata', 'acetaminofen cada 8 horas'),
(2, NULL, 1, '12345', '2026-05-23 22:48:23', 'chequeo general', 'El perro llego enfermo', 5.00, 34.0, 127, 'Gripa', 'reposo en cama durante una semana'),
(3, NULL, 5, '12345', '2026-05-23 22:49:39', 'Tiene moquillo', 'esta pa morirse', 55.00, 34.0, 111, 'lele pancha', 'agua tibia en las mañanas');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `desparasitaciones`
--

CREATE TABLE `desparasitaciones` (
  `id_desparasitacion` int(11) NOT NULL,
  `id_mascota` int(11) NOT NULL,
  `tipo` enum('interna','externa') NOT NULL,
  `producto` varchar(150) NOT NULL,
  `periodicidad` enum('mensual','trimestral','semestral') NOT NULL,
  `fecha_aplicacion` date NOT NULL,
  `fecha_proxima` date NOT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `especies`
--

CREATE TABLE `especies` (
  `id_especie` int(11) NOT NULL,
  `nombre_especie` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `especies`
--

INSERT INTO `especies` (`id_especie`, `nombre_especie`) VALUES
(1, 'Canino'),
(2, 'Felino'),
(3, 'Roedor'),
(4, 'Ave'),
(5, 'Reptil'),
(6, 'Exótico'),
(7, 'PAN');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `especie_vacunas`
--

CREATE TABLE `especie_vacunas` (
  `id_especie_vacuna` int(11) NOT NULL,
  `id_especie` int(11) NOT NULL,
  `id_vacuna_base` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `especie_vacunas`
--

INSERT INTO `especie_vacunas` (`id_especie_vacuna`, `id_especie`, `id_vacuna_base`) VALUES
(1, 1, 1),
(2, 1, 2),
(3, 1, 3),
(4, 1, 4),
(5, 1, 5),
(6, 1, 6),
(7, 1, 7),
(8, 1, 8),
(9, 1, 9),
(10, 1, 13),
(11, 1, 14),
(12, 1, 15),
(16, 2, 3),
(13, 2, 10),
(14, 2, 11),
(15, 2, 12),
(17, 2, 15),
(18, 3, 15),
(19, 4, 15),
(20, 5, 15),
(21, 6, 15);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horarios_clinica`
--

CREATE TABLE `horarios_clinica` (
  `id` int(11) NOT NULL,
  `dia_semana` int(11) NOT NULL COMMENT '1=Lunes, 2=Martes, ..., 7=Domingo',
  `activo` tinyint(1) DEFAULT 1 COMMENT '1=Activo, 0=Inactivo',
  `bloque_morning_activo` tinyint(1) NOT NULL DEFAULT 1,
  `bloque_afternoon_activo` tinyint(1) NOT NULL DEFAULT 1,
  `bloque_morning_inicio` time DEFAULT NULL COMMENT 'Inicio horario mañana',
  `bloque_morning_fin` time DEFAULT NULL COMMENT 'Fin horario mañana',
  `bloque_afternoon_inicio` time DEFAULT NULL COMMENT 'Inicio horario tarde',
  `bloque_afternoon_fin` time DEFAULT NULL COMMENT 'Fin horario tarde'
) ;

--
-- Volcado de datos para la tabla `horarios_clinica`
--

INSERT INTO `horarios_clinica` (`id`, `dia_semana`, `activo`, `bloque_morning_activo`, `bloque_afternoon_activo`, `bloque_morning_inicio`, `bloque_morning_fin`, `bloque_afternoon_inicio`, `bloque_afternoon_fin`) VALUES
(148, 1, 1, 1, 1, '08:00:00', '12:00:00', '14:00:00', '18:00:00'),
(149, 2, 1, 1, 1, '08:00:00', '12:00:00', '14:00:00', '18:00:00'),
(150, 3, 1, 1, 1, '10:00:00', '12:00:00', '14:00:00', '18:00:00'),
(151, 4, 1, 1, 1, '08:00:00', '12:00:00', '14:00:00', '18:00:00'),
(152, 5, 1, 1, 1, '08:00:00', '12:00:00', '14:00:00', '18:00:00'),
(153, 6, 0, 0, 0, '08:00:00', '12:00:00', '00:00:00', '00:00:00'),
(154, 7, 0, 0, 0, '00:00:00', '00:00:00', '00:00:00', '00:00:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `laboratorios_base`
--

CREATE TABLE `laboratorios_base` (
  `id_laboratorio` int(11) NOT NULL,
  `nombre_laboratorio` varchar(150) NOT NULL,
  `estado` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `laboratorios_base`
--

INSERT INTO `laboratorios_base` (`id_laboratorio`, `nombre_laboratorio`, `estado`) VALUES
(1, 'MSD Animal Health', 1),
(2, 'Zoetis', 1),
(3, 'Boehringer Ingelheim', 1),
(4, 'Elanco', 1),
(5, 'Ceva', 1),
(6, 'Virbac', 1),
(7, 'Merial', 1),
(8, 'Bayer', 1),
(9, 'Laboratorios Calier', 1),
(10, 'Laboratorios Syntex', 1),
(11, 'Laboratorios Farvet', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mascotas`
--

CREATE TABLE `mascotas` (
  `id_mascota` int(11) NOT NULL,
  `numero_historia_clinica` varchar(255) NOT NULL,
  `doc_propietario` varchar(20) NOT NULL,
  `id_especie` int(11) NOT NULL,
  `id_raza` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `peso` decimal(5,2) NOT NULL,
  `sexo` enum('Macho','Hembra','Desconocido') NOT NULL DEFAULT 'Desconocido',
  `color` varchar(255) NOT NULL,
  `estado` tinyint(4) NOT NULL DEFAULT 1,
  `url_foto` varchar(255) DEFAULT NULL,
  `patron` varchar(50) DEFAULT 'Sólido'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `mascotas`
--

INSERT INTO `mascotas` (`id_mascota`, `numero_historia_clinica`, `doc_propietario`, `id_especie`, `id_raza`, `nombre`, `fecha_nacimiento`, `peso`, `sexo`, `color`, `estado`, `url_foto`, `patron`) VALUES
(1, 'HC-1-2026', '1080361993', 1, 12, 'Manolito jr', '2025-01-10', 1.50, 'Macho', '', 1, '1778460528_Manolito_jr.jpg', 'Bicolor'),
(2, '', '1080361992', 1, 12, 'max', '2025-02-18', 4.00, 'Macho', '', 1, NULL, 'Bicolor'),
(3, '', '12345678', 2, 27, 'Mina', '2024-01-10', 4.00, 'Hembra', '', 1, NULL, 'Bicolor'),
(4, '', '1080361993', 1, 12, 'toby', '2020-12-01', 1.50, 'Macho', '', 1, '1779228985_toby.jpg', 'Sólido'),
(5, 'HC-5-2026', '1080361993', 3, 33, 'Santi jr', '2008-04-14', 53.00, 'Desconocido', '', 1, '1779401973_Santi_jr.jpg', 'Sólido'),
(6, '', '111111777', 1, 48, 'Lucas', '2023-09-21', 3.00, 'Macho', '', 1, '1779402887_Lucas.jpg', 'Sólido'),
(7, '', '1080361993', 1, 12, 'Raton quesuno', '2016-12-08', 7.00, 'Macho', '', 1, NULL, 'Sólido');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mascota_colores`
--

CREATE TABLE `mascota_colores` (
  `id_mascota` int(11) NOT NULL,
  `id_color` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `mascota_colores`
--

INSERT INTO `mascota_colores` (`id_mascota`, `id_color`) VALUES
(1, 3),
(1, 6),
(2, 1),
(2, 3),
(3, 1),
(3, 4),
(4, 1),
(4, 3),
(5, 1),
(5, 2),
(6, 3),
(7, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id_notificacion` int(11) NOT NULL,
  `doc_propietario` varchar(20) NOT NULL,
  `tipo_entidad` varchar(50) NOT NULL,
  `id_entidad` int(11) NOT NULL,
  `destinatario_email` varchar(255) NOT NULL,
  `tipo_notificacion` varchar(50) NOT NULL,
  `asunto` varchar(255) DEFAULT NULL,
  `mensaje` text DEFAULT NULL,
  `fecha_envio` datetime DEFAULT current_timestamp(),
  `estado` enum('pendiente','enviado','error') DEFAULT 'pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `notificaciones`
--

INSERT INTO `notificaciones` (`id_notificacion`, `doc_propietario`, `tipo_entidad`, `id_entidad`, `destinatario_email`, `tipo_notificacion`, `asunto`, `mensaje`, `fecha_envio`, `estado`) VALUES
(1, '1080361992', 'vacuna', 2, 'Sebastian.liqsy@gmail.com', 'recordatorio_1_dia', 'Recordatorio de Vacunación: max', 'Cuerpo del correo omitido por espacio...', '2026-05-10 23:30:02', 'enviado'),
(2, '12345678', 'vacuna', 3, 'juancarlosquesadaome@gmail.com', 'recordatorio_1_dia', 'Recordatorio de Vacunación: Mina', 'Cuerpo del correo omitido por espacio...', '2026-05-10 23:33:04', 'enviado');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones_internas`
--

CREATE TABLE `notificaciones_internas` (
  `id` int(11) NOT NULL,
  `doc_usuario` varchar(20) DEFAULT NULL,
  `id_rol_destino` int(11) DEFAULT NULL,
  `tipo` varchar(50) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `mensaje` text NOT NULL,
  `enlace` varchar(255) DEFAULT NULL,
  `leida` tinyint(1) DEFAULT 0,
  `fecha_creacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `usuario_documento` varchar(20) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `password_resets`
--

INSERT INTO `password_resets` (`id`, `usuario_documento`, `email`, `token_hash`, `expires_at`, `used`, `created_at`) VALUES
(1, '1080361991', 'carvajal7lsch@gmail.com', '$2y$10$9tQRK8ENiFLSruJ0tkTgFOL7qMubWRAAyv0pM50hg2G4qvLrs/rCK', '2026-06-04 01:00:52', 0, '2026-06-03 17:00:52');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos_desparasitacion_base`
--

CREATE TABLE `productos_desparasitacion_base` (
  `id_producto` int(11) NOT NULL,
  `nombre_producto` varchar(150) NOT NULL,
  `tipo` enum('interna','externa','ambas') DEFAULT 'interna',
  `estado` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos_desparasitacion_base`
--

INSERT INTO `productos_desparasitacion_base` (`id_producto`, `nombre_producto`, `tipo`, `estado`) VALUES
(1, 'Ivermectina', 'interna', 1),
(2, 'Fenbendazol', 'interna', 1),
(3, 'Praziquantel', 'interna', 1),
(4, 'Pyrantel', 'interna', 1),
(5, 'Milbemycina', 'interna', 1),
(6, 'Selamectina', 'externa', 1),
(7, 'Fipronil', 'externa', 1),
(8, 'Imidacloprid', 'externa', 1),
(9, 'Permetrina', 'externa', 1),
(10, 'Deltametrina', 'externa', 1),
(11, 'Afoxolaner', 'ambas', 1),
(12, 'Fluralaner', 'ambas', 1),
(13, 'Sarolaner', 'ambas', 1),
(14, 'Lufenuron', 'interna', 1),
(15, 'Nitenpyram', 'externa', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `razas`
--

CREATE TABLE `razas` (
  `id_raza` int(11) NOT NULL,
  `id_especie` int(11) NOT NULL,
  `nombre_raza` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `razas`
--

INSERT INTO `razas` (`id_raza`, `id_especie`, `nombre_raza`) VALUES
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
(48, 1, 'Pitbull');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL,
  `nombre_rol` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre_rol`) VALUES
(1, 'administrador'),
(4, 'propietario'),
(3, 'recepcionista'),
(2, 'veterinario');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_cita`
--

CREATE TABLE `tipos_cita` (
  `id_tipo_cita` int(11) NOT NULL,
  `nombre_tipo` varchar(100) NOT NULL,
  `duracion_minutos` int(11) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `color` varchar(20) DEFAULT '#0C66E4',
  `activo` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tipos_cita`
--

INSERT INTO `tipos_cita` (`id_tipo_cita`, `nombre_tipo`, `duracion_minutos`, `descripcion`, `color`, `activo`) VALUES
(1, 'Vacunación/Revisión rápida', 15, 'Vacunación rutinaria o revisión rápida de la mascota', '#10B981', 1),
(2, 'Consulta general/Enfermedad', 30, 'Consulta general por enfermedad o chequeo completo', '#0C66E4', 1),
(3, 'Primera visita/Mascota nueva', 45, 'Primera visita para mascota nueva, requiere historia clínica completa', '#8B5CF6', 1),
(4, 'Consulta de especialidad/Urgencia', 60, 'Consulta de especialidad o urgencia veterinaria', '#EF4444', 1),
(5, 'Desparasitación', 15, 'Aplicación de desparasitante interna o externa', '#F59E0B', 1),
(6, 'Control post-operatorio', 20, 'Control después de cirugía o procedimiento', '#6366F1', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tratamientos`
--

CREATE TABLE `tratamientos` (
  `id_tratamiento` int(11) NOT NULL,
  `id_consulta` int(11) NOT NULL,
  `medicamento` varchar(255) NOT NULL,
  `dosis` varchar(255) NOT NULL,
  `via_administracion` varchar(255) NOT NULL,
  `duracion` varchar(100) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `documento` varchar(20) NOT NULL,
  `tipo_documento` varchar(20) DEFAULT NULL,
  `nombre_completo` varchar(200) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `password_definida` tinyint(1) NOT NULL DEFAULT 1,
  `id_rol` int(11) DEFAULT NULL,
  `estado` tinyint(4) DEFAULT 1,
  `debe_cambiar_password` tinyint(1) DEFAULT 0,
  `fecha_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`documento`, `tipo_documento`, `nombre_completo`, `telefono`, `email`, `password`, `id_rol`, `estado`, `debe_cambiar_password`, `fecha_registro`) VALUES
('012345', 'CC', 'Luisa Fernanda', '3115265529', 'Luisa@gmail.com', '$2y$10$f1oudm57dTPbF6TZ7AYXsu.hxGLPXWg7pZIiQIf5ty90PY8jSgKne', 2, 1, 0, '2026-05-25 00:13:28'),
('0123456', 'CC', 'Pepo Juarez', '3115265529', 'juan.carvajal0767@gmail.com', '$2y$10$AGDVjvQK.6P7J8dt2AkKaeot.CFC1Kq5MYMm0SpU.JgMCLNUv7y3S', 2, 1, 0, '2026-05-25 10:01:36'),
('1080361991', 'CC', 'Sebastian Carvajal ', '3115265529', 'carvajal7lsch@gmail.com', '$2y$10$cbqvAu95fGKjzbAn/fdbNeaj8g0CENmwjBJHMmSOx3Kl74AKHhKbm', 1, 1, 0, '2026-05-08 13:34:21'),
('1080361992', 'CC', 'Juan Sebastian Carvajal', '3115265529', 'Sebastian.liqsy@gmail.com', '$2y$10$TitQEUKQYhtqVBVx2.9Hlu3QtOVFqAzUKEqQYu/f3LbeB4MLBbqgW', 4, 1, 0, '2026-05-08 15:14:28'),
('1080361993', 'CC', 'Manuel Cardenas', '320 9891830', 'manuel@gmail.com', '$2y$10$nShTW5QTvAFxyAe1X3emUuMXZ.4zeSGYDzZIxOTXmV7hUQ0rK7AZK', 4, 1, 0, '2026-05-08 17:26:38'),
('1080361997', 'CC', 'Cristian GPT', '+573206034364', 'cristi@gmail.com', '$2y$10$iOqJ391m6mjedXH0dtdD8uB0I/15AKLKTrnX2VV.J8ZQufLKz0Sey', 2, 1, 1, '2026-06-21 18:49:05'),
('111111777', 'TI', 'Santiago Lizcano', '314345434543434', 'santilizcanoaliasbombi@gmail.com', '$2y$10$.dOM2D8Q5AyX3HQ6VZtZDetcHbkcBmDBesShMmB5wz.8Lh4LaZWiW', 4, 1, 0, '2026-05-21 17:33:26'),
('11178583838', 'CC', 'Juan perez', '+573115265529', 'carvajal.2@gmail.com', '$2y$10$irJxkTwZ3oGMHBUYwfhiCO31tZQejyELwQsnXnE7M8LXNN0M9uLG2', 1, 0, 0, '2026-05-14 15:47:01'),
('12345', 'CC', 'Maria Lopez', '+573434343434', 'maria.vet@zooki.com', '$2y$10$WWH3I5RFLApvKI1P5515du3m/DjJgOzHuW19Hb98yZoqxoP8jh2IW', 2, 1, 0, '2026-05-08 13:34:22'),
('123456', 'CC', 'Carlos Ruiz', '3205554433', 'carlos@propietario.com', '$2y$10$YizKkjU9.KR1gRvoLm0feeKBNa9pu.l3n3qB7yKRWMtkVgj3YVeNe', 4, 1, 0, '2026-05-08 13:34:22'),
('1234567', 'CC', 'Verita', '3115265529', 'carvajal7lsckh@gmail.com', '$2y$10$Ik78R2oKzLMQ8OIwBpREmOfOijvKVOPmMaI2SO57QUZBOWCcOa1Tu', 2, 1, 0, '2026-05-22 00:04:40'),
('12345678', 'CC', 'Juan Carlos', '3118260008', 'juancarlosquesadaome@gmail.com', '$2y$10$77qzo/bSHhSH/do/01tXxOMU/eg7THj6J36IYMXiekmwfZtvgkyVe', 4, 1, 0, '2026-05-10 23:31:21');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vacunas`
--

CREATE TABLE `vacunas` (
  `id_vacuna` int(11) NOT NULL,
  `id_mascota` int(11) NOT NULL,
  `nombre_vacuna` varchar(150) NOT NULL,
  `laboratorio` varchar(150) DEFAULT NULL,
  `lote` varchar(100) DEFAULT NULL,
  `fecha_aplicacion` date NOT NULL,
  `fecha_proxima_dosis` date DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `vacunas`
--

INSERT INTO `vacunas` (`id_vacuna`, `id_mascota`, `nombre_vacuna`, `laboratorio`, `lote`, `fecha_aplicacion`, `fecha_proxima_dosis`, `observaciones`, `fecha_registro`) VALUES
(1, 1, 'Rabia', 'zinqui', '044', '2026-05-11', '2026-05-12', '', '2026-05-10 22:48:31'),
(2, 2, 'Rabia', 'MSD', '044', '2026-05-11', '2026-05-11', 'Prueba test', '2026-05-10 23:27:29'),
(3, 3, 'Fiebre amarilla', 'MSD', '044', '2026-05-11', '2026-05-11', 'GATA TRIPLE HP', '2026-05-10 23:32:52'),
(4, 1, 'Leptospirosis', 'Bayer', '044', '2026-05-22', '2026-06-22', '', '2026-05-22 02:09:32'),
(5, 4, 'Coronavirus', 'Ceva', '044', '2026-05-23', '2026-06-22', '', '2026-05-22 02:13:36');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vacunas_base`
--

CREATE TABLE `vacunas_base` (
  `id_vacuna_base` int(11) NOT NULL,
  `nombre_vacuna` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `vacunas_base`
--

INSERT INTO `vacunas_base` (`id_vacuna_base`, `nombre_vacuna`, `descripcion`, `estado`) VALUES
(1, 'Pentavalente', 'Protege contra Distemper, Hepatitis, Parvovirus, Parainfluenza y Leptospirosis', 1),
(2, 'Séxtuple', 'Pentavalente más Coronavirus', 1),
(3, 'Rabia', 'Vacuna antirrábica obligatoria', 1),
(4, 'Parvovirus', 'Protege contra Parvovirus canino', 1),
(5, 'Distemper', 'Protege contra Moquillo canino', 1),
(6, 'Hepatitis Infecciosa', 'Protege contra Hepatitis infecciosa canina', 1),
(7, 'Leptospirosis', 'Protege contra Leptospirosis', 1),
(8, 'Parainfluenza', 'Protege contra Parainfluenza canina', 1),
(9, 'Coronavirus', 'Protege contra Coronavirus canino', 1),
(10, 'Triple Felina', 'Protege contra Panleucopenia, Rinotraqueítis y Calicivirus', 1),
(11, 'Leucemia Felina', 'Protege contra Leucemia viral felina', 1),
(12, 'Clamidiosis', 'Protege contra Clamidiosis felina', 1),
(13, 'Bordetella', 'Protege contra Tos de las perreras', 1),
(14, 'Lyme', 'Protege contra Enfermedad de Lyme', 1),
(15, 'Otra', 'Otra vacuna no listada', 1);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `archivos_clinicos`
--
ALTER TABLE `archivos_clinicos`
  ADD PRIMARY KEY (`id_archivo`),
  ADD KEY `idx_archivos_clinicos_consulta` (`id_consulta`);

--
-- Indices de la tabla `auditoria_mascotas`
--
ALTER TABLE `auditoria_mascotas`
  ADD PRIMARY KEY (`id_auditoria`),
  ADD KEY `id_mascota` (`id_mascota`),
  ADD KEY `usuario_doc` (`usuario_doc`);

--
-- Indices de la tabla `auditoria_sistema`
--
ALTER TABLE `auditoria_sistema`
  ADD PRIMARY KEY (`id_auditoria`),
  ADD KEY `idx_auditoria_usuario` (`usuario_doc`),
  ADD KEY `idx_auditoria_fecha` (`fecha_hora`),
  ADD KEY `idx_auditoria_accion` (`accion`);

--
-- Indices de la tabla `citas`
--
ALTER TABLE `citas`
  ADD PRIMARY KEY (`id_cita`),
  -- Solo las citas activas ocupan el horario: slot_activo es NULL en las
  -- canceladas y en "no asistió", y un índice único admite varios NULL.
  ADD UNIQUE KEY `uq_cita_vet_activa` (`doc_veterinario`,`fecha`,`hora`,`slot_activo`),
  ADD KEY `id_mascota` (`id_mascota`),
  ADD KEY `idx_tipo_cita` (`id_tipo_cita`),
  ADD KEY `idx_veterinario_fecha_hora` (`doc_veterinario`,`fecha`,`hora`),
  ADD KEY `idx_fecha_estado` (`fecha`,`estado`);

--
-- Indices de la tabla `colores_base`
--
ALTER TABLE `colores_base`
  ADD PRIMARY KEY (`id_color`);

--
-- Indices de la tabla `consultas`
--
ALTER TABLE `consultas`
  ADD PRIMARY KEY (`id_consulta`),
  ADD UNIQUE KEY `uq_consulta_cita` (`id_cita`),
  ADD KEY `doc_veterinario` (`doc_veterinario`),
  ADD KEY `idx_consultas_mascota` (`id_mascota`),
  ADD KEY `idx_consultas_fecha` (`fecha_hora`);

--
-- Indices de la tabla `desparasitaciones`
--
ALTER TABLE `desparasitaciones`
  ADD PRIMARY KEY (`id_desparasitacion`),
  ADD KEY `id_mascota` (`id_mascota`);

--
-- Indices de la tabla `especies`
--
ALTER TABLE `especies`
  ADD PRIMARY KEY (`id_especie`);

--
-- Indices de la tabla `especie_vacunas`
--
ALTER TABLE `especie_vacunas`
  ADD PRIMARY KEY (`id_especie_vacuna`),
  ADD UNIQUE KEY `unique_especie_vacuna` (`id_especie`,`id_vacuna_base`),
  ADD KEY `fk_especie_vacunas_vacuna` (`id_vacuna_base`);

--
-- Indices de la tabla `horarios_clinica`
--
ALTER TABLE `horarios_clinica`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_dia_semana` (`dia_semana`);

--
-- Indices de la tabla `laboratorios_base`
--
ALTER TABLE `laboratorios_base`
  ADD PRIMARY KEY (`id_laboratorio`);

--
-- Indices de la tabla `mascotas`
--
ALTER TABLE `mascotas`
  ADD PRIMARY KEY (`id_mascota`),
  ADD KEY `doc_propietario` (`doc_propietario`),
  ADD KEY `fk_mascota_especie` (`id_especie`),
  ADD KEY `fk_mascota_raza` (`id_raza`);

--
-- Indices de la tabla `mascota_colores`
--
ALTER TABLE `mascota_colores`
  ADD PRIMARY KEY (`id_mascota`,`id_color`),
  ADD KEY `fk_mascota_colores_color` (`id_color`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id_notificacion`),
  ADD KEY `doc_propietario` (`doc_propietario`);

--
-- Indices de la tabla `notificaciones_internas`
--
ALTER TABLE `notificaciones_internas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_noti_usr` (`doc_usuario`),
  ADD KEY `fk_noti_rol` (`id_rol_destino`);

--
-- Indices de la tabla `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_password_resets_email` (`email`),
  ADD KEY `idx_password_resets_documento` (`usuario_documento`);

--
-- Indices de la tabla `productos_desparasitacion_base`
--
ALTER TABLE `productos_desparasitacion_base`
  ADD PRIMARY KEY (`id_producto`);

--
-- Indices de la tabla `razas`
--
ALTER TABLE `razas`
  ADD PRIMARY KEY (`id_raza`),
  ADD KEY `id_especie` (`id_especie`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `nombre_rol` (`nombre_rol`);

--
-- Indices de la tabla `tipos_cita`
--
ALTER TABLE `tipos_cita`
  ADD PRIMARY KEY (`id_tipo_cita`);

--
-- Indices de la tabla `tratamientos`
--
ALTER TABLE `tratamientos`
  ADD PRIMARY KEY (`id_tratamiento`),
  ADD KEY `idx_tratamientos_consulta` (`id_consulta`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`documento`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_usuario_rol` (`id_rol`);

--
-- Indices de la tabla `vacunas`
--
ALTER TABLE `vacunas`
  ADD PRIMARY KEY (`id_vacuna`),
  ADD KEY `idx_vacunas_mascota` (`id_mascota`);

--
-- Indices de la tabla `vacunas_base`
--
ALTER TABLE `vacunas_base`
  ADD PRIMARY KEY (`id_vacuna_base`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `archivos_clinicos`
--
ALTER TABLE `archivos_clinicos`
  MODIFY `id_archivo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `auditoria_mascotas`
--
ALTER TABLE `auditoria_mascotas`
  MODIFY `id_auditoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `auditoria_sistema`
--
ALTER TABLE `auditoria_sistema`
  MODIFY `id_auditoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=189;

--
-- AUTO_INCREMENT de la tabla `citas`
--
ALTER TABLE `citas`
  MODIFY `id_cita` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `colores_base`
--
ALTER TABLE `colores_base`
  MODIFY `id_color` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `consultas`
--
ALTER TABLE `consultas`
  MODIFY `id_consulta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `desparasitaciones`
--
ALTER TABLE `desparasitaciones`
  MODIFY `id_desparasitacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `especies`
--
ALTER TABLE `especies`
  MODIFY `id_especie` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `especie_vacunas`
--
ALTER TABLE `especie_vacunas`
  MODIFY `id_especie_vacuna` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `horarios_clinica`
--
ALTER TABLE `horarios_clinica`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `laboratorios_base`
--
ALTER TABLE `laboratorios_base`
  MODIFY `id_laboratorio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `mascotas`
--
ALTER TABLE `mascotas`
  MODIFY `id_mascota` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id_notificacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `notificaciones_internas`
--
ALTER TABLE `notificaciones_internas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `productos_desparasitacion_base`
--
ALTER TABLE `productos_desparasitacion_base`
  MODIFY `id_producto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `razas`
--
ALTER TABLE `razas`
  MODIFY `id_raza` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `tipos_cita`
--
ALTER TABLE `tipos_cita`
  MODIFY `id_tipo_cita` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT de la tabla `tratamientos`
--
ALTER TABLE `tratamientos`
  MODIFY `id_tratamiento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `vacunas`
--
ALTER TABLE `vacunas`
  MODIFY `id_vacuna` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `vacunas_base`
--
ALTER TABLE `vacunas_base`
  MODIFY `id_vacuna_base` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `archivos_clinicos`
--
ALTER TABLE `archivos_clinicos`
  ADD CONSTRAINT `archivos_clinicos_ibfk_1` FOREIGN KEY (`id_consulta`) REFERENCES `consultas` (`id_consulta`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `auditoria_mascotas`
--
ALTER TABLE `auditoria_mascotas`
  ADD CONSTRAINT `auditoria_mascotas_ibfk_1` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`),
  ADD CONSTRAINT `auditoria_mascotas_ibfk_2` FOREIGN KEY (`usuario_doc`) REFERENCES `usuarios` (`documento`);

--
-- Filtros para la tabla `citas`
--
ALTER TABLE `citas`
  ADD CONSTRAINT `citas_ibfk_1` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `citas_ibfk_2` FOREIGN KEY (`doc_veterinario`) REFERENCES `usuarios` (`documento`);

--
-- Filtros para la tabla `consultas`
--
ALTER TABLE `consultas`
  ADD CONSTRAINT `consultas_ibfk_1` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `consultas_ibfk_2` FOREIGN KEY (`doc_veterinario`) REFERENCES `usuarios` (`documento`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `consultas_ibfk_3` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id_cita`) ON DELETE SET NULL;

--
-- Filtros para la tabla `desparasitaciones`
--
ALTER TABLE `desparasitaciones`
  ADD CONSTRAINT `desparasitaciones_ibfk_1` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `especie_vacunas`
--
ALTER TABLE `especie_vacunas`
  ADD CONSTRAINT `fk_especie_vacunas_especie` FOREIGN KEY (`id_especie`) REFERENCES `especies` (`id_especie`),
  ADD CONSTRAINT `fk_especie_vacunas_vacuna` FOREIGN KEY (`id_vacuna_base`) REFERENCES `vacunas_base` (`id_vacuna_base`);

--
-- Filtros para la tabla `mascotas`
--
ALTER TABLE `mascotas`
  ADD CONSTRAINT `fk_mascota_especie` FOREIGN KEY (`id_especie`) REFERENCES `especies` (`id_especie`),
  ADD CONSTRAINT `fk_mascota_raza` FOREIGN KEY (`id_raza`) REFERENCES `razas` (`id_raza`),
  ADD CONSTRAINT `mascotas_ibfk_1` FOREIGN KEY (`doc_propietario`) REFERENCES `usuarios` (`documento`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `mascota_colores`
--
ALTER TABLE `mascota_colores`
  ADD CONSTRAINT `fk_mascota_colores_color` FOREIGN KEY (`id_color`) REFERENCES `colores_base` (`id_color`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mascota_colores_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`doc_propietario`) REFERENCES `usuarios` (`documento`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `notificaciones_internas`
--
ALTER TABLE `notificaciones_internas`
  ADD CONSTRAINT `fk_noti_rol` FOREIGN KEY (`id_rol_destino`) REFERENCES `roles` (`id_rol`),
  ADD CONSTRAINT `fk_noti_usr` FOREIGN KEY (`doc_usuario`) REFERENCES `usuarios` (`documento`);

--
-- Filtros para la tabla `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_password_resets_usuario` FOREIGN KEY (`usuario_documento`) REFERENCES `usuarios` (`documento`);

--
-- Filtros para la tabla `razas`
--
ALTER TABLE `razas`
  ADD CONSTRAINT `razas_ibfk_1` FOREIGN KEY (`id_especie`) REFERENCES `especies` (`id_especie`);

--
-- Filtros para la tabla `tratamientos`
--
ALTER TABLE `tratamientos`
  ADD CONSTRAINT `tratamientos_ibfk_1` FOREIGN KEY (`id_consulta`) REFERENCES `consultas` (`id_consulta`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`);

--
-- Filtros para la tabla `vacunas`
--
ALTER TABLE `vacunas`
  ADD CONSTRAINT `vacunas_ibfk_1` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`) ON DELETE NO ACTION ON UPDATE NO ACTION;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
