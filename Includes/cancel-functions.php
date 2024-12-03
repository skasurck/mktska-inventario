<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action('woocommerce_order_status_cancelled', 'mktska_guardar_detalles_cancelacion');

function mktska_guardar_detalles_cancelacion($order_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'stock_history';
    $order = wc_get_order($order_id);

    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id();
        $quantity = $item->get_quantity();
        $order_number = $order->get_order_number();
        $order_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $cancelled_by = wp_get_current_user()->user_login;

        // Recuperar el producto y el inventario actual
        $product = wc_get_product($product_id);
        $old_stock = $product->get_stock_quantity();
        $new_stock = $old_stock + $quantity; // Revertir la cantidad vendida

        /* Actualizar el inventario del producto
        $product->set_stock_quantity($new_stock);
        $product->save();*/

        // Registrar los detalles de cancelación en el historial
        $cancel_details = array(
            'order_number' => $order_number,
            'order_name' => $order_name,
            'cancelled_by' => $cancelled_by
        );

        // Verificar si existe un registro previo de venta para esta orden
        $sale_row = $wpdb->get_row("SELECT * FROM $table_name WHERE product_id = $product_id AND sale_details LIKE '%$order_number%'");

        if ($sale_row) {
            // Actualizar el registro existente
            $wpdb->update(
                $table_name,
                array(
                    'cancel_details' => maybe_serialize($cancel_details),
                    'new_stock' => $new_stock
                ),
                array('id' => $sale_row->id),
                array('%s', '%d'),
                array('%d')
            );
        } else {
            // Insertar un nuevo registro si no existe uno previo
            $wpdb->insert(
                $table_name,
                array(
                    'timestamp' => current_time('mysql'),
                    'product_id' => $product_id,
                    'stock' => $old_stock,
                    'new_stock' => $new_stock,
                    'stock_change_meta' => maybe_serialize($cancel_details),
                    'cancel_details' => maybe_serialize($cancel_details)
                ),
                array('%s', '%d', '%d', '%d', '%s', '%s')
            );
        }
    }
}

