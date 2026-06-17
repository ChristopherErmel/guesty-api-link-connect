<?php
if (!defined('ABSPATH')) exit;

class Guesty_ALC_API {

    private $token_url = 'https://open-api.guesty.com/oauth2/token';
    private $api_url = 'https://open-api.guesty.com/v1/listings';

    public function __construct() {
        add_filter('cron_schedules', [$this, 'add_custom_cron_schedule']);
        add_action('init', [$this, 'setup_cron_job']);
        add_action('guesty_background_cache_refresh', [$this, 'background_refresh_listings']);
    }

    public function add_custom_cron_schedule($schedules) {
        $duration = $this->get_cache_duration_seconds();
        $refresh_interval = $duration > 600 ? $duration - 300 : max(60, $duration); 
        
        $schedules['guesty_custom_interval'] = [
            'interval' => $refresh_interval,
            'display'  => __('Guesty Background Cache Warming Interval')
        ];
        return $schedules;
    }

    public function setup_cron_job() {
        if (get_option('guesty_is_manually_cleared', 'no') === 'yes') return;
        if (!wp_next_scheduled('guesty_background_cache_refresh')) {
            wp_schedule_event(time() + 60, 'guesty_custom_interval', 'guesty_background_cache_refresh');
        }
    }

    public function reset_cron_job() {
        wp_clear_scheduled_hook('guesty_background_cache_refresh');
        $this->setup_cron_job();
    }

    public function background_refresh_listings() {
        if (get_option('guesty_is_manually_cleared', 'no') === 'yes') {
            wp_clear_scheduled_hook('guesty_background_cache_refresh');
            return;
        }
        $this->log('Cache Warming Cron Job Triggered: Fetching fresh data silently in background.', 'INFO');
        $this->get_listings(true);
    }

    public function clear_data_cache() {
        delete_transient('guesty_listings_data');
        delete_transient('guesty_all_amenities');
        delete_transient('guesty_unique_locations');
    }

    public function clear_token_cache() {
        delete_transient('guesty_access_token');
    }

    public function log($message, $type = 'ERROR') {
        if (false === get_transient('guesty_weekly_log_cleanup')) {
            delete_option('guesty_vrbo_logs');
            set_transient('guesty_weekly_log_cleanup', '1', WEEK_IN_SECONDS);
            $logs = [];
        } else {
            $logs = get_option('guesty_vrbo_logs', []);
            if (!is_array($logs)) $logs = [];
        }
        
        if (count($logs) > 100) array_shift($logs);
        $logs[] = [ 'time' => current_time('mysql'), 'type' => $type, 'message' => is_array($message) || is_object($message) ? print_r($message, true) : $message ];
        update_option('guesty_vrbo_logs', $logs, false); 
    }

    private function get_cache_duration_seconds() {
        $val = (int) get_option('guesty_cache_time_value', 2);
        if ($val <= 0) $val = 1; 
        $unit = get_option('guesty_cache_time_unit', 'hours');
        
        if ($unit === 'minutes') return $val * MINUTE_IN_SECONDS;
        if ($unit === 'days') return $val * DAY_IN_SECONDS;
        return $val * HOUR_IN_SECONDS; 
    }

    private function get_rate_limit_string($response) {
        if (is_wp_error($response)) return '';
        $parts = [];
        foreach(['second', 'minute', 'hour'] as $int) {
            $lim = wp_remote_retrieve_header($response, "x-ratelimit-limit-$int");
            $rem = wp_remote_retrieve_header($response, "x-ratelimit-remaining-$int");
            if($lim !== '') $parts[] = ucfirst(substr($int,0,3)) . ": $rem/$lim";
        }
        return empty($parts) ? "" : "API Quota: [" . implode(' | ', $parts) . "]";
    }

