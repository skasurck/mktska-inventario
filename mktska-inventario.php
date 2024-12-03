<?php
/**
 * @package Mktska_Inventario
 * @version 1.0.1
 */

/*
Plugin Name: Mktska_inventario
Plugin URI: http://mktska.com
Description: Este plugin lleva un control completo del inventario de salidas y entradas de cada uno de los productos y quien hizo ese movimiento a cualquier modificación en el producto.
Author: Mktska
Version: 1.0.1
Author URI: http://mktska.com
*/

// Establece la zona horaria predeterminada a 'America/Mexico_City' (GMT-6)
date_default_timezone_set('America/Mexico_City'); // Establecer zona horaria a GMT-6

// Crear tabla en la activación del plugin
register_activation_hook(__FILE__, 'crear_tabla_stock_history'); // Registra la función 'crear_tabla_stock_history' para ejecutarse al activar el plugin

/**
 * Función para crear la tabla 'stock_history' en la base de datos al activar el plugin.
 */
function crear_tabla_stock_history() {
    global $wpdb; // Accede al objeto global $wpdb para interactuar con la base de datos
    $charset_collate = $wpdb->get_charset_collate(); // Obtiene el conjunto de caracteres y colación
    $table_name = $wpdb->prefix . 'stock_history'; // Define el nombre de la tabla con el prefijo de WordPress

    // Verifica si la tabla ya existe
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        // Consulta SQL para crear la tabla
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

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php'); // Incluye el archivo necesario para usar dbDelta
        dbDelta($sql); // Ejecuta la consulta para crear o actualizar la tabla en la base de datos
    }
}

/* Historial de stock de producto */
add_action('woocommerce_product_set_stock', 'ayudawp_historial_stock_inferior'); // Añade una acción cuando se establece el stock de un producto simple
add_action('woocommerce_variation_set_stock', 'ayudawp_historial_stock_inferior'); // Añade una acción cuando se establece el stock de una variación de producto

/**
 * Función para registrar cambios en el stock del producto en la tabla 'stock_history'.
 *
 * @param WC_Product $product El objeto producto de WooCommerce.
 */

// Captura el stock antes de guardar el producto
add_action('woocommerce_pre_product_object_save', 'capturar_stock_antes_cambio');
function capturar_stock_antes_cambio($product) {
    $product->old_stock = $product->get_stock_quantity('edit'); // Captura el stock actual antes de los cambios
}

// Registra el cambio en el stock después de actualizar el producto
function ayudawp_historial_stock_superior($product)
{
    global $wpdb; // Accede al objeto global $wpdb
    $table_name = $wpdb->prefix . 'stock_history'; // Nombre de la tabla

    // Obtener el stock antes del cambio
    $old_stock = isset($product->old_stock) ? $product->old_stock : $product->get_stock_quantity('edit');

    // Obtener el stock después del cambio
    $new_stock = $product->get_stock_quantity();

    // Obtener los metadatos del cambio de stock
    $stock_change_meta = get_post_meta($product->get_id(), '_stock_change_meta', true);

    // Inserta el nuevo registro en la tabla
    $wpdb->insert($table_name, array(
        'timestamp' => current_time('mysql'), // Hora actual en formato MySQL
        'product_id' => $product->get_id(), // ID del producto
        'stock' => $old_stock, // Stock antes del cambio
        'new_stock' => $new_stock, // Stock después del cambio
        'stock_change_meta' => maybe_serialize($stock_change_meta) // Metadatos del cambio
    ));
}

// Añade una caja meta al producto para mostrar el historial de inventario
add_action('add_meta_boxes', 'ayudawp_caja_meta_historial_stock'); // Añade una acción para agregar metaboxes

/**
 * Función para registrar una caja meta en la página de edición del producto.
 */
