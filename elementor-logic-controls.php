<?php
/**
 * Plugin Name: Elementor Logic Controls
 * Plugin URI:  https://example.com/
 * Description: Adds advanced logic controls to Elementor widgets.
 * Version:     1.1.66
 * Author:      Nick Gaultney
 * Author URI:  https://gauwebsolutions.com/
 * Text Domain: elementor-logic-controls
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License:     GPL v2 or later
 * GitHub Plugin URI: https://github.com/NickGaultney/elementor-logic-controls
 * Primary Branch: master
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'ELC_VERSION', '1.1.66' );
define( 'ELC_PATH', plugin_dir_path( __FILE__ ) );
define( 'ELC_URL', plugin_dir_url( __FILE__ ) );

// Include the core plugin file.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-logic-controls.php';

// Include the logic assistant file.
require_once plugin_dir_path( __FILE__ ) . 'includes/logic-assistant.php';

// Initialize the plugin.
add_action( 'plugins_loaded', [ 'Elementor_Logic_Controls', 'init' ] );
