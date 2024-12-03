<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Capturar stock antes del cambio
add_action('woocommerce_pre_product_object_save', 'mktska_capturar_stock_antes_cambio');

function mktska_capturar_stock_antes_cambio($product) {
    $product->old_stock = $product->get_stock_quantity('edit');
}

// Guardar cambios de stock manuales
add_action('woocommerce_product_object_updated_props', 'mktska_guardar_cambio_stock_meta', 10, 2);

function mktska_guardar_cambio_stock_meta($product, $updated_props) {
    // Solo proceder si estamos en el área de administración y no es una llamada AJAX
    if (!is_admin() || wp_doing_ajax()) {
        return;
    }

    // Verificar que la pantalla actual es la de edición de productos
    $current_screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($current_screen && $current_screen->id !== 'product') {
        return;
    }

    // Verificar si la propiedad 'stock_quantity' está entre las actualizadas
    if (in_array('stock_quantity', $updated_props)) {
        $old_stock = isset($product->old_stock) ? $product->old_stock : $product->get_stock_quantity('edit');
        $new_stock = $product->get_stock_quantity();

        // Verificar si el stock realmente cambió
        if ($old_stock === $new_stock) {
            return;
        }

        $user_name = wp_get_current_user()->user_login;
        $process = 'Se actualizó manualmente por un usuario';

        $stock_change_meta = array(
            'old_stock' => $old_stock,
            'new_stock' => $new_stock,
            'user_name' => $user_name,
            'process' => $process
        );

        update_post_meta($product->get_id(), '_stock_change_meta', $stock_change_meta);

        // Registrar en la tabla de historial de inventario
        global $wpdb;
        $table_name = $wpdb->prefix . 'stock_history';
        $wpdb->insert(
            $table_name,
            array(
                'timestamp' => current_time('mysql'),
                'product_id' => $product->get_id(),
                'stock' => $old_stock,
                'new_stock' => $new_stock,
                'stock_change_meta' => maybe_serialize($stock_change_meta)
            ),
            array('%s', '%d', '%d', '%d', '%s')
        );
    }
}

// Registrar restock
add_action('woocommerce_order_item_quantity_restocked', 'mktska_registrar_restock_item', 10, 2);

function mktska_registrar_restock_item($order, $item_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'stock_history';

    // Obtener el item correctamente
    $item = $order->get_item($item_id);
    $product_id = $item->get_product_id();
    $quantity = $item->get_quantity();
    $order_number = $order->get_order_number();
    $order_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
    $cancelled_by = wp_get_current_user()->user_login;

    // Obtener el producto y el inventario actual (después de la cancelación)
    $product = wc_get_product($product_id);
    $new_stock = $product->get_stock_quantity();

    // Calcular el stock antes de la cancelación
    $old_stock = $new_stock - $quantity;

    // Registrar los detalles de la cancelación
    $cancel_details = array(
        'order_number' => $order_number,
        'order_name' => $order_name,
        'cancelled_by' => $cancelled_by
    );

    // Insertar en el historial
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
