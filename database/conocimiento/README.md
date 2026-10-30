# Formato del conocimiento — M2 B1, B2 y B2.1

Desde B2 estos archivos contienen el conocimiento inicial: lo que exige la batería de triage y un núcleo común de perros y gatos.
Todo elemento está **pendiente de validación veterinaria** y cita su fuente pública con localizador; no es una aprobación clínica (RN-209).
El fundamento distingue `clinico` (lo sostiene la fuente), `politica` (colores de triage de RN-416 a RN-418 y la batería) y `heuristico` (pesos del ranking).
Un bloqueo solo existe con fundamento clínico explícito de toxicidad. Desde 2.3.0 el formato admite pautas y planes como sugerencias editables (decisión del dueño, 2026-10-29); el veterinario revisa y guarda, RN-209.
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
Los metadatos admiten solo `cobertura` por motor (`sin_cobertura`, `parcial`, `completa`), `tamanio` (`pequeno`, `mediano`, `grande`, `gigante`), `braquicefalo` booleano y, exclusivamente en diagnóstico, `plan` opcional.
`plan` exige ambas listas: `examenes` (máximo 8 textos de hasta 80 caracteres) y `recomendaciones` (máximo 6 textos de hasta 160); pueden estar vacías. No admite campos adicionales.

### Pautas de tratamiento (2.3.0, RE-2.8.2/4/5, RE-2.9.4)

Cada relación `trata` va de diagnóstico a fármaco, con `signo: positivo`, `efecto: evidencia` y `peso` entre 0 y 1 como preferencia (mayor primero; desempate por código).
Exige una fuente de fundamento `clinico` con localizador, y `condiciones` con el predicado `especie` existente: cada alternativa debe restringir especie. No incorpora predicados nuevos.
Solo `trata` admite el campo obligatorio `pauta`, de lista cerrada:

```json
{
  "dosis_mg_kg": {"minimo": 0.5, "maximo": 1},
  "dosis_texto": null,
  "via": "oral",
  "frecuencia_horas": 24,
  "duracion_dias": 5,
  "indicacion": "Ejemplo sintético; no usar clínicamente"
}
```

Exactamente uno de `dosis_mg_kg` y `dosis_texto` es distinto de null. El rango exige ambos extremos numéricos positivos finitos y mínimo ≤ máximo; un valor fijo repite ambos extremos. `dosis_texto` es texto no vacío que cabe en el campo de dosis (100 caracteres).
Vías: `oral`, `subcutanea`, `intramuscular`, `intravenosa`, `topica`, `otica`, `oftalmica`. `frecuencia_horas` es entero positivo o null (dosis única); `duracion_dias` es entero positivo o null (según evolución); `indicacion` es texto no vacío de hasta 160 caracteres. Se rechazan espacios sobrantes y campos desconocidos.
La migración 16 agrega `grafo_aristas.pauta` JSON; el plan usa los metadatos existentes. Cambiar pauta o plan modifica la versión y conserva IDs en recarga idempotente. Fixtures exclusivamente sintéticos en `tests/Fixtures/conocimiento/pautas`.
`MotorPrescripcion::sugerir` filtra condiciones y bloqueos de toxicidad/alergia, ordena pautas y marca advertencias mediante `PrescripcionPublica` (RN-113). Con peso de la consulta calcula mg y conserva mg/kg y rango, redondeando a dos decimales o tres cifras significativas para dosis pequeñas; sin peso muestra mg/kg y «calcula con el peso».
`POST sugerir_tratamientos_ajax` exige CSRF, contexto veterinario y vínculo activo; recibe mascota, diagnóstico y peso actual opcional. No guarda ni devuelve medicación o evidencias protegidas. Usar agrega sin duplicar y permite Deshacer; todo sigue editable y pasa por la evaluación normal de filas y guardado.
Gran danés se incorpora al catálogo en B1; su atributo gigante con fuente se carga en el nodo en B2.

## Condiciones: lista cerrada y evaluador de C2

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
`EvaluadorCondiciones` es el evaluador único de C2, reutilizable por C3/C4.

### Contexto de evaluación

`evaluar(condicion, contexto, fecha)` recibe una condición validada por el cargador, un array normalizado y una `DateTimeImmutable` explícita.
Devuelve `valor` (`true`, `false` o `null` = desconocido) y `faltantes` (rutas de los datos necesarios).
No consulta el reloj, la sesión, archivos ni la base.
`todos` da falso ante cualquier falso y `alguno` verdadero ante cualquier verdadero; solo si nada decide, un desconocido deja el grupo desconocido.
Los datos faltantes de una rama que ya no puede cambiar el resultado no se solicitan.

