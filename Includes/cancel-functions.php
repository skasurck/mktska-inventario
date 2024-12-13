<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evitar el acceso directo al archivo.
}

// Hook para capturar cancelaciones de pedidos en WooCommerce

add_action('woocommerce_order_status_cancelled', 'mktska_guardar_detalles_cancelacion', 5);

/**
 * Función para gestionar la cancelación de pedidos y actualizar el inventario
 *
 * @param int $order_id ID del pedido cancelado en WooCommerce.
 */
function mktska_guardar_detalles_cancelacion($order_id) {
    error_log("Hook 'woocommerce_order_status_cancelled' activado para la orden ID: $order_id");
    global $wpdb;
    $table_name = $wpdb->prefix . 'stock_history';

    // Obtener la orden con base en su ID
    $order = wc_get_order($order_id);

    if (!$order) {
        error_log("Error: No se pudo obtener la orden con ID: " . $order_id);
        return;
    }

    // Iterar a través de todos los productos en la orden
    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id(); // Obtener el ID del producto
        $quantity = $item->get_quantity(); // Cantidad comprada del producto

        error_log("Procesando cancelación para producto ID: " . $product_id . " con cantidad: " . $quantity);
        $order_number = $order->get_order_number(); // Número de la orden
        $order_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(); // Nombre del cliente
        $cancelled_by = wp_get_current_user()->user_login; // Usuario que canceló la orden

        // Recuperar el producto y el inventario actual
        $product = wc_get_product($product_id);
        $old_stock = $product->get_stock_quantity(); // Inventario actual antes de la cancelación
        $new_stock = $old_stock + $quantity; // Nuevo inventario después de revertir la cantidad vendida

        // Registrar los detalles de la cancelación
        $cancel_details = array(
            'order_number' => $order_number,
            'order_name' => $order_name,
            'cancelled_by' => $cancelled_by // Usuario que canceló el pedido
        );

        // Verificar si existe un registro previo de la venta en el historial
        $sale_row = $wpdb->get_row("SELECT * FROM $table_name WHERE product_id = $product_id AND sale_details LIKE '%$order_number%'");

        if ($sale_row) {
            // Si existe un registro previo, actualizarlo con los detalles de cancelación
            $wpdb->update(
                $table_name,
                array(
                    'cancel_details' => maybe_serialize($cancel_details), // Registrar detalles de la cancelación
                    'new_stock' => $new_stock, // Actualizar el stock con la cantidad revertida
                    'quantity_cancelled' => $quantity, // Registrar la cantidad cancelada
                    'stock_old' => $old_stock // Registrar el stock antes de la cancelación
                ),
                array('id' => $sale_row->id), // Condición de actualización (ID del registro)
                array('%s', '%d', '%d', '%d'), // Tipos de datos
                array('%d') // Formato del ID
            );          
        } else {
            // Si no existe un registro previo, insertar un nuevo registro
            $wpdb->insert(
                $table_name,
                array(
                    'timestamp' => current_time('mysql'), // Fecha y hora de la cancelación
                    'product_id' => $product_id, // ID del producto
                    'stock' => $old_stock, // Inventario antes de la cancelación
                    'new_stock' => $new_stock, // Inventario después de la cancelación
                    'quantity_cancelled' => $quantity, // Cantidad cancelada
                    'quantity' => 0, // Inicializar cantidad vendida como 0 para cancelaciones sin ventas
                    'stock_change_meta' => maybe_serialize($cancel_details), // Detalles del cambio de inventario
                    'cancel_details' => maybe_serialize($cancel_details) // Detalles de la cancelación
                ),
                array('%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s') // Formato de datos
            );            
        }
    }
}

