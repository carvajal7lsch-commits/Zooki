# HU-NN — Nombre del cambio

> Estado: borrador | aprobado | en curso | terminado
> Versión prevista: vX.Y.Z · Fecha: AAAA-MM-DD

## 1. Especificación (qué y por qué)

**Problema:** qué pasa hoy y a quién le afecta.

**Historia:** Como <rol>, quiero <acción> para <beneficio>. (Enlazar la HU en `documentacion/HistoriasUsuario.md`.)

**Requisitos** (los mismos que se agregan a `documentacion/RequisitosEspecificos.md`):

| ID | Requisito | Criterio de aceptación |
|---|---|---|
| RE-NN.M | El sistema debe… | Dado…, cuando…, entonces… |

**Reglas de negocio que aplican:** RN-…

**Fuera de alcance:** lo que este cambio no va a hacer.

## 2. Plan (cómo)

- **Archivos:** qué se crea o modifica y para qué.
- **Datos:** migración `database/NN_nombre.sql` (repetible) o «ninguna».
- **Rutas y permisos:** acciones nuevas en `public/index.php` y su entrada en la matriz de `helpers/Security.php`.
- **Pantallas:** qué cambia en móvil, tablet y escritorio.
- **Riesgos:** qué se puede romper y cómo se evita.

## 3. Tareas

- [ ] Documentar HU y RE
- [ ] …
- [ ] Pruebas: `tests/…` cubre RE-NN.M
- [ ] README (sección de versión) y `config/App.php`

## 4. Verificación

Cómo se comprobó cada requisito: prueba automática, o pasos manuales y en qué tamaños de pantalla.
