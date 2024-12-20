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
// Definir constantes para las rutas
define( 'MKTSKA_INVENTARIO_PATH', plugin_dir_path( __FILE__ ) );
define( 'MKTSKA_INVENTARIO_URL', plugin_dir_url( __FILE__ ) );

// Ruta personalizada para el archivo de log
define( 'MKTSKA_DEBUG_LOG', MKTSKA_INVENTARIO_PATH . 'mktdebug.log' );

// Función para escribir logs con marca de tiempo
function mktska_escribir_log($mensaje) {
    $marca_tiempo = date('Y-m-d H:i:s');
    $mensaje_log = "[{$marca_tiempo}] {$mensaje}\n";
    file_put_contents(MKTSKA_DEBUG_LOG, $mensaje_log, FILE_APPEND);
}

/* Ejemplo de uso en init
add_action('init', function() {
    if (class_exists('WooCommerce')) {
        // Hook para ventas
        add_action('woocommerce_reduce_order_stock', 'mktska_guardar_detalles_venta', 10, 1);
        mktska_escribir_log("El hook de venta fue registrado correctamente en 'init'.");
        
        // Hook para cancelaciones
        add_action('woocommerce_order_status_cancelled', 'mktska_guardar_detalles_cancelacion', 10, 1);
        mktska_escribir_log("El hook de cancelación fue registrado correctamente en 'init'.");
    } else {
        mktska_escribir_log("WooCommerce no está activo, hooks no registrados.");
    }
});*/

// Incluir archivos necesarios
require_once MKTSKA_INVENTARIO_PATH . 'includes/db-functions.php';
require_once MKTSKA_INVENTARIO_PATH . 'includes/stock-history.php';
require_once MKTSKA_INVENTARIO_PATH . 'includes/admin-meta-box.php';
require_once MKTSKA_INVENTARIO_PATH . 'includes/sales-functions.php';
require_once MKTSKA_INVENTARIO_PATH . 'includes/cancel-functions.php';
require_once MKTSKA_INVENTARIO_PATH . 'includes/stock-changes.php';

// Registrar la función de activación
register_activation_hook( __FILE__, 'mktska_crear_tabla_stock_history' );
