<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Remover acción existente si es necesario
remove_action('woocommerce_order_item_product_set_stock', 'mktska_guardar_detalles_venta');

add_action('woocommerce_reduce_order_stock', 'mktska_guardar_detalles_venta', 5);
add_action('yith_pos_reduce_order_stock', 'mktska_guardar_detalles_venta', 10, 1);

function mktska_guardar_detalles_venta($order_input) {
    // Indicar que el siguiente cambio en stock es esperado
    $GLOBALS['mktska_skip_stock_history'] = true;
    mktska_escribir_log("Hook 'mktska_guardar_detalles_venta' disparado con: " . var_export($order_input, true));

    global $wpdb;
    $table_name = $wpdb->prefix . 'stock_history';

    // Determinar si $order_input es un objeto WC_Order o un ID de orden
    if (is_object($order_input) && method_exists($order_input, 'get_id')) {
        $order = $order_input;
        $order_id = $order->get_id();
    } elseif (is_numeric($order_input)) {
        $order_id = (int)$order_input;
        $order = wc_get_order($order_id);
    } else {
        mktska_escribir_log("No se pudo interpretar el parámetro del hook de venta. Ni objeto WC_Order ni ID numérico.");
        return;
    }

    if (!$order) {
        mktska_escribir_log("Error: No se pudo obtener la orden con ID: " . $order_id);
        return;
    }

    // Determinar si la venta es del POS
    $is_pos_sale = false;
    if (get_post_meta($order_id, '_yit_pos_order', true) === '1' || $order->get_created_via() === 'pos') {
        $is_pos_sale = true;
    }

    mktska_escribir_log("Procesando venta para la orden ID: " . $order_id);

    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id();
        $quantity = $item->get_quantity();

        mktska_escribir_log("Producto procesado en venta: ID " . $product_id . ", cantidad: " . $quantity);

        $order_number = $order->get_order_number();
        $order_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

        $product = wc_get_product($product_id);
        if ( ! $product ) {
            mktska_escribir_log("No se pudo obtener el producto con ID {$product_id}");
            continue;
        }

        // Para ventas POS, intentar usar _original_stock si está disponible.
        if ($is_pos_sale && isset($_POST['_original_stock'])) {
            $stock_before = (int) $_POST['_original_stock'];
            mktska_escribir_log("Producto {$product_id}: POS venta => stock_before obtenido via _original_stock: {$stock_before}");
        } else {
            $stock_before = $product->get_stock_quantity('edit');
            mktska_escribir_log("Producto {$product_id}: stock_before obtenido via get_stock_quantity('edit'): {$stock_before}");
        }
        $stock_after = $product->get_stock_quantity();

        // Preparar detalles de venta.
        $sale_details = array(
            'order_number' => $order_number,
            'order_name'   => $order_name,
            'is_pos_sale'  => $is_pos_sale
        );

        // Para ventas POS, detectar sobreventa
        if ($is_pos_sale) {
            // La diferencia esperada (lo que se debería descontar) es:
            $expected_sale = $stock_before - $stock_after;
            if ($quantity > $expected_sale) {
                $oversell = $quantity - $expected_sale;
                $sale_details['oversell'] = $oversell;
                mktska_escribir_log("Sobreventa detectada en producto {$product_id}: vendido {$quantity} pero esperado {$expected_sale}, oversell = {$oversell}");
            }
        }

        $result = $wpdb->insert(
            $table_name,
            array(
                'timestamp'         => current_time('mysql'),
                'product_id'        => $product_id,
                'stock'             => $stock_before,
                'new_stock'         => $stock_after,
                'quantity'          => $quantity, // cantidad vendida
                'sale_details'      => maybe_serialize($sale_details),
                'stock_change_meta' => ''
            ),
            array('%s', '%d', '%d', '%d', '%d', '%s', '%s')
        );

        if ( false === $result ) {
            mktska_escribir_log("Error en INSERT de venta: " . $wpdb->last_error);
        } else {
            mktska_escribir_log("INSERT de venta realizado con éxito. ID insertado: " . $wpdb->insert_id);
        }
    }
}
