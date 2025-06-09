<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Declaramos un arreglo global para almacenar el stock anterior (para Quick Edit).
 */
global $mktska_old_stock;
if ( ! isset( $mktska_old_stock ) ) {
    $mktska_old_stock = array();
}

/**
 * Hook para capturar el stock actual antes de que se actualice el producto.
 * Se ejecuta en el hook save_post_product (prioridad 9, antes de la actualización).
 */
add_action('save_post_product', 'mktska_guardar_stock_antes_update', 9, 3);
function mktska_guardar_stock_antes_update($post_ID, $post, $update) {
    if ($post->post_type !== 'product') return;
    global $mktska_old_stock;
    $mktska_old_stock[$post_ID] = (int) get_post_meta($post_ID, '_stock', true);
    mktska_escribir_log("Pre-save: Capturado stock anterior para producto {$post_ID}: " . $mktska_old_stock[$post_ID]);
}

/**
 * (Opcional) Hook para capturar el stock anterior en la edición completa.
 * Se engancha a woocommerce_product_object_updated_props, pero en este caso
 * usaremos _original_stock enviado por el formulario para la edición completa.
 */
add_action('woocommerce_product_object_updated_props', 'mktska_capturar_stock_antes_cambio', 10, 2);
function mktska_capturar_stock_antes_cambio( $product, $updated_props ) {
    // Indicamos que este cambio se espera (para ventas, cancelaciones o edición completa)
    $GLOBALS['mktska_skip_stock_history'] = true;
    if ( in_array( '_stock', $updated_props ) ) {
        // Se podría actualizar el global, pero en edición completa usaremos el valor enviado en el formulario.
        mktska_escribir_log("Capturado (object updated) para producto {$product->get_id()} (se usará _original_stock en edición completa)");
    }
}

/**
 * 1) Registrar cambios manuales de stock en la edición completa del producto.
 * Se engancha a woocommerce_process_product_meta (este hook NO se dispara en Quick Edit).
 *
 * En la edición completa, el formulario envía:
 *   - _original_stock: el valor anterior.
 *   - _stock: el nuevo valor.
 */
add_action('woocommerce_process_product_meta', 'mktska_registrar_cambio_manual_de_stock', 99);
function mktska_registrar_cambio_manual_de_stock( $product_id ) {
    // Indicamos que el cambio en stock es esperado.
    $GLOBALS['mktska_skip_stock_history'] = true;
    
    if ( ! is_admin() ) return;
    
    // Evitar registrar cambios del POS.
    if ( isset( $_POST['action'] ) && $_POST['action'] === 'yith_pos_update_stock' ) {
        mktska_escribir_log("Acción yith_pos_update_stock detectada para producto {$product_id}, ignorando cambio manual.");
        return;
    }
    
    // Loguear $_POST para depuración.
    mktska_escribir_log("Producto {$product_id}: Datos POST (edición completa) => " . print_r($_POST, true));
    
    global $wpdb;
    // Para la edición completa, usar _original_stock como valor anterior.
    if ( isset( $_POST['_original_stock'] ) ) {
        $old_stock = (int) $_POST['_original_stock'];
        mktska_escribir_log("Producto {$product_id}: Old stock obtenido via _original_stock: {$old_stock}");
    } else {
        // Si no se envía, se intenta obtener de la meta.
        $old_stock = (int) get_post_meta( $product_id, '_stock', true );
        mktska_escribir_log("Producto {$product_id}: Old stock obtenido desde meta: {$old_stock}");
    }
    
    // Obtener el nuevo stock desde _stock.
    if ( isset( $_POST['_stock'] ) ) {
        $new_stock = (int) $_POST['_stock'];
        mktska_escribir_log("Producto {$product_id}: New stock obtenido via _stock: {$new_stock}");
    } else {
        mktska_escribir_log("Producto {$product_id}: No se encontró valor en POST para _stock (edición completa).");
        return;
    }
    
    // Si no hay cambio, salir.
    if ( $old_stock === $new_stock ) {
        mktska_escribir_log("Producto {$product_id}: No hay cambio en stock (old_stock == new_stock) en edición completa.");
        return;
    }
    
    // Calcular la diferencia: Inventario Anterior – Inventario Actual.
    $difference = $old_stock - $new_stock;
    mktska_escribir_log("Producto {$product_id}: Diferencia calculada (edición completa): {$difference}");
    
    $table_name = $wpdb->prefix . 'stock_history';
    $user_name  = wp_get_current_user()->user_login;
    $stock_change_meta = array(
        'old_stock' => $old_stock,
        'new_stock' => $new_stock,
        'user_name' => $user_name,
        'process'   => 'Cambio manual (admin)'
    );
    
    // Insertar el registro en la tabla de historial.
    $result = $wpdb->insert(
        $table_name,
        array(
            'timestamp'         => current_time( 'mysql' ),
            'product_id'        => $product_id,
            'stock'             => $old_stock,
            'new_stock'         => $new_stock,
            'sale_details'      => '',
            'cancel_details'    => '',
            'quantity'          => $difference,
            'stock_change_meta' => maybe_serialize( $stock_change_meta )
        ),
        array( '%s', '%d', '%d', '%d', '%s', '%s', '%d', '%s' )
    );
    
    if ( false === $result ) {
        mktska_escribir_log("Error al insertar cambio de stock (edición completa) para producto {$product_id}: " . $wpdb->last_error);
    } else {
        mktska_escribir_log("Cambio de stock (edición completa) insertado con éxito para producto {$product_id}. ID insertado: " . $wpdb->insert_id);
    }
}

