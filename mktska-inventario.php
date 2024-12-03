<?php
/**
 * @package Mktska_Inventario
 * @version 1.0.1
 */
/*
Plugin Name: Mktska_inventario
Plugin URI: http://mktska.com
Description: Este plugin lleva un control completo del inventario de salidas y entradas de cada uno de los productos y quien hizo ese movimiento al cual quier modificacion en el producto.
Author: Mktska
Version: 1.0.1
Author URI: http://mktska.com
*/

date_default_timezone_set('America/Mexico_City'); //Establecer zona horaria a GMT-6

// Crear tabla en la activación del plugin
register_activation_hook(__FILE__, 'crear_tabla_stock_history');
function crear_tabla_stock_history() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'stock_history';

    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            product_id mediumint(9) NOT NULL,
            stock int(11) NOT NULL,
            stock_old int(11),
            new_stock int(11),
            stock_change_meta text NOT NULL,
            sale_details text,
            cancel_details text,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

/* Historial de stock de producto */
add_action('woocommerce_product_set_stock', 'ayudawp_historial_stock_inferior');
add_action('woocommerce_variation_set_stock', 'ayudawp_historial_stock_inferior');


function ayudawp_historial_stock_superior($product)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'stock_history';

    $stock_history = $wpdb->get_results("SELECT * FROM $table_name WHERE product_id = " . $product->get_id(), ARRAY_A);

    $stock_history[] = array(
        'timestamp' => time(),
        'stock' => (int) get_post_meta($product->get_id(), '_stock', true),
        'stock_change_meta' => get_post_meta($product->get_id(), '_stock_change_meta', true)
    );

    $wpdb->insert($table_name, array(
        'timestamp' => current_time('mysql'),
        'product_id' => $product->get_id(),
        'stock' => end($stock_history)['stock'],
        'new_stock' => $product->get_stock_quantity(),
        'stock_change_meta' => maybe_serialize(end($stock_history)['stock_change_meta'])
    ));
}

add_action('add_meta_boxes', 'ayudawp_caja_meta_historial_stock');
function ayudawp_caja_meta_historial_stock()
{
    add_meta_box('stock_history', 'Historial de inventario', 'ayudawp_mostrar_historial_stock', 'product', 'advanced', 'high');
}
/**
 * Función para mostrar el historial de stock de un producto en WooCommerce.
 * 
 * Esta función recupera y muestra el historial de cambios de stock para un producto específico.
 * Si el producto es variable, se recupera el historial de stock para cada variación.
 * Los detalles incluyen la fecha y hora del cambio, la cantidad de stock, detalles de la venta (si corresponde),
 * detalles de la cancelación (si corresponde) y el stock actual del producto.
 * 
 * @global object $post Objeto global de WordPress que contiene información sobre el post/producto actual.
 * @global object $wpdb Objeto global de WordPress para interactuar con la base de datos.
 */
