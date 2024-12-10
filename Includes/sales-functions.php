<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Remueve cualquier acción previa asociada con la función "guardar_detalles_venta" para evitar conflictos.
remove_action('woocommerce_order_item_product_set_stock', 'guardar_detalles_venta');
// Agrega un hook para registrar ventas que reduzcan el stock.
add_action('woocommerce_reduce_order_stock', 'mktska_guardar_detalles_venta');
// Agrega un hook para registrar ventas realizadas específicamente a través del POS.
add_action('yith_pos_register_order', 'mktska_guardar_detalles_venta', 10, 1);

/**
 * Función para guardar detalles de las ventas en la base de datos.
 *
 * @param int $order_id ID de la orden de WooCommerce.
 */
function mktska_guardar_detalles_venta($order_id) {
    global $wpdb;
    // Nombre de la tabla personalizada 'stock_history' para almacenar el historial de stock.
    $table_name = $wpdb->prefix . 'stock_history';

    // Obtiene los datos de la orden utilizando el ID.
    $order = wc_get_order($order_id);

    // Itera sobre cada artículo en la orden.
    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id();// ID del producto iterado.
        $quantity = $item->get_quantity(); // Cantidad comprada del producto.
        $order_number = $order->get_order_number(); // Número de la orden.
        $order_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(); // Nombre del cliente.

         // Obtiene los datos del producto para el stock antes y después de la venta.
        $product = wc_get_product($product_id); //Aqui almacena el producto iterado para comprobar despues obtener su inventario actual y el despues de la venta
        $stock_before = $product->get_stock_quantity('edit');// Stock antes de la venta.
        $stock_after = $product->get_stock_quantity();// Stock actual (después de la venta).

        // Determinar si la venta es del POS
        $is_pos_sale = false; //se inicializa en falso 
        if (get_post_meta($order_id, '_yith_pos_order', true) === '1' || $order->get_created_via() === 'pos') {//aqui se hace una doble comprobacion para ver si esta creado en POS
            $is_pos_sale = true;
        }

        //Se está creando un array asociativo llamado $sale_details la cual se va insertar en sale_details' => maybe_serialize($sale_details)
        $sale_details = array( 
            'order_number' => $order_number,
            'order_name' => $order_name,
            'is_pos_sale' => $is_pos_sale
        );

        //Aqui se inserta el la informacion a la base de datos llamada stock_history
        $wpdb->insert(
            $table_name,
            array(
                'timestamp' => current_time('mysql'),
                'product_id' => $product_id,
                'stock' => $stock_before,
                'new_stock' => $stock_after,
                'quantity' => $quantity, // Agrega la cantidad vendida.
                'sale_details' => maybe_serialize($sale_details)
            ),
            array(
                '%s', // timestamp: string timestamp
                '%d', // product_id: integer product_id
                '%d', // stock: integer stock antes de la venta
                '%d', // new_stock: integer stock después de la venta
                '%d',  // quantity:  integer cantidad vendida
                '%s'  // sale_details: string (serializado) detalles de la venta (serializado)
            )
        );
        error_log("Última consulta SQL: " . $wpdb->last_query);
        error_log("Error SQL: " . $wpdb->last_error);
    }
}

