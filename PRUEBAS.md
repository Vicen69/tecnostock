# PRUEBAS.md – TecnoStock

**Autor:** Vicente Castro  
**Proyecto:** TecnoStock – Sistema de inventario (Caso 1)  
**Ejecución:** XAMPP (Apache + MySQL), PHP 8 con PDO, `http://localhost/Tecnostock/`

Los casos siguientes se ejecutaron sobre la aplicación y quedaron registrados en el vídeo explicativo (`tecnostock_final.mp4`).

## Datos de prueba

| Concepto | Valor |
|---|---|
| Base de datos | `tecnostock` (script `sql/tecnostock.sql`) |
| Usuario inicial | `admin@tecnostock.cl` / clave `admin123` |
| Usuario de BD | `root` / clave vacía (valores por defecto de XAMPP) |
| Productos de ejemplo | los cargados por el script SQL, con categorías y stock mínimo |
| Movimientos de ejemplo | una entrada válida y una salida válida, más una salida rechazada |

## Casos de prueba

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| P01 | Login con clave incorrecta | Ingresar `admin@tecnostock.cl` con una clave errónea. | Acceso denegado, mensaje de credenciales incorrectas, sin sesión iniciada. | ✅ Rechazado |
| P02 | Login correcto | Ingresar con `admin@tecnostock.cl` / `admin123`. | Sesión iniciada y acceso a `inicio.php`. | ✅ Correcto |
| P03 | Cierre de sesión | Pulsar "Cerrar sesión". | Sesión destruida y redirección al login. | ✅ Correcto |
| P04 | Listado de productos | Abrir `productos.php`. | Tabla con código, nombre, categoría, precio, stock actual ystock mínimo. | ✅ Correcto |
| P05 | Búsqueda de productos | Buscar por nombre, código o categoría. | El listado muestra solo las coincidencias (consulta `LIKE` preparada). | ✅ Correcto |
| P06 | Alerta de stock bajo | Revisar un producto con `stock_actual < stock_minimo`. | Fila destacada y etiqueta "Stock bajo"; sin alerta en productos desactivados o con stock igual al mínimo. | ✅ Correcto |
| P07 | Alta de producto con dato inválido | Enviar el formulario con precio o cantidad negativa. | Validación en JavaScript y en PHP; no se inserta el producto. | ✅ Rechazado |
| P08 | Alta de producto válida | Registrar producto con código, nombre, categoría, precio y stocks válidos. | Producto creado; si tiene stock inicial, se registra una entrada "Stock inicial" en la misma transacción. | ✅ Correcto |
| P09 | Código duplicado | Intentar crear otro producto con el mismo código. | Restricción `UNIQUE (codigo)`; mensaje claro de duplicado. | ✅ Rechazado |
| P10 | Edición de producto | Modificar precio, descripción, categoría y stock mínimo. | Cambios guardados con `UPDATE` preparado; el stock actual sigue bloqueado en el formulario. | ✅ Correcto |
| P11 | Edición con precio negativo | Enviar un precio negativo en el formulario de edición. | Validación rechaza el valor; no se guarda el cambio. | ✅ Rechazado |
| P12 | Movimiento de entrada válido | Registrar una entrada de existencias. | Se inserta el movimiento y aumenta el stock en una sola transacción. | ✅ Correcto |
| P13 | Movimiento de salida válido | Registrar una salida con cantidad menor o igual al stock. | Se inserta el movimiento y disminuye el stock en una sola transacción. | ✅ Correcto |
| P14 | Movimiento rechazado por exceder el stock | Registrar una salida mayor que el stock disponible. | Movimiento rechazado con mensaje; el stock permanece intacto (`SELECT ... FOR UPDATE` + `CHECK`). | ✅ Rechazado |
| P15 | Movimiento con cantidad no positiva | Registrar una entrada/salida con cantidad 0 o negativa. | Validación `cantidad > 0` en cliente y servidor; no se registra. | ✅ Rechazado |
| P16 | Desactivación de producto | Pulsar "Desactivar" en un producto con movimientos. | `UPDATE estado = 0`; el producto no se elimina, conserva su historial y deja de mostrarse alertas sobre él. | ✅ Correcto |
| P17 | Historial de movimientos | Abrir la sección de movimientos. | Tabla con fecha, producto, tipo, cantidad, observación y usuario (últimos 50 registros). | ✅ Correcto |

## Resumen

- **Casos correctos:** 10
- **Casos rechazados correctamente:** 7
- **Controles verificados:** autenticación, validación de datos,unicidad de código, alerta de stock bajo, integridad de movimientos, transacciones y baja lógica.

> Nota: los resultados se corresponden con la grabación `Escritorio/edicion/tecnostock_final.mp4` (4:55). El archivo se entrega junto con el proyecto; al publicar el repositorio en GitHub, sustituir la ruta local del README por la URL pública del vídeo.