    private function get_access_token() {
        $token = get_transient('guesty_access_token');
        if ($token) return $token;

        $client_id = get_option('guesty_client_id');
        $client_secret = get_option('guesty_client_secret');

        if (!$client_id || !$client_secret) {
            $this->log('Token Request Aborted: Client ID or Client Secret is missing in settings.', 'ERROR');
            return false;
        }

        $this->log('Requesting new Access Token from Guesty...', 'INFO');

        $max_retries = 3; $attempt = 0; $response = null; $code = 0; $body_raw = '';

        while ($attempt < $max_retries) {
            $response = wp_remote_post($this->token_url, [
                'headers' => [ 'Accept' => 'application/json', 'Content-Type' => 'application/x-www-form-urlencoded' ],
                'body' => [ 'grant_type' => 'client_credentials', 'client_id' => $client_id, 'client_secret' => $client_secret ]
            ]);

            if (is_wp_error($response)) {
                $this->log('WordPress cURL Error during Token Request: ' . $response->get_error_message(), 'ERROR');
                return false;
            }

            $code = wp_remote_retrieve_response_code($response);
            $body_raw = wp_remote_retrieve_body($response);
            $rl_string = $this->get_rate_limit_string($response);

            if ($code == 429) {
                $attempt++;
                if ($attempt < $max_retries) {
                    $retry_after = (int) wp_remote_retrieve_header($response, 'retry-after');
                    sleep($retry_after > 0 ? $retry_after : ($attempt * 2));
                    continue;
                }
            }
            break;
        }

        if ($code != 200) {
            $rl_string = isset($rl_string) ? $rl_string : $this->get_rate_limit_string($response);
            $this->log("Guesty API rejected Token Request. HTTP Status: {$code}. {$rl_string}\nRaw Response: {$body_raw}", 'ERROR');
            return false;
        }

        $body = json_decode($body_raw, true);
        if (isset($body['access_token'])) {
            $this->log("Successfully generated new Guesty Access Token. {$rl_string}", 'SUCCESS');
            set_transient('guesty_access_token', $body['access_token'], 23 * HOUR_IN_SECONDS);
            return $body['access_token'];
        }

        return false;
    }

    private function fetch_availability_chunk($listing_ids, $start_date, $end_date, $token, $base) {
        $url = $base . '/availability?listingIds=' . implode(',', $listing_ids) . '&startDate=' . urlencode($start_date) . '&endDate=' . urlencode($end_date);
        
        $response = wp_remote_get($url, [
            'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json' ],
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) return ['success' => false, 'data' => []];
        
        $code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        
        if ($code == 200) {
            $body = json_decode($body_raw, true);
            $results = [];
            $items = isset($body['data']) ? $body['data'] : (isset($body['results']) ? $body['results'] : $body);
            
            if (is_array($items)) {
                foreach ($items as $item) {
                    $lid = isset($item['listingId']) ? $item['listingId'] : (isset($item['_id']) ? $item['_id'] : null);
                    if (!$lid) continue;
                    
                    $is_available = true; $total_price = 0;
                    
                    if (isset($item['days']) && is_array($item['days'])) {
                        foreach ($item['days'] as $day) {
                            if (isset($day['status']) && strtolower($day['status']) !== 'available') $is_available = false;
                            if (isset($day['price'])) $total_price += (float)$day['price'];
                        }
                    } elseif (isset($item['status'])) {
                         $is_available = strtolower($item['status']) === 'available';
                         $total_price = isset($item['price']) ? (float)$item['price'] : 0;
                    }
                    $results[$lid] = [ 'is_available' => $is_available, 'total_price' => $total_price ];
                }
                return ['success' => true, 'data' => $results];
            }
        }
        return ['success' => false, 'data' => [], 'raw' => $body_raw, 'code' => $code, 'url' => $url];
    }

    public function get_live_availability($listing_ids, $start_date, $end_date) {
        $token = $this->get_access_token();
        if (!$token || empty($listing_ids)) return ['success' => false, 'data' => []];
        
        $base = str_replace('/v1/listings', '/v1', $this->api_url);
        $results = []; $overall_success = false;
        
        $valid_ids = array_filter($listing_ids, function($id) { return !empty($id) && is_string($id); });
        if (empty($valid_ids)) return ['success' => false, 'data' => []];

        // Production speed: Safely bump to 50 items since corrupt IDs are handled at sync level
        $chunks = array_chunk($valid_ids, 50); 
        
        foreach ($chunks as $chunk) {
            $chunk_res = $this->fetch_availability_chunk($chunk, $start_date, $end_date, $token, $base);
            if ($chunk_res['success']) {
                $overall_success = true;
                foreach ($chunk_res['data'] as $k => $v) { $results[$k] = $v; }
            } else {
                $this->log("Availability batch failed. Retrying individually to isolate the bad unit.", 'INFO');
                foreach ($chunk as $single_id) {
                    $single_res = $this->fetch_availability_chunk([$single_id], $start_date, $end_date, $token, $base);
                    if ($single_res['success']) {
                        $overall_success = true;
                        foreach ($single_res['data'] as $k => $v) { $results[$k] = $v; }
                    } else {
                        $err_code = isset($single_res['code']) ? $single_res['code'] : 'Unknown';
                        $this->log("Identified broken Guesty Listing ID: {$single_id}. HTTP {$err_code}.", 'WARNING');
                    }
                }
            }
        }
        return ['success' => $overall_success, 'data' => $results];
    }

