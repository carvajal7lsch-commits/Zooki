# Batería de casos de triage — Zooki v2.0

> **Cierre M2 — 16/10/2026 (2.0.0-alpha.4)** · Batería automatizada, validación veterinaria pendiente

> **Documento interno de pruebas** · No se publica en el portal de documentación · SENA ADSO — Ficha 3142784

Esta batería sirve para poner a prueba el triage de 4 niveles antes y después de construirlo: en la presentación, para que cualquiera intente «corchar» al sistema con casos difíciles, y en la construcción, como casos de prueba del Grafo I. Cada caso conserva un relato ilustrativo y explicita las marcas, tiempos y datos del paciente que recibe el motor.
El texto libre no interviene en el cálculo; el buscador solo sugiere y el propietario confirma las opciones.

**Trazabilidad:** RN-411 y RN-416 a RN-425 (triage y agenda), RN-209 (el soporte es apoyo, no diagnóstico), RN-113 (historia compartida), HU-2.11, HU-4.13, HU-4.15, HU-4.17, HU-4.18, RF-2.12, RF-4.7, RF-4.10 a RF-4.13. La HU-2.11 usa esta batería como prueba de aceptación (RE-2.11.7).

**Automatizada el 2026-10-15 (M2 C4, Codex):** los 26 casos estructurados viven en `tests/Fixtures/triage.php` y se ejecutan mediante el proveedor de datos de `tests/Unit/MotorTriageTest.php`, con el conocimiento real de `database/conocimiento/`.
La prueba conserva los niveles esperados de esta batería; compara la revisión donde está expresamente exigida, y también revisión inmediata y contagio.
Incluye pruebas adicionales de P1–P6, tóxicos, ventanas temporales y decisiones aprobadas, sin convertir los colores pendientes en validación veterinaria.

> ⚠️ **Validación clínica pendiente.** Los niveles esperados son orientativos para probar el software. Por decisión del usuario (2026-10-14), se permite construir y cargar conocimiento pendiente desde fuentes reconocidas, identificadas por elemento.
Eso no constituye aprobación clínica: la validación veterinaria del conjunto sigue pendiente. El sistema apoya la decisión; no la reemplaza (RN-209).

**Caso T-20 pendiente:** el motor aplica el valor de reserva 🟡 de RN-418 y solicita revisión inmediata antes de ofrecer un horario. El nivel 🟠 propuesto para el conejo no es una regla automática aprobada: requiere validación de un veterinario con experiencia en esa especie y, si procede, una regla documentada de cobertura para conejos. La batería clínica completa no queda aprobada hasta cerrar esta revisión.

**Niveles:** 🔴 Rojo (crítico: no se agenda, va a urgencias) · 🟠 Naranja (urgente: sobrecupo) · 🟡 Amarillo (prioritario: primer espacio) · 🟢 Verde (no urgente: el actor elige).

---

## 1. Reglas de evaluación

Aprobadas el 05/10/2026 e incorporadas a las [Reglas de Negocio](ReglasNegocio.md) (Rev 2.0):

| ID | Regla | Por qué hace falta | Regla de negocio |
|---|---|---|---|
| P1 | **Señales de alarma universales → 🔴 en cualquier especie:** dificultad respiratoria, encías pálidas o azuladas, convulsión en curso, colapso o inconsciencia, hemorragia que no se detiene, no poder orinar, golpe de calor y parto con esfuerzo sin cría por más de 30 minutos o tiempo desconocido. | Garantiza un piso de seguridad aunque la especie no tenga cobertura en el grafo; decisión del usuario durante B2.1 para el tiempo desconocido del parto. | RN-417 |
| P2 | **El nivel final es el más alto** que dispare cualquier síntoma o combinación. Nunca se promedia, y los datos tranquilizadores («come bien», «está activo») no bajan una alarma. | Evita que el propietario «diluya» una señal grave con buenas noticias. | RN-416 |
| P3 | **Las combinaciones escalan:** tres o más síntomas moderados, o uno moderado con signos de deshidratación o decaimiento, suben un nivel. | Ningún síntoma solo parece grave, pero juntos sí. | RN-416 |
| P4 | **Modificadores del paciente:** la raza (braquicéfalos con síntomas respiratorios; razas grandes o gigantes con abdomen distendido), la edad (menor de 6 meses, geriátrico), el estado (sin vacunas, hembra entera, gato macho) y el historial (epilepsia conocida, alergias registradas en cualquier clínica) cambian el nivel. | El mismo texto no significa lo mismo en un bulldog que en un labrador. | RN-416 |
| P5 | **El tiempo cuenta:** para tóxicos y para el inicio de los síntomas, el formulario pregunta hace cuánto pasó, y el nivel depende de esa respuesta. | Un tóxico recién ingerido tiene una ventana de tratamiento; dos días después, otra. | RN-416, RN-424 |
| P6 | **Sin cobertura, «Otro / no sé» o sin regla aplicable a las marcas: nunca 🟢 por defecto.** El caso queda en 🟡 con la marca «revisión del personal», y las alarmas universales (P1) siguen aplicando. | Un caso que el sistema no entiende no puede terminar al final de la fila. | RN-418 |
| P7 | **El veterinario puede ajustar el nivel**, con motivo obligatorio y registro en auditoría. La diferencia entre el nivel calculado y el ajustado queda guardada para mejorar el grafo. | La decisión final es del veterinario (RN-209), pero debe quedar trazada. | RN-419 |
| P8 | **Una urgencia 🔴 nunca se bloquea** por el límite del plan ni por una suscripción en mora. | El modelo de negocio no puede impedir atender una emergencia. | RN-420 |

