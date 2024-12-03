<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Historial de stock de producto
add_action('woocommerce_product_set_stock', 'mktska_registrar_cambio_stock');
add_action('woocommerce_variation_set_stock', 'mktska_registrar_cambio_stock');

function mktska_registrar_cambio_stock($product) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'stock_history';

    $stock_history[] = array(
        'timestamp' => time(),
        'stock' => (int) get_post_meta($product->get_id(), '_stock', true),
        'stock_change_meta' => get_post_meta($product->get_id(), '_stock_change_meta', true)
    );

    $wpdb->insert(
        $table_name,
        array(
            'timestamp' => current_time('mysql'),
            'product_id' => $product->get_id(),
            'stock' => end($stock_history)['stock'],
            'new_stock' => $product->get_stock_quantity(),
            'stock_change_meta' => maybe_serialize(end($stock_history)['stock_change_meta'])
        )
    );
}
