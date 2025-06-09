<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action('woocommerce_product_set_stock', 'mktska_registrar_cambio_stock');
add_action('woocommerce_variation_set_stock', 'mktska_registrar_cambio_stock');

function mktska_registrar_cambio_stock( $product ) {
    global $wpdb, $mktska_skip_stock_history;

    // Si la bandera está activa, el cambio fue esperado y no lo registramos aquí.
    if ( isset($mktska_skip_stock_history) && $mktska_skip_stock_history === true ) {
        $mktska_skip_stock_history = false;
        return;
    }

    $table_name = $wpdb->prefix . 'stock_history';

    // Obtener el stock "viejo" desde la base de datos (meta _stock)
    $old_stock = (int) get_post_meta( $product->get_id(), '_stock', true );
    // Obtener el stock "nuevo" directamente del objeto producto
    $new_stock = (int) $product->get_stock_quantity();

    // Si no hay cambio real, salir
    if ( $old_stock === $new_stock ) {
        return;
    }

    // Preparar meta indicando que es un cambio inesperado
    $stock_change_meta = array(
         'process' => 'Cambio inesperado',
         'reason'  => 'Se detectó una variación en el stock no asociada a procesos esperados (ventas, cancelaciones o cambios manuales).'
    );

    // Calcular la diferencia: old_stock - new_stock
    // De este modo, si se reduce el stock, la diferencia será positiva (descuento)
    // y si aumenta, negativa (aumento)
    $difference = $old_stock - $new_stock;

    // Insertar el registro en la tabla de historial
    $wpdb->insert(
        $table_name,
        array(
            'timestamp'         => current_time( 'mysql' ),
            'product_id'        => $product->get_id(),
            'stock'             => $old_stock,
            'new_stock'         => $new_stock,
            'quantity'          => $difference,
            'stock_change_meta' => maybe_serialize( $stock_change_meta ),
        ),
        array( '%s', '%d', '%d', '%d', '%d', '%s' )
    );
}
