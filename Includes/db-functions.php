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
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            product_id mediumint(9) NOT NULL,
            stock int(11) NOT NULL,
            stock_old int(11),
            new_stock int(11),
            quantity int(11),
            quantity_cancelled int(11) DEFAULT 0,  -- Agregamos la columna para cancelaciones
            stock_change_meta text NOT NULL,
            sale_details text,
            cancel_details text,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Incluye el archivo necesario para ejecutar la función de creación o actualización de tablas en WordPress.
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // Ejecuta la instrucción SQL para crear o actualizar la tabla en la base de datos.
        dbDelta( $sql );
    }
}
