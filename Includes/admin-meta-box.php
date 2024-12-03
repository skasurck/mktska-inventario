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
    $stock_old = $product->get_stock_quantity();

    if ($product->get_type() == 'variable') {
        foreach ($product->get_available_variations() as $key) {
            $products[] = $key['variation_id'];
        }
    } else {
        $products[] = $post->ID;
    }

    foreach ($products as $product_id) {
        $product = wc_get_product($product_id);
        echo '<h3>' . $product->get_name() . '</h3>';
        $stock_history = $wpdb->get_results("SELECT * FROM $table_name WHERE product_id = " . $product_id . " ORDER BY timestamp DESC", ARRAY_A);

        if ($stock_history) {
            foreach ($stock_history as $stockvalue) {

                if (!isset($stockvalue['stock'])) {
                    continue;
                }
                $datetime = new DateTime($stockvalue['timestamp']);
                $datetime->setTimezone(new DateTimeZone('GMT-6'));

                // Sección de ventas actualizada
                if (isset($stockvalue['sale_details'])) {
                    $sale_details = maybe_unserialize($stockvalue['sale_details']);
                    $datetime = new DateTime($stockvalue['timestamp']);
                    $datetime->setTimezone(new DateTimeZone('GMT-6'));

                    // Obtener la diferencia de stock
                    $diferencia = $stockvalue['stock'] - $stockvalue['new_stock'];

                    echo '<p>Fecha y hora: ' . $datetime->format('d/m/Y g:i a') . '</p>';
                    echo '<p> Se vendió ' . abs($diferencia) . ' producto(s)</p>';

                    if (!empty($sale_details['order_name'])) {
                        echo '<p> Compra realizada por: ' . $sale_details['order_name'] . '</p>';
                    } else {
                        echo '<p> Compra realizada por: Cliente Anónimo</p>';
                    }

                    // Verificar si la venta fue realizada a través del POS
                    if (isset($sale_details['is_pos_sale']) && $sale_details['is_pos_sale']) {
                        echo '<p> Método de venta: <strong>POS</strong></p>';
                    } else {
                        echo '<p> Método de venta: <strong>Tienda en línea</strong></p>';
                    }

                    echo '<p> Acción: Venta - Orden Número: ' . $sale_details['order_number'] . '</p>';

                    if ($diferencia > 0) {
                        echo '<p style="color: #ff0000"> Disminución: ' . $diferencia . '</p>';
                    } elseif ($diferencia < 0) {
                        echo '<p style="color: #008f39"> Incremento: ' . abs($diferencia) . '</p>';
                    } else {
                        echo '<p> No hubo cambio en el inventario. </p>';
                    }

                    echo '<p>-------------------</p>';
                }
                // Fin de la sección de ventas actualizada

                elseif (isset($stockvalue['cancel_details'])) {
                    $cancel_details = maybe_unserialize($stockvalue['cancel_details']);
                    echo '<p>Fecha y hora: ' . $datetime->format('d/m/Y g:i a') . '</b></p>';
                    echo '<p> Orden cancelada por: ' . $cancel_details['cancelled_by'] . '</p>';
                    echo '<p> Orden: ' . $cancel_details['order_number'] . ' por ' . $cancel_details['order_name'] . '</p>';

                    // Mostrar el cambio de inventario correctamente
                    echo '<p> El inventario cambió de ' . $stockvalue['stock'] . ' -> ' . $stockvalue['new_stock'] . '</p>';
                    $diferencia = $stockvalue['new_stock'] - $stockvalue['stock'];

                    if ($diferencia > 0) {
                        echo '<p style="color: #008f39 "> Incremento: ' . $diferencia . '</p>';
                    } elseif ($diferencia < 0) {
                        echo '<p style="color: #ff0000 "> Disminución: ' . abs($diferencia) . '</p>';
                    } else {
                        echo '<p> No hubo cambio en el inventario. </p>';
                    }

                    echo '<p style="color: #ff0000"> Acción: Cancelación </p>';
                    echo '<p>-------------------</p>';
                }

                elseif ($stockvalue['cancel_details'] === null && $stockvalue['sale_details'] === null) {
                    $stock_change_meta = maybe_unserialize($stockvalue['stock_change_meta']);

                    if (is_array($stock_change_meta)) {
                        if (isset($stock_change_meta['old_stock']) && isset($stockvalue['new_stock'])) {
                            if ($stock_change_meta['old_stock'] != $stockvalue['new_stock']) {
                                echo '<p>Fecha y hora: ' . $datetime->format('d/m/Y g:i a') . '</b></p>';
                                echo '<p> Cambiado por: ' . $stock_change_meta['user_name'] . '</p>';

                                echo isset($stock_change_meta['process']) ? '<p> Proceso de cambio: ' . $stock_change_meta['process'] . '</p>' : '';

                                echo '<p> El inventario cambió de ' . $stock_change_meta['old_stock'] . ' -> ' . $stockvalue['new_stock'] . '</p>';

                                if ($stock_change_meta['old_stock'] > $stockvalue['new_stock']) {
                                    $valor1 = $stock_change_meta['old_stock'] - $stockvalue['new_stock'];
                                    echo '<p style="color: #ff0000"> Disminución: ' . $valor1 . '</p>';
                                } else {
                                    $valor2 = $stockvalue['new_stock'] - $stock_change_meta['old_stock'];
                                    echo '<p style="color: #008f39"> Incremento: ' . $valor2 . '</p>';
                                }

                                if (isset($stock_change_meta['action'])) {
                                    echo '<p> Acción: ' . $stock_change_meta['action'] . '</p>';
                                }

                                echo '<p>-------------------</p>';
                            }
                        } else {
                            echo '<p> Error: la variable $stock_change_meta no contiene los índices "old_stock" o $stockvalue no contiene "new_stock". </p>';
                        }
                    } else {
                        $valor1 = $stockvalue['new_stock'];
                        echo '<p>El Producto no tiene Historial de inventario Previo, inicia con ' . $valor1 . ' piezas.</p>';
                    }
                }
            }
        }
        echo '<p>Existencias actuales: <b>' . $product->get_stock_quantity() . '</b></p>';
    }
}

