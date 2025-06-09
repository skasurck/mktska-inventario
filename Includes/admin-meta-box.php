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

    // Obtener IDs: si es producto variable, recorrer sus variaciones; sino, el ID del producto.
    $products = array();
    if ($product->get_type() == 'variable') {
        foreach ($product->get_available_variations() as $variation) {
            $products[] = $variation['variation_id'];
        }
    } else {
        $products[] = $post->ID;
    }

    // Recorrer cada producto/variación.
    foreach ($products as $product_id) {
        $product = wc_get_product($product_id);
        echo '<h3>' . esc_html($product->get_name()) . '</h3>';

        // Obtener historial de este producto.
        $stock_history = $wpdb->get_results("SELECT * FROM $table_name WHERE product_id = $product_id ORDER BY timestamp DESC", ARRAY_A);

        if ($stock_history) {
            foreach ($stock_history as $stockvalue) {
                $datetime = new DateTime($stockvalue['timestamp']);
                $datetime->setTimezone(new DateTimeZone('GMT-6')); // Ajusta según tu zona.

                // 1) Mostrar VENTAS si sale_details no está vacío.
                if (!empty($stockvalue['sale_details'])) {
                    $sale_details = maybe_unserialize($stockvalue['sale_details']);
                    $order_id = $sale_details['order_number'];
                    $order = wc_get_order($order_id);
                    $order_status = $order ? wc_get_order_status_name($order->get_status()) : 'Desconocido';
                    $order_name = (!empty(trim($sale_details['order_name']))) ? $sale_details['order_name'] : 'Cliente Anónimo';
                    $metodo_venta = (!empty(trim($sale_details['order_name']))) ? '<strong>Tienda en línea</strong>' : '<strong>POS</strong>';
                    // Para ventas, se calcula el stock anterior sumando la cantidad vendida al stock registrado.
                    $stockantes = $stockvalue['stock'] + $stockvalue['quantity'];

                    echo '<p><strong>Fecha y hora: ' . $datetime->format('d/m/Y g:i a') . '</strong></p>';
                    echo '<ul>';
                    echo '<li style="color: green;"><strong>Venta registrada</strong></li>';
                    echo '<li><strong>Cantidad vendida: </strong>' . intval($stockvalue['quantity']) . '</li>';
                    echo '<li><strong>Nombre del cliente: </strong>' . esc_html($order_name) . '</li>';
                    echo '<li><strong>Orden Número: </strong>' . esc_html($order_id) . '</li>';
                    echo '<li><strong>Estado del pedido: </strong>' . esc_html($order_status) . '</li>';
                    echo '<li><strong>Método de venta: </strong>' . $metodo_venta . '</li>';
                    echo '<li><strong>Cambio de inventario: </strong>' . intval($stockantes) . ' -> ' . intval($stockvalue['new_stock']) . '</li>';
                    // Si se detectó sobreventa, mostrar el mensaje.
                    if (isset($sale_details['oversell']) && $sale_details['oversell'] > 0) {
                        echo '<li style="color: orange;"><strong>Se han sobrevendido: </strong>' . intval($sale_details['oversell']) . ' unidades</li>';
                    }
                    echo '</ul>';
                    echo '<p>-------------------</p>';
                }
                // 2) Mostrar CANCELACIONES si cancel_details no está vacío.
                elseif (!empty($stockvalue['cancel_details'])) {
                    $cancel_details = maybe_unserialize($stockvalue['cancel_details']);
                    
                    echo '<p><strong>Fecha y hora: ' . $datetime->format('d/m/Y g:i a') . '</strong></p>';
                    echo '<ul>';
                    echo '<li style="color:red;"><strong>Cancelación registrada</strong></li>';
                    echo '<li><strong>Cantidad cancelada:</strong> ' . intval($stockvalue['quantity_cancelled']) . '</li>';
                    echo '<li><strong>Cancelada por:</strong> ' . esc_html($cancel_details['cancelled_by']) . '</li>';
                    echo '<li><strong>Orden Número:</strong> ' . esc_html($cancel_details['order_number']) . '</li>';
                    echo '<li><strong>Cliente:</strong> ' . esc_html($cancel_details['order_name']) . '</li>';
                    echo '<li><strong>Cambio de inventario:</strong> ' . intval($stockvalue['stock']) . ' -> ' . intval($stockvalue['new_stock']) . '</li>';
                    echo '</ul>';
                    echo '<p>-------------------</p>';
                }
                // 3) Mostrar CAMBIOS MANUALES / QUICK EDIT / INESPERADOS.
                else {
                    // Se obtienen los valores registrados.
                    $old_stock = isset($stockvalue['stock']) ? (int)$stockvalue['stock'] : 0;
                    $new_stock = isset($stockvalue['new_stock']) ? (int)$stockvalue['new_stock'] : 0;
                    // Calcular la diferencia y el valor absoluto.
                    $difference = $old_stock - $new_stock;
                    $absolute_difference = abs($difference);
                    
                    $meta = !empty($stockvalue['stock_change_meta']) ? maybe_unserialize($stockvalue['stock_change_meta']) : array();
                    $usuario = !empty($meta['user_name']) ? $meta['user_name'] : 'Desconocido';
                    $process = isset($meta['process']) ? $meta['process'] : 'Cambio manual (admin)';
                    
                    if ($difference > 0) {
                        $tipo_cambio = '<span style="color: red">Disminución de inventario</span>';
                    } elseif ($difference < 0) {
                        $tipo_cambio = '<span style="color: green">Aumento de inventario</span>';
                    } else {
                        $tipo_cambio = 'Sin cambio';
                    }
                    
                    echo '<p><strong>Fecha y hora: ' . $datetime->format('d/m/Y g:i a') . '</strong></p>';
                    echo '<p><strong>' . esc_html($process) . '</strong></p>';
                    echo '<ul>';
                    echo '<li><strong>Inventario Anterior:</strong> ' . $old_stock . '</li>';
                    echo '<li><strong>Inventario Actual:</strong> ' . $new_stock . '</li>';
                    echo '<li><strong>Cambio en inventario:</strong> ' . $absolute_difference  . ' (' . $tipo_cambio . ')</li>';
                    echo '<li><strong>Realizado por:</strong> ' . esc_html($usuario) . '</li>';
                    echo '</ul>';
                    echo '<p>-------------------</p>';
                }
            }
        } else {
            echo '<p>No hay historial de inventario para este producto.</p>';
        }

        echo '<p>Existencias actuales: <b>' . $product->get_stock_quantity() . '</b></p>';
    }
}
