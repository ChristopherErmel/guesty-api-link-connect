<?php
/**
 * Shortcode: Dynamic Locations Grid for Guesty
 * Output a "living" bento-box grid of locations based on active Guesty units.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Guesty_Locations_Grid_Shortcode {

    public function __construct() {
        add_shortcode( 'guesty_locations_grid', array( $this, 'render_shortcode' ) );
    }

    /**
     * Get unique locations from Guesty units.
     * We cache this using transients to ensure the page loads blazingly fast.
     */
    private function get_active_locations() {
        $transient_key = 'guesty_active_locations_list';
        $locations = get_transient( $transient_key );

        if ( false === $locations ) {
            global $wpdb;

            // Common meta keys where Guesty sync plugins store the city/location
            // Adjust 'city' to 'guesty_city' or 'address_city' depending on your specific plugin's schema.
            $meta_key = 'city'; 
            
            // Query to find unique cities attached to published units
            $query = $wpdb->prepare("
                SELECT DISTINCT meta_value 
                FROM $wpdb->postmeta pm
                JOIN $wpdb->posts p ON p.ID = pm.post_id
                WHERE pm.meta_key = %s 
                AND p.post_status = 'publish' 
                AND pm.meta_value != ''
            ", $meta_key);

            $results = $wpdb->get_col( $query );
            
            // Fallback: If DB query fails to find the meta key, default to the image's locations for display
            if ( empty( $results ) ) {
                $locations = ['Muskoka', 'Haliburton', 'Parry Sound', 'Kawarthas', 'Georgian Bay', 'Bruce Peninsula', 'Near North', 'Orillia', 'North Bay'];
            } else {
                $locations = array_map('trim', $results);
            }

            // Cache for 12 hours (it will update automatically when transients expire)
            set_transient( $transient_key, $locations, 12 * HOUR_IN_SECONDS );
        }

        return $locations;
    }

    /**
     * The Smart Dictionary for Images and Blurbs.
     * Maps specific locations to high-quality water/landscape imagery and text.
     */
    private function get_location_data( $location_name ) {
        $dictionary = array(
            'Muskoka' => array(
                'image' => 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?q=80&w=1000&auto=format&fit=crop', // Forest lake
                'blurb' => 'The premier cottage country experience.'
            ),
            'Haliburton' => array(
                'image' => 'https://images.unsplash.com/photo-1478479405421-ce83c92fb3ba?q=80&w=1000&auto=format&fit=crop', // Sunset lake
                'blurb' => "Discover cottage country's best-kept secret."
            ),
            'Parry Sound' => array(
                'image' => 'https://images.unsplash.com/photo-1510843265597-8c3af6af715c?q=80&w=1000&auto=format&fit=crop', // Open water
                'blurb' => 'Gateway to the 30,000 Islands.'
            ),
            'Kawarthas' => array(
                'image' => 'https://images.unsplash.com/photo-1469522851174-8b65e9dc322c?q=80&w=1000&auto=format&fit=crop', // Fall trees and water
                'blurb' => 'Connected lakes and locks.'
            ),
            'Georgian Bay' => array(
                'image' => 'https://images.unsplash.com/photo-1559406048-c309f4fbcfbe?q=80&w=1000&auto=format&fit=crop', // Crystal blue water
                'blurb' => 'Crystal clear open waters.'
            ),
            'Bruce Peninsula' => array(
                'image' => 'https://images.unsplash.com/photo-1505881503375-c917df50f968?q=80&w=1000&auto=format&fit=crop', // Turquoise bay
                'blurb' => 'Dramatic cliffs and turquoise bays.'
            ),
            'Near North' => array(
                'image' => 'https://images.unsplash.com/photo-1456345636545-fb41cb76b890?q=80&w=1000&auto=format&fit=crop', // Rugged lake
                'blurb' => 'Rugged wilderness escapes.'
            ),
            'Orillia' => array(
                'image' => 'https://images.unsplash.com/photo-1495562569060-2eec283d3391?q=80&w=1000&auto=format&fit=crop', // Soft twilight water
                'blurb' => 'The Sunshine City connections.'
            ),
            'North Bay' => array(
                'image' => 'https://images.unsplash.com/photo-1513233830848-18544520e5c9?q=80&w=1000&auto=format&fit=crop', // Trees looking at water
                'blurb' => 'Lake Nipissing horizons.'
            )
        );

        // Fallback images for new locations to ensure they look great (water/nature focused)
        $fallbacks = array(
            'https://images.unsplash.com/photo-1439853949127-fa647821eba0?q=80&w=1000&auto=format&fit=crop',
            'https://images.unsplash.com/photo-1473448912268-2022ce9509d8?q=80&w=1000&auto=format&fit=crop',
            'https://images.unsplash.com/photo-1445308394109-4ec2920981b1?q=80&w=1000&auto=format&fit=crop'
        );

        // If location is known, return specific data
        foreach($dictionary as $key => $data) {
            if ( stripos($location_name, $key) !== false ) {
                return $data;
            }
        }

        // If location is NEW, auto-generate a beautiful fallback
        $random_index = crc32($location_name) % count($fallbacks); // Consistently pick the same fallback for the same city
        return array(
            'image' => $fallbacks[$random_index],
            'blurb' => 'Discover beautiful stays in ' . esc_html($location_name) . '.'
        );
    }

    public function render_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'title'    => 'Discover Your Perfect Backdrop',
            'subtitle' => "From the rocky shores of Georgian Bay to the serene pines of Haliburton. <br>Choose your destination and find the cottage getaway you've been dreaming of."
        ), $atts, 'guesty_locations_grid' );

        $locations = $this->get_active_locations();
        
        ob_start();
        ?>
        <style>
            .guesty-locations-container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 40px 20px;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            }
            .guesty-locations-header {
                text-align: center;
                margin-bottom: 40px;
            }
            .guesty-locations-header h2 {
                color: #001f3f; /* Navy blue */
                font-size: 2.5rem;
                font-weight: 700;
                margin-bottom: 16px;
                font-family: Georgia, serif;
            }
            .guesty-locations-header p {
                color: #4a5568;
                font-size: 1.1rem;
                line-height: 1.6;
                max-width: 600px;
                margin: 0 auto;
            }
            .guesty-bento-grid {
                display: grid;
                grid-template-columns: repeat(12, 1fr);
                gap: 16px;
                grid-auto-rows: 240px;
            }
            .guesty-bento-card {
                position: relative;
                border-radius: 16px;
                overflow: hidden;
                text-decoration: none;
                display: block;
                group: group;
            }
            
            /* Responsive Bento Grid Sizing based on the image provided */
            .guesty-bento-card:nth-child(9n+1) { grid-column: span 7; }
            .guesty-bento-card:nth-child(9n+2) { grid-column: span 5; }
            .guesty-bento-card:nth-child(9n+3) { grid-column: span 4; }
            .guesty-bento-card:nth-child(9n+4) { grid-column: span 4; }
            .guesty-bento-card:nth-child(9n+5) { grid-column: span 4; }
            .guesty-bento-card:nth-child(9n+6) { grid-column: span 4; }
            .guesty-bento-card:nth-child(9n+7) { grid-column: span 8; }
            .guesty-bento-card:nth-child(9n+8) { grid-column: span 6; }
            .guesty-bento-card:nth-child(9n+9) { grid-column: span 6; }

            .guesty-bento-bg {
                position: absolute;
                top: 0; left: 0; right: 0; bottom: 0;
                background-size: cover;
                background-position: center;
                transition: transform 0.6s ease;
            }
            .guesty-bento-overlay {
                position: absolute;
                top: 0; left: 0; right: 0; bottom: 0;
                background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.2) 50%, rgba(0,0,0,0) 100%);
            }
            .guesty-bento-content {
                position: absolute;
                bottom: 0;
                left: 0;
                padding: 24px;
                width: 100%;
                box-sizing: border-box;
                z-index: 2;
            }
            .guesty-bento-content h3 {
                color: #ffffff;
                font-size: 1.8rem;
                margin: 0 0 8px 0;
                font-family: Georgia, serif;
                font-weight: bold;
                text-shadow: 1px 1px 3px rgba(0,0,0,0.3);
            }
            .guesty-bento-content p {
                color: rgba(255, 255, 255, 0.95);
                font-size: 0.9rem;
                margin: 0;
                font-weight: 500;
            }
            .guesty-bento-card:hover .guesty-bento-bg {
                transform: scale(1.05);
            }

            /* Mobile Responsiveness */
            @media (max-width: 900px) {
                .guesty-bento-card { grid-column: span 6 !important; }
            }
            @media (max-width: 600px) {
                .guesty-bento-card { grid-column: span 12 !important; }
            }
        </style>

        <div class="guesty-locations-container">
            <div class="guesty-locations-header">
                <h2><?php echo esc_html( $atts['title'] ); ?></h2>
                <p><?php echo wp_kses_post( $atts['subtitle'] ); ?></p>
            </div>

            <div class="guesty-bento-grid">
                <?php foreach ( $locations as $location ) : 
                    $data = $this->get_location_data( $location );
                    // Optional: You can wrap the card in an <a> tag pointing to your search/results page
                    // e.g., <a href="/search?location=<?php echo urlencode($location); " ... >
                ?>
                    <div class="guesty-bento-card">
                        <div class="guesty-bento-bg" style="background-image: url('<?php echo esc_url($data['image']); ?>');"></div>
                        <div class="guesty-bento-overlay"></div>
                        <div class="guesty-bento-content">
                            <h3><?php echo esc_html( $location ); ?></h3>
                            <p><?php echo esc_html( $data['blurb'] ); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize the shortcode
new Guesty_Locations_Grid_Shortcode();
