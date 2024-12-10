<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action('add_meta_boxes', 'mktska_agregar_caja_meta_historial_stock');

function mktska_agregar_caja_meta_historial_stock() {
    add_meta_box('stock_history', 'Historial de inventario', 'mktska_mostrar_historial_stock', 'product', 'advanced', 'high');
}

function mktska_mostrar_historial_stock() {
    global $post, $wpdb;
    $product = wc_get_product($post->ID);
    $table_name = $wpdb->prefix . 'stock_history';

    if ($product->get_type() == 'variable') {
        foreach ($product->get_available_variations() as $variation) {
            $products[] = $variation['variation_id'];
        }
    } else {
        $products[] = $post->ID;
    }

    foreach ($products as $product_id) {
        $product = wc_get_product($product_id);
        echo '<h3>' . $product->get_name() . '</h3>';
        $stock_history = $wpdb->get_results("SELECT * FROM $table_name WHERE product_id = $product_id ORDER BY timestamp DESC", ARRAY_A);

        if ($stock_history) {
            foreach ($stock_history as $stockvalue) {
                $datetime = new DateTime($stockvalue['timestamp']);
                $datetime->setTimezone(new DateTimeZone('GMT-6')); // Ajustar la zona horaria
            
                // Mostrar ventas
                if (!empty($stockvalue['sale_details'])) {
                    $sale_details = maybe_unserialize($stockvalue['sale_details']);
                    echo '<p>Fecha y hora: ' . $datetime->format('d/m/Y g:i a') . '</p>';
                    echo '<p>Venta registrada:</p>';
                    echo '<p> - Cantidad vendida: ' . $stockvalue['quantity'] . '</p>';
                    echo '<p> - Nombre del cliente: ' . (!empty($sale_details['order_name']) ? $sale_details['order_name'] : 'Cliente Anónimo') . '</p>';
                    echo '<p> - Orden Número: ' . $sale_details['order_number'] . '</p>';
                    echo '<p> - Método de venta: ' . ($sale_details['is_pos_sale'] ? '<strong>POS</strong>' : '<strong>Tienda en línea</strong>') . '</p>';
                    echo '<p> - Cambio de inventario: ' . $stockvalue['stock'] . ' -> ' . $stockvalue['new_stock'] . '</p>';
                    echo '<p>-------------------</p>';
                }
            
                // Mostrar cancelaciones
                elseif (!empty($stockvalue['cancel_details'])) {
                    $cancel_details = maybe_unserialize($stockvalue['cancel_details']);
                    echo '<p>Fecha y hora: ' . $datetime->format('d/m/Y g:i a') . '</p>';
                    echo '<p>Cancelación registrada:</p>';
                    echo '<p> - Cantidad cancelada: ' . $stockvalue['quantity_cancelled'] . '</p>';
                    echo '<p> - Cancelada por: ' . $cancel_details['cancelled_by'] . '</p>';
                    echo '<p> - Orden Número: ' . $cancel_details['order_number'] . '</p>';
                    echo '<p> - Cliente: ' . $cancel_details['order_name'] . '</p>';
                    echo '<p> - Cambio de inventario: ' . $stockvalue['stock'] . ' -> ' . $stockvalue['new_stock'] . '</p>';
                    echo '<p>-------------------</p>';
                }
            
                /* Mostrar cambios manuales
                else {
                    echo '<p>Fecha y hora: ' . $datetime->format('d/m/Y g:i a') . '</p>';
                    echo '<p>Cambio manual en el inventario:</p>';
                    echo '<p> - Inventario anterior: ' . $stockvalue['stock'] . '</p>';
                    echo '<p> - Inventario actual: ' . $stockvalue['new_stock'] . '</p>';
                    echo '<p>-------------------</p>';
                }*/
            }            
        } else {
            echo '<p>No hay historial de inventario para este producto.</p>';
        }
        echo '<p>Existencias actuales: <b>' . $product->get_stock_quantity() . '</b></p>';
    }
}


