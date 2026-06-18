<?php
/**
 * Addon: Dynamic Location Pages
 * Catches 404 errors, checks if the URL is a valid location, and generates a virtual page on the fly.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action('template_redirect', 'guesty_dynamic_location_page_handler');

function guesty_dynamic_location_page_handler() {
    global $wp_query;

    // Only intercept if WordPress thinks this page doesn't exist (404 error)
    if ( ! is_404() ) {
        return;
    }

    $path = trim($_SERVER['REQUEST_URI'], '/');
    $path = explode('?', $path)[0]; // Strip any URL parameters (e.g. ?guests=2)
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
        
        // If the URL matches our location slug (e.g., 'burks-falls' === 'burks-falls')
        if ( sanitize_title( $clean_name ) === $slug ) {
            $matched_location = $clean_name;
            break;
        }
    }

    if ( $matched_location ) {
        // 1. Force a 200 OK success status instead of a 404 error
        status_header( 200 );
        $wp_query->is_404 = false;
        $wp_query->is_page = true;
        
        // 2. Change the browser tab title dynamically
        add_filter( 'document_title_parts', function( $title ) use ( $matched_location ) {
            $title['title'] = $matched_location . ' Vacations & Stays';
            return $title;
        });

        // 3. Output the site's normal Header
        get_header();
        
        // 4. Output the page content container
        ?>
        <div class="guesty-dynamic-location-wrapper" style="max-width: 1400px; margin: 60px auto; padding: 0 20px;">
            
            <h1 style="text-align: center; margin-bottom: 40px; font-family: Georgia, serif; font-size: 3rem;">
                Explore <?php echo esc_html( $matched_location ); ?>
            </h1>
            
            <?php 
            /* 
             * IMPORTANT: Replace '[YOUR_SEARCH_SHORTCODE]' with the actual shortcode 
             * that outputs your main search bar and unit grid. 
             * 
             * Once rendered, our 'addon-location-locker.php' script will automatically detect it,
             * lock it to this location, and hit search!
             */
            echo do_shortcode('[guesty_perfect_stay search_bar="true" hide_tabs="false"]'); 
            ?>
            
        </div>
        <?php
        
        // 5. Output the site's normal Footer
        get_footer();
        
        // 6. Exit immediately so WordPress doesn't try to load the actual 404 template
        exit; 
    }
}
