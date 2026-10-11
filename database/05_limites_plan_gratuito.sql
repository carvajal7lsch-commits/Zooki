SET NAMES utf8mb4;

-- HU-0.4: conserva cualquier configuración personalizada y permite repetir la migración.
UPDATE planes
SET limite_personal = 2, limite_mascotas = 50, limite_citas_mes = 100
WHERE nombre = 'Gratuito'
  AND limite_personal = 2 AND limite_mascotas = 5 AND limite_citas_mes = 30;
