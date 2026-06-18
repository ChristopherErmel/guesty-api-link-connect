<?php
/**
 * Addon: Location Page Search Locker
 * Automatically detects if the current URL matches a Guesty location,
 * locks the search dropdown to that location, and triggers the search.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action('wp_footer', 'guesty_location_locker_script', 99);

function guesty_location_locker_script() {
    // Pull the active locations
    $cached_locations = get_transient( 'guesty_unique_locations' );
    
    if ( ! is_array( $cached_locations ) || empty( $cached_locations ) ) {
        return;
    }

    $map = array();
    foreach ( $cached_locations as $loc ) {
        // Strip suffixes to get the base name (e.g., "Armour")
        $clean_name = str_ireplace( array(', Canada', ', USA'), '', $loc );
        $clean_name = trim( $clean_name );
        
        // Convert to URL slug (e.g., "armour")
        $slug = sanitize_title( $clean_name );
        
        // Map the slug directly to the exact dropdown value (e.g. "Armour, Canada")
        $map[$slug] = $loc;
    }

    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Pass the PHP dictionary to Javascript
        const locMap = <?php echo wp_json_encode( $map ); ?>;
        
        // Grab the last part of the current URL
        let pathSegments = window.location.pathname.replace(/\/$/, '').split('/');
        let currentSlug = pathSegments[pathSegments.length - 1];
        
        // If the current URL matches one of our locations (e.g. they are on /armour/)
        if (currentSlug && locMap[currentSlug]) {
            const searchSelect = document.querySelector('.gvs-search-loc');
            
            if (searchSelect) {
                // 1. Force the dropdown to the exact database value
                searchSelect.value = locMap[currentSlug];
                
                // 2. Visually and technically lock the dropdown
                const parentField = searchSelect.closest('.gvs-search-field');
                if (parentField) {
                    parentField.style.opacity = '0.5';
                    parentField.style.pointerEvents = 'none';
                    
                    const label = parentField.querySelector('label');
                    if (label) label.innerText = 'Location (Locked)';
                } else {
                    searchSelect.style.pointerEvents = 'none';
                    searchSelect.style.opacity = '0.5';
                }
                
                // 3. Auto-trigger the Search button to filter the units
                // We add a slight 500ms delay to ensure the grid has fully loaded first
                setTimeout(() => {
                    const searchBtn = document.querySelector('.gvs-do-search');
                    if (searchBtn) {
                        searchBtn.click();
                    }
                }, 500);

                // 4. FIX: Overpower the "Clear" button so it doesn't unlock the location
                const clearBtns = document.querySelectorAll('.gvs-clear-button');
                clearBtns.forEach(btn => {
                    btn.addEventListener('click', () => {
                        // Wait a fraction of a second (50ms) for the native Clear script to empty the dates/guests
                        setTimeout(() => {
                            // Instantly force the location back into the dropdown
                            searchSelect.value = locMap[currentSlug];
                            
                            // Click the search button again to fetch the locked location results
                            const searchBtn = document.querySelector('.gvs-do-search');
                            if (searchBtn) searchBtn.click();
                        }, 50); 
                    });
                });
            }
        }
    });
    </script>
    <?php
}