    public function get_listings($force_refresh = false) {
        if (get_option('guesty_is_manually_cleared', 'no') === 'yes') return [];
        if (!$force_refresh) {
            $cached_listings = get_transient('guesty_listings_data');
            if ($cached_listings) return $cached_listings;
        }

        $token = $this->get_access_token();
        if (!$token) return [];

        $this->log('Requesting property listings from Guesty API (Supports Pagination > 100 units)...', 'INFO');

        $all_raw_listings = []; $limit = 100; $skip = 0; $has_more = true;

        while ($has_more) {
            $max_retries = 3; $attempt = 0; $chunk_success = false;
            while ($attempt < $max_retries) {
                $response = wp_remote_get($this->api_url . "?limit={$limit}&skip={$skip}&active=true&listed=true", [
                    'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json' ], 'timeout' => 30 
                ]);

                if (is_wp_error($response)) {
                    $this->log('WordPress cURL Error during Listings Request: ' . $response->get_error_message(), 'ERROR');
                    break; 
                }

                $code = wp_remote_retrieve_response_code($response);
                $body_raw = wp_remote_retrieve_body($response);

                if ($code == 401) {
                    $this->clear_token_cache();
                    $token = $this->get_access_token();
                    if (!$token) break;
                    $attempt++; continue; 
                }

                if ($code == 429) {
                    $attempt++;
                    if ($attempt < $max_retries) {
                        $retry_after = (int) wp_remote_retrieve_header($response, 'retry-after');
                        sleep($retry_after > 0 ? $retry_after : ($attempt * 2));
                        continue;
                    }
                }
                
                if ($code == 200) {
                    $body = json_decode($body_raw, true);
                    if (isset($body['results']) && is_array($body['results'])) {
                        $all_raw_listings = array_merge($all_raw_listings, $body['results']);
                        $chunk_success = true;
                        if (count($body['results']) < $limit) { $has_more = false; } else { $skip += $limit; }
                    } else {
                        $has_more = false;
                    }
                    break; 
                } else {
                    $this->log("Guesty API rejected Listings Request. HTTP Status: {$code}.", 'ERROR');
                    break;
                }
            }
            if (!$chunk_success) $has_more = false;
        }