---

**Parto (decisión del usuario, 2026-10-14):** la captura distingue «Parto con esfuerzo sin cría: más de 30 minutos o no sé» (alarma universal 🔴) de «Parto con esfuerzo sin cría: menos de 30 minutos» (🟠 con revisión del personal).
El buscador solo sugiere ambas marcas; la persona confirma cuál corresponde.
El tiempo desconocido no puede dejar este parto en la reserva amarilla.
Los colores son política del producto y siguen pendientes de validación veterinaria.

## 2. Casos clínicos

### A. Señales de alarma evidentes (control)

Si el sistema falla aquí, falla todo lo demás.

| ID | Paciente | Relato ilustrativo (no calcula) | Nivel | Por qué | Qué pone a prueba |
|---|---|---|---|---|---|
| T-01 | Perro mestizo, 6 años | «Respira con la boca abierta y las encías se le ven moradas» | 🔴 | Dificultad respiratoria con cianosis (P1). | Que reconozca la alarma básica. |
| T-02 | Gata, 3 años | «Está convulsionando ahora mismo, ya van como 6 minutos» | 🔴 | Convulsión en curso de más de 5 minutos (P1). | Diferenciar convulsión activa de una ya pasada (ver T-18). |
| T-03 | Perro, 4 años | «Lo atropelló una moto y le sangra mucho la pata, no para» | 🔴 | Hemorragia que no se detiene (P1). | Reconocer el sangrado activo dentro de un relato de trauma. |

### B. Alarmas escondidas o minimizadas

El propietario describe algo grave como si fuera leve.

| ID | Paciente | Relato ilustrativo (no calcula) | Nivel | Por qué | Qué pone a prueba |
|---|---|---|---|---|---|
| T-04 | Gato macho castrado, 5 años | «Va mucho a la arena pero casi no hace, maúlla; por lo demás está bien» | 🔴 | Probable obstrucción urinaria: riesgo de alteración del potasio y paro cardíaco. | «Por lo demás está bien» no debe bajar el nivel (P2); «va a la arena» suena leve. |
| T-05 | Gran danés, 7 años | «Intenta vomitar pero no le sale nada, tiene la barriga hinchada y está inquieto» | 🔴 | Cuadro compatible con dilatación y torsión gástrica en raza gigante. | Ningún síntoma solo parece crítico: la combinación más la raza sí (P3, P4). |
| T-06 | Perra no esterilizada, 8 años; celo hace 5 semanas | «Toma mucha agua, está decaída y le sale un flujo» | 🟠 | Cuadro compatible con piometra. | Que use el sexo, el estado reproductivo y el tiempo desde el celo (P4). |
| T-07 | Perro, 5 años | «Lo atropellaron pero se paró y camina normal» | 🟠 | Un trauma puede dejar lesiones internas que no se ven al principio. | Que «camina normal» no lo deje en 🟢. |
| T-08 | Gato, 2 años | «Estuvo mordiendo las flores del jarrón (lirios), pero está normal» | 🔴 | Los lirios son tóxicos para los riñones del gato aun en poca cantidad, y al principio no dan síntomas. | Toxicidad por especie sin síntomas visibles: debe coincidir con la arista negativa del Grafo I. |

### C. La especie, la raza o la edad cambian la gravedad

