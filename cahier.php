<?php

/**
 * Plugin Name:         Cahier
 * Plugin URI:          https://github.com/analoguesorg/cahier
 * Description:         Cahier is a collection of custom modules for www.analogues.org
 * Version:             0.0.3
 * Text Domain:         analogues-cahier
 * GitHub Plugin URI:   analoguesorg/cahier
 * Primary Branch:      main
 *
 * Requires at least:   6.0
 * Requires PHP:        7.4
 *
 * Author:              Sahil Nawab
 * Author URI:          https://analogues.org/development
 * License:             GPL-3.0-or-later
 * License URI:         https://www.gnu.org/licenses/gpl-3.0.html
 * Copyright:			(c) 2026 Sahil Nawab; all rights reserved.
 */

declare(strict_types=1);

namespace Cahier;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// -----------------------------------------------------------------------------
// Core Constants
// -----------------------------------------------------------------------------
define( 'CAHIER_VERSION', '0.0.3' );
define( 'CAHIER_FILE', __FILE__ );
define( 'CAHIER_PATH', plugin_dir_path( __FILE__ ) );
define( 'CAHIER_URL', plugin_dir_url( __FILE__ ) );

// Load optional local configuration overrides if present
if ( file_exists( CAHIER_PATH . 'config.php' ) ) {
    require_once CAHIER_PATH . 'config.php';
}

// -----------------------------------------------------------------------------
// Autoloader & Kernel Instantiation
// -----------------------------------------------------------------------------
require_once CAHIER_PATH . 'core/class-module-contract.php';
require_once CAHIER_PATH . 'core/class-plugin.php';

add_action( 'plugins_loaded', static function() {
    \Cahier\Core\Plugin::instance();
}, 5 );