```php
$contexto = [
    'especie' => 'especie.canino',
    'raza' => ['codigo' => 'raza.gran_danes', 'atributos' => ['tamanio' => 'gigante']],
    'edad_meses' => 84,
    'sexo' => 'Macho',
    'esterilizado' => false,
    'vacunacion' => null,
    'dias_desde_celo' => null,
    'alertas_activas' => ['condicion.epilepsia'],
    'sintomas' => ['sintoma.arcadas_improductivas' => ['minutos' => null]],
    'exposiciones' => ['sustancia.chocolate' => ['minutos' => 20]],
];
```

Cada campo puede faltar o ser `null`: es desconocido, nunca cero ni falso.
Una edad cero es un recién nacido conocido y `esterilizado: false` es un estado conocido.
`sexo` admite Macho/Hembra; la ficha «Desconocido» se normaliza a `null`.
`vacunacion` admite completa/incompleta/desconocida si se declara: `desconocida` es una respuesta explícita del catálogo; omitirla produce `null`, y el motor no la deduce de vacunas registradas.
`raza.atributos` solo lleva los atributos acreditados del nodo enlazado; un atributo ausente no equivale a falso ni se inventa desde `raza_indicada`.
`alertas_activas` es la lista de códigos enlazados a alertas activas conocidas; vacía significa que no hay esos registros, no que el paciente carezca de alergias o enfermedades.

`sintomas` y `exposiciones` son mapas por código, sin duplicados.
Un mapa vacío es una selección conocida vacía; que un código no esté significa «no marcado», no un hallazgo clínico negativo.
Si el mapa completo es `null`, la selección es desconocida.
La marca con `minutos: null` acredita el evento pero desconoce el tiempo; las exposiciones sin hora pueden activar reglas sin intervalo.
Para un evento no marcado, su condición temporal no aplica (falso); para uno marcado sin hora es desconocida.
Los minutos son números no negativos referidos a la fecha de evaluación.
Alternativamente, un episodio interno puede traer `desde: DateTimeImmutable`; el evaluador obtiene los minutos respecto a la fecha inyectada, y un inicio futuro queda desconocido.
Ese objeto no es un dato aceptado directamente desde HTTP.
La edad de la ficha se expresa en meses calendario cumplidos a la fecha de evaluación; nacimiento ausente, inválido o futuro queda desconocido.

### Lectura y ranking

`GrafoConocimiento::instantanea()` reutiliza la lectura activa del mapa de C6 y conserva una `ConocimientoClinico` inmutable durante la petición.
No cambia el contrato de `mapa()` ni lee registros de pacientes.
El modelo `PacienteDiagnostico` verifica el vínculo activo y arma por separado especie/raza enlazadas, edad, sexo, esterilización y alertas activas globales (RN-113); no lee consultas ni tratamientos protegidos.

`MotorDiagnosticos` suma signo por peso de las aristas `sugiere` aplicables desde las marcas, más los modificadores verdaderos del motor `diagnosticos`.
Las condiciones desconocidas no aportan peso y se devuelven como `datos_faltantes` con el código de arista o regla.
El contexto por sí solo no crea candidatos: se exige al menos una coincidencia positiva de síntomas y puntuación positiva.
La cobertura cuenta síntomas únicos positivos coincidentes sobre los síntomas positivos característicos cuyas condiciones son verdaderas para ese contexto.
No cuenta dos veces una marca o sus sinónimos ni usa aristas negativas en el denominador.
Ordena por puntuación descendente, fracción de cobertura descendente y código estable ascendente; devuelve como máximo cinco candidatos, sin probabilidades ni porcentajes.
La respuesta conserva fuentes, versión, evidencia y la marca pendiente de validación; nunca guarda ni elige el diagnóstico final (C5, RN-202).

El resultado admite `nivel`, `revision`, `revision_inmediata`, `contagio` para triage; `diagnostico` y `peso` para ranking; y `efecto`/`motivo` para la semántica de apoyo/prescripción.
Un efecto de bloqueo solo es válido por toxicidad o alergia, en prescripción, con fuente clínica explícita; las interacciones advierten.
Las relaciones permitidas son `sugiere`, `trata`, `toxico_para`, `alergia`, `interactua_con`, `contraindica` y `asociado`.
El signo y peso no convierten por sí solos una relación en bloqueo.

### Prescripción y medicación vigente (C3)

`MotorPrescripcion` es puro y comparte `EvaluadorCondiciones` con el ranking.
Una alergia individual activa enlazada bloquea aunque no tenga fuente bibliográfica; `toxico_para` respeta su efecto de bloqueo o advertencia y las interacciones se cruzan en ambos sentidos, incluidas las otras filas nuevas.
No interpreta nombres escritos a mano ni deduce nodos; un fármaco sin código o una medicación sin enlace deja la evaluación incompleta, nunca «sin interacciones».

`MedicacionVigente` es una lectura interna separada del historial, tras comprobar el vínculo activo del paciente.
Incluye tratamientos de todas las clínicas con inicio no futuro y fin desde hoy inclusive, y alertas activas de medicación continua.
Un fin nulo conserva vigencia desconocida y solo genera advertencia si existe un cruce; el texto pide conciliar.
Los tratamientos ajenos requieren autorización para nombrarse; las alertas médicas siguen siendo globales por RN-113.

