<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente
}

function mktska_crear_tabla_stock_history() {
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

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
    }
}