/**
 * 2) Registrar cambios de stock en la EDICIÓN RÁPIDA (Quick Edit).
 * WooCommerce Quick Edit utiliza el hook woocommerce_product_quick_edit_save.
 */
add_action('woocommerce_product_quick_edit_save', 'mktska_registrar_cambio_quick_edit_stock', 10, 1);
function mktska_registrar_cambio_quick_edit_stock( $product ) {
    if ( ! is_admin() ) return;
    global $wpdb, $mktska_old_stock;
    $product_id = $product->get_id();
    
    // Loguear los datos POST para depuración.
    mktska_escribir_log("Producto {$product_id}: Datos POST (Quick Edit) => " . print_r($_POST, true));
    
    // Obtener el nuevo stock desde $_POST si se envía.
    if ( isset($_POST['_stock']) ) {
        $new_stock = (int) $_POST['_stock'];
        mktska_escribir_log("Producto {$product_id}: Quick Edit => new_stock from POST: {$new_stock}");
    } else {
        $new_stock = (int) $product->get_stock_quantity();
        mktska_escribir_log("Producto {$product_id}: Quick Edit => new_stock from product object: {$new_stock}");
    }
    
    // Obtener el stock anterior: intentar primero con el valor capturado en save_post_product.
    if ( isset($mktska_old_stock[$product_id]) ) {
        $old_stock = $mktska_old_stock[$product_id];
        mktska_escribir_log("Producto {$product_id}: Quick Edit => old_stock from global: {$old_stock}");
    } else {
        $old_stock = (int) get_post_meta( $product_id, '_stock', true );
        mktska_escribir_log("Producto {$product_id}: Quick Edit => old_stock from meta: {$old_stock}");
    }
    
    // Verificar si hay cambio.
    if ($old_stock === $new_stock) {
        mktska_escribir_log("Producto {$product_id}: Quick Edit => sin cambios en el stock.");
        return;
    }
    
    // Calcular la diferencia: Inventario Anterior – Inventario Actual.
    $difference = $old_stock - $new_stock;
    
    $table_name = $wpdb->prefix . 'stock_history';
    $user_name  = wp_get_current_user()->user_login;
    
    $stock_change_meta = array(
        'old_stock' => $old_stock,
        'new_stock' => $new_stock,
        'user_name' => $user_name,
        'process'   => 'Edición rápida (Quick Edit)'
    );
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'timestamp'         => current_time('mysql'),
            'product_id'        => $product_id,
            'stock'             => $old_stock,
            'new_stock'         => $new_stock,
            'sale_details'      => '',
            'cancel_details'    => '',
            'quantity'          => $difference,
            'stock_change_meta' => maybe_serialize( $stock_change_meta )
        ),
        array( '%s','%d','%d','%d','%s','%s','%d','%s' )
    );
    
    if ( false === $result ) {
        mktska_escribir_log("Error al insertar cambio de stock (Quick Edit) para producto {$product_id}: " . $wpdb->last_error);
    } else {
        mktska_escribir_log("Cambio de stock (Quick Edit) insertado con éxito para producto {$product_id}. ID insertado: " . $wpdb->insert_id);
    }
}