function ayudawp_mostrar_historial_stock()
{
    global $post, $wpdb;
    $product = wc_get_product($post->ID);
    $table_name = $wpdb->prefix . 'stock_history';
    $stock_old = $product->get_stock_quantity();

    if ($product->get_type() == 'variable') {
        foreach ($product->get_available_variations() as $key) {
            $products[] = $key['variation_id'];
        }
    } else
        $products[] = $post->ID;

    foreach ($products as $product_id) {
        $product = wc_get_product($product_id);
        echo '<h3>' . $product->get_name() . '</h3>';
        $stock_history = $wpdb->get_results("SELECT * FROM $table_name WHERE product_id = " . $product_id, ARRAY_A);

        if ($stock_history) {
            foreach ($stock_history as $stockvalue) {

                if (!isset($stockvalue['stock']))
                    continue;
                $datetime = new DateTime($stockvalue['timestamp']);
                $datetime->setTimezone(new DateTimeZone('GMT-6'));
                

                if (isset($stockvalue['sale_details'])) {
                    $sale_details = maybe_unserialize($stockvalue['sale_details']);
                    if (is_array($sale_details) && isset($sale_details['order_number'])) {
                        $sale_details_json = json_decode($sale_details['order_number'], true);
                        echo '<p>Fecha y hora: ' . $datetime->format('d/m/Y g:i a') . '</b></p>';
                        if($stockvalue['stock'] == 1){
                            echo '<b></p> <p>Se vendio ' . $stockvalue['stock'] . '  producto</b></p>';
                        }else{
                            echo '<b></p> <p>Se vendio ' . $stockvalue['stock'] . '  productos</b></p>';
                        }
                        if (empty($sale_details['order_name'])) {
                            echo '<p> Compra realizada Punto de Venta</p>';
                        } else {
                            echo '<p> Compra realizada por: ' . $sale_details['order_name'] . '</p>';
                        }
                        
                        echo '<p> Acción: Compra - Orden Número: ' . $sale_details_json['id'] . ', Estado: ' . $sale_details_json['status'] . '</p>';
                    }
                    echo '<p>-------------------</p>';
                }
                if (isset($stockvalue['cancel_details'])) {
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
                                
                if ($stockvalue['cancel_details'] === null && $stockvalue['sale_details'] === null) {
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
                        $valor1 =  $stockvalue['new_stock'];
                        echo '<p>El Producto no tiene Historial de inventario Previo inicia con '.$valor1.' piezas.</p>';
                    }
                } 
            }
        }
        echo '<p>Existencias actuales: <b>' . $product->get_stock_quantity() . '</b></p>';
    }
}

remove_action('woocommerce_order_item_product_set_stock', 'guardar_detalles_venta');
add_action('woocommerce_reduce_order_stock', 'guardar_detalles_venta');

function guardar_detalles_venta($order) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'stock_history';

    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id();
        $quantity = $item->get_quantity();
        $order_number = $order->get_order_number();
        $order_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

        $sale_details = array(
            'order_number' => $order,
            'order_name' => $order_name
        );

        $wpdb->insert(
            $table_name,
            array(
                'timestamp' => current_time('mysql'),
                'product_id' => $product_id,
                'stock' => $quantity, // Asegúrate de que este es el valor correcto para el stock
                'sale_details' => maybe_serialize($sale_details)
            ),
            array(
                '%s',
                '%d',
                '%d',
                '%s'
            )
        );
    }
}

add_action('woocommerce_order_status_cancelled', 'guardar_detalles_cancelacion');

function guardar_detalles_cancelacion($order_id) {
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


add_action('woocommerce_product_set_stock', 'ayudawp_historial_stock_inferior');

function ayudawp_historial_stock_inferior($product)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'stock_history';

    $stock_history = $wpdb->get_results("SELECT * FROM $table_name WHERE product_id = " . $product->get_id(), ARRAY_A);

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
        ),
        array(
            '%s',
            '%d',
            '%d',
            '%s'
        )
    );
}
add_action('woocommerce_pre_product_object_save', 'capturar_stock_antes_cambio');
function capturar_stock_antes_cambio($product) {
    $product->old_stock = $product->get_stock_quantity('edit');
}

add_action('woocommerce_product_object_updated_props', 'ayudawp_guardar_cambio_stock_meta', 10, 2);
function ayudawp_guardar_cambio_stock_meta($product, $updated_props) {
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



/*function ayudawp_guardar_cambio_stock_meta($product, $updated_props) {
    // Solo ejecutar si el cambio se realiza desde el área de administración
    if (!is_admin()) {
        return;
    }

    // Verificar si el cambio de inventario incluye 'stock_quantity'
    if (in_array('stock_quantity', $updated_props)) {
        $old_stock = $product->get_stock_quantity('edit');
        $new_stock = $product->get_stock_quantity();

        // Registrar el cambio
        $user_name = wp_get_current_user()->user_login;
        $process = 'Se actualizó manualmente por un usuario';

        $stock_change_meta = array(
            'old_stock' => $old_stock,
            'new_stock' => $new_stock,
            'user_name' => $user_name,
            'process' => $process
        );

        update_post_meta($product->get_id(), '_stock_change_meta', $stock_change_meta);

        // Registrar en la base de datos para el historial
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
}*/
add_action('woocommerce_order_item_quantity_restocked', 'registrar_restock_item', 10, 2);

function registrar_restock_item($order, $item_id) {
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