Pares de casos con el mismo texto y distinto paciente: si salen igual, los modificadores (P4) no están funcionando.

| ID | Paciente | Relato ilustrativo (no calcula) | Nivel | Por qué | Qué pone a prueba |
|---|---|---|---|---|---|
| T-09 | Bulldog francés, 3 años; día caluroso | «Después de caminar respira muy ruidoso y está muy cansado» | 🟠 (🔴 si colapsa o tiene encías moradas) | Raza braquicéfala con esfuerzo respiratorio y calor. | La raza sube el nivel. |
| T-10 | Labrador, 3 años; mismo día | El mismo texto de T-09 | 🟡 | Sin el factor braquicéfalo, el riesgo es menor. | Control de T-09: el mismo texto debe dar otro nivel. |
| T-11 | Cachorro de 3 meses, sin vacunas completas | «Vomita, tiene diarrea con sangre y no quiere comer» | 🟠 + aviso de aislamiento | Cuadro compatible con parvovirus: se deshidrata rápido y contagia. | La edad y la vacunación suben el nivel; además, el sistema debería avisar que llega un posible caso contagioso. |
| T-12 | Perro adulto vacunado, 6 años | «Vomitó una vez y tuvo diarrea, pero sigue activo» | 🟡 | Episodio aislado en un adulto estable. | Control de T-11. |
| T-13 | Gato obeso, 9 años | «No come desde hace 3 días y se esconde» | 🟠 | En gatos con sobrepeso, varios días sin comer arriesgan el hígado (lipidosis hepática). | Los días sin comer pesan distinto en gato que en perro. |

### D. Tóxicos y tiempo

| ID | Paciente | Relato ilustrativo (no calcula) | Nivel | Por qué | Qué pone a prueba |
|---|---|---|---|---|---|
| T-14 | Perro de 15 kg | «Se comió un chicle sin azúcar hace 20 minutos» | 🟠 | El xilitol puede bajar el azúcar en sangre; todavía hay ventana para actuar. | Asintomático pero con reloj: el tiempo desde la ingesta importa (P5). |
| T-15 | Gato | «Le di acetaminofén para la fiebre» | 🔴 | El paracetamol es tóxico para los gatos. | El triage y la alerta de toxicidad del Grafo I (RF-2.9) deben coincidir. |
| T-16 | Perro | «Se comió una barra de chocolate hace 2 días y está normal» | 🟡 provisional + revisión | Tipo y cantidad desconocidos requieren revisión; 48 horas no descartan toxicidad ni son una regla general para bajar nivel. | Tiempo contextual sin rebajar alarmas; limitación aprobada por el usuario, pendiente de validación clínica. |

### E. Combinaciones y contradicciones

| ID | Paciente | Relato ilustrativo (no calcula) | Nivel | Por qué | Qué pone a prueba |
|---|---|---|---|---|---|
| T-17 | Perro, 10 años | «Vomitó 4 veces hoy, no toma agua, está muy decaído y tiene las encías secas» | 🟠 | Cada síntoma solo sería 🟡; juntos indican deshidratación. | Escalamiento por combinación (P3). |
| T-18 | Perro, 4 años | «Está activo y come normal; ayer convulsionó por primera vez» | 🟠 | Una primera convulsión necesita estudio pronto, aunque el animal esté estable. | Los datos tranquilizadores no anulan la alarma (P2), pero tampoco es 🔴 porque ya pasó. |
| T-19 | Perro con epilepsia conocida y en tratamiento | «Tuvo una convulsión corta como las de siempre y ya está bien» | 🟡 | Paciente conocido, episodio único y recuperado. | El historial cambia el nivel; si el historial es de otra clínica, debe leerse por la historia compartida (RN-113). |

### F. Sin cobertura y texto ambiguo

| ID | Paciente | Relato ilustrativo (no calcula) | Nivel | Por qué | Qué pone a prueba |
|---|---|---|---|---|---|
| T-20 | Conejo, 2 años | «No ha comido ni ha hecho popó desde ayer» | 🟡 + revisión inmediata del personal; 🟠 propuesto, pendiente de validación veterinaria | El grafo aún no cubre conejos: aplica el valor de reserva de RN-418. La falta de apetito y heces exige valoración veterinaria pronta; el color definitivo requiere revisión clínica. | El motor no puede asignar 🟠 sin una regla validada para conejos ni agendar este caso sin revisión humana. |
| T-21 | Loro | «Respira moviendo mucho la cola y está esponjado» | 🔴 | Dificultad respiratoria: alarma universal (P1). | Las alarmas universales funcionan aunque la especie no tenga cobertura. |
| T-22 | Perro | «Está raro» | 🟡 + revisión del personal | No hay síntomas reconocibles. | Un texto vago no debe terminar en 🟢 (P6). |
| T-23 | Gato | «bomita sangre i esta desaido» | 🟠 | Vómito con sangre y decaimiento. | Que el buscador sugiera opciones ante errores, y que el usuario confirme Vómito con sangre y Decaimiento antes de calcular. |

