-- ---------------------------------------------------------------------------
-- HU-36 (VD-SEG-08) — Verificacion de correo en el auto-registro.
--
-- Antes, `process_register` creaba la cuenta con estado = 1 y ademas iniciaba
-- sesion automaticamente: cualquiera podia registrarse con el correo de otra
-- persona y quedar dentro del sistema sin demostrar que ese buzon era suyo.
--
-- Se resolvio con una tabla aparte y NO con una columna nueva en `usuarios`,
-- a proposito: un usuario se considera pendiente solo si tiene una fila con
-- used = 0. Los usuarios que ya existen no tienen filas, asi que quedan
-- verificados sin necesidad de migrar datos, y si esta tabla llegara a faltar
-- el sistema no bloquea a nadie en vez de dejar a todos fuera.
--
-- Se puede ejecutar varias veces sin romper nada (IF NOT EXISTS).
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `verificaciones_email` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_documento` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_verif_documento` (`usuario_documento`),
  KEY `idx_verif_pendiente` (`usuario_documento`, `used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