`POST ?action=validar_prescripcion_ajax`, con CSRF y contexto veterinario, recibe `id_mascota` y una lista `tratamientos` con `farmaco` opcional.
Devuelve por fila `bloqueada`, `motivos`, `advertencias`, `evaluacion_incompleta`, `requiere_decision`, `requiere_conciliacion` y `huella`.
`PrescripcionPublica` usa una lista cerrada de campos y agrupa todas las interacciones protegidas en un solo aviso genérico, sin evidencia que permita reconstruir la medicación.
La huella HMAC usa una clave secreta de sesión y vincula paciente, contexto, filas, fecha, cruces, visibilidad y versión.

El guardado acepta `med_farmaco`, `med_fin`, `med_decision`, `med_conciliacion` y `med_huella`, paralelos a los campos heredados del tratamiento.
Dentro de la transacción se relee y evalúa antes de insertar la consulta o mover adjuntos.
Un bloqueo no se confirma; una advertencia exige `confirmar` y huella vigente; si es protegida exige además conciliación de máximo `ValidadorPrescripcion::CONCILIACION_MAX` (500), rechazando el exceso sin recortarlo.
Cada decisión se audita con la versión y la evidencia interna dentro de la transacción; el rechazo se audita después del rollback.
Las filas heredadas sin código siguen guardándose como evaluación incompleta; el buscador y la presentación de las alertas se integran en C5.

### Triage y buscador (C4)

`MotorTriage::calcular(sintomas, exposiciones, paciente, grafo, fecha)` recibe mapas por código y una fecha explícita; no recibe la nota libre ni cuotas o mora.
Cada episodio conserva `tiempo` (`conocido`, `no_se`, `no_aplica`) y `minutos` numéricos o `null`; «No aplica» solo corresponde a `sintoma.control_preventivo`.
Los síntomas se cuentan una sola vez por código; la API rechaza repetir una marca con tiempos distintos.
P1 es universal; el nivel base exige cobertura; P3 escala una sola vez los síntomas moderados distintos, sin leer resultados anteriores.
Cada regla verdadera aporta un candidato independiente y el resultado toma el máximo explícito rojo/naranja/amarillo/verde.
Sin cobertura, con «Otro / no sé» o sin candidato hay reserva amarilla con revisión; un dato desconocido decisivo también pide revisión.
Rojo no informa revisión del personal, y `revision_inmediata` y `contagio` se conservan como banderas independientes.
La respuesta incluye `reglas_aplicadas`, `datos_faltantes`, `version` y `aviso` de apoyo pendiente de validación veterinaria.

`GET ?action=buscar_sintomas_ajax&q=bomita&especie=especie.canino` entrega sugerencias de código/etiqueta, coincidencia y similitud, sin seleccionar ni calcular.
`BuscadorSintomas` normaliza con `ArchivosConocimiento`, ordena exacto/prefijo/palabras y finalmente Damerau-Levenshtein sobre Unicode.
El umbral técnico es 0.65 y el máximo ocho opciones, reservando una para «Otro / no sé»; la búsqueda admite hasta 100 caracteres para acotar su costo.
El filtro conserva alarmas universales y conceptos sin restricción de especie; el catálogo actual declara sus síntomas globales, sin inventar exclusividades a partir de su nombre.
La lectura de C6 aporta únicamente nodos y sinónimos activos.

`POST ?action=calcular_triage_ajax`, con CSRF, recibe `id_mascota`, `sintomas` y `exposiciones` como listas de objetos `codigo`, `tiempo`, `minutos`.
Se requiere al menos una marca de síntoma o exposición; una nota sola no calcula.
Una exposición es una marca del catálogo de tipo sustancia/fármaco, necesaria para los casos sin síntomas como T-08 y T-14.
Puede incluir `vacunacion` y `dias_desde_celo` declarados; especie, raza, edad, sexo, esterilización y alertas salen del contexto común de la ficha, no de campos enviados por el cliente.
Ambas APIs son para veterinario y propietario según Modelos §4; el cálculo exige vínculo activo en clínica o mascota propia en el portal, con 403 auditado.
No guardan citas, síntomas ni decisiones; M4 integra captura, revisión previa a asignar horario, derivación, aislamiento y ajuste veterinario auditado.

La batería versionada está en `tests/Fixtures/triage.php` y corre con `MotorTriageTest` sobre el conocimiento real.
El esperado de revisión solo se compara donde la batería lo define (nota de B2.1 — Revisión); `null` significa no especificado, nunca revisión negativa inferida.
T-16 y T-20 mantienen sus limitaciones clínicas; pasar las pruebas verifica el software y no valida el conocimiento.

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