### G. No sobre-clasificar (casos 🟢)

Un triage que manda todo a urgencias llena la agenda de sobrecupos y deja de servir.

| ID | Paciente | Relato ilustrativo (no calcula) | Nivel | Por qué | Qué pone a prueba |
|---|---|---|---|---|---|
| T-24 | Perro, 3 años | «Control anual y vacuna de la rabia» | 🟢 | Consulta preventiva. | Que una consulta de rutina no suba de nivel. |
| T-25 | Gato, 4 años | «Se rasca las orejas desde hace una semana; come y juega normal» | 🟢 | Molestia leve, paciente estable. | Síntoma real pero no urgente. |
| T-26 | Perro, 6 años | «Cojea un poco de una pata desde hace un mes, igual corre» | 🟢 | Molestia crónica leve. | Que lo crónico y estable no se trate como agudo. |

---

### H. Entrada estructurada de los 26 casos

Los relatos anteriores no se interpretan automáticamente.
El dato ausente sigue desconocido, con revisión cuando haga falta; no se supone vacunación, raza, edad ni hora.
La opción temporal «No aplica» se limita a atención preventiva.
T-22 marca «Otro / no sé»; T-23 prueba primero el buscador y luego las dos marcas confirmadas.
Buscar «bomita» no añade sangre ni selecciona un síntoma.
T-16 y T-20 son esperados provisionales con las limitaciones expresas de sus filas.

