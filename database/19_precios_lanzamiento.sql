SET NAMES utf8mb4;

-- RN-008: decisión del dueño del2026-11-02; no altera importes de órdenes ni periodos contratados.
UPDATE planes
SET precio_mensual=40000,precio_anual=400000
WHERE nombre='Profesional' AND (precio_mensual<>40000 OR precio_anual IS NULL OR precio_anual<>400000);