        if (!empty($all_raw_listings)) {
            $total = count($all_raw_listings);
            $this->log("Successfully fetched {$total} total raw active/listed listings from Guesty.", 'SUCCESS');

            $all_amenities = [];
            foreach ($all_raw_listings as $listing) {
                if (isset($listing['amenities']) && is_array($listing['amenities'])) {
                    foreach ($listing['amenities'] as $am) $all_amenities[$am] = true;
                }
            }
            $all_amenities = array_keys($all_amenities);
            sort($all_amenities);
            
            $cache_duration = $this->get_cache_duration_seconds();
            set_transient('guesty_all_amenities', $all_amenities, $cache_duration);

            $cleaned_listings = $this->format_listings($all_raw_listings);
            
            // --- SECURITY & SPEED PATCH: VALIDATE IDS AGAINST 400 ERRORS DURING SYNC ---
            $this->log("Testing calendar endpoints to purge corrupted unit IDs...", 'INFO');
            $ids_to_check = array_column($cleaned_listings, 'id');
            $test_start = date('Y-m-d', strtotime('+30 days'));
            $test_end = date('Y-m-d', strtotime('+32 days'));
            
            $validation = $this->get_live_availability($ids_to_check, $test_start, $test_end);
            if ($validation['success'] || count($validation['data']) > 0) {
                $safe_ids = array_keys($validation['data']);
                $filtered_listings = array_filter($cleaned_listings, function($l) use ($safe_ids) { return in_array($l['id'], $safe_ids); });
                $cleaned_listings = array_values($filtered_listings);
            }

            set_transient('guesty_listings_data', $cleaned_listings, $cache_duration);
            
            $unique_locations = [];
            foreach ($cleaned_listings as $lst) {
                $c = trim($lst['city']); $cy = trim($lst['country']);
                if (!empty($c) && !empty($cy)) { $unique_locations["$c, $cy"] = "$c, $cy"; } 
                elseif (!empty($c)) { $unique_locations[$c] = $c; }
            }
            ksort($unique_locations);
            set_transient('guesty_unique_locations', array_values($unique_locations), $cache_duration);
            
            $this->log("Finished caching " . count($cleaned_listings) . " thoroughly validated listings.", 'SUCCESS');
            return $cleaned_listings;
        }
        return [];
    }

    private function format_listings($raw_listings) {
        $hidden_reviews = get_option('guesty_hidden_reviews', []);
        if (!is_array($hidden_reviews)) $hidden_reviews = [];

        $formatted = [];
        foreach ($raw_listings as $listing) {
            if (isset($listing['active']) && $listing['active'] === false) continue;
            if (isset($listing['listed']) && $listing['listed'] === false) continue;

            $raw_amenities = isset($listing['amenities']) && is_array($listing['amenities']) ? $listing['amenities'] : [];
            $type = isset($listing['propertyType']) ? $listing['propertyType'] : '';
            $allows_pets = false;

            foreach ($raw_amenities as $am) {
                if (stripos($am, 'pet') !== false || stripos($am, 'dog') !== false || stripos($am, 'cat') !== false || stripos($am, 'animal') !== false) {
                    $allows_pets = true; break;
                }
            }

            $rating_score = 0; $reviews_count = 0;
            if (isset($listing['reviews'])) {
                if (is_array($listing['reviews'])) {
                    $rating_score = isset($listing['reviews']['score']) ? $listing['reviews']['score'] : 0;
                    $reviews_count = isset($listing['reviews']['count']) ? $listing['reviews']['count'] : 0;
                } else {
                    $reviews_count = $listing['reviews'];
                }
            }
            if (isset($listing['rating']) && empty($rating_score)) $rating_score = $listing['rating'];
            if (isset($listing['reviewScore']) && empty($rating_score)) $rating_score = $listing['reviewScore'];
            if (isset($listing['reviewsCount']) && empty($reviews_count)) $reviews_count = $listing['reviewsCount'];

            // Added caching for images, descriptions, and slugs to support the Unit Pages Add-on
            $pictures = [];
            if (isset($listing['pictures']) && is_array($listing['pictures'])) {
                foreach ($listing['pictures'] as $pic) {
                    if (!empty($pic['original'])) $pictures[] = $pic['original'];
                }
            }
            $description = isset($listing['publicDescription']['summary']) ? $listing['publicDescription']['summary'] : '';

            // Extremely lean array mapping to save DB Memory and prevent slow HTML generation
            $formatted[] = [
                'id' => $listing['_id'] ?? '',
                'slug' => sanitize_title($listing['title'] ?? 'Beautiful Stay'),
                'title' => $listing['title'] ?? 'Beautiful Stay',
                'type' => $type ? ucfirst($type) : 'House',
                'city' => $listing['address']['city'] ?? '',
                'country' => $listing['address']['country'] ?? '',
                'accommodates' => $listing['accommodates'] ?? 2,
                'bedrooms' => $listing['bedrooms'] ?? 1,
                'bathrooms' => $listing['bathrooms'] ?? 1,
                'price' => $listing['prices']['basePrice'] ?? 0, 
                'currency' => $listing['prices']['currency'] ?? 'CAD',
                'image' => isset($listing['pictures'][0]['original']) ? $listing['pictures'][0]['original'] : '',
                'pictures' => $pictures,
                'description' => $description,
                'raw_amenities' => $raw_amenities, 
                'allows_pets' => $allows_pets,
                'rating' => (float)$rating_score, 
                'reviews' => (int)$reviews_count,
                'hide_reviews' => in_array($listing['_id'] ?? '', $hidden_reviews)
            ];
        }
        return $formatted;
    }

    public function get_default_icon_class_for_amenity($amenity) {
        $name = strtolower($amenity);
        $map = [
            'air condition' => 'ph-wind', 'ac' => 'ph-wind', 'climate' => 'ph-wind',
            'bbq' => 'ph-campfire', 'barbeque' => 'ph-campfire', 'grill' => 'ph-campfire',
            'bake' => 'ph-cooking-pot', 'oven' => 'ph-oven', 'stove' => 'ph-oven',
            'bath' => 'ph-bathtub', 'tub' => 'ph-bathtub', 'beach' => 'ph-umbrella',
            'sand' => 'ph-umbrella', 'water' => 'ph-waves', 'lake' => 'ph-waves',
            'ocean' => 'ph-waves', 'river' => 'ph-waves', 'sea' => 'ph-waves',
            'board game' => 'ph-dice-five', 'dice' => 'ph-dice-five', 'game' => 'ph-game-controller',
            'boat' => 'ph-anchor', 'dock' => 'ph-anchor', 'slip' => 'ph-anchor',
            'kayak' => 'ph-anchor', 'canoe' => 'ph-anchor', 'tv' => 'ph-television',
            'television' => 'ph-television', 'cable' => 'ph-television', 'netflix' => 'ph-television',
            'carbon' => 'ph-warning-circle', 'smoke' => 'ph-warning-circle', 'alarm' => 'ph-warning-circle',
            'detector' => 'ph-warning-circle', 'fan' => 'ph-fan', 'coffee' => 'ph-coffee',
            'espresso' => 'ph-coffee', 'keurig' => 'ph-coffee', 'maker' => 'ph-coffee',
            'dish' => 'ph-drop', 'wash' => 'ph-washing-machine', 'dry' => 'ph-washing-machine',
            'laundry' => 'ph-washing-machine', 'essential' => 'ph-tote', 'towel' => 'ph-tote',
            'linen' => 'ph-bed', 'pillow' => 'ph-bed', 'blanket' => 'ph-bed', 'bed' => 'ph-bed',
            'crib' => 'ph-baby', 'cot' => 'ph-bed', 'fire' => 'ph-fire', 'first aid' => 'ph-first-aid',
            'medical' => 'ph-first-aid', 'emergency' => 'ph-first-aid', 'park' => 'ph-car',
            'garage' => 'ph-car', 'driveway' => 'ph-car', 'gym' => 'ph-barbell',
            'fit' => 'ph-barbell', 'workout' => 'ph-barbell', 'exercise' => 'ph-barbell',
            'hair' => 'ph-wind', 'heat' => 'ph-thermometer-hot', 'warm' => 'ph-thermometer-hot',
            'high chair' => 'ph-baby', 'baby' => 'ph-baby', 'child' => 'ph-baby',
            'hot tub' => 'ph-bathtub', 'jacuzzi' => 'ph-bathtub', 'internet' => 'ph-wifi-high',
            'wifi' => 'ph-wifi-high', 'wi-fi' => 'ph-wifi-high', 'web' => 'ph-wifi-high',
            'iron' => 'ph-t-shirt', 'kitchen' => 'ph-cooking-pot', 'cook' => 'ph-cooking-pot',
            'chef' => 'ph-cooking-pot', 'meal' => 'ph-cooking-pot', 'microwave' => 'ph-oven',
            'patio' => 'ph-tree', 'balcony' => 'ph-tree', 'deck' => 'ph-tree',
            'terrace' => 'ph-tree', 'yard' => 'ph-tree', 'garden' => 'ph-plant',
            'outdoor' => 'ph-tree', 'pet' => 'ph-paw-print', 'dog' => 'ph-paw-print',
            'cat' => 'ph-paw-print', 'animal' => 'ph-paw-print', 'pool' => 'ph-swimming-pool',
            'swim' => 'ph-swimming-pool', 'fridge' => 'ph-snowflake', 'refriger' => 'ph-snowflake',
            'freezer' => 'ph-snowflake', 'ice' => 'ph-snowflake', 'shampoo' => 'ph-drop',
            'soap' => 'ph-drop', 'conditioner' => 'ph-drop', 'body wash' => 'ph-drop',
            'ski' => 'ph-mountains', 'snow' => 'ph-mountains', 'work' => 'ph-laptop',
            'desk' => 'ph-desk', 'office' => 'ph-briefcase', 'laptop' => 'ph-laptop',
            'view' => 'ph-binoculars', 'scenic' => 'ph-binoculars', 'safe' => 'ph-lock-key',
            'lock' => 'ph-lock-key', 'security' => 'ph-shield-check', 'music' => 'ph-speaker-hifi',
            'sound' => 'ph-speaker-hifi', 'speaker' => 'ph-speaker-hifi', 'bluetooth' => 'ph-speaker-hifi',
            'book' => 'ph-book-open', 'read' => 'ph-book-open',
        ];

        foreach ($map as $keyword => $icon_class) {
            if (strpos($name, $keyword) !== false) return $icon_class;
        }
        return 'ph-star';
    }
}