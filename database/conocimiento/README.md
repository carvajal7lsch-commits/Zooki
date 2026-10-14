# Formato del conocimiento — M2 B1

El conocimiento real corresponde a B2; estos cinco archivos están vacíos deliberadamente.
No contienen nodos clínicos ni declaran cobertura.
El conjunto sintético vive exclusivamente en `tests/Fixtures/conocimiento/`.

## Conjunto e identidad

`manifiesto.json` exige `formato: 1`, versión editorial, limitaciones y la lista exacta de los cinco archivos JSON.
No incluye hashes por archivo.
El lector calcula SHA-256 sobre un objeto ordenado por nombre de archivo con todos sus contenidos, incluido el manifiesto.
Ese hash de 64 caracteres identifica la instantánea leída, se guarda en `version_conocimiento` y completo en `auditoria_sistema.datos_nuevos.version`.
Cambiar cualquier archivo cambia la versión; repetir los mismos bytes no escribe ni audita otra carga.
Los códigos son únicos por clase de elemento, en minúsculas ASCII, de hasta 100 caracteres, con letras, dígitos, punto, guion y guion bajo.
El prefijo `legado.` está reservado a la migración.
Las referencias usan códigos, nunca IDs numéricos de otra instalación.

## Archivos

| Archivo | Campos |
|---|---|
| `fuentes.json` | Lista de objetos con `codigo`, `entidad`, `titulo`, `url` HTTPS específica, `fecha_consulta` y `seccion`; `fecha_publicacion` opcional, fechas ISO. |
| `nodos.json` | `codigo`, `tipo`, `etiqueta`, `fuentes`, `estado_validacion`; opcionales `validacion`, `nivel_triage`, `alarma_universal`, `moderado`, `metadatos`, `taxonomia`. |
| `aristas.json` | Campos comunes, `origen`, `destino`, `tipo_relacion`, `signo`, `peso`, `efecto`; `condiciones` opcional. |
| `sinonimos.json` | Campos comunes, `nodo` y `texto`; el normalizado lo calcula el cargador. |
| `reglas.json` | Campos comunes, `motor`, `condiciones` y `resultado`; no referencias a otras reglas ni a casos de prueba. |

Los campos comunes son `codigo`, `fuentes`, `estado_validacion` y `validacion` opcional.
Cada referencia de `fuentes` exige `codigo` existente, `localizador` específico y `fundamento` (`clinico`, `politica`, `heuristico`).
La base conserva la bibliografía resuelta junto a la referencia; no basta una portada como evidencia clínica.
Los estados son `pendiente`, `validado`, `rechazado`; una validación exige en el archivo `validacion.quien` y `validacion.fecha`.
Esos datos no crean columnas ni usuarios en la base.
Quien cambie contenido clínico debe retirar su validación anterior y volver a documentarla; el hash conserva la versión exacta revisada.

Tipos de nodo: síntoma, diagnóstico, fármaco, especie, raza, condición y sustancia, con sus códigos sin tildes.
Una especie declara `taxonomia.especie`; una raza también `taxonomia.raza`.
Ambos nombres deben corresponder a una pareja ya existente; el cargador no da de alta taxonomía.
En base, el nodo especie enlaza `id_especie`; el nodo raza enlaza `id_raza` y obtiene su especie del catálogo, sin duplicarla.
Los metadatos admiten solo `cobertura` por motor (`sin_cobertura`, `parcial`, `completa`), `tamanio` (`pequeno`, `mediano`, `grande`, `gigante`) y `braquicefalo` booleano.
Gran danés se incorpora al catálogo en B1; su atributo gigante con fuente se carga en el nodo en B2.

## Condiciones: lista cerrada, todavía sin evaluador

Los grupos son `{"todos": [...]}` o `{"alguno": [...]}`; como máximo dos niveles de grupos anidados.
Las hojas tienen `predicado` y exclusivamente estos campos:

| Predicado | Campos y unidad |
|---|---|
| `sintoma_marcado` | `codigo` de nodo síntoma. |
| `especie` | `codigo` de nodo especie. |
| `raza` | `atributo` (`tamanio` o `braquicefalo`) y `valor`; no código de raza hecho a la medida de un caso. |
| `edad_meses` | Intervalo numérico en meses. |
| `sexo` | `valor`: `Macho`, `Hembra` o `Desconocido`. |
| `esterilizado` | `valor` booleano; la falta de dato no es `false`. |
| `alerta_activa` | `codigo` de nodo condición o fármaco enlazado a una alerta. |
| `inicio_episodio` | `codigo` de síntoma e intervalo de minutos transcurridos desde su inicio. |
| `minutos_desde_exposicion` | `codigo` de sustancia o fármaco e intervalo de minutos desde exposición. |

Un intervalo incluye al menos `minimo` o `maximo`, no negativos, y el booleano `incluye_minimo`/`incluye_maximo` para cada extremo existente.
No se permiten unidades alternativas, campos desconocidos, referencias inexistentes, código ejecutable ni ciclos entre reglas.
Los atributos adicionales de episodios o nuevos predicados requerirán revisión antes de ampliar esta lista en una etapa posterior.
El evaluador único de estas condiciones pertenece a C2 y será compartido por C3/C4.

El resultado admite `nivel`, `revision`, `revision_inmediata`, `contagio` para triage; `diagnostico` y `peso` para ranking; y `efecto`/`motivo` para la semántica de apoyo/prescripción.
Un efecto de bloqueo solo es válido por toxicidad o alergia, en prescripción, con fuente clínica explícita; las interacciones advierten.
Las relaciones permitidas son `sugiere`, `trata`, `toxico_para`, `alergia`, `interactua_con`, `contraindica` y `asociado`.
El signo y peso no convierten por sí solos una relación en bloqueo.

## Carga y retiro

`php scripts/cargar_conocimiento.php --revisar` valida y muestra altas, cambios y retiros sin escribir.
Sin esa opción aplica la carga completa con candado y transacción.
`--carpeta=...` selecciona otro conjunto explícito para pruebas por consola; no hay ruta web.
Un faltante o JSON inválido devuelve error antes de modificar datos.
Quitar un código de un conjunto completo lo desactiva; nunca lo borra ni cambia su ID; reintroducirlo recupera ese ID.
No se puede cambiar el tipo del concepto reutilizando su código.
La migración conserva elementos legados inactivos, con códigos reservados y fuentes vacías que expresan ausencia de evidencia, sin inventar validaciones.
El proceso de arranque carga únicamente después de una migración exitosa; los errores llevan el prefijo `[conocimiento]` y Apache arranca igualmente.
