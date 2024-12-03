# Mktska Inventario

**Versión:** 1.0.1  
**Autor:** Mktska  
**Plugin URI:** [http://mktska.com](http://mktska.com)  
**Autor URI:** [http://mktska.com](http://mktska.com)

## Descripción

Mktska Inventario es un plugin para WordPress que extiende las funcionalidades de WooCommerce. Permite llevar un control detallado del inventario de tus productos, registrando todas las entradas y salidas de stock, así como los usuarios responsables de cada movimiento. Es ideal para tiendas en línea que necesitan un seguimiento preciso de su inventario y un historial de cambios para auditorías o análisis.

## Características

- 📊 **Historial de inventario:** Guarda un registro detallado de cada cambio en el stock de los productos.
- 👤 **Seguimiento de usuarios:** Registra qué usuario realizó cada modificación en el inventario.
- 🛒 **Integración con WooCommerce:** Compatible con los procesos de venta, cancelación de pedidos y ajustes manuales de stock.
- 🖥️ **Visualización en el administrador:** Añade una caja meta en la página de edición de productos para consultar el historial de stock.
- 🔄 **Soporte para productos variables:** Maneja correctamente el inventario de productos variables y sus variaciones.

## Instalación

1. **Descarga el plugin:**
   - Clona este repositorio o descarga el archivo ZIP desde GitHub.

2. **Sube el plugin a WordPress:**
   - Accede al panel de administración de WordPress.
   - Ve a `Plugins` > `Añadir nuevo` > `Subir plugin`.
   - Selecciona el archivo ZIP y haz clic en `Instalar ahora`.

3. **Activa el plugin:**
   - Una vez instalado, haz clic en `Activar`.
   - Al activarse, se crea automáticamente la tabla `wp_stock_history` en la base de datos.

## Uso

1. **Visualización del historial de inventario:**
   - Ve a `Productos` > `Editar` en cualquier producto.
   - En la sección **Historial de inventario**, consulta los registros de entradas y salidas de stock.

2. **Registro de movimientos:**
   - **Ventas:** El plugin registra automáticamente la reducción de stock en cada venta.
   - **Cancelaciones:** En caso de cancelaciones, el stock se reabastece y se registra el usuario que realizó la acción.
   - **Cambios manuales:** Si un administrador ajusta el inventario, se registra el cambio y los detalles del usuario.

## Requisitos

- **WordPress:** 5.0 o superior
- **WooCommerce:** 3.0 o superior
- **PHP:** 7.0 o superior

## Ejemplo de uso

A continuación, un ejemplo de cómo se visualiza el historial de inventario en el panel de administración:

"plaintext
Producto: Auriculares Bluetooth
-------------------
Fecha y hora: 01/12/2024 10:15 AM
Acción: Venta
Stock anterior: 10
Nuevo stock: 7
Responsable: Sistema (Venta automatizada)

-------------------
Fecha y hora: 30/11/2024 09:00 PM
Acción: Ajuste manual
Stock anterior: 15
Nuevo stock: 10
Responsable: admin_user
Proceso: Ajuste por inventario"


## Hooks Utilizados
El plugin utiliza varios hooks de WooCommerce y WordPress para registrar los movimientos de inventario:

- register_activation_hook: Crea la tabla en la base de datos al activar el plugin.
- woocommerce_product_set_stock: Registra cambios en el stock de productos simples.
- woocommerce_variation_set_stock: Registra cambios en el stock de variaciones.
- add_meta_boxes: Añade la caja meta del historial de inventario en la página de edición de productos.
- woocommerce_reduce_order_stock: Registra detalles de ventas al reducir el stock por una orden.
- woocommerce_order_status_cancelled: Registra detalles al cancelar un pedido.
- woocommerce_pre_product_object_save: Captura el stock antes de guardar cambios en un producto.
- woocommerce_product_object_updated_props: Registra cambios manuales de stock.
- woocommerce_order_item_quantity_restocked: Registra reabastecimientos de inventario.

## Contribuciones
¡Contribuciones son bienvenidas! 🎉

Si deseas colaborar en el desarrollo de este plugin, sigue estos pasos:

Haz un fork de este repositorio.
Crea una nueva rama para tus cambios:
bash
Copiar código
git checkout -b nombre-de-tu-rama

Realiza tus cambios y súbelos a tu rama.

Envía un pull request detallando tus modificaciones.

## Licencia
Este plugin está licenciado bajo la Licencia GPL v2 o superior.

## Créditos
Desarrollado por Mktska.

