CREATE TABLE `archivos_clinicos` (
  `id_archivo` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_consulta` int(11) NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `nombre_servidor` varchar(255) NOT NULL,
  `ruta_archivo` varchar(255) NOT NULL,
  `tipo_archivo` varchar(255) NOT NULL,
  `extension` varchar(20) NOT NULL,
  `tamano_bytes` int(11) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `fecha_subida` datetime DEFAULT current_timestamp()
);

CREATE TABLE `auditoria_mascotas` (
  `id_auditoria` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_mascota` int(11) NOT NULL,
  `usuario_doc` varchar(20) NOT NULL,
  `campo_modificado` varchar(100) DEFAULT NULL,
  `valor_anterior` text DEFAULT NULL,
  `valor_nuevo` text DEFAULT NULL,
  `fecha_cambio` datetime DEFAULT current_timestamp()
);

CREATE TABLE `auditoria_sistema` (
  `id_auditoria` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `usuario_doc` varchar(20) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `fecha_hora` datetime DEFAULT current_timestamp(),
  `accion` enum('LOGIN','LOGIN_FAIL','LOGOUT','INSERT','UPDATE','DELETE','VIEW','OTHER') NOT NULL,
  `tabla_afectada` varchar(50) DEFAULT NULL,
  `registro_id` varchar(50) DEFAULT NULL,
  `datos_anteriores` longtext DEFAULT NULL,
  `datos_nuevos` longtext DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL
);

CREATE TABLE `citas` (
  `id_cita` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_mascota` int(11) NOT NULL,
  `doc_veterinario` varchar(20) NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `hora_fin` time DEFAULT NULL,
  `motivo` varchar(255) NOT NULL,
  `id_tipo_cita` int(11) DEFAULT NULL,
  `duracion_minutos` int(11) DEFAULT NULL,
  `estado` enum('pendiente','confirmada','cancelada','completada') DEFAULT 'pendiente',
  `observaciones` text DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp()
);

CREATE TABLE `colores_base` (
  `id_color` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `nombre_color` varchar(30) NOT NULL
);

CREATE TABLE `consultas` (
  `id_consulta` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_cita` int(11) DEFAULT NULL,
  `id_mascota` int(11) NOT NULL,
  `doc_veterinario` varchar(20) NOT NULL,
  `fecha_hora` datetime NOT NULL,
  `motivo_consulta` text NOT NULL,
  `anamnesis` text NOT NULL,
  `peso` decimal(5,2) DEFAULT NULL,
  `temperatura` decimal(4,1) DEFAULT NULL,
  `frecuencia_cardiaca` int(11) DEFAULT NULL,
  `diagnostico` text NOT NULL,
  `plan_tratamiento` text NOT NULL
);

CREATE TABLE `desparasitaciones` (
  `id_desparasitacion` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_mascota` int(11) NOT NULL,
  `tipo` enum('interna','externa') NOT NULL,
  `producto` varchar(150) NOT NULL,
  `periodicidad` enum('mensual','trimestral','semestral') NOT NULL,
  `fecha_aplicacion` date NOT NULL,
  `fecha_proxima` date NOT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp()
);

CREATE TABLE `especies` (
  `id_especie` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `nombre_especie` varchar(50) NOT NULL
);

CREATE TABLE `especie_vacunas` (
  `id_especie_vacuna` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_especie` int(11) NOT NULL,
  `id_vacuna_base` int(11) NOT NULL
);

CREATE TABLE `horarios_clinica` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `dia_semana` int(11) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `bloque_morning_activo` tinyint(1) NOT NULL DEFAULT 1,
  `bloque_afternoon_activo` tinyint(1) NOT NULL DEFAULT 1,
  `bloque_morning_inicio` time DEFAULT NULL,
  `bloque_morning_fin` time DEFAULT NULL,
  `bloque_afternoon_inicio` time DEFAULT NULL,
  `bloque_afternoon_fin` time DEFAULT NULL
);

CREATE TABLE `laboratorios_base` (
  `id_laboratorio` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `nombre_laboratorio` varchar(150) NOT NULL,
  `estado` tinyint(4) DEFAULT 1
);

CREATE TABLE `mascotas` (
  `id_mascota` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
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
);

CREATE TABLE `mascota_colores` (
  `id_mascota` int(11) NOT NULL,
  `id_color` int(11) NOT NULL,
  PRIMARY KEY (`id_mascota`, `id_color`)
);

CREATE TABLE `notificaciones` (
  `id_notificacion` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `doc_propietario` varchar(20) NOT NULL,
  `tipo_entidad` varchar(50) NOT NULL,
  `id_entidad` int(11) NOT NULL,
  `destinatario_email` varchar(255) NOT NULL,
  `tipo_notificacion` varchar(50) NOT NULL,
  `asunto` varchar(255) DEFAULT NULL,
  `mensaje` text DEFAULT NULL,
  `fecha_envio` datetime DEFAULT current_timestamp(),
  `estado` enum('pendiente','enviado','error') DEFAULT 'pendiente'
);

CREATE TABLE `notificaciones_internas` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `doc_usuario` varchar(20) DEFAULT NULL,
  `id_rol_destino` int(11) DEFAULT NULL,
  `tipo` varchar(50) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `mensaje` text NOT NULL,
  `enlace` varchar(255) DEFAULT NULL,
  `leida` tinyint(1) DEFAULT 0,
  `fecha_creacion` datetime DEFAULT current_timestamp()
);

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `usuario_documento` varchar(20) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
);

CREATE TABLE `productos_desparasitacion_base` (
  `id_producto` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `nombre_producto` varchar(150) NOT NULL,
  `tipo` enum('interna','externa','ambas') DEFAULT 'interna',
  `estado` tinyint(4) DEFAULT 1
);