| Caso | Marcas del catálogo y atributos cerrados del episodio | Tiempo declarado | Paciente y estado aportados | Esperado | Conocimiento mínimo |
|---|---|---|---|---|---|
| T-01 | Dificultad para respirar; encías azuladas/moradas. | En curso; inicio sin dato. | Perro mestizo, 6 años; resto sin dato. | 🔴 | P1 universal; dos alarmas no se promedian. |
| T-02 | Convulsión en curso. | Ahora, duración 6 minutos. | Gata, 3 años; resto sin dato. | 🔴 | P1: basta que siga en curso; no exigir superar 5 minutos para activar la alarma. |
| T-03 | Atropello/traumatismo; hemorragia que no se detiene. | Sangrado actual; hora del atropello sin dato. | Perro, 4 años. | 🔴 | P1 prevalece sobre regla de trauma. |
| T-04 | Intentos repetidos de orinar con salida mínima; dolor/maullido al intentarlo. | Inicio sin dato. | Gato macho castrado, 5 años; se declara «por lo demás bien». | 🔴 | Combinación de signos urinarios de alarma, no exigir que el propietario diagnostique obstrucción ni confundirlo con simple frecuencia urinaria. |
| T-05 | Arcadas sin producir vómito; abdomen distendido; inquietud. | Inicio sin dato. | Perro gran danés, 7 años; raza gigante. | 🔴 | Regla explícita combinación + raza gigante; no confundir arcadas con vómito aislado. |
| T-06 | Mucha sed; decaimiento; secreción genital. | Celo hace 5 semanas; inicio de otros signos sin dato. | Perra, 8 años, no esterilizada. | 🟠 | Sexo/estado reproductivo y evento «último celo»; sospecha de piometra como apoyo, no diagnóstico automático. |
| T-07 | Atropello/traumatismo, aunque camina. | Sin dato. | Perro, 5 años; camina normalmente. | 🟠 | Regla de trauma; caminar no anula el riesgo. |
| T-08 | Mordió/ingirió lirios identificados. | Sin dato. | Gato, 2 años, sin signos visibles. | 🔴 | Sustancia + toxicidad por especie; no exige un síntoma posterior. |
| T-09 | Respiración ruidosa después de ejercicio; cansancio tras ejercicio; exposición a calor. | Después de caminar; intervalo exacto sin dato. | Perro bulldog francés, 3 años. | 🟠 | Modificador braquicéfalo; cansancio no equivale por sí solo a colapso ni respiración ruidosa a dificultad respiratoria P1; añadir cualquiera de esas alarmas da rojo. |
| T-10 | Las mismas marcas de T-09. | Igual que T-09. | Perro labrador, 3 años. | 🟡 | Control sin modificador braquicéfalo; no clasificar «cansancio tras ejercicio» como decaimiento sistémico de P3. |
| T-11 | Vómito; diarrea con sangre; falta de apetito. | Sin dato. | Perro, 3 meses, vacunas incompletas. | 🟠 + aislamiento | P3 y contexto cachorro/vacunación; regla de posible contagio separada del color. |
| T-12 | Vómito, un episodio; diarrea sin sangre declarada. | Inicio sin dato; un vómito. | Perro, 6 años, vacunado, activo. | 🟡 | Episodio aislado y ausencia de marcas de alarma; no inventar vacunas específicas desde una edad. |
| T-13 | No come; se esconde. | Sin comer 3 días. | Gato, 9 años, obesidad declarada. | 🟠 | Anorexia sostenida + especie/condición corporal; peso solo no prueba obesidad; fuente para la ventana usada. |
| T-14 | Ingesta de chicle sin azúcar; posible exposición a xilitol. | 20 minutos. | Perro, 15 kg; edad/raza sin dato. | 🟠 | Sospecha de sustancia y tiempo; no afirmar ingrediente, dosis ni seguridad cuando se desconocen. |
| T-15 | Administración/ingesta de acetaminofén (sinónimo de paracetamol). | Sin dato. | Gato; fiebre referida. | 🔴 | Mismo nodo fármaco y misma relación de toxicidad que prescripción; no inventar dosis. |
| T-16 | Ingesta de chocolate; sin signos declarados. | 48 horas. | Perro; peso, tipo y cantidad de chocolate sin dato. | 🟡 provisional + revisión | P5 contextual, no descuento general por tiempo; faltan cantidad/tipo; decisión del usuario durante A y validación clínica pendiente. |
| T-17 | Vómito repetido; no bebe; decaimiento marcado; encías secas/deshidratación. | 4 vómitos hoy. | Perro, 10 años; raza/tamaño sin dato. | 🟠 | P3 sube una vez la evidencia moderada; no sumar sucesivamente deshidratación, número de síntomas y una geriatría inventada hasta rojo. |
| T-18 | Episodio convulsivo ya finalizado; primera crisis. | Ayer; recuperado ahora. | Perro, 4 años, activo y come. | 🟠 | Primera crisis resuelta es distinta de convulsión en curso; recuperación no borra esa regla. |
| T-19 | Episodio convulsivo finalizado, único, corto y habitual; recuperado. | Pasado, duración exacta sin dato. | Perro con epilepsia conocida y tratamiento; edad/raza sin dato. | 🟡 | Alerta global de epilepsia + medicación conocida, o historia autorizada; no interpretar una consulta protegida; sin evidencia de patrón habitual, revisión. |
| T-20 | No come; no defeca. | Desde ayer. | Conejo, 2 años. | 🟡 + revisión inmediata antes de agendar | P6: conejo sin cobertura específica; no cargar naranja como regla aprobada; ninguna respuesta tranquilizadora elimina la revisión. |
| T-21 | Dificultad para respirar, con ayuda visible «mueve mucho la cola al respirar»; plumaje erizado. | Sin dato. | Loro; edad/raza sin dato. | 🔴 | P1 universal; la marca explícita sustituye la interpretación automática del relato, aunque aves no tengan cobertura específica. |
| T-22 | «Otro / no sé»; nota opcional «está raro». | «No sé». | Perro; resto sin dato. | 🟡 + revisión | P6; una nota sola no satisface la captura ni calcula un nivel. |
| T-23 | Buscar «bomita sangre» y «desaido»; confirmar Vómito con sangre y Decaimiento. | «No sé» si no se conoce. | Gato; resto sin dato. | 🟠 | Búsqueda tolerante + selección explícita; sangre en vómito no equivale por sí sola a hemorragia incontrolable P1; P3/combinación. |
| T-24 | Control preventivo / vacunación, sin síntomas de alarma marcados. | No aplica, motivo preventivo explícito. | Perro, 3 años. | 🟢 | Entrada reconocida del catálogo para atención preventiva; añadir P1 sigue dando rojo. |
| T-25 | Picor de oídos leve; sin otras alarmas marcadas. | Una semana. | Gato, 4 años, come y juega. | 🟢 | Regla de molestia leve estable; no tratar la ausencia de marcas como síntoma desconocido si la entrada sí está cubierta. |
| T-26 | Cojera leve estable; mantiene actividad habitual. | Un mes. | Perro, 6 años. | 🟢 | Intensidad/estabilidad explícitas y cronicidad; no aplicar esa regla a cojera aguda, incapacidad de apoyar ni trauma. |

