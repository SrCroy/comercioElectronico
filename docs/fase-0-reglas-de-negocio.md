# Mechanical Bull — Fase 0: Reglas de negocio

> Documento vivo. Las cifras marcadas con **(propuesta)** son recomendaciones iniciales para validar con el equipo y ajustar según la competencia local.

## 1. Datos generales

| Tema | Decisión |
|---|---|
| País | El Salvador |
| Moneda | Dólar (USD) |
| Impuesto | IVA 13 %, **precios mostrados con IVA incluido** |
| Plataforma | WordPress + WooCommerce (tema hijo de Astra + plugin propio `mb-core`) |
| Ubicación | Km 144, Carretera al Cuco, Cantón El Jute, San Miguel |
| Entorno actual | Local, en servidor Docker propio. Hosting y dominio: pendiente |
| Líneas de producto | Celulares (Android, iPhone), Laptops, PC de escritorio y Gamer, Herramientas |

## 2. Catálogo

- **Categoría = tipo de pieza** (Celulares › Pantallas, Laptops › RAM, PC › Tarjetas de video…).
- **Marcas y modelos = compatibilidad** (Apple › iPhone 13, Samsung › Galaxy A54…). Un producto puede ser compatible con varios modelos.
- **Calidad** obligatoria en cada repuesto: Original, OEM, Alternativo (AAA), Incell, OLED, Nuevo, Reacondicionado. Se muestra siempre al cliente (valor de *Honestidad*).
- **Disponibilidad**:
  - *En stock*: entrega inmediata.
  - *Por encargo*: se muestra "llega en aprox. X días hábiles" y el anticipo requerido.

### Estrategia de arranque sin catálogo
1. Cargar primero **lo que más se repara**: pantallas, baterías y puertos de carga de los ~20 modelos más comunes en el país.
2. Componentes de PC/Gamer (GPU, CPU, tarjetas madre) mayormente **por encargo**: precios cambian rápido → revisar precios **cada semana**.
3. Botón **"¿No encuentras tu pieza? Cotízala por WhatsApp"** para todo lo que no esté publicado. Cada consulta repetida = candidato a publicar.

## 3. Garantías

### Piezas vendidas
| Tipo de pieza | Garantía **(propuesta)** |
|---|---|
| Componentes electrónicos sellados: RAM, SSD/HDD, procesadores, fuentes de poder, tarjetas de video | **12 meses** |
| Piezas de desgaste o de instalación delicada: pantallas, baterías, flex, puertos de carga, cámaras | **6 meses** |
| Reacondicionadas | 3 meses |

**No cubre:** golpes, humedad, pantallas con presión o rayones, pieza instalada por terceros con daño por mala instalación, **sello de garantía roto o alterado**.

> Recomendación: poner un **sticker de garantía con código/QR** en cada pieza vendida. Sin sticker o con sticker roto, no aplica.

### Reparaciones
| Caso | Garantía |
|---|---|
| Reparación hecha **por Mechanical Bull** con cambio de pieza | La garantía de la pieza: **6 o 12 meses** |
| Mano de obra / microelectrónica (soldadura en placa) | **6 meses** sobre el trabajo realizado **(propuesta)** |
| Equipos con daño por líquido | **30 días** o sin garantía: la corrosión puede seguir avanzando **(propuesta)** |
| Reparación hecha **por terceros** | **Sin garantía** |
| Equipo abierto por terceros **después** de nuestra reparación | Se pierde la garantía |

- La garantía cuenta desde la **fecha de entrega** al cliente.
- Cubre solo la pieza o el trabajo realizado, no otras fallas del equipo.
- Las condiciones se entregan **por escrito** en la orden y la factura (requisito de la Defensoría del Consumidor y valor de *Honestidad*).

## 4. Diagnóstico **(propuesta)**

**Diagnóstico gratis si el cliente aprueba la reparación.** Si la rechaza, se cobra:

| Equipo | Costo si rechaza |
|---|---|
| Celular | $5 |
| Laptop | $10 |
| PC de escritorio / Gamer | $10 |
| Diagnóstico avanzado (placa, equipo mojado, no enciende) | $15 |

