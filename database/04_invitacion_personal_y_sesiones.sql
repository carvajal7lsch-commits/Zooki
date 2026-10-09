SET NAMES utf8mb4;
-- D2.2 (RE-T.7.5, RN-705, RN-G06): la invitación a una persona que ya tiene
-- cuenta guarda el rol que se le ofrece y los datos que escribió el
-- administrador. La lista de la clínica muestra esos datos, nunca los de la
-- cuenta, hasta que el titular acepta: así no se revela si la cuenta existe.
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'verificaciones_email' AND COLUMN_NAME = 'id_rol_vinculo'), 'SELECT 1', 'ALTER TABLE verificaciones_email ADD id_rol_vinculo INT DEFAULT NULL AFTER id_clinica_vinculo');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'verificaciones_email' AND CONSTRAINT_NAME = 'fk_verif_rol_vinculo'), 'SELECT 1', 'ALTER TABLE verificaciones_email ADD CONSTRAINT fk_verif_rol_vinculo FOREIGN KEY (id_rol_vinculo) REFERENCES roles (id_rol)');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'verificaciones_email' AND COLUMN_NAME = 'nombre_invitado'), 'SELECT 1', 'ALTER TABLE verificaciones_email ADD nombre_invitado VARCHAR(200) DEFAULT NULL AFTER email');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'verificaciones_email' AND COLUMN_NAME = 'tipo_documento_invitado'), 'SELECT 1', 'ALTER TABLE verificaciones_email ADD tipo_documento_invitado VARCHAR(20) DEFAULT NULL AFTER nombre_invitado');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'verificaciones_email' AND COLUMN_NAME = 'documento_invitado'), 'SELECT 1', 'ALTER TABLE verificaciones_email ADD documento_invitado VARCHAR(20) DEFAULT NULL AFTER tipo_documento_invitado');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'verificaciones_email' AND COLUMN_NAME = 'email_invitado'), 'SELECT 1', 'ALTER TABLE verificaciones_email ADD email_invitado VARCHAR(255) DEFAULT NULL AFTER documento_invitado');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'verificaciones_email' AND COLUMN_NAME = 'telefono_invitado'), 'SELECT 1', 'ALTER TABLE verificaciones_email ADD telefono_invitado VARCHAR(20) DEFAULT NULL AFTER email_invitado');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;

-- D2.2 (RE-T.2.5): cada sesión guarda la versión al entrar; al crear o
-- cambiar la contraseña sube y las demás sesiones de la cuenta se cierran.
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'version_sesion'), 'SELECT 1', 'ALTER TABLE usuarios ADD version_sesion INT NOT NULL DEFAULT 0 AFTER debe_cambiar_password');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
