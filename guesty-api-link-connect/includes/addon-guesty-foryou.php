<?php
if (!defined('ABSPATH')) exit;

/**
 * Registers the [guesty_for_you] shortcode 
 */
class Guesty_ALC_ForYou {
    private $api;

    public function __construct($api) {
        $this->api = $api;
        add_shortcode('guesty_for_you', [$this, 'render_foryou_shortcode']);
        add_action('admin_init', [$this, 'register_foryou_settings']);
    }

    public function register_foryou_settings() {
        register_setting('guesty-settings-group', 'guesty_fy_title');
        register_setting('guesty-settings-group', 'guesty_fy_icon');
        register_setting('guesty-settings-group', 'guesty_fy_icon_color');
        register_setting('guesty-settings-group', 'guesty_fy_badge_text');
        register_setting('guesty-settings-group', 'guesty_fy_badge_bg');
        register_setting('guesty-settings-group', 'guesty_fy_badge_color');
    }

    public function render_foryou_shortcode($atts) {
        $fy_title_default = get_option('guesty_fy_title', 'Recommended For You');
        $atts = shortcode_atts([
            'title' => $fy_title_default
        ], $atts);

        // Fetch Global Properties from the highly-optimized transient cache
        $cached_listings = get_transient('guesty_listings_data');
        if (!$cached_listings) $cached_listings = [];

        // Build data structure exactly like the main grid so JS can process it easily
        $all_listings_for_js = [];
        $hidden_units = get_option('guesty_hidden_units', []);
        
        foreach ($cached_listings as $listing) {
            if (in_array($listing['id'], $hidden_units)) continue;
            
            $all_listings_for_js[] = [
                'id' => $listing['id'],
                'slug' => $listing['slug'],
                'title' => $listing['title'],
                'city' => $listing['city'],
                'country' => $listing['country'],
                'image' => $listing['image'],
                'price' => (float)$listing['price'],
                'currency' => $listing['currency'],
                'accommodates' => (int)$listing['accommodates'],
                'bedrooms' => (int)$listing['bedrooms'],
                'bathrooms' => (int)$listing['bathrooms'],
                'rating' => (float)$listing['rating'],
                'reviews' => (int)$listing['reviews'],
                'hide_reviews' => $listing['hide_reviews'],
                'allows_pets' => $listing['allows_pets']
            ];
        }

        // Pass variables to the template
        $local_listings_json = wp_json_encode($all_listings_for_js);
        $unique_wrapper_id = 'gvs-foryou-' . uniqid();
        $title = $atts['title'];
        
        // Fetch custom For You widget styles
        $fy_icon = get_option('guesty_fy_icon', 'ph-star');
        $fy_icon_color = get_option('guesty_fy_icon_color', '#f59e0b');
        $fy_badge_text = get_option('guesty_fy_badge_text', 'Recommended');
        $fy_badge_bg = get_option('guesty_fy_badge_bg', '#f59e0b');
        $fy_badge_color = get_option('guesty_fy_badge_color', '#ffffff');
        
        // Grab style options from database
        $btn_color = get_option('guesty_btn_color', '#0062ff');
        $btn_hover_color = get_option('guesty_btn_hover_color', '#0052cc');
        $btn_text = get_option('guesty_btn_text', 'View Cottage');
        $base_url = get_option('guesty_base_url', '');
        $fallback_img = get_option('guesty_fallback_image', '');
        $show_reviews_global = get_option('guesty_show_reviews', 'yes');
        $custom_badges = get_option('guesty_custom_badges', []);
        
        $show_pet_badge = get_option('guesty_show_pet_badge', 'yes') === 'yes';
        $pet_badge_icon = get_option('guesty_pet_badge_icon', 'ph-paw-print');
        
        $badge_padding = get_option('guesty_badge_padding', '0');
        $badge_font_size = get_option('guesty_badge_font_size', '14');
        $badge_transform = get_option('guesty_badge_text_transform', 'uppercase');
        $badge_weight = get_option('guesty_badge_font_weight', '400');
        
        $price_label = get_option('guesty_price_label', 'base price');
        $currency_display = get_option('guesty_currency_display', 'auto');
        
        $row_d = (int) get_option('guesty_units_per_row_desktop', '4');
        $row_t = (int) get_option('guesty_units_per_row_tablet', '2');
        $row_m = (int) get_option('guesty_units_per_row_mobile', '1');

        $template_path = GUESTY_ALC_PATH . 'frontend/shortcode-foryou.php';

        ob_start();
        include $template_path;
        return ob_get_clean();
    }
}