Por qué así:
- "Diagnóstico gratis" atrae clientes; el cobro al rechazar paga el tiempo del técnico y evita que usen el taller solo para "cotizar".
- Encaja con la voz de marca: *"Si tu equipo tiene arreglo, te lo decimos. Si no vale la pena repararlo, también."*
- **Equipos enviados**: el envío de regreso lo paga el cliente si rechaza el presupuesto.
- **Abandono**: equipos no retirados en **90 días** después de avisar que están listos pasan a disposición del taller (avisar al recibir y por escrito).

## 5. Tipos de cliente y descuentos **(propuesta)**

| Tipo | Requisito | Beneficio |
|---|---|---|
| Cliente final | Ninguno | Precio público |
| **Técnico** | Solicitud aprobada por admin/secretaria (DUI + foto del taller o redes del negocio) | **10 % de descuento** sobre precio público, desde la primera unidad |
| **Mayorista** | Solicitud aprobada + NIT/NRC + **compra mínima $150 por pedido** | **15 %** · pedidos de **$500 o más: 20 %** |

Reglas de protección del margen:
1. **Nunca vender por debajo de costo + 15 %**, aunque aplique descuento. Si un producto tiene margen bajo (ej. pantallas originales de iPhone), se marca **"sin descuento B2B"**.
2. Productos **por encargo**: el descuento aplica, pero requieren anticipo.
3. Los precios B2B **solo se ven con sesión iniciada** y cuenta aprobada.
4. Revisar cada 3 meses: si un técnico compra poco, puede volver a precio público.

> Mejor práctica a mediano plazo: en lugar de un % fijo, guardar **costo** de cada pieza y calcular precio público y B2B por margen. Se prepara en la Fase 3.

## 6. Pagos

| Método | Uso |
|---|---|
| Wompi | Tarjeta débito/crédito (integración WooCommerce) |
| PayPal | Plugin oficial WooCommerce PayPal Payments |
| Chivo Wallet | Manual: cliente sube comprobante, secretaria valida. **Confirmar estado actual del servicio** |
| Transferencia bancaria | Manual con comprobante |
| Pago al retirar en local | Solo retiro en tienda; reserva **48 h**, luego se libera el stock |

- Piezas con envío: **pago completo antes de despachar**.
- Piezas por encargo: **anticipo 50 %** (el cobro parcial automático requiere un plugin de depósitos; se evalúa en Fase 1).
- Reparaciones: se paga al aprobar presupuesto o al entregar.
- Piezas por encargo **no tienen devolución**, salvo defecto de fábrica.

## 7. Facturación

- Checkout pregunta: **Factura consumidor final** o **Comprobante de Crédito Fiscal (CCF)**.
- Para CCF: NIT, NRC, razón social y giro.
- Fase 1: solo se guardan los datos. Integración con proveedor de **DTE** de Hacienda: fase posterior.

## 8. Envíos

| Zona | Opción |
|---|---|
| Retiro en local | Gratis |
| San Miguel | Domicilio $2.50 (motorista/delivery) |
| Resto del país | Courier $5.00 (comparar C807 Xpress, Cargo Expreso, Correos de El Salvador) |

Equipos enviados al taller: orden en la web → instrucciones de envío → al recibir se registra **con fotos** del estado del equipo.

## 9. Roles

| Rol | Puede | No puede |
|---|---|---|
| Dueño (admin) | Todo | — |
| Secretaria / Vendedor | Pedidos, productos, clientes, órdenes de reparación, aprobar técnicos/mayoristas | Ajustes de la tienda, reportes, costos y márgenes |
| Técnico | Sus órdenes asignadas, diagnóstico, fotos, cambiar estado | Ventas, costos, pagos |
| Cliente (final / técnico / mayorista) | Comprar, ver pedidos, garantías y reparaciones | — |

## 10. Pendientes

- [ ] Validar cifras de diagnóstico y descuentos con el equipo.
- [ ] Número de WhatsApp del negocio.
- [ ] Logo en vector (SVG/AI/PDF).
- [ ] Corregir manual de marca (tabla de colores, "Mecanical", contactos de plantilla).
- [ ] Confirmar plugin de Wompi para El Salvador y estado de Chivo Wallet.
- [ ] Elegir y registrar dominio (mechanicalbull.com / .com.sv).
