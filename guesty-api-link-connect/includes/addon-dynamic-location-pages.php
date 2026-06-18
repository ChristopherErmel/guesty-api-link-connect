<?php
/**
 * Addon: Dynamic Location Pages
 * Catches 404 errors, checks if the URL is a valid location, and generates a virtual page on the fly.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Register our new Custom Settings to the native WordPress engine
add_action('admin_init', 'guesty_alc_auto_loc_register_settings');
function guesty_alc_auto_loc_register_settings() {
    // By registering these to your existing 'guesty-settings-group', WordPress natively handles saving & stripping slashes!
    register_setting('guesty-settings-group', 'guesty_alc_auto_loc_shortcode', 'sanitize_text_field');
    register_setting('guesty-settings-group', 'guesty_alc_auto_loc_header_text', 'sanitize_text_field');
    register_setting('guesty-settings-group', 'guesty_alc_auto_loc_browser_title', 'sanitize_text_field');
    register_setting('guesty-settings-group', 'guesty_alc_auto_loc_bg_color', 'sanitize_hex_color');
    register_setting('guesty-settings-group', 'guesty_alc_auto_loc_search_color', 'sanitize_hex_color');
    register_setting('guesty-settings-group', 'guesty_alc_auto_loc_header_color', 'sanitize_hex_color');
}

// 2. The Dynamic Page Handler
add_action('template_redirect', 'guesty_dynamic_location_page_handler');

function guesty_dynamic_location_page_handler() {
    global $wp_query;

    // Only intercept if WordPress thinks this page doesn't exist (404 error)
    if ( ! is_404() ) {
        return;
    }

    $path = trim($_SERVER['REQUEST_URI'], '/');
    $path = explode('?', $path)[0]; // Strip any URL parameters
    $segments = explode('/', $path);
    $slug = end($segments); // Get the very last part of the URL

    if ( empty($slug) ) {
        return;
    }

    $cached_locations = get_transient( 'guesty_unique_locations' );
    
    if ( ! is_array( $cached_locations ) || empty( $cached_locations ) ) {
        return;
    }

    $matched_location = false;
    
    foreach ( $cached_locations as $loc ) {
        // Clean the name exactly how we do in the grid and the locker
        $clean_name = str_ireplace( array(', Canada', ', USA'), '', $loc );
        $clean_name = trim( $clean_name );
        
        // If the URL matches our location slug
        if ( sanitize_title( $clean_name ) === $slug ) {
            $matched_location = $clean_name;
            break;
        }
    }

    if ( $matched_location ) {
        // Fetch User Configured Settings from the Admin Panel (with defaults)
        $shortcode    = get_option('guesty_alc_auto_loc_shortcode', '[YOUR_SEARCH_SHORTCODE]');
        $header_fmt   = get_option('guesty_alc_auto_loc_header_text', 'Explore {location}');
        
        // Failsafe: Strip any existing slashes that were saved before this patch
        $shortcode    = stripslashes($shortcode); 
        
        $bg_color     = get_option('guesty_alc_auto_loc_bg_color', '#f7f9fc');
        $search_color = get_option('guesty_alc_auto_loc_search_color', '#ffffff');
        $header_color = get_option('guesty_alc_auto_loc_header_color', '#001f3f');

        // Dynamically build the header text replacing the placeholder
        $custom_header = str_ireplace('{location}', $matched_location, $header_fmt);

        // Fetch and format the Browser Tab Title
        $browser_title_fmt = get_option('guesty_alc_auto_loc_browser_title', '{location} Vacations & Stays - {site_name}');
        $site_name         = get_bloginfo('name');
        $browser_title     = str_ireplace( array('{location}', '{site_name}'), array($matched_location, $site_name), $browser_title_fmt );

        // Force a 200 OK success status instead of a 404 error
        status_header( 200 );
        $wp_query->is_404 = false;
        $wp_query->is_page = true;
        
        // Aggressively override the Browser Tab Title (handles Native WP, Yoast, and RankMath)
        add_filter( 'pre_get_document_title', function() use ( $browser_title ) { return $browser_title; }, 999 );
        add_filter( 'wpseo_title', function() use ( $browser_title ) { return $browser_title; }, 999 ); // Yoast Override
        add_filter( 'rank_math/frontend/title', function() use ( $browser_title ) { return $browser_title; }, 999 ); // RankMath Override

        get_header();
        
        ?>
        <!-- Dynamic Styles based on Admin Settings -->
        <style>
            /* Apply custom background color */
            body {
                background-color: <?php echo esc_attr($bg_color); ?> !important;
            }
            .guesty-dynamic-location-wrapper {
                max-width: 1400px;
                margin: 60px auto;
                padding: 0 20px;
                min-height: 60vh;
            }
            /* Apply custom header text color */
            .guesty-dynamic-location-header {
                text-align: center;
                margin-bottom: 40px;
                font-family: Georgia, serif;
                font-size: 3.5rem;
                color: <?php echo esc_attr($header_color); ?> !important;
            }
            /* Targeting the Search Bar Container for custom colors */
            .guesty-dynamic-location-wrapper .gvs-search-bar,
            .guesty-dynamic-location-wrapper .gvs-search-bar-container {
                background-color: <?php echo esc_attr($search_color); ?> !important;
                border-color: <?php echo esc_attr($search_color); ?> !important; 
            }
        </style>

        <div class="guesty-dynamic-location-wrapper">
            
            <h1 class="guesty-dynamic-location-header">
                <?php echo esc_html( $custom_header ); ?>
            </h1>
            
            <?php 
            // Render the user's configured shortcode dynamically
            echo do_shortcode($shortcode); 
            ?>
            
        </div>
        <?php
        
        get_footer();
        exit; 
    }
}
