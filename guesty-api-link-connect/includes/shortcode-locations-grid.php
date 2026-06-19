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
        $cached_locations = get_transient( 'guesty_unique_locations' );
        $final_locations = array();

        if ( is_array( $cached_locations ) && ! empty( $cached_locations ) ) {
            foreach ( $cached_locations as $loc ) {
                $loc = str_ireplace( array(', Canada', ', USA'), '', $loc );
                $loc = trim( $loc );
                $ignore = array( 'yes', 'no', 'true', 'false', 'address', 'location' );
                if ( in_array( strtolower( $loc ), $ignore ) || empty( $loc ) ) continue;
                $final_locations[] = ucwords( strtolower( $loc ) );
            }
        }
        $final_locations = array_unique( $final_locations );
        sort( $final_locations );
        return $final_locations;
    }

    public function render_shortcode( $atts ) {
        $locations = $this->get_active_locations();
        $base_bg_image = 'https://ocrvacations.com/wp-content/uploads/2026/02/Muskoka.jpg';
        
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
            .guesty-locations-container { max-width: 1536px; margin: 0 auto; padding: 40px 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .guesty-bento-grid { display: grid; grid-template-columns: 1fr; gap: 20px; grid-auto-rows: 280px; }
            @media (min-width: 768px) {
                .guesty-bento-grid { grid-template-columns: repeat(3, 1fr); }
                .guesty-bento-card:nth-child(7n + 1), .guesty-bento-card:nth-child(7n + 7) { grid-column: span 2; }
            }
            .guesty-bento-card {
                position: relative; border-radius: 20px; overflow: hidden; display: flex; align-items: flex-end; text-decoration: none;
                background-image: url('<?php echo esc_url($base_bg_image); ?>'); background-size: cover; background-position: center; background-attachment: fixed;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
                /* FIXED: Removed transform to prevent Chrome/Safari repainting bugs, unified to 0.5s */
                transition: box-shadow 0.5s ease; 
            }
            .guesty-bento-card::before {
                content: ''; position: absolute; inset: 0; background-image: var(--hover-img); background-size: cover; background-position: center;
                /* FIXED: Removed background-attachment: fixed here so the hover images scale sharply to the card limits */
                opacity: 0; transition: opacity 0.5s ease; z-index: 0;
            }
            .guesty-bento-card.is-hovered::before { opacity: 1; }
            @supports (-webkit-touch-callout: none) { .guesty-bento-card { background-attachment: scroll; } }
            
            /* FIXED: Replaced transform with an enhanced shadow glow to maintain interactivity */
            .guesty-bento-card:hover { box-shadow: 0 12px 30px rgba(0,0,0,0.3); } 
            
            /* FIXED: Removed heavy blue overlay to make images bright and vibrant, only keeping a soft bottom shadow for text legibility */
            .guesty-bento-overlay { position: absolute; inset: 0; background: linear-gradient(to top, rgba(0, 0, 0, 0.7) 0%, transparent 40%); z-index: 1; pointer-events: none; }
            
            .guesty-bento-content { position: relative; z-index: 2; padding: 30px; width: 100%; }
            .guesty-bento-content h3 { font-size: 2.2rem; margin: 0; font-family: Georgia, serif; font-weight: 700; color: #ffffff !important; text-shadow: 0 2px 4px rgba(0,0,0,0.6); }
        </style>

        <div class="guesty-locations-container">
            <div class="guesty-bento-grid">
                <?php foreach ( $locations as $location ) : 
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
                const hoverImages = <?php echo wp_json_encode( $hover_images ); ?>;
                let lastRandomIndex = -1;
                const bentoCards = document.querySelectorAll('.guesty-bento-card');

                // Preload all hover images so they fade in instantly
                hoverImages.forEach(function(src) {
                    const img = new Image();
                    img.src = src;
                });

                // Helper function to pick a random image that is different from the last one
                function getRandomImage() {
                    let randomIndex;
                    do {
                        randomIndex = Math.floor(Math.random() * hoverImages.length);
                    } while (randomIndex === lastRandomIndex && hoverImages.length > 1);
                    lastRandomIndex = randomIndex;
                    return hoverImages[randomIndex];
                }

                bentoCards.forEach(function(card) {
                    // 1. Initial pre-stage: give every card a random image immediately on page load
                    card.style.setProperty('--hover-img', `url('${getRandomImage()}')`);
                    
                    let leaveTimeout; // Dedicated timeout tracking for each specific card
                    
                    card.addEventListener('mouseenter', function() {
                        // Stop the swap if they quickly re-hover while it is still fading out
                        clearTimeout(leaveTimeout); 
                        this.classList.add('is-hovered');
                    });

                    card.addEventListener('mouseleave', function() {
                        this.classList.remove('is-hovered');
                        
                        // Wait EXACTLY 500ms for CSS transition to reach opacity 0, THEN swap the image invisibly
                        leaveTimeout = setTimeout(() => {
                            this.style.setProperty('--hover-img', `url('${getRandomImage()}')`);
                        }, 500);
                    });
                });
            });
        </script>
        <?php
        return ob_get_clean();
    }
}

new Guesty_Locations_Grid_Shortcode();
