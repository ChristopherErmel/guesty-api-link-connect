<?php
/**
 * Shortcode: Dynamic Locations Grid for Guesty
 * Output a living, responsive bento-box grid that grows automatically with your Guesty units.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Guesty_Locations_Grid_Shortcode {

    public function __construct() {
        add_shortcode( 'guesty_locations_grid', array( $this, 'render_shortcode' ) );
    }

    private function get_active_locations() {
        // Pull directly from the plugin's native transient cache
        $cached_locations = get_transient( 'guesty_unique_locations' );
        $final_locations = array();

        if ( is_array( $cached_locations ) && ! empty( $cached_locations ) ) {
            foreach ( $cached_locations as $loc ) {
                // Strip the ", Canada" suffix for clean display on the grid
                $loc = str_ireplace( ', Canada', '', $loc );
                $loc = str_ireplace( ', USA', '', $loc );
                $loc = trim( $loc );
                
                $ignore = array( 'yes', 'no', 'true', 'false', 'address', 'location' );
                if ( in_array( strtolower( $loc ), $ignore ) || empty( $loc ) ) {
                    continue;
                }
                
                $final_locations[] = ucwords( strtolower( $loc ) );
            }
        }

        // Clean up duplicates and sort alphabetically
        $final_locations = array_unique( $final_locations );
        sort( $final_locations );
        
        return $final_locations;
    }

    public function render_shortcode( $atts ) {
        $locations = $this->get_active_locations();
        
        // The new universally shared background image
        $base_bg_image = 'https://ocrvacations.com/wp-content/uploads/2026/02/Muskoka.jpg';
        
        // Array of unique hover images to randomly assign to locations
        $hover_images = array(
            'https://ocrvacations.com/wp-content/uploads/2026/02/ParrySound.jpg',
            'https://ocrvacations.com/wp-content/uploads/2026/02/Kawarthas.jpg',
            'https://ocrvacations.com/wp-content/uploads/2026/02/Haliburton-e1770394531747.jpg',
            'https://ocrvacations.com/wp-content/uploads/2026/06/ctmhcneb9aniojvc0thd-scaled.jpg',
            'https://ocrvacations.com/wp-content/uploads/2026/06/fuhdhrkep71sfjwsajac-scaled.jpg',
            'https://ocrvacations.com/wp-content/uploads/2026/06/zl4juztceneggmlrypqc.jpg',
            'https://ocrvacations.com/wp-content/uploads/2026/06/sislsfudf7crpicqwujr-1-scaled.jpg',
            'https://ocrvacations.com/wp-content/uploads/2026/06/ps5qjz0ehbt2ij13oo8s-scaled.jpg',
            'https://ocrvacations.com/wp-content/uploads/2026/06/d4qgze8t0njfrhqdfifa-scaled.jpg',
            'https://ocrvacations.com/wp-content/uploads/2026/06/agtxwnvpo0k39xtt8fep-scaled.jpg',
            'https://ocrvacations.com/wp-content/uploads/2026/06/zmtcfgewtaxtfmzmdmd1-scaled.jpg',
            'https://ocrvacations.com/wp-content/uploads/2026/06/sislsfudf7crpicqwujr-scaled.jpg',
            'https://ocrvacations.com/wp-content/uploads/2026/06/aeexkmwf5hngluke1xer-scaled.jpg'
        );
        
        ob_start();
        ?>
        <style>
            .guesty-locations-container {
                max-width: 1536px; /* Widened from 1200px for a more expansive grid */
                margin: 0 auto;
                padding: 40px 20px;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            }
            
            .guesty-bento-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 20px;
                grid-auto-rows: 280px;
            }

            @media (min-width: 768px) {
                .guesty-bento-grid {
                    grid-template-columns: repeat(3, 1fr);
                }
                
                /* * The Infinite Bento Box Matrix */
                .guesty-bento-card:nth-child(7n + 1),
                .guesty-bento-card:nth-child(7n + 7) {
                    grid-column: span 2;
                }
            }

            .guesty-bento-card {
                position: relative;
                border-radius: 20px;
                overflow: hidden;
                display: flex;
                align-items: flex-end;
                text-decoration: none;
                
                /* The Shared Background Canvas */
                background-image: url('<?php echo esc_url($base_bg_image); ?>');
                background-size: cover;
                background-position: center;
                background-attachment: fixed; 
                
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                transition: transform 0.4s ease, box-shadow 0.4s ease;
            }

            /* The Hover Image Pseudo-element for smooth fading */
            .guesty-bento-card::before {
                content: '';
                position: absolute;
                inset: 0;
                /* Pulling the dynamic CSS variable passed from the HTML element */
                background-image: var(--hover-img);
                background-size: cover;
                background-position: center;
                background-attachment: fixed;
                opacity: 0; /* Hidden by default */
                transition: opacity 0.4s ease;
                z-index: 0;
            }

            .guesty-bento-card:hover::before {
                opacity: 1; /* Fades in on hover */
            }

            /* iOS Safari fallback as it handles fixed backgrounds poorly */
            @supports (-webkit-touch-callout: none) {
                .guesty-bento-card, .guesty-bento-card::before {
                    background-attachment: scroll;
                }
            }

            .guesty-bento-card:hover { 
                transform: translateY(-5px); 
                box-shadow: 0 12px 25px rgba(0,0,0,0.2);
            }

            /* Deep Water Overlay layered ON TOP of the shared image */
            .guesty-bento-overlay {
                position: absolute;
                inset: 0;
                background: linear-gradient(135deg, rgba(0, 31, 63, 0.75) 0%, rgba(0, 91, 159, 0.25) 100%);
                z-index: 1;
                transition: background 0.4s ease;
            }

            .guesty-bento-card:hover .guesty-bento-overlay {
                background: linear-gradient(135deg, rgba(0, 31, 63, 0.85) 0%, rgba(0, 91, 159, 0.4) 100%);
            }

            .guesty-bento-content {
                position: relative;
                z-index: 2;
                padding: 30px;
                width: 100%;
            }

            /* Forcing titles to be pure white */
            .guesty-bento-content h3 { 
                font-size: 2.2rem; 
                margin: 0; 
                font-family: Georgia, "Times New Roman", serif; 
                font-weight: 700;
                color: #ffffff !important;
                text-shadow: 0 2px 4px rgba(0,0,0,0.6);
            }
        </style>

        <div class="guesty-locations-container">
            <?php /* Header Removed as requested */ ?>
            <div class="guesty-bento-grid">
                <?php foreach ( $locations as $location ) : 
                    // Converts "Burk's Falls" to "burks-falls" for clean linking
                    $slug = sanitize_title( $location );
                ?>
                    <a href="/<?php echo esc_attr( $slug ); ?>/" class="guesty-bento-card">
                        <div class="guesty-bento-overlay"></div>
                        <div class="guesty-bento-content">
                            <h3><?php echo esc_html( $location ); ?></h3>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Pass the PHP array into JavaScript
                const hoverImages = <?php echo wp_json_encode( $hover_images ); ?>;
                let currentHoverIndex = 0;
                const bentoCards = document.querySelectorAll('.guesty-bento-card');

                // Preload all hover images so they fade in instantly without blinking
                hoverImages.forEach(function(src) {
                    const img = new Image();
                    img.src = src;
                });

                // Cycle through the images globally on every mouseenter event
                bentoCards.forEach(function(card) {
                    card.addEventListener('mouseenter', function() {
                        // Set the dynamic CSS variable to the next image in the cycle
                        this.style.setProperty('--hover-img', `url('${hoverImages[currentHoverIndex]}')`);
                        
                        // Increment the tracker, and reset to 0 if we reach the end of the array
                        currentHoverIndex = (currentHoverIndex + 1) % hoverImages.length;
                    });
                });
            });
        </script>
        <?php
        return ob_get_clean();
    }
}

new Guesty_Locations_Grid_Shortcode();
