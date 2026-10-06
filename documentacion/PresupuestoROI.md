# Presupuesto y Retorno de la Inversión (ROI) — Zooki

> **Revisión 1.0** · Análisis económico de la plataforma Zooki v2.0 · SENA ADSO — Ficha 3142784
>
> Tasa de referencia: **TRM ≈ $3.273 COP/USD** (5 de octubre de 2026). Todos los montos están en pesos colombianos (COP) salvo indicación; el equivalente en USD es aproximado.
>
> Los **límites** y el **precio** de los planes los fijan las [Reglas de Negocio](ReglasNegocio.md#módulo-0--plataforma-clínicas-planes-y-suscripción-nuevo-v2): RN-003 (límites del plan gratuito) y RN-008 (precio mensual y anual).

## 1. Resumen ejecutivo

Zooki es un proyecto formativo desarrollado por un único aprendiz. Este documento separa dos cosas que suelen confundirse: **lo que costó construirlo** (la inversión) y **cuánto podría retornar** si se ofrece como servicio (SaaS) a varias clínicas. La inversión real de desarrollo es de **≈ $5.800.000 COP (~$1.772 USD)**. Con el precio del plan profesional ($100.000 COP/mes por clínica), la plataforma **recupera esa inversión dentro del primer año a partir de 6 clínicas de pago**; con 5 clínicas tarda unos 13 meses. Por encima de ese punto el retorno crece rápido, porque el costo de atender una clínica más es casi nulo.

## 2. Inversión del proyecto (costo real)

El costo real del proyecto es el **esfuerzo de desarrollo** del aprendiz, valorado a su tarifa. La tarifa de $40.000 por hora es la de un desarrollador junior, aunque el aprendiz cubre todos los roles (producto, análisis, backend, frontend, base de datos, despliegue y pruebas).

| Concepto | Cantidad | Valor | Total |
|---|---|---|---|
| Horas de desarrollo (todo el proyecto, v1 + documentación v2) | 145 h | $40.000 / h | **$5.800.000 COP** |
| Infraestructura (VPS, dominio) — ya en uso para otros proyectos | — | marginal | ~$0–$600.000 / año |
| Herramientas y servicios (GitHub, Docker/Dokploy, PHPMailer con Brevo, Algolia, Cloudflare Turnstile, Google Identity) | — | planes gratuitos / propias | $0 |
| **Inversión total de desarrollo** | | | **≈ $5.800.000 COP (~$1.772 USD)** |

> No se llevó un registro de horas. Las 145 h son una estimación del tiempo acumulado hasta octubre de 2026, y la cifra crece a medida que avanza el desarrollo. La infraestructura no se contabiliza como costo nuevo porque el VPS ya existe y es compartido con otros proyectos del aprendiz.

### Distribución aproximada del esfuerzo

| Etapa | Horas aprox. |
|---|---|
| Construcción v1.x (módulos, seguridad, despliegue) | ~95 h |
| Documentación v2 (ERS, reglas, historias, requisitos, MER, modelos y grafos) | ~35 h |
| Pruebas, portal de documentación y ajustes | ~15 h |
| **Total** | **~145 h** |

## 3. Valoración referencial de mercado

Si el mismo trabajo lo hiciera un equipo con un especialista por rol, cada hora se pagaría a la tarifa de ese rol. Las tarifas son de referencia y parten de la de un junior ($40.000/h):

| Rol | Horas | Tarifa | Total |
|---|---|---|---|
| Producto y análisis (requisitos y documentación) | 35 h | $45.000 / h | $1.575.000 |
| Backend | 45 h | $45.000 / h | $2.025.000 |
| Frontend | 30 h | $40.000 / h | $1.200.000 |
| Base de datos y DevOps | 15 h | $50.000 / h | $750.000 |
| QA (pruebas) | 12 h | $40.000 / h | $480.000 |
| Scrum Master | 8 h | $50.000 / h | $400.000 |
| **Total** | **145 h** | | **≈ $6.430.000 COP (~$1.964 USD)** |

El aprendiz cobra todos los roles a la tarifa de junior, así que su inversión ($5.800.000) queda por debajo de la valoración de mercado. Es una referencia del valor del trabajo, no un desembolso real del proyecto.

## 4. Modelo de ingresos y facturación (SaaS freemium)

Zooki v2.0 opera como plataforma multi-inquilino con una estrategia **freemium**:

| Plan | Precio (RN-008) | Qué incluye |
|---|---|---|
| Gratuito | $0 | Todas las capacidades, **incluidos los dos grafos en funcionamiento**, limitadas por **volumen**: 5 mascotas vinculadas, 30 citas por mes y 2 cuentas de personal (RN-003). |
| Profesional | **$100.000 COP / mes por clínica** · **$960.000 COP / año** con pago anual anticipado (equivale a $80.000/mes, 20 % de descuento) | Las mismas capacidades **sin límites de volumen**. |

**Método de facturación.** El cobro es por **suscripción** (mensual, o anual con descuento). El **cobro real mediante pasarela de pago se pospone a una fase posterior** (RF-F.1); en la v2.0 el sistema gestiona el **estado del plan y sus límites** (RN-003), y congela los beneficios si la suscripción no está al día.

> A diferencia de un freemium que oculta lo principal, en Zooki el plan gratuito incluye los grafos funcionando para que la clínica compruebe su valor **antes** de pagar; lo que limita es el volumen. Ese es el gancho de conversión. Las capas avanzadas (aprendizaje del grafo con los datos de la propia clínica) se prevén para una versión posterior (RF-F.8) y serán un beneficio adicional del plan profesional.

**Referencia de mercado.** [OkVet](https://okvet.co/okvet-planes/), un software veterinario colombiano, cobra **$132.999 COP/mes** por su plan Pro (consultado el 5 de octubre de 2026), que incluye facturación electrónica, inventario y hospitalización; su plan gratuito admite 50 agendamientos al mes. Zooki se ubica por debajo porque no tiene esos módulos, pero ofrece el triage y el soporte a la decisión clínica, que aquel no tiene.

## 5. Proyección de retorno (ROI)

**Supuestos:** inversión inicial $5.800.000 · plan profesional **$100.000/mes** por clínica (RN-008) · costo operativo de la plataforma ≈ $50.000/mes (hosting compartido, repartido entre todas las clínicas) · se consideran solo las clínicas de **pago**.

| Clínicas de pago | Ingreso anual | Costo operativo anual | Utilidad anual | ROI 1.er año | Recuperación (payback) |
|---|---|---|---|---|---|
| 3 | $3.600.000 | $600.000 | $3.000.000 | −48% | ~23 meses |
| 5 | $6.000.000 | $600.000 | $5.400.000 | −7% | ~13 meses |
| 10 | $12.000.000 | $600.000 | $11.400.000 | +97% | ~6 meses |
| 15 | $18.000.000 | $600.000 | $17.400.000 | +200% | ~4 meses |
| 20 | $24.000.000 | $600.000 | $23.400.000 | +303% | ~3 meses |

**Fórmulas usadas:**

- Utilidad anual = Ingreso anual − Costo operativo anual.
- ROI 1.er año = (Utilidad anual − Inversión inicial) ÷ Inversión inicial × 100.
- Recuperación (meses) = Inversión inicial ÷ (ingreso mensual − costo operativo mensual).

**Lectura.** Con 3 clínicas de pago la inversión tarda casi dos años en recuperarse, y con 5 apenas pasa del año (unos 13 meses). A partir de **6 clínicas** se recupera dentro del primer año, y de ahí hacia arriba el retorno crece rápido (con 20 clínicas el ROI del primer año supera el 300%), porque el costo marginal de atender una clínica más es casi nulo: la misma instancia multi-inquilino sirve a todas. Si las clínicas pagan el año por anticipado ($80.000 de mes efectivo), el ingreso baja un 20 % pero mejora la caja inicial.

## 6. Punto de equilibrio

Con los supuestos anteriores, el **punto de equilibrio operativo** (cubrir el costo mensual de la plataforma) se alcanza con **1 clínica de pago** ($100.000 > $50.000/mes). El **punto de equilibrio de la inversión** (recuperar los $5.800.000 dentro del primer año) se logra con **6 clínicas** que pagan mes a mes (unos 10,5 meses), o con **7** si todas pagan el año anticipado. Con 10 clínicas se recupera en unos 6 meses y con 20 en unos 3.

## 7. Supuestos y notas

- Los **límites** del plan gratuito (RN-003) y el **precio** del plan profesional (RN-008) están fijados en las reglas de negocio. El precio es el inicial: el super-administrador puede cambiarlo en el catálogo de planes.
- Las **145 h** son una estimación (no se llevó registro de horas) y aumentan con el desarrollo; cambiarlas cambia la inversión y la recuperación en la misma proporción.
- Se supone que todas las clínicas de pago pagan desde el primer mes; una adopción gradual alarga la recuperación.
- Montos en COP; USD a TRM $3.273 (5-oct-2026), solo de referencia.
- El **costo operativo** y el **número de clínicas** son parámetros editables; cámbialos para ver otros escenarios.
- No se incluye impuesto ni pasarela de pago (fase futura); tampoco costos de marketing o soporte, que escalarían con la adopción.
- Los servicios externos se usan en su plan gratuito, que tiene topes: Brevo, por ejemplo, permite 300 correos al día. Con muchas clínicas los recordatorios superan ese tope y el costo operativo debe incluir un plan de pago de correo.
- La ventaja económica del modelo SaaS multi-inquilino es que el costo de infraestructura se **comparte** entre todas las clínicas: sumar una clínica más casi no agrega costo.

_Documento elaborado por Juan Sebastián Carvajal Home — Ficha 3142784 · SENA, Florencia (Caquetá) · 2026. Uso académico._
