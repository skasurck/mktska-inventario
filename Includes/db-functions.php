<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente al archivo.
}

/**
 * Función para crear la tabla 'stock_history' en la base de datos.
 * 
 * Esta función crea una tabla personalizada en la base de datos para almacenar el historial de inventario de los productos.
 * Se verifica primero si la tabla ya existe para evitar duplicados. 
 * Si la tabla no existe, se ejecuta el script SQL para crearla.
 */
function mktska_crear_tabla_stock_history() {
    global $wpdb;

    // Obtiene la codificación de caracteres utilizada por la base de datos (charset y collation).
    $charset_collate = $wpdb->get_charset_collate();

    // Define el nombre de la tabla con el prefijo de WordPress.
    $table_name = $wpdb->prefix . 'stock_history';

    // Verifica si la tabla 'stock_history' ya existe en la base de datos.
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        // SQL para crear la tabla si no existe.
        // Crear la tabla 'stock_history' para almacenar el historial de cambios de inventario.
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,           -- ID único para cada registro en la tabla (clave primaria).
            timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL, -- Fecha y hora del registro (cuándo ocurrió la transacción).
            product_id mediumint(9) NOT NULL,                  -- ID del producto afectado.
            stock int(11) NOT NULL,                            -- Stock antes de la transacción.
            stock_old int(11),                                 -- (Opcional) Stock previo a la transacción para histórico.
            new_stock int(11),                                 -- Stock después de la transacción.
            quantity int(11),                                  -- Cantidad vendida del producto en la transacción.
            stock_change_meta text NOT NULL,                   -- Metadatos adicionales sobre los cambios de inventario.
            sale_details text,                                 -- Detalles de la venta (número de orden, nombre del cliente, etc.).
            cancel_details text,                               -- Detalles de la cancelación, si aplica.
            PRIMARY KEY  (id)                                  -- La clave primaria es el campo 'id'.
        ) $charset_collate;";

// Ejecutar la consulta SQL usando dbDelta.
require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
dbDelta( $sql );


        // Incluye el archivo necesario para ejecutar la función de creación o actualización de tablas en WordPress.
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // Ejecuta la instrucción SQL para crear o actualizar la tabla en la base de datos.
        dbDelta( $sql );
    }
}
