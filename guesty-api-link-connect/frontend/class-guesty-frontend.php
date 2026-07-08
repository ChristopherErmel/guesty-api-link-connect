<?php
if (!defined('ABSPATH')) exit;

class Guesty_ALC_Frontend {
    public $api;

    public function __construct($api) {
        $this->api = $api;

        add_shortcode('guesty_perfect_stay', [$this, 'render_shortcode']);
        add_action('wp_ajax_guesty_load_properties', [$this, 'ajax_load_properties']);
        add_action('wp_ajax_nopriv_guesty_load_properties', [$this, 'ajax_load_properties']);

add_action('wp_ajax_guesty_proxy_custom_fields', [$this, 'ajax_proxy_custom_fields']);
        add_action('wp_ajax_nopriv_guesty_proxy_custom_fields', [$this, 'ajax_proxy_custom_fields']);
        
        // Safely register frontend assets in WordPress (but don't load them yet)
        add_action('wp_enqueue_scripts', [$this, 'register_frontend_assets']);
    }

    public function register_frontend_assets() {
        wp_register_script('guesty-phosphor-icons', 'https://unpkg.com/@phosphor-icons/web', [], null, false);
        
        // Ensure you create the assets folder and files on your server for these!
        wp_enqueue_style('guesty-frontend-css', GUESTY_ALC_URL . 'assets/css/guesty-frontend.css', [], '4.2.0');
        wp_enqueue_script('guesty-frontend-js', GUESTY_ALC_URL . 'assets/js/guesty-frontend.js', [], '4.2.0', true);
        
        wp_localize_script('guesty-frontend-js', 'guestyAlcvars', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('guesty_alc_frontend_nonce')
        ]);
    }

 public function ajax_proxy_custom_fields() {
        // Crucial CORS headers to allow GuestyBooking.com to read this JSON response
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
        header('Content-Type: application/json');

        if (get_option('guesty_enable_custom_fields_proxy', 'no') !== 'yes') {
            http_response_code(403);
            echo wp_json_encode(['error' => 'Custom Fields Proxy is currently disabled in the plugin settings.']);
            exit;
        }

        $property_id = isset($_GET['id']) ? preg_replace('/[^a-zA-Z0-9]/', '', $_GET['id']) : null;

        if (!$property_id) {
            http_response_code(400);
            echo wp_json_encode(['error' => 'Property ID is required.']);
            exit;
        }

        $cache_key = 'guesty_cf_proxy_' . $property_id;
        $cached_fields = get_transient($cache_key);

        if (false !== $cached_fields) {
            // Serve instantly from WordPress memory
            echo wp_json_encode($cached_fields);
            exit;
        }

        // If not cached, fetch fresh data from Guesty
        $result = $this->api->get_listing_custom_fields($property_id);

        if ($result['success']) {
            // Cache the successful result for 24 hours
            set_transient($cache_key, $result, 24 * HOUR_IN_SECONDS);
            echo wp_json_encode($result);
        } else {
            http_response_code(500);
            echo wp_json_encode($result);
        }
        exit;
    }
    
    public function ajax_load_properties() {
        // Security check
        check_ajax_referer('guesty_alc_frontend_nonce', 'nonce');

        $category = sanitize_text_field($_POST['category'] ?? 'All');
        $sort_by = sanitize_text_field($_POST['sort_by'] ?? 'default');
        $offset = (int)($_POST['offset'] ?? 0);
        $limit = (int)($_POST['limit'] ?? 12);
        
        $search_checkin = sanitize_text_field($_POST['search_checkin'] ?? '');
        $search_checkout = sanitize_text_field($_POST['search_checkout'] ?? '');
        
        // Security: Strict Regex validation for dates
        if (!empty($search_checkin) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $search_checkin)) $search_checkin = '';
        if (!empty($search_checkout) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $search_checkout)) $search_checkout = '';

        $search_loc = sanitize_text_field($_POST['search_loc'] ?? '');
        $search_guests = (int)($_POST['search_guests'] ?? 0);
        $search_bedrooms = (int)($_POST['search_bedrooms'] ?? 0);
        $search_pets = sanitize_text_field($_POST['search_pets'] ?? '');
        $search_amenity = sanitize_text_field($_POST['search_amenity'] ?? '');

        $all_listings = get_transient('guesty_listings_data');
        if (!is_array($all_listings)) {
            if (!wp_next_scheduled('guesty_background_cache_refresh')) {
                wp_schedule_single_event(time(), 'guesty_background_cache_refresh');
            }
            wp_send_json(['success' => true, 'data' => ['items' => [], 'total' => 0, 'hasMore' => false]]);
        }

        $hidden_listings = get_option('guesty_hidden_listings', []);
        $custom_order = get_option('guesty_custom_order', []);
        $active_filters = get_option('guesty_active_filters', []);

        if (!is_array($hidden_listings)) $hidden_listings = [];
        if (!is_array($custom_order)) $custom_order = [];
        if (!is_array($active_filters)) $active_filters = [];

        $listings = [];
        
        foreach ($all_listings as $item) {
            if (in_array($item['id'], $hidden_listings)) continue;

            if (!empty($search_loc)) {
                $loc_str = trim($item['city']) . ', ' . trim($item['country']);
                if (strcasecmp($loc_str, $search_loc) !== 0 && strcasecmp(trim($item['city']), $search_loc) !== 0) continue; 
            }
            if ($search_guests > 0 && $item['accommodates'] < $search_guests) continue;
            if ($search_bedrooms > 0 && $item['bedrooms'] < $search_bedrooms) continue; 
            if ($search_pets === '1' && !$item['allows_pets']) continue; 
            
            if (!empty($search_amenity)) {
                $has_am = false;
                if(isset($item['raw_amenities']) && is_array($item['raw_amenities'])) {
                    foreach ($item['raw_amenities'] as $am) {
                        if (strcasecmp($am, $search_amenity) == 0) { $has_am = true; break; }
                    }
                }
                if (!$has_am) continue; 
            }

            $item['display_order'] = isset($custom_order[$item['id']]) && $custom_order[$item['id']] > 0 ? $custom_order[$item['id']] : 999999;

            $categories = ['All'];
            if (isset($item['raw_amenities']) && is_array($item['raw_amenities'])) {
                foreach ($active_filters as $filter) {
                    foreach ($item['raw_amenities'] as $am) {
                        if (strcasecmp($am, $filter) == 0) { $categories[] = $filter; break; }
                    }
                }
            }
            $item['categories'] = $categories;
            if (in_array($category, $categories)) $listings[] = $item;
        }
        
        if (!empty($search_checkin) && !empty($search_checkout)) {
            $filtered_ids = array_column($listings, 'id');
            if (!empty($filtered_ids)) {
                $api_res = $this->api->get_live_availability($filtered_ids, $search_checkin, $search_checkout);
                if ($api_res['success']) {
                    $availability_data = $api_res['data'];
                    $dynamic_pricing_enabled = get_option('guesty_dynamic_pricing', 'no') === 'yes';
                    
                    $available_listings = [];
                    foreach ($listings as $item) {
                        $id = $item['id'];
                        if (isset($availability_data[$id]) && $availability_data[$id]['is_available']) {
                            if ($dynamic_pricing_enabled && $availability_data[$id]['total_price'] > 0) {
                                $item['price'] = $availability_data[$id]['total_price'];
                                $item['is_dynamic_price'] = true;
                            }
                            $available_listings[] = $item;
                        }
                    }
                    $listings = $available_listings;
                } else {
                    $this->api->log("Live Availability Engine Failed gracefully. Showing locally cached listings.", 'WARNING');
                }
            }
        }

        usort($listings, function($a, $b) use ($sort_by) {
            switch ($sort_by) {
                case 'default':
                    if ($a['display_order'] !== $b['display_order']) return $a['display_order'] <=> $b['display_order'];
                    return strcmp($a['id'], $b['id']);
                case 'name-asc': return strcmp($a['title'], $b['title']);
                case 'name-desc': return strcmp($b['title'], $a['title']);
                case 'price-asc': return $a['price'] <=> $b['price'];
                case 'price-desc': return $b['price'] <=> $a['price'];
                case 'beds-desc': return $b['bedrooms'] <=> $a['bedrooms'];
                case 'beds-asc': return $a['bedrooms'] <=> $b['bedrooms'];
                case 'guests-desc': return $b['accommodates'] <=> $a['accommodates'];
                case 'guests-asc': return $a['accommodates'] <=> $b['accommodates'];
                case 'rating-desc': return $b['rating'] <=> $a['rating'];
                default: return 0;
            }
        });

        $total = count($listings);
        $sliced = array_slice($listings, $offset, $limit);

        wp_send_json([
            'success' => true,
            'data' => [ 'items' => $sliced, 'total' => $total, 'hasMore' => ($offset + $limit) < $total ]
        ]);
    }

    public function render_shortcode($atts) {
        // Enqueue the assets ONLY when the shortcode is processed on the page
        wp_enqueue_script('guesty-phosphor-icons');
        wp_enqueue_style('guesty-frontend-css');
        wp_enqueue_script('guesty-frontend-js');

        $atts = shortcode_atts([
            'start_tab' => '',
            'hide_tabs' => 'false',
            'search_bar' => 'false',
            'search_only' => 'false',
            'redirect_url' => '',
            'show_location' => '',
            'show_dates' => '',
            'show_guests' => '',
            'show_bedrooms' => '',
            'show_amenity' => '',
            'show_pets' => ''
        ], $atts, 'guesty_perfect_stay');

        $cached_data = get_transient('guesty_listings_data');
        if (empty($cached_data) && get_option('guesty_is_manually_cleared', 'no') !== 'yes') {
            if (!wp_next_scheduled('guesty_background_cache_refresh')) {
                wp_schedule_single_event(time(), 'guesty_background_cache_refresh');
            }
            return '<div style="padding: 40px; text-align: center; font-family: sans-serif; background: #f9fafb; border-radius: 8px;">
                <h3 style="color: #1f2937; margin-top:0;">Synchronizing Properties</h3>
                <p style="color: #6b7280; margin-bottom:0;">The property database is currently performing its initial sync with Guesty. Please refresh the page in a few minutes.</p>
            </div>';
        }

        if (empty($cached_data)) return '<p>Loading properties or no properties available.</p>';

        // Fetch settings required for view
        $active_filters = get_option('guesty_active_filters', []);
        $show_reviews_global = get_option('guesty_show_reviews', 'yes');
        $base_url = get_option('guesty_base_url', ''); 
        $fallback_img = get_option('guesty_fallback_image');
        if (empty($fallback_img)) $fallback_img = 'https://via.placeholder.com/400x300?text=No+Image';

        $custom_icons = get_option('guesty_custom_icons', []);
        $custom_badges = get_option('guesty_custom_badges', []);
        if (!is_array($custom_badges)) $custom_badges = [];

        $price_label = get_option('guesty_price_label', 'base price');
        $currency_display = get_option('guesty_currency_display', 'auto');
        $all_tab_text = get_option('guesty_all_tab_text', 'All');
        $all_tab_icon = get_option('guesty_all_tab_icon', 'ph-house');
        $main_header_text = get_option('guesty_main_header', 'Find your perfect place to stay');
        $count_label = get_option('guesty_count_text_label', 'cottages');
        
        $btn_color = get_option('guesty_btn_color', '#0062ff');
        $btn_hover_color = get_option('guesty_btn_hover_color', '#0052cc');
        $btn_text = get_option('guesty_btn_text', 'View Cottage');
        $load_more_btn_text = get_option('guesty_load_more_btn_text', 'View More Cottages');
        $load_more_btn_color = get_option('guesty_load_more_btn_color', '#0062ff');
        $tab_default_color = get_option('guesty_tab_default_color', '#6b7280');
        $tab_hover_color = get_option('guesty_tab_hover_color', '#1f2937');
        $tab_active_color = get_option('guesty_tab_active_color', '#2563eb');
        $scroll_btn_bg = get_option('guesty_scroll_btn_bg', '#ffffff');
        $scroll_btn_color = get_option('guesty_scroll_btn_color', '#374151');
        
        $show_pet_badge = get_option('guesty_show_pet_badge', 'yes') === 'yes';
        $pet_badge_icon = get_option('guesty_pet_badge_icon', 'ph-paw-print');

        $search_bg = get_option('guesty_search_bg', '#ffffff');
        $search_text = get_option('guesty_search_text', '#4b5563');
        $search_label = get_option('guesty_search_label', '#1f2937');
        $search_btn_bg = get_option('guesty_search_btn_bg', $btn_color);
        $search_btn_text = get_option('guesty_search_btn_text', '#ffffff');
        
        $badge_padding = get_option('guesty_badge_padding', '0');
        $badge_font_size = get_option('guesty_badge_font_size', '14');
        $badge_transform = get_option('guesty_badge_text_transform', 'uppercase');
        $badge_weight = get_option('guesty_badge_font_weight', '400');

        $row_d = (int) get_option('guesty_units_per_row_desktop', '4');
        $row_t = (int) get_option('guesty_units_per_row_tablet', '2');
        $row_m = (int) get_option('guesty_units_per_row_mobile', '1');
        $rows_load_d = (int) get_option('guesty_rows_per_load_desktop', '3');
        $rows_load_t = (int) get_option('guesty_rows_per_load_tablet', '6');
        $rows_load_m = (int) get_option('guesty_rows_per_load_mobile', '8');
        
        $enabled_sorts = get_option('guesty_enabled_sorts', ['name-asc','name-desc','price-desc','price-asc','beds-desc','beds-asc','guests-desc','guests-asc','rating-desc']);
        $additional_css = get_option('guesty_additional_css', '');

        // Resolve Search Bar Fields & Logic
        $enable_search_bar = in_array(strtolower($atts['search_bar']), ['true', 'yes']);
        $search_only = in_array(strtolower($atts['search_only']), ['true', 'yes']);
        $redirect_url = !empty($atts['redirect_url']) ? $atts['redirect_url'] : get_option('guesty_search_results_url', '/');
        
        $show_location = $atts['show_location'] !== '' ? in_array(strtolower($atts['show_location']), ['yes', 'true']) : get_option('guesty_search_field_location', 'yes') === 'yes';
        $show_dates = $atts['show_dates'] !== '' ? in_array(strtolower($atts['show_dates']), ['yes', 'true']) : get_option('guesty_search_field_dates', 'yes') === 'yes';
        $show_guests = $atts['show_guests'] !== '' ? in_array(strtolower($atts['show_guests']), ['yes', 'true']) : get_option('guesty_search_field_guests', 'yes') === 'yes';
        $show_bedrooms = $atts['show_bedrooms'] !== '' ? in_array(strtolower($atts['show_bedrooms']), ['yes', 'true']) : get_option('guesty_search_field_bedrooms', 'no') === 'yes';
        $show_amenity = $atts['show_amenity'] !== '' ? in_array(strtolower($atts['show_amenity']), ['yes', 'true']) : get_option('guesty_search_field_amenity', 'yes') === 'yes';
        $show_pets = $atts['show_pets'] !== '' ? in_array(strtolower($atts['show_pets']), ['yes', 'true']) : get_option('guesty_search_field_pets', 'no') === 'yes';

        $search_amenities = get_option('guesty_search_active_amenities', []);
        if (!is_array($search_amenities)) $search_amenities = [];
        
        $unique_locations = get_transient('guesty_unique_locations');
        if (!is_array($unique_locations) || empty($unique_locations)) {
            $loc_map = [];
            foreach ($cached_data as $lst) {
                $c = trim($lst['city']); $cy = trim($lst['country']);
                if (!empty($c) && !empty($cy)) { $loc_map["$c, $cy"] = "$c, $cy"; } 
                elseif (!empty($c)) { $loc_map[$c] = $c; }
            }
            ksort($loc_map); $unique_locations = array_values($loc_map);
            set_transient('guesty_unique_locations', $unique_locations, 12 * HOUR_IN_SECONDS);
        }

        if (!is_array($active_filters)) $active_filters = [];
        if (!is_array($enabled_sorts)) $enabled_sorts = [];

        $hidden_listings = get_option('guesty_hidden_listings', []);
        if (!is_array($hidden_listings)) $hidden_listings = [];
        
        $custom_order = get_option('guesty_custom_order', []);
        if (!is_array($custom_order)) $custom_order = [];

        $use_ajax = get_option('guesty_enable_ajax', 'no') === 'yes';
        $local_listings_json = '[]';

        if (!$use_ajax) {
            $listings = array_values(array_filter($cached_data, function($item) use ($hidden_listings) {
                return !in_array($item['id'], $hidden_listings);
            }));

            // Strip unnecessary fields to drastically reduce DOM weight for large websites when AJAX is disabled
            foreach ($listings as &$listing) {
                $listing['display_order'] = isset($custom_order[$listing['id']]) && $custom_order[$listing['id']] > 0 ? $custom_order[$listing['id']] : 999999;
                if (isset($listing['raw_amenities']) && is_array($listing['raw_amenities'])) {
                    $listing['categories'] = ['All'];
                    foreach ($active_filters as $filter) {
                        foreach ($listing['raw_amenities'] as $am) {
                            if (strcasecmp($am, $filter) == 0) { $listing['categories'][] = $filter; break; }
                        }
                    }
                } else if (!isset($listing['categories'])) {
                    $listing['categories'] = ['All'];
                }
            }
            $local_listings_json = json_encode($listings);
        }
        
        $unique_wrapper_id = 'gvs-container-' . uniqid();

        ob_start();
        include GUESTY_ALC_PATH . 'frontend/views/shortcode-grid.php';
        return ob_get_clean();
    }
}