CREATE TABLE `razas` (
  `id_raza` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_especie` int(11) NOT NULL,
  `nombre_raza` varchar(50) NOT NULL
);

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `nombre_rol` varchar(50) NOT NULL
);

CREATE TABLE `tipos_cita` (
  `id_tipo_cita` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `nombre_tipo` varchar(100) NOT NULL,
  `duracion_minutos` int(11) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `color` varchar(20) DEFAULT '#0C66E4',
  `activo` tinyint(4) DEFAULT 1
);

CREATE TABLE `tratamientos` (
  `id_tratamiento` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_consulta` int(11) NOT NULL,
  `medicamento` varchar(255) NOT NULL,
  `dosis` varchar(255) NOT NULL,
  `via_administracion` varchar(255) NOT NULL,
  `duracion` varchar(100) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp()
);

CREATE TABLE `usuarios` (
  `documento` varchar(20) NOT NULL PRIMARY KEY,
  `tipo_documento` varchar(20) DEFAULT NULL,
  `nombre_completo` varchar(200) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `id_rol` int(11) DEFAULT NULL,
  `estado` tinyint(4) DEFAULT 1,
  `debe_cambiar_password` tinyint(1) DEFAULT 0,
  `fecha_registro` datetime DEFAULT current_timestamp()
);

CREATE TABLE `vacunas` (
  `id_vacuna` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_mascota` int(11) NOT NULL,
  `nombre_vacuna` varchar(150) NOT NULL,
  `laboratorio` varchar(150) DEFAULT NULL,
  `lote` varchar(100) DEFAULT NULL,
  `fecha_aplicacion` date NOT NULL,
  `fecha_proxima_dosis` date DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp()
);

CREATE TABLE `vacunas_base` (
  `id_vacuna_base` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `nombre_vacuna` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` tinyint(4) DEFAULT 1
);

ALTER TABLE `archivos_clinicos`
  ADD CONSTRAINT `archivos_clinicos_ibfk_1` FOREIGN KEY (`id_consulta`) REFERENCES `consultas` (`id_consulta`) ON DELETE NO ACTION ON UPDATE NO ACTION;
ALTER TABLE `auditoria_mascotas`
  ADD CONSTRAINT `auditoria_mascotas_ibfk_1` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`),
  ADD CONSTRAINT `auditoria_mascotas_ibfk_2` FOREIGN KEY (`usuario_doc`) REFERENCES `usuarios` (`documento`);
ALTER TABLE `citas`
  ADD CONSTRAINT `citas_ibfk_1` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `citas_ibfk_2` FOREIGN KEY (`doc_veterinario`) REFERENCES `usuarios` (`documento`);
ALTER TABLE `consultas`
  ADD CONSTRAINT `consultas_ibfk_1` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `consultas_ibfk_2` FOREIGN KEY (`doc_veterinario`) REFERENCES `usuarios` (`documento`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `consultas_ibfk_3` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id_cita`) ON DELETE SET NULL;
ALTER TABLE `desparasitaciones`
  ADD CONSTRAINT `desparasitaciones_ibfk_1` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`) ON DELETE NO ACTION ON UPDATE NO ACTION;
ALTER TABLE `especie_vacunas`
  ADD CONSTRAINT `fk_especie_vacunas_especie` FOREIGN KEY (`id_especie`) REFERENCES `especies` (`id_especie`),
  ADD CONSTRAINT `fk_especie_vacunas_vacuna` FOREIGN KEY (`id_vacuna_base`) REFERENCES `vacunas_base` (`id_vacuna_base`);
ALTER TABLE `mascotas`
  ADD CONSTRAINT `fk_mascota_especie` FOREIGN KEY (`id_especie`) REFERENCES `especies` (`id_especie`),
  ADD CONSTRAINT `fk_mascota_raza` FOREIGN KEY (`id_raza`) REFERENCES `razas` (`id_raza`),
  ADD CONSTRAINT `mascotas_ibfk_1` FOREIGN KEY (`doc_propietario`) REFERENCES `usuarios` (`documento`) ON DELETE NO ACTION ON UPDATE NO ACTION;
ALTER TABLE `mascota_colores`
  ADD CONSTRAINT `fk_mascota_colores_color` FOREIGN KEY (`id_color`) REFERENCES `colores_base` (`id_color`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mascota_colores_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`doc_propietario`) REFERENCES `usuarios` (`documento`) ON DELETE NO ACTION ON UPDATE NO ACTION;
ALTER TABLE `notificaciones_internas`
  ADD CONSTRAINT `fk_noti_rol` FOREIGN KEY (`id_rol_destino`) REFERENCES `roles` (`id_rol`),
  ADD CONSTRAINT `fk_noti_usr` FOREIGN KEY (`doc_usuario`) REFERENCES `usuarios` (`documento`);
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_password_resets_usuario` FOREIGN KEY (`usuario_documento`) REFERENCES `usuarios` (`documento`);
ALTER TABLE `razas`
  ADD CONSTRAINT `razas_ibfk_1` FOREIGN KEY (`id_especie`) REFERENCES `especies` (`id_especie`);
ALTER TABLE `tratamientos`
  ADD CONSTRAINT `tratamientos_ibfk_1` FOREIGN KEY (`id_consulta`) REFERENCES `consultas` (`id_consulta`) ON DELETE NO ACTION ON UPDATE NO ACTION;
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`);
ALTER TABLE `vacunas`
  ADD CONSTRAINT `vacunas_ibfk_1` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`) ON DELETE NO ACTION ON UPDATE NO ACTION;