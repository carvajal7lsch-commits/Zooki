# Presupuesto y Retorno de la Inversión (ROI) — Zooki

> **Revisión 1.0** · Análisis económico de la plataforma Zooki v2.0 · SENA ADSO — Ficha 3142784
>
> Tasa de referencia: **TRM ≈ $3.273 COP/USD** (5 de octubre de 2026). Todos los montos están en pesos colombianos (COP) salvo indicación; el equivalente en USD es aproximado.
>
> Los **límites** y el **precio** de los planes los fijan las [Reglas de Negocio](ReglasNegocio.md#módulo-0--plataforma-clínicas-planes-y-suscripción-nuevo-v2): RN-003 (límites del plan gratuito) y RN-008 (precio mensual y anual).

## 1. Resumen ejecutivo

Zooki es un proyecto formativo de un único aprendiz, con una inversión estimada de **$5.800.000 COP (~$1.772 USD)**.
Profesional cuesta **$40.000 COP/mes** o **$400.000 COP/año**.
Con la oferta «Paga 1, lleva 2», una clínica nueva que paga mensualmente durante doce meses realiza once pagos ($440.000); el equilibrio operativo medio requiere dos clínicas y recuperar la inversión dentro del primer año requiere quince.
Son proyecciones brutas con los costos existentes, antes de comisiones de pasarela y otros costos no cuantificados.

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
| Gratuito | $0 | Todas las capacidades, **incluidos los dos grafos en funcionamiento**, limitadas por **volumen**: 50 mascotas vinculadas, 100 citas por mes y 2 cuentas de personal (RN-003). |
| Profesional | **$40.000 COP / mes por clínica** · **$400.000 COP / año** anticipado: dos meses gratis (equivale a $33.333,33/mes; ahorro del16,67 % frente a doce mensualidades) | Las mismas capacidades **sin límites de volumen**. |

**Método de facturación.** Suscripción mensual o anual con Wompi (HU-0.5), preservando importes de las órdenes existentes.
La oferta de lanzamiento «Paga 1, lleva 2» concede dos meses calendario al primer pago mensual aprobado de cada clínica sin aprobados previos; no aplica al anual ni a pagos posteriores y puede apagarse en un único interruptor.
No se cambia la inversión ni los costos de infraestructura de este estudio por modificar el precio.

> A diferencia de un freemium que oculta lo principal, en Zooki el plan gratuito incluye los grafos funcionando para que la clínica compruebe su valor **antes** de pagar; lo que limita es el volumen. Ese es el gancho de conversión. Las capas avanzadas (aprendizaje del grafo con los datos de la propia clínica) se prevén para una versión posterior (RF-F.8) y serán un beneficio adicional del plan profesional.

**Referencia de mercado.** [OkVet](https://okvet.co/okvet-planes/), un software veterinario colombiano, cobra **$132.999 COP/mes** por su plan Pro (consultado el 5 de octubre de 2026), que incluye facturación electrónica, inventario y hospitalización; su plan gratuito admite 50 agendamientos al mes. Zooki se ubica por debajo porque no tiene esos módulos, pero ofrece el triage y el soporte a la decisión clínica, que aquel no tiene.

## 5. Proyección de retorno (ROI)

**Supuestos:** inversión inicial $5.800.000; costo operativo compartido $50.000/mes ($600.000/año); todas las clínicas de pago entran al comienzo, permanecen doce meses y pagan oportunamente.
La oferta está activa y ninguna tiene pagos aprobados previos.
No se suman meses gratis al anual.

| Modalidad | Cobros por clínica durante12 meses | Ingreso anual por clínica |
|---|---|---|
| Mensual con oferta inicial |11 pagos de $40.000; el primero cubre meses1 y2 | $440.000 |
| Mensual sin oferta |12 pagos de $40.000 | $480.000 |
| Anual anticipado |1 pago de $400.000 por12 meses | $400.000 |

### Primer año mensual con oferta

| Clínicas de pago | Ingreso anual | Costo operativo anual | Utilidad anual | ROI1.er año | Recuperación aproximada |
|---|---|---|---|---|---|
|3 | $1.320.000 | $600.000 | $720.000 | −87,6 % |85 meses |
|5 | $2.200.000 | $600.000 | $1.600.000 | −72,4 % |40 meses |
|10 | $4.400.000 | $600.000 | $3.800.000 | −34,5 % |18 meses |
|15 | $6.600.000 | $600.000 | $6.000.000 | +3,4 % |12 meses |
|20 | $8.800.000 | $600.000 | $8.200.000 | +41,4 % |9 meses |

Utilidad anual = ingreso anual − $600.000.
ROI del primer año = (utilidad anual − $5.800.000) ÷ $5.800.000 ×100.
La recuperación aproximada de la cohorte mensual con oferta resuelve:40.000 × N × (m −1) −50.000 × m =5.800.000, para m≥2.
Se redondea hacia arriba al mes completo y se supone permanencia también después del primer año cuando haga falta.
No es una promesa de ingresos ni una estimación descontada; una adopción gradual o bajas alargan el plazo.

## 6. Punto de equilibrio

El equilibrio operativo estable requiere **2 clínicas**:2 × $40.000 > $50.000 al mes.
Con la oferta, el ingreso medio del primer año también cubre operación desde dos clínicas:2 × $440.000 > $600.000 al año.
Durante los dos meses iniciales, el ingreso devengado equivale a $20.000/mes por clínica y se requieren **3 clínicas** para cubrir ese tramo.
En caja, el segundo mes de la cohorte no tiene un nuevo pago: se debe reservar parte del cobro inicial para cubrirlo.
El anual requiere dos clínicas para cubrir operación del año ($800.000 > $600.000), aunque se cobra anticipadamente.

| Modalidad | Clínicas mínimas para recuperar inversión y operación en12 meses |
|---|---|
| Mensual con oferta |15: techo(($5.800.000 + $600.000) ÷ $440.000) |
| Mensual sin oferta |14: techo(($5.800.000 + $600.000) ÷ $480.000) |
| Anual anticipado |16: techo(($5.800.000 + $600.000) ÷ $400.000) |

Con15 clínicas mensuales y oferta se obtiene una utilidad anual de $6.000.000, superior a la inversión de $5.800.000.
El descuento anual es del16,67 % frente a doce mensualidades ordinarias; comparado con el primer año mensual promocional de $440.000, su ahorro es del9,09 %.

## 7. Supuestos y notas

- Los **límites** del plan gratuito (RN-003) y el **precio** del plan profesional (RN-008) están fijados en las reglas de negocio. El precio es el inicial: el super-administrador puede cambiarlo en el catálogo de planes.
- Las **145 h** son una estimación (no se llevó registro de horas) y aumentan con el desarrollo; cambiarlas cambia la inversión y la recuperación en la misma proporción.
- Se supone que todas las clínicas de pago pagan desde el primer mes; una adopción gradual alarga la recuperación.
- Montos en COP; USD a TRM $3.273 (5-oct-2026), solo de referencia.
- El **costo operativo** y el **número de clínicas** son parámetros editables; cámbialos para ver otros escenarios.
- Proyección bruta: no se descuentan comisiones Wompi, costos de marketing, soporte ni otros costos no cuantificados; incorporarlos reduce ROI y puede elevar los umbrales. Los precios vigentes son total sin IVA según RN-008.
- Los servicios externos se usan en su plan gratuito, que tiene topes: Brevo, por ejemplo, permite 300 correos al día. Con muchas clínicas los recordatorios superan ese tope y el costo operativo debe incluir un plan de pago de correo.
- La ventaja económica del modelo SaaS multi-inquilino es que el costo de infraestructura se **comparte** entre todas las clínicas: sumar una clínica más casi no agrega costo.

_Documento elaborado por Juan Sebastián Carvajal Home — Ficha 3142784 · SENA, Florencia (Caquetá) · 2026. Uso académico._
