<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evitar el acceso directo al archivo.
}

// Hook para capturar cancelaciones de pedidos en WooCommerce
add_action('woocommerce_order_status_cancelled', 'mktska_guardar_detalles_cancelacion', 5);

/**
 * Función para gestionar la cancelación de pedidos y registrar un nuevo cambio en el inventario.
 *
 * @param int $order_id ID del pedido cancelado en WooCommerce.
 */
function mktska_guardar_detalles_cancelacion($order_id) {
    // Indicar que el cambio en stock es esperado
    $GLOBALS['mktska_skip_stock_history'] = true;
    
    mktska_escribir_log("Hook 'woocommerce_order_status_cancelled' activado para la orden ID: $order_id");
    global $wpdb;
    $table_name = $wpdb->prefix . 'stock_history';

    // Obtener la orden con base en su ID
    $order = wc_get_order($order_id);
    if ( ! $order ) {
        mktska_escribir_log("Error: No se pudo obtener la orden con ID: " . $order_id);
        return;
    }

    // Iterar a través de todos los productos en la orden
    foreach ( $order->get_items() as $item_id => $item ) {
        $product_id = $item->get_product_id(); // Obtener el ID del producto
        $quantity   = $item->get_quantity();     // Cantidad comprada del producto

        mktska_escribir_log("Procesando cancelación para producto ID: " . $product_id . " con cantidad: " . $quantity);
        $order_number = $order->get_order_number(); // Número de la orden
        $order_name   = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(); // Nombre del cliente
        $cancelled_by = wp_get_current_user()->user_login; // Usuario que canceló el pedido

        // Recuperar el producto y el inventario actual
        $product   = wc_get_product( $product_id );
        $old_stock = $product->get_stock_quantity(); // Inventario actual antes de la cancelación
        $new_stock = $old_stock + $quantity; // Nuevo inventario después de revertir la cantidad vendida

        // Registrar los detalles de la cancelación
        $cancel_details = array(
            'order_number' => $order_number,
            'order_name'   => $order_name,
            'cancelled_by' => $cancelled_by
        );

        // Insertar un nuevo registro para la cancelación (no se actualiza el registro de venta existente)
        $result = $wpdb->insert(
            $table_name,
            array(
                'timestamp'         => current_time('mysql'),
                'product_id'        => $product_id,
                'stock'             => $old_stock,
                'new_stock'         => $new_stock,
                'quantity_cancelled'=> $quantity,
                'quantity'          => 0,
                'stock_change_meta' => maybe_serialize($cancel_details),
                'cancel_details'    => maybe_serialize($cancel_details)
            ),
            array('%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s')
        );

        if ( false === $result ) {
            mktska_escribir_log("Error en INSERT de cancelación: " . $wpdb->last_error);
        } else {
            mktska_escribir_log("INSERT de cancelación realizado con éxito. ID insertado: " . $wpdb->insert_id);
        }
    }
}