function ayudawp_caja_meta_historial_stock()
{
    add_meta_box(
        'stock_history', // ID único para la caja meta
        'Historial de inventario', // Título de la caja meta
        'ayudawp_mostrar_historial_stock', // Función que renderiza el contenido de la caja
        'product', // Tipo de post al que se aplica (producto)
        'advanced', // Contexto donde se muestra la caja (avanzado)
        'high' // Prioridad de la caja
    );
}

/**
 * Función para mostrar el historial de stock de un producto en WooCommerce.
 *
 * Esta función recupera y muestra el historial de cambios de stock para un producto específico.
 */
function ayudawp_mostrar_historial_stock()
{
    global $post, $wpdb; // Variables globales
    $product = wc_get_product($post->ID); // Obtiene el objeto producto actual
    $table_name = $wpdb->prefix . 'stock_history'; // Nombre de la tabla
    $stock_old = $product->get_stock_quantity(); // Stock actual

    // Verifica si el producto es variable
    if ($product->get_type() == 'variable') {
        foreach ($product->get_available_variations() as $key) {
            $products[] = $key['variation_id']; // Agrega las variaciones al array
        }
    } else {
        $products[] = $post->ID; // Agrega el ID del producto si es simple
    }

    // Recorre cada producto o variación
    foreach ($products as $product_id) {
        $product = wc_get_product($product_id); // Obtiene el objeto producto
        echo '<h3>' . $product->get_name() . '</h3>'; // Muestra el nombre del producto
        $stock_history = $wpdb->get_results("SELECT * FROM $table_name WHERE product_id = " . $product_id, ARRAY_A); // Recupera el historial

        if ($stock_history) {
            foreach ($stock_history as $stockvalue) {

                if (!isset($stockvalue['stock'])) {
                    continue; // Si no hay stock, pasa al siguiente
                }
                $datetime = new DateTime($stockvalue['timestamp']); // Crea un objeto DateTime
                $datetime->setTimezone(new DateTimeZone('GMT-6')); // Establece la zona horaria

                // Si existen detalles de venta
                if (isset($stockvalue['sale_details'])) {
                    $sale_details = maybe_unserialize($stockvalue['sale_details']); // Deserializa los detalles
                    if (is_array($sale_details) && isset($sale_details['order_number'])) {
                        $sale_details_json = json_decode($sale_details['order_number'], true); // Decodifica el JSON
                        echo '<p>Fecha y hora: ' . $datetime->format('d/m/Y g:i a') . '</b></p>';
                        if($stockvalue['stock'] == 1){
                            echo '<b></p> <p>Se vendió ' . $stockvalue['stock'] . '  producto</b></p>';
                        }else{
                            echo '<b></p> <p>Se vendieron ' . $stockvalue['stock'] . '  productos</b></p>';
                        }
                        if (empty($sale_details['order_name'])) {
                            echo '<p> Compra realizada en Punto de Venta</p>';
                        } else {
                            echo '<p> Compra realizada por: ' . $sale_details['order_name'] . '</p>';
                        }
                        
                        echo '<p> Acción: Compra - Orden Número: ' . $sale_details_json['id'] . ', Estado: ' . $sale_details_json['status'] . '</p>';
                    }
                    echo '<p>-------------------</p>';
                }

                // Si existen detalles de cancelación
                if (isset($stockvalue['cancel_details'])) {
                    $cancel_details = maybe_unserialize($stockvalue['cancel_details']); // Deserializa los detalles
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

                // Si no hay detalles de venta ni cancelación, es un cambio manual
                if ($stockvalue['cancel_details'] === null && $stockvalue['sale_details'] === null) {
                    $stock_change_meta = maybe_unserialize($stockvalue['stock_change_meta']); // Deserializa los metadatos
                    
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
                        echo '<p>El Producto no tiene historial de inventario previo, inicia con '.$valor1.' piezas.</p>';
                    }
                } 
            }
        }
        echo '<p>Existencias actuales: <b>' . $product->get_stock_quantity() . '</b></p>'; // Muestra el stock actual
    }
}