---

## 3. Casos de comportamiento de la agenda

Aquí no se prueba el nivel, sino qué hace el sistema con él.

| ID | Situación | Comportamiento esperado | Estado en la especificación |
|---|---|---|---|
| O-01 | Sale 🔴 desde el portal. | No se agenda: se muestra un mensaje de emergencia con el teléfono de la clínica y se notifica a la clínica. | Definido (RN-411, HU-4.15) |
| O-02 | Sale 🔴 a las 11 p. m., con la clínica cerrada. | No debe prometer atención: avisa que la clínica está cerrada y recomienda acudir a un servicio de urgencias 24 horas. | Definido (RN-421, RF-4.13) |
| O-03 | Sale 🟠 y no hay espacio. | Sobrecupo en el próximo bloque del veterinario adecuado, con aviso al veterinario. | Definido (HU-4.13) |
| O-04 | Dos casos 🟠 compiten por el mismo sobrecupo. | Primero el que llegó antes; un veterinario puede cambiar el orden con motivo y auditoría. | Definido (RN-422) |
| O-05 | Llegan muchos 🟠 el mismo día. | Un tope de sobrecupos por bloque; al superarlo, se avisa al personal para decidir. | Definido (RN-422) |
| O-06 | Un propietario agendó en 🟢 para el viernes y el miércoles la mascota empeora. | Al actualizar los síntomas se recalcula el nivel; si sube, la cita se reubica y se avisa. | Definido (RN-423, HU-4.17) |
| O-07 | El veterinario baja un caso de 🔴 a 🟢. | Se permite con motivo obligatorio y queda en auditoría (P7). | Definido (RN-419, HU-4.18) |
| O-08 | El propietario exagera los síntomas para conseguir un sobrecupo. | El personal reclasifica al llegar y queda registrado; si se repite, la clínica puede verlo en el historial del propietario. | Definido (RN-419, HU-4.18) |
| O-09 | Llega un 🔴 de una mascota que no está registrada. | Registro rápido de emergencia («ficha por completar»). | Definido (HU-4.15) |
| O-10 | La clínica en plan gratuito llegó al límite de citas del mes y entra un 🔴. | La urgencia se atiende igual; el límite no aplica a urgencias (P8). | Definido (RN-420) |
| O-11 | La alergia de la mascota está registrada en otra clínica. | El triage y las alertas usan la alergia por la historia compartida. | Definido (RN-113) |

---

## 4. Huecos que destapó esta batería (resueltos)

| Hueco | Solución |
|---|---|
| No estaba definido cómo se calcula el nivel. | RN-416 a RN-418, HU-2.11, RF-2.12. |
| Captura de síntomas solo en texto libre. | Captura estructurada con lista, texto y tiempo (RN-424). |
| Las urgencias se bloqueaban por el límite del plan. | Excepción explícita (RN-420; RN-006 y RN-007 ajustadas; el flujo de agendamiento calcula el triage antes de verificar el límite). |
| Urgencia con la clínica cerrada, desempate, tope de sobrecupos y re-triage. | RN-421 a RN-423, HU-4.17, RF-4.11 y RF-4.13. |
| Aviso de caso contagioso. | RN-425. |

## 5. Cómo registrar una prueba

| Campo | Contenido |
|---|---|
| Caso | ID de la batería (p. ej. T-04). |
| Fecha y versión | Fecha de la prueba y versión de Zooki. |
| Nivel obtenido | Lo que calculó el sistema. |
| ¿Coincide? | Sí / No. |
| Observación | Si no coincide: qué síntoma no reconoció, qué regla faltó o qué se debe ajustar en el grafo. |

_Referencia de orientación sobre priorización en urgencias veterinarias: [The Art of Triage… Who Goes First? (VetTechPrep)](https://blog.vettechprep.com/how-to-triage-pet-patients/). Los niveles deben validarse con un médico veterinario._

_Documento elaborado por Juan Sebastián Carvajal Home — Ficha 3142784 · SENA, Florencia (Caquetá) · 2026. Uso académico._
