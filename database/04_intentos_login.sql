-- ---------------------------------------------------------------------------
-- HU-38 (VD-SEG-05) — Almacen server-side para el limite de intentos.
--
-- Antes el contador vivia en $_SESSION, asi que bastaba con no enviar la
-- cookie de sesion para que cada intento arrancara de cero: el limite de 5
-- intentos era inexistente para cualquier script. Al moverlo a la base de
-- datos, el contador deja de depender de algo que controla el cliente.
--
-- Se puede ejecutar varias veces sin romper nada (IF NOT EXISTS).
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `intentos_login` (
  `id_intento` int(11) NOT NULL AUTO_INCREMENT,
  -- Clave del contador. Lleva prefijo para no mezclar espacios de nombres:
  --   'ip:186.30.x.x'   limite por origen de la peticion
  --   'cuenta:12345678' limite por documento, sobrevive al cambio de IP
  --   'chk:186.30.x.x'  limite de las verificaciones de documento/correo
  `identificador` varchar(120) NOT NULL,
  `intentos` int(11) NOT NULL DEFAULT 0,
  `bloqueado_hasta` datetime DEFAULT NULL,
  `primer_intento` datetime NOT NULL,
  `ultimo_intento` datetime NOT NULL,
  PRIMARY KEY (`id_intento`),
  UNIQUE KEY `uq_intentos_identificador` (`identificador`),
  KEY `idx_intentos_ultimo` (`ultimo_intento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