// Remueve una acción para evitar conflictos
remove_action('woocommerce_order_item_product_set_stock', 'guardar_detalles_venta'); // Elimina una función de un hook específico
add_action('woocommerce_reduce_order_stock', 'guardar_detalles_venta'); // Añade una acción cuando se reduce el stock por una orden

/**
 * Función para guardar detalles de venta al reducir el stock.
 *
 * @param WC_Order $order La orden de WooCommerce.
 */
function guardar_detalles_venta($order) {
    global $wpdb; // Accede al objeto global $wpdb
    $table_name = $wpdb->prefix . 'stock_history'; // Nombre de la tabla

    // Recorre los ítems de la orden
    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id(); // ID del producto
        $quantity = $item->get_quantity(); // Cantidad vendida
        $order_number = $order->get_order_number(); // Número de orden
        $order_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(); // Nombre del cliente

        $sale_details = array(
            'order_number' => $order,
            'order_name' => $order_name
        );

        // Inserta los detalles de la venta en la tabla
        $wpdb->insert(
            $table_name,
            array(
                'timestamp' => current_time('mysql'), // Hora actual
                'product_id' => $product_id, // ID del producto
                'stock' => $quantity, // Cantidad vendida
                'sale_details' => maybe_serialize($sale_details) // Detalles de la venta serializados
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

// Añade una acción cuando una orden es cancelada
add_action('woocommerce_order_status_cancelled', 'guardar_detalles_cancelacion');

/**
 * Función para guardar detalles de cancelación en el historial de stock.
 *
 * @param int $order_id El ID de la orden cancelada.
 */
function guardar_detalles_cancelacion($order_id) {
    global $wpdb; // Accede al objeto global $wpdb
    $table_name = $wpdb->prefix . 'stock_history'; // Nombre de la tabla
    $order = wc_get_order($order_id); // Obtiene la orden

    // Recorre los ítems de la orden
    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id(); // ID del producto
        $quantity = $item->get_quantity(); // Cantidad cancelada
        $order_number = $order->get_order_number(); // Número de orden
        $order_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(); // Nombre del cliente
        $cancelled_by = wp_get_current_user()->user_login; // Usuario que canceló

        // Recuperar el producto y el inventario actual
        $product = wc_get_product($product_id);
        $old_stock = $product->get_stock_quantity(); // Stock actual
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

// Añade una acción cuando se establece el stock de un producto
add_action('woocommerce_product_set_stock', 'ayudawp_historial_stock_inferior');

/**
 * Función para registrar cambios en el stock del producto en la tabla 'stock_history'.
 *
 * @param WC_Product $product El objeto producto de WooCommerce.
 */
function ayudawp_historial_stock_inferior($product)
{
    global $wpdb; // Accede al objeto global $wpdb
    $table_name = $wpdb->prefix . 'stock_history'; // Nombre de la tabla

    // Obtiene el historial de stock del producto
    $stock_history = $wpdb->get_results("SELECT * FROM $table_name WHERE product_id = " . $product->get_id(), ARRAY_A);

    // Agrega un nuevo registro al historial
    $stock_history[] = array(
        'timestamp' => time(), // Marca de tiempo actual
        'stock' => (int) get_post_meta($product->get_id(), '_stock', true), // Stock actual
        'stock_change_meta' => get_post_meta($product->get_id(), '_stock_change_meta', true) // Metadatos del cambio
    );

    // Inserta el nuevo registro en la tabla
    $wpdb->insert(
        $table_name,
        array(
            'timestamp' => current_time('mysql'), // Hora actual
            'product_id' => $product->get_id(), // ID del producto
            'stock' => end($stock_history)['stock'], // Stock actual
            'new_stock' => $product->get_stock_quantity(), // Nueva cantidad de stock
            'stock_change_meta' => maybe_serialize(end($stock_history)['stock_change_meta']) // Metadatos serializados
        ),
        array(
            '%s',
            '%d',
            '%d',
            '%s'
        )
    );
}

// Captura el stock antes de guardar cambios en el producto
add_action('woocommerce_pre_product_object_save', 'capturar_stock_antes_cambio');

/**
 * Función para capturar el stock antes de que se guarde el producto.
 *
 * @param WC_Product $product El objeto producto de WooCommerce.
 */
function capturar_stock_antes_cambio($product) {
    $product->old_stock = $product->get_stock_quantity('edit'); // Almacena el stock antiguo en una propiedad del objeto
}

// Guarda los cambios en el stock después de actualizar las propiedades del producto
add_action('woocommerce_product_object_updated_props', 'ayudawp_guardar_cambio_stock_meta', 10, 2);

/**
 * Función para registrar en el historial los cambios manuales de stock.
 *
 * @param WC_Product $product El objeto producto de WooCommerce.
 * @param array $updated_props Las propiedades actualizadas.
 */
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
        $old_stock = isset($product->old_stock) ? $product->old_stock : $product->get_stock_quantity('edit'); // Stock antiguo
        $new_stock = $product->get_stock_quantity(); // Nuevo stock

        // Verificar si el stock realmente cambió
        if ($old_stock === $new_stock) {
            return;
        }

        $user_name = wp_get_current_user()->user_login; // Nombre de usuario que realizó el cambio
        $process = 'Se actualizó manualmente por un usuario'; // Proceso

        $stock_change_meta = array(
            'old_stock' => $old_stock,
            'new_stock' => $new_stock,
            'user_name' => $user_name,
            'process' => $process
        );

        update_post_meta($product->get_id(), '_stock_change_meta', $stock_change_meta); // Guarda los metadatos

        // Registrar en la tabla de historial de inventario
        global $wpdb; // Accede al objeto global $wpdb
        $table_name = $wpdb->prefix . 'stock_history'; // Nombre de la tabla
        $wpdb->insert(
            $table_name,
            array(
                'timestamp' => current_time('mysql'), // Hora actual
                'product_id' => $product->get_id(), // ID del producto
                'stock' => $old_stock, // Stock antiguo
                'new_stock' => $new_stock, // Nuevo stock
                'stock_change_meta' => maybe_serialize($stock_change_meta) // Metadatos serializados
            ),
            array('%s', '%d', '%d', '%d', '%s')
        );
    }
}

// Añade una acción para registrar el reabastecimiento de inventario
add_action('woocommerce_order_item_quantity_restocked', 'registrar_restock_item', 10, 2);

/**
 * Función para registrar en el historial cuando se reabastece el stock de un ítem de orden.
 *
 * @param WC_Order $order La orden de WooCommerce.
 * @param int $item_id El ID del ítem reabastecido.
 */
function registrar_restock_item($order, $item_id) {
    global $wpdb; // Accede al objeto global $wpdb
    $table_name = $wpdb->prefix . 'stock_history'; // Nombre de la tabla

    // Obtener el item correctamente
    $item = $order->get_item($item_id); // Obtiene el ítem
    $product_id = $item->get_product_id(); // ID del producto
    $quantity = $item->get_quantity(); // Cantidad reabastecida
    $order_number = $order->get_order_number(); // Número de orden
    $order_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(); // Nombre del cliente
    $cancelled_by = wp_get_current_user()->user_login; // Usuario que realizó el reabastecimiento

    // Obtener el producto y el inventario actual (después de la cancelación)
    $product = wc_get_product($product_id);
    $new_stock = $product->get_stock_quantity(); // Stock actual

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
            'timestamp' => current_time('mysql'), // Hora actual
            'product_id' => $product_id, // ID del producto
            'stock' => $old_stock, // Stock antes de la cancelación
            'new_stock' => $new_stock, // Stock después de la cancelación
            'stock_change_meta' => maybe_serialize($cancel_details), // Metadatos serializados
            'cancel_details' => maybe_serialize($cancel_details) // Detalles de cancelación serializados
        ),
        array('%s', '%d', '%d', '%d', '%s', '%s')
    );
}
?>
