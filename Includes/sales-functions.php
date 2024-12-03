<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Remover acción existente si es necesario
remove_action('woocommerce_order_item_product_set_stock', 'guardar_detalles_venta');

add_action('woocommerce_reduce_order_stock', 'mktska_guardar_detalles_venta');

function mktska_guardar_detalles_venta($order) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'stock_history';

    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id();
        $quantity = $item->get_quantity();
        $order_number = $order->get_order_number();
        $order_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

        $sale_details = array(
            'order_number' => $order,
            'order_name' => $order_name
        );

        $wpdb->insert(
            $table_name,
            array(
                'timestamp' => current_time('mysql'),
                'product_id' => $product_id,
                'stock' => $quantity, // Asegúrate de que este es el valor correcto para el stock
                'sale_details' => maybe_serialize($sale_details)
            ),
            array(
                '%s',
                '%d',
                '%d',
                '%s'
            )
        );
    }
}
