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
                
                    // Obtener el ID del pedido
                    $order_id = $sale_details['order_number'];
                    $order = wc_get_order($order_id);
                    
                    // Obtener el estado del pedido en un formato legible
                    $order_status = $order ? wc_get_order_status_name($order->get_status()) : 'Desconocido';
                
                    // Si el nombre está vacío, usar "Cliente Anónimo"
                    $order_name = (isset($sale_details['order_name']) && !empty(trim($sale_details['order_name']))) ? $sale_details['order_name'] : 'Cliente Anónimo';
                    // Determinar método de venta: si no hay nombre, asumimos POS, si hay nombre, Tienda en línea
                    $metodo_venta = (isset($sale_details['order_name']) && !empty(trim($sale_details['order_name']))) ? '<strong>Tienda en línea</strong>' : '<strong>POS</strong>';

                
                    // Calcular stock anterior (stock + cantidad vendida)
                    $stockantes = $stockvalue['stock'] + $stockvalue['quantity'];
                
                    echo '<p><strong>Fecha y hora: ' . $datetime->format('d/m/Y g:i a') . '</strong></p>';
                    echo '<ul>';
                    echo '  <li style="color: green;"><strong>Venta registrada</strong></li>';
                    echo '  <li><strong>Cantidad vendida: </strong>' . $stockvalue['quantity'] . '</li>';
                    echo '  <li><strong>Nombre del cliente: </strong>' . $order_name . '</li>';
                    echo '  <li><strong>Orden Número: </strong>' . $order_id . '</li>';
                    echo '  <li><strong>Estado del pedido: </strong>' . $order_status . '</li>';
                    echo '  <li><strong>Método de venta: </strong>' . $metodo_venta . '</li>';
                    echo '  <li><strong>Cambio de inventario: </strong>' . $stockantes . ' -> ' . $stockvalue['new_stock'] . '</li>';
                    echo '</ul>';
                    echo '<p>-------------------</p>';
                }
                
            
                // Mostrar cancelaciones
                elseif (!empty($stockvalue['cancel_details'])) {
                    $cancel_details = maybe_unserialize($stockvalue['cancel_details']);
                    echo '<p><strong>Fecha y hora: ' . $datetime->format('d/m/Y g:i a') . '</strong></p>';
                    echo '<ul>';
                    echo '  <li style="color:red;"><strong>Cancelación registrada</strong></li>';
                    echo '  <li><strong>Cantidad cancelada:</strong> <span>' . $stockvalue['quantity_cancelled'] . '</span></li>';
                    echo '  <li><strong>Cancelada por:</strong> ' . $cancel_details['cancelled_by'] . '</li>';
                    echo '  <li><strong>Orden Número:</strong> ' . $cancel_details['order_number'] . '</li>';
                    echo '  <li><strong>Cliente:</strong> ' . $cancel_details['order_name'] . '</li>';
                    echo '  <li><strong>Cambio de inventario:</strong> ' . $stockvalue['stock'] . ' -> ' . $stockvalue['new_stock'] . '</li>';
                    echo '</ul>';
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


