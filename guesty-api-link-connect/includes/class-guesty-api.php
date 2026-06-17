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

    private function parse_calendar_item($item, $start_date, $end_date, $fallback_lid = null) {
        $lid = isset($item['listingId']) ? $item['listingId'] : (isset($item['_id']) ? $item['_id'] : $fallback_lid);
        if (!$lid) return false;

        $is_available = true; 
        $total_price = 0;
        $block_reasons = []; 
        $stay_length = max(1, round((strtotime($end_date) - strtotime($start_date)) / 86400));
        $valid_days_count = 0;
        
        if (isset($item['days']) && is_array($item['days'])) {
            if (count($item['days']) === 0) {
                $is_available = false;
                $block_reasons[] = "Empty calendar array returned by Guesty.";
            }

            foreach ($item['days'] as $day) {
                $day_date = isset($day['date']) ? $day['date'] : '';
                
                // Prevent evaluating the exact checkout date's primary status
                if (!empty($day_date) && strpos($day_date, $end_date) !== false) {
                    // But we MUST check Closed to Departure on checkout day
                    if (isset($day['ctd']) && $day['ctd'] == true) {
                        $is_available = false;
                        $block_reasons[] = "{$day_date} (Closed to Departure)";
                    }
                    continue; 
                }

                $valid_days_count++;
                $day_is_available = false;
                
                if (isset($day['allotment']) && is_numeric($day['allotment'])) {
                    $day_is_available = ((int)$day['allotment'] > 0);
                    if (!$day_is_available) $block_reasons[] = "{$day_date} (allotment: {$day['allotment']})";
                } elseif (isset($day['status'])) {
                    $day_is_available = (strtolower($day['status']) === 'available');
                    if (!$day_is_available) $block_reasons[] = "{$day_date} (status: {$day['status']})";
                } else {
                    $block_reasons[] = "{$day_date} (missing status/allotment fields)";
                }
                
                // Strict Checking for Minimum and Maximum Nights
                if (isset($day['minNights']) && $stay_length < (int)$day['minNights']) {
                    $day_is_available = false;
                    $block_reasons[] = "{$day_date} (violates minNights of {$day['minNights']})";
                }
                if (isset($day['maxNights']) && $stay_length > (int)$day['maxNights']) {
                    $day_is_available = false;
                    $block_reasons[] = "{$day_date} (violates maxNights of {$day['maxNights']})";
                }

                // Check Closed to Arrival on the check-in day
                if (!empty($day_date) && strpos($day_date, $start_date) !== false && isset($day['cta']) && $day['cta'] == true) {
                    $day_is_available = false;
                    $block_reasons[] = "{$day_date} (Closed to Arrival)";
                }

                if (!$day_is_available) {
                    $is_available = false;
                }

                if (isset($day['price'])) {
                    $total_price += (float)$day['price'];
                }
            }

            // Ensure Guesty didn't glitch and return incomplete data (e.g. 3 days for a 7-day search)
            if ($is_available && $valid_days_count < $stay_length) {
                $is_available = false;
                $block_reasons[] = "Incomplete calendar data. Expected {$stay_length} days, got {$valid_days_count}.";
            }

        } elseif (isset($item['status'])) {
             $is_available = strtolower($item['status']) === 'available';
             if (!$is_available) $block_reasons[] = "Unit overall status: {$item['status']}";
             $total_price = isset($item['price']) ? (float)$item['price'] : 0;
        } else {
            $is_available = false;
            $block_reasons[] = "No calendar data returned for unit by Guesty.";
        }
        
        if (!$is_available) {
            $this->log("Unit {$lid} marked UNAVAILABLE. Reasons: " . implode(', ', array_unique($block_reasons)), 'INFO');
        }

        return [
            'listing_id' => $lid,
            'data' => [ 'is_available' => $is_available, 'total_price' => $total_price ]
        ];
    }

    private function fetch_rolling_concurrent_availability($listing_ids, $start_date, $end_date, $token, $base) {
        if (!function_exists('curl_multi_init')) {
            $this->log("curl_multi_init is disabled on this server. This may cause slowness.", 'WARNING');
            // Graceful fallback for ancient servers
            $results = [];
            foreach ($listing_ids as $id) {
                $url = $base . '/availability-pricing/api/calendar/listings?listingIds=' . urlencode($id) . '&startDate=' . urlencode($start_date) . '&endDate=' . urlencode($end_date);
                $res = wp_remote_get($url, [ 'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json' ], 'timeout' => 15 ]);
                if (!is_wp_error($res) && wp_remote_retrieve_response_code($res) == 200) {
                    $parsed = $this->parse_calendar_item(json_decode(wp_remote_retrieve_body($res), true), $start_date, $end_date, $id);
                    $results[$id] = $parsed ? $parsed['data'] : ['is_available' => false, 'total_price' => 0];
                } else {
                    $results[$id] = ['is_available' => false, 'total_price' => 0];
                }
            }
            return ['success' => true, 'data' => $results];
        }

        $this->log("Fetching " . count($listing_ids) . " units via high-speed Rolling Window Queue (Max 6 concurrent)...", 'INFO');

        $mh = curl_multi_init();
        if (function_exists('curl_multi_setopt') && defined('CURLMOPT_PIPELINING')) {
            curl_multi_setopt($mh, CURLMOPT_PIPELINING, 2); 
        }

        $results = [];
        $queue = $listing_ids;
        $handles = []; 
        $retries = array_fill_keys($listing_ids, 0);
        $max_retries = 2;
        $max_concurrent = 6; 

        $add_request = function($id) use ($mh, &$handles, $base, $start_date, $end_date, $token) {
            $url = $base . '/availability-pricing/api/calendar/listings?listingIds=' . urlencode($id) . '&startDate=' . urlencode($start_date) . '&endDate=' . urlencode($end_date);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'Authorization: Bearer ' . $token, 'Accept: application/json' ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
            curl_setopt($ch, CURLOPT_ENCODING, ''); 
            curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
            if (defined('CURL_HTTP_VERSION_2_0')) {
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
            }
            curl_multi_add_handle($mh, $ch);
            $handles[(int)$ch] = $id;
        };

        while (!empty($queue) && count($handles) < $max_concurrent) {
            $add_request(array_shift($queue));
        }

        $active = null;
        do {
            while (($mrc = curl_multi_exec($mh, $active)) == CURLM_CALL_MULTI_PERFORM);

            if ($mrc != CURLM_OK) break;

            while ($info = curl_multi_info_read($mh)) {
                $ch = $info['handle'];
                $id = $handles[(int)$ch];
                
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $body_raw = curl_multi_getcontent($ch);
                
                if ($code == 200) {
                    $body = json_decode($body_raw, true);
                    $items = isset($body['data']) ? $body['data'] : (isset($body['results']) ? $body['results'] : $body);
                    
                    if (isset($items['days']) && is_array($items['days'])) {
                        $items = [ $id => $items ];
                    }
                    
                    $found_data = false;
                    if (is_array($items)) {
                        foreach ($items as $key => $item) {
                            $fallback = (is_string($key) && strlen($key) >= 20) ? $key : $id;
                            $parsed = $this->parse_calendar_item($item, $start_date, $end_date, $fallback);
                            if ($parsed) {
                                $results[$parsed['listing_id']] = $parsed['data'];
                                $found_data = true;
                            }
                        }
                    }
                    if (!$found_data) {
                        $results[$id] = ['is_available' => false, 'total_price' => 0];
                    }

                } elseif (($code == 429 || $code == 403 || $code >= 500) && $retries[$id] < $max_retries) {
                    $this->log("Unit {$id} hit HTTP {$code} (Rate Limit). Re-queuing to back of the line...", 'WARNING');
                    $retries[$id]++;
                    array_push($queue, $id); 
                    usleep(250000); 
                } else {
                    if ($code != 200) {
                        $this->log("Unit {$id} failed permanently (HTTP {$code}).", 'ERROR');
                    }
                    $results[$id] = ['is_available' => false, 'total_price' => 0];
                }

                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                unset($handles[(int)$ch]);

                while (!empty($queue) && count($handles) < $max_concurrent) {
                    $add_request(array_shift($queue));
                }

                while (($mrc = curl_multi_exec($mh, $active)) == CURLM_CALL_MULTI_PERFORM);
            }

            if ($active) {
                curl_multi_select($mh, 0.1);
            }

        } while ($active || !empty($queue) || count($handles) > 0);

        curl_multi_close($mh);

        return ['success' => true, 'data' => $results];
    }

    private function fetch_availability_chunk($listing_ids, $start_date, $end_date, $token, $base) {
        $joined_ids = implode(',', $listing_ids);
        $url = $base . '/availability-pricing/api/calendar/listings?listingIds=' . $joined_ids . '&startDate=' . urlencode($start_date) . '&endDate=' . urlencode($end_date);
        
        $this->log("Fetching batch availability for " . count($listing_ids) . " listings in a single request. URL: {$url}", 'INFO');

        $response = wp_remote_get($url, [
            'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json' ],
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            $this->log("cURL Error in fetch_availability_chunk: " . $response->get_error_message(), 'ERROR');
            return ['success' => false, 'data' => []];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);

        if ($code == 200) {
            $body = json_decode($body_raw, true);
            $results = [];
            
            // BREAKTHROUGH FIX: Guesty's batch endpoint returns a flattened array of days for ALL properties.
            // We must group them by listingId before processing so we don't accidentally drop units.
            if (isset($body['data']['days']) && is_array($body['data']['days'])) {
                $this->log("Successfully received a flattened batch array of days. Sorting and grouping them by property...", 'INFO');
                
                $grouped_listings = [];
                // Pre-fill the array with all requested IDs so any completely missing units are caught
                foreach ($listing_ids as $id) {
                    $grouped_listings[$id] = ['days' => []];
                }
                
                // Group each individual day back into its parent property array
                foreach ($body['data']['days'] as $day) {
                    $lid = isset($day['listingId']) ? $day['listingId'] : (isset($day['_id']) ? $day['_id'] : null);
                    if ($lid) {
                        if (!isset($grouped_listings[$lid])) {
                            $grouped_listings[$lid] = ['days' => []];
                        }
                        $grouped_listings[$lid]['days'][] = $day;
                    }
                }
                
                // Now parse each grouped listing array natively
                foreach ($grouped_listings as $lid => $item) {
                    $parsed = $this->parse_calendar_item($item, $start_date, $end_date, $lid);
                    if ($parsed) {
                        $results[$parsed['listing_id']] = $parsed['data'];
                    } else {
                        $results[$lid] = ['is_available' => false, 'total_price' => 0];
                    }
                }
                
                return ['success' => true, 'data' => $results];
            } 
            // Fallback just in case Guesty ever reverts to an array of listing objects
            else {
                $items = isset($body['data']) ? $body['data'] : (isset($body['results']) ? $body['results'] : $body);
                if (is_array($items)) {
                    foreach ($items as $key => $item) {
                        $fallback = (is_string($key) && strlen($key) >= 20) ? $key : null;
                        $parsed = $this->parse_calendar_item($item, $start_date, $end_date, $fallback);
                        if ($parsed) {
                            $results[$parsed['listing_id']] = $parsed['data'];
                        }
                    }
                    return ['success' => true, 'data' => $results];
                }
            }
        }
        
        return ['success' => false, 'data' => [], 'raw' => $body_raw, 'code' => $code, 'url' => $url];
    }

    public function get_live_availability($listing_ids, $start_date, $end_date) {
        $valid_ids = array_filter($listing_ids, function($id) { return !empty($id) && is_string($id); });
        if (empty($valid_ids)) {
             $this->log("Live availability search aborted. No valid listing IDs after filtering.", 'WARNING');
             return ['success' => false, 'data' => []];
        }

        // SPEED OPTIMIZATION: Session cache to bypass rapid re-querying
        sort($valid_ids);
        $cache_key = 'guesty_live_avail_v8_' . md5($start_date . '_' . $end_date . '_' . implode(',', $valid_ids));
        $cached_result = get_transient($cache_key);

        if (false !== $cached_result) {
            $this->log("Returning cached live availability for dates {$start_date} to {$end_date} (Cache expires in 5 mins).", 'INFO');
            return $cached_result;
        }

        $this->log("Initiating live availability search from {$start_date} to {$end_date} for " . count($valid_ids) . " listings.", 'INFO');
        
        $token = $this->get_access_token();
        if (!$token) {
            $this->log("Live availability search aborted. Missing token.", 'ERROR');
            return ['success' => false, 'data' => []];
        }
        
        $base = str_replace('/v1/listings', '/v1', $this->api_url);
        $results = []; $overall_success = false;
        
        // Break up into 50-property chunks (Standard batch endpoint supports up to 50 comfortably)
        $chunks = array_chunk($valid_ids, 50); 
        
        foreach ($chunks as $chunk) {
            // Send exactly 1 API request for the entire chunk of 50 units
            $chunk_res = $this->fetch_availability_chunk($chunk, $start_date, $end_date, $token, $base);
            
            if ($chunk_res['success'] && !empty($chunk_res['data'])) {
                $overall_success = true;
                foreach ($chunk_res['data'] as $k => $v) { $results[$k] = $v; }
                
                // If any units mysteriously drop out of the flat array completely, fetch them via the concurrent backup
                $missing_ids = array_values(array_diff($chunk, array_keys($chunk_res['data'])));
                if (!empty($missing_ids)) {
                    $this->log(count($missing_ids) . " units dropped completely from the batch response. Triggering concurrent backup fetch.", 'WARNING');
                    $retry_res = $this->fetch_rolling_concurrent_availability($missing_ids, $start_date, $end_date, $token, $base);
                    if ($retry_res['success'] && !empty($retry_res['data'])) {
                        foreach ($retry_res['data'] as $k => $v) { $results[$k] = $v; }
                    }
                }
            } else {
                $this->log("Batch failed completely (HTTP 500/400). Falling back to the multi-cURL parallel fetcher.", 'ERROR');
                $retry_res = $this->fetch_rolling_concurrent_availability($chunk, $start_date, $end_date, $token, $base);
                if ($retry_res['success'] && !empty($retry_res['data'])) {
                    $overall_success = true;
                    foreach ($retry_res['data'] as $k => $v) { $results[$k] = $v; }
                }
            }
        }
        
        $final_result = ['success' => $overall_success, 'data' => $results];
        
        if ($overall_success) {
            set_transient($cache_key, $final_result, 5 * MINUTE_IN_SECONDS);
        }

        $this->log("Live availability search complete. Found " . count($results) . " tracked availability records. Overall success: " . ($overall_success ? 'Yes' : 'No'), 'INFO');
        return $final_result;
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

            $pictures = [];
            if (isset($listing['pictures']) && is_array($listing['pictures'])) {
                foreach ($listing['pictures'] as $pic) {
                    if (!empty($pic['original'])) $pictures[] = $pic['original'];
                }
            }
            $description = isset($listing['publicDescription']['summary']) ? $listing['publicDescription']['summary'] : '';

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
