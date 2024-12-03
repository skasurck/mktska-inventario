<?php
/**
 * Plugin Name: Mktska Inventario
 * Plugin URI: http://mktska.com
 * Description: Control de inventario para entradas y salidas de productos.
 * Author: Mktska
 * Version: 1.0.2
 * Author URI: http://mktska.com
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente
}

date_default_timezone_set('America/Mexico_City');

// Definir constantes para las rutas
define( 'MKTSKA_INVENTARIO_PATH', plugin_dir_path( __FILE__ ) );
define( 'MKTSKA_INVENTARIO_URL', plugin_dir_url( __FILE__ ) );

// Incluir archivos necesarios
require_once MKTSKA_INVENTARIO_PATH . 'includes/db-functions.php';
require_once MKTSKA_INVENTARIO_PATH . 'includes/stock-history.php';
require_once MKTSKA_INVENTARIO_PATH . 'includes/admin-meta-box.php';
require_once MKTSKA_INVENTARIO_PATH . 'includes/sales-functions.php';
require_once MKTSKA_INVENTARIO_PATH . 'includes/cancel-functions.php';
require_once MKTSKA_INVENTARIO_PATH . 'includes/stock-changes.php';

// Registrar la función de activación
register_activation_hook( __FILE__, 'mktska_crear_tabla_stock_history' );