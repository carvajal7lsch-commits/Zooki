# Formato del conocimiento — M2 B1, B2 y B2.1

Desde B2 estos archivos contienen el conocimiento inicial: lo que exige la batería de triage y un núcleo común de perros y gatos.
Todo elemento está **pendiente de validación veterinaria** y cita su fuente pública con localizador; no es una aprobación clínica (RN-209).
El fundamento distingue `clinico` (lo sostiene la fuente), `politica` (colores de triage de RN-416 a RN-418 y la batería) y `heuristico` (pesos del ranking).
Un bloqueo solo existe con fundamento clínico explícito de toxicidad; no hay dosis ni pautas de tratamiento.
El conjunto sintético de las pruebas del cargador sigue en `tests/Fixtures/conocimiento/`; las pruebas del conjunto real están en `tests/Unit/ConocimientoRealTest.php`.

**Para corregir el conocimiento:** editar el JSON, correr `vendor/bin/phpunit tests/Unit/ConocimientoRealTest.php` y `php scripts/cargar_conocimiento.php --revisar` antes de cargar.
Un cambio de contenido clínico deja el elemento en `pendiente`; quitar un código lo retira sin borrarlo.

## Conjunto e identidad

`manifiesto.json` exige `formato: 1`, versión editorial, limitaciones y la lista exacta de los cinco archivos JSON.
No incluye hashes por archivo.
El lector normaliza CRLF a LF y calcula SHA-256 sobre un objeto ordenado por nombre de archivo con todos sus contenidos, incluido el manifiesto.
Ese hash de 64 caracteres identifica la instantánea leída, se guarda en `version_conocimiento` y completo en `auditoria_sistema.datos_nuevos.version`.
Cambiar contenido cambia la versión; cambiar solamente CRLF por LF conserva la versión y no provoca otra carga.
`.gitattributes` fija `eol=lf` para `database/conocimiento/*.json`.
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
| `exposicion` | `codigo` de sustancia o fármaco; no exige conocer el tiempo. |
| `vacunacion` | `valor`: `completa`, `incompleta` o `desconocida`. |
| `dias_desde_celo` | Intervalo de días transcurridos desde el último celo; sin código de síntoma ni unidades alternativas. |

Un intervalo incluye al menos `minimo` o `maximo`, no negativos, y el booleano `incluye_minimo`/`incluye_maximo` para cada extremo existente.
No se permiten unidades alternativas, campos desconocidos, referencias inexistentes, código ejecutable ni ciclos entre reglas.
Los atributos adicionales de episodios o nuevos predicados requerirán revisión antes de ampliar esta lista en una etapa posterior.
La lista cerrada contiene exactamente doce predicados.
Los errores indican archivo y código del elemento; si todavía no existe un código válido, indican su posición o el conjunto, además del campo afectado cuando aplica.
El evaluador único de estas condiciones pertenece a C2 y será compartido por C3/C4.

El resultado admite `nivel`, `revision`, `revision_inmediata`, `contagio` para triage; `diagnostico` y `peso` para ranking; y `efecto`/`motivo` para la semántica de apoyo/prescripción.
Un efecto de bloqueo solo es válido por toxicidad o alergia, en prescripción, con fuente clínica explícita; las interacciones advierten.
Las relaciones permitidas son `sugiere`, `trata`, `toxico_para`, `alergia`, `interactua_con`, `contraindica` y `asociado`.
El signo y peso no convierten por sí solos una relación en bloqueo.

## Política de B2.1

El conjunto contiene 105 nodos (51 síntomas), 105 aristas, 201 sinónimos, 29 reglas (24 de triage y 5 de ranking) y 47 fuentes.
La fuente `zooki.decisiones_b21` identifica las tres decisiones del usuario del 2026-10-14 en la revisión de B2; su fundamento es `politica`.
Las fuentes clínicas de cada toxicidad se conservan como `clinico`; los colores no se presentan como una validación veterinaria.

- `triage.toxicos_canino` y `triage.toxicos_felino` agrupan las exposiciones con `toxico_para` hacia cada especie y fijan un mínimo naranja con revisión.
  Las toxicidades por raza siguen siendo relaciones por raza; no se extrapolan a toda una especie.
  El chocolate entra únicamente mediante `minutos_desde_exposicion < 720`; `triage.chocolate_tardio` conserva amarillo con revisión y su intervalo desde 720 minutos.
  Un tiempo desconocido no satisface ese intervalo y conserva la reserva con revisión; no declara una exposición segura.
- `triage.etilenglicol` da rojo en perro o gato sin exigir tiempo conocido.
  Las reglas rojas de lirios y paracetamol en gatos permanecen.
- `triage.abdomen_canino` da naranja por arcadas improductivas y abdomen distendido sin exigir tamaño.
  `triage.abdomen_gigante` conserva rojo para tamaño grande o gigante; los candidatos se combinan por máximo en C4.
- `sintoma.parto_esfuerzo_sin_cria` conserva su código y pasa a «más de 30 minutos o no sé», como alarma universal roja sin condición temporal adicional.
  `sintoma.parto_esfuerzo_menos_30` representa menos de 30 minutos y da naranja con revisión mediante `triage.parto_menos_30`.
  Los sinónimos sugieren ambas marcas y la persona confirma cuál corresponde.
  `triage.parto_prolongado` se retiró del archivo: al recargar queda inactiva, conserva su ID y no se borra.

## Carga y retiro

`php scripts/cargar_conocimiento.php --revisar` valida y muestra altas, cambios y retiros sin escribir.
Sin esa opción aplica la carga completa con candado y transacción.
`--carpeta=...` selecciona otro conjunto explícito para pruebas por consola; no hay ruta web.
Un faltante o JSON inválido devuelve error antes de modificar datos.
Quitar un código de un conjunto completo lo desactiva; nunca lo borra ni cambia su ID; reintroducirlo recupera ese ID.
No se puede cambiar el tipo del concepto reutilizando su código.
La migración conserva elementos legados inactivos, con códigos reservados y fuentes vacías que expresan ausencia de evidencia, sin inventar validaciones.
El proceso de arranque carga únicamente después de una migración exitosa; los errores llevan el prefijo `[conocimiento]` y Apache arranca igualmente.
