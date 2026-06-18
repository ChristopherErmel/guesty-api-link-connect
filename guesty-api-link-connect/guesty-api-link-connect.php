<?php
/**
 * Plugin Name: Guesty API Link Connect
 * Description: Production-ready connection from Guesty API to WordPress for caching and connecting unit data and filters.
 * Version: 4.4.0
 * Author: Christopher E
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define Core Plugin Constants
define('GUESTY_ALC_PATH', plugin_dir_path(__FILE__));
define('GUESTY_ALC_URL', plugin_dir_url(__FILE__));

// Load Core Classes
require_once GUESTY_ALC_PATH . 'includes/class-guesty-api.php';
require_once GUESTY_ALC_PATH . 'admin/class-guesty-admin.php';
require_once GUESTY_ALC_PATH . 'frontend/class-guesty-frontend.php';
require_once GUESTY_ALC_PATH . 'includes/addon-guesty-foryou.php';
require_once GUESTY_ALC_PATH . 'includes/shortcode-locations-grid.php';
require_once GUESTY_ALC_PATH . 'includes/addon-location-locker.php';

/**
 * Initialize the plugin
 */
function run_guesty_api_link_connect() {
    // 1. Initialize the API & Cache Engine
    $api = new Guesty_ALC_API();

    // 2. Initialize the Backend Dashboard (injecting the API engine)
    if (is_admin()) {
        new Guesty_ALC_Admin($api);
    }

    // 3. Initialize the Frontend Shortcodes & AJAX (injecting the API engine)
    new Guesty_ALC_Frontend($api);

    // 4. Initialize the "For You" Recommendation Engine Addon
    new Guesty_ALC_ForYou($api);
}

// Boot the plugin
run_guesty_api_link_connect();
