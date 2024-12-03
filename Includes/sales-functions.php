<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Remover acción existente si es necesario
remove_action('woocommerce_order_item_product_set_stock', 'guardar_detalles_venta');

add_action('woocommerce_reduce_order_stock', 'mktska_guardar_detalles_venta');
// Hook para registrar ventas realizadas a través del POS
add_action('yith_pos_register_order', 'mktska_guardar_detalles_venta', 10, 1);

function mktska_guardar_detalles_venta($order_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'stock_history';

    // Obtener la orden
    $order = wc_get_order($order_id);

    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id();
        $quantity = $item->get_quantity();
        $order_number = $order->get_order_number(); // Asegurarse de obtener solo el número de orden
        $order_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

        // Obtener el stock antes de la venta
        $product = wc_get_product($product_id);
        $stock_before = $product->get_stock_quantity('edit');

        // El stock después de la venta es el stock actual
        $stock_after = $product->get_stock_quantity();

        // Determinar si la venta es del POS
        $is_pos_sale = false;
        if (get_post_meta($order_id, 'is_pos_order', true) === '1' || $order->get_created_via() === 'pos') {
            $is_pos_sale = true;
        }

        $sale_details = array(
            'order_number' => $order_number,
            'order_name' => $order_name,
            'is_pos_sale' => $is_pos_sale
        );

        $wpdb->insert(
            $table_name,
            array(
                'timestamp' => current_time('mysql'),
                'product_id' => $product_id,
                'stock' => $stock_before,
                'new_stock' => $stock_after,
                'sale_details' => maybe_serialize($sale_details)
            ),
            array(
                '%s',
                '%d',
                '%d',
                '%d',
                '%s'
            )
        );
    }
}

