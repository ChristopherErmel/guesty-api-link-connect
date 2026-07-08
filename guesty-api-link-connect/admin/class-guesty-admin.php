<?php
if (!defined('ABSPATH')) exit;

class Guesty_ALC_Admin {
    public $api;

    public function __construct($api) {
        $this->api = $api;

        add_action('admin_menu', [$this, 'create_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

        // Wire Option Updates to API Cache Clearing
        add_action('update_option_guesty_cache_time_value', [$this->api, 'reset_cron_job']);
        add_action('update_option_guesty_cache_time_unit', [$this->api, 'reset_cron_job']);
        add_action('update_option_guesty_client_id', [$this->api, 'clear_token_cache']);
        add_action('update_option_guesty_client_secret', [$this->api, 'clear_token_cache']);
        
        $data_clear_triggers = ['guesty_client_id', 'guesty_client_secret'];
        foreach($data_clear_triggers as $trigger) {
            add_action("update_option_$trigger", [$this->api, 'clear_data_cache']);
        }
    }

    public function enqueue_admin_scripts($hook) {
        if (isset($_GET['page']) && $_GET['page'] === 'guesty-api-settings') {
            wp_enqueue_media();
        }
    }

    public function create_admin_menu() {
        add_options_page('Guesty API Settings', 'Guesty Settings', 'manage_options', 'guesty-api-settings', [$this, 'settings_page']);
    }

    public function register_settings() {
        register_setting('guesty-settings-group', 'guesty_client_id');
        register_setting('guesty-settings-group', 'guesty_client_secret');
        register_setting('guesty-settings-group', 'guesty_base_url', 'sanitize_url');
        register_setting('guesty-settings-group', 'guesty_search_results_url', 'sanitize_text_field'); // NEW GLOBAL URL SETTING
        register_setting('guesty-settings-group', 'guesty_enable_custom_fields_proxy');
        register_setting('guesty-settings-group', 'guesty_fallback_image', 'sanitize_url'); 
        register_setting('guesty-settings-group', 'guesty_cache_time_value');
        register_setting('guesty-settings-group', 'guesty_cache_time_unit');
        register_setting('guesty-settings-group', 'guesty_enable_ajax');
        register_setting('guesty-settings-group', 'guesty_dynamic_pricing');
        register_setting('guesty-settings-group', 'guesty_search_active_amenities', ['type' => 'array', 'sanitize_callback' => [$this, 'sanitize_filters']]);
        register_setting('guesty-settings-group', 'guesty_search_field_location');
        register_setting('guesty-settings-group', 'guesty_search_field_dates');
        register_setting('guesty-settings-group', 'guesty_search_field_guests');
        register_setting('guesty-settings-group', 'guesty_search_field_bedrooms');
        register_setting('guesty-settings-group', 'guesty_search_field_amenity');
        register_setting('guesty-settings-group', 'guesty_search_field_pets');
        register_setting('guesty-settings-group', 'guesty_price_label');
        register_setting('guesty-settings-group', 'guesty_currency_display');
        register_setting('guesty-settings-group', 'guesty_show_reviews'); 
        register_setting('guesty-settings-group', 'guesty_all_tab_text');
        register_setting('guesty-settings-group', 'guesty_all_tab_icon');
        register_setting('guesty-settings-group', 'guesty_active_filters', ['type' => 'array', 'sanitize_callback' => [$this, 'sanitize_filters']]);
        register_setting('guesty-settings-group', 'guesty_custom_icons', ['type' => 'array', 'sanitize_callback' => [$this, 'sanitize_custom_icons']]);
        register_setting('guesty-settings-group', 'guesty_main_header');
        register_setting('guesty-settings-group', 'guesty_count_text_label');
        register_setting('guesty-settings-group', 'guesty_btn_color');
        register_setting('guesty-settings-group', 'guesty_btn_hover_color');
        register_setting('guesty-settings-group', 'guesty_btn_text');
        register_setting('guesty-settings-group', 'guesty_load_more_btn_text');
        register_setting('guesty-settings-group', 'guesty_load_more_btn_color');
        register_setting('guesty-settings-group', 'guesty_tab_default_color');
        register_setting('guesty-settings-group', 'guesty_tab_hover_color');
        register_setting('guesty-settings-group', 'guesty_tab_active_color');
        register_setting('guesty-settings-group', 'guesty_scroll_btn_bg');
        register_setting('guesty-settings-group', 'guesty_scroll_btn_color');
        register_setting('guesty-settings-group', 'guesty_show_pet_badge');
        register_setting('guesty-settings-group', 'guesty_pet_badge_icon');
        register_setting('guesty-settings-group', 'guesty_search_bg');
        register_setting('guesty-settings-group', 'guesty_search_text');
        register_setting('guesty-settings-group', 'guesty_search_label');
        register_setting('guesty-settings-group', 'guesty_search_btn_bg');
        register_setting('guesty-settings-group', 'guesty_search_btn_text');
        register_setting('guesty-settings-group', 'guesty_badge_padding');
        register_setting('guesty-settings-group', 'guesty_badge_font_size');
        register_setting('guesty-settings-group', 'guesty_badge_text_transform');
        register_setting('guesty-settings-group', 'guesty_badge_font_weight');
        register_setting('guesty-settings-group', 'guesty_units_per_row_desktop');
        register_setting('guesty-settings-group', 'guesty_units_per_row_tablet');
        register_setting('guesty-settings-group', 'guesty_units_per_row_mobile');
        register_setting('guesty-settings-group', 'guesty_rows_per_load_desktop');
        register_setting('guesty-settings-group', 'guesty_rows_per_load_tablet');
        register_setting('guesty-settings-group', 'guesty_rows_per_load_mobile');
        register_setting('guesty-settings-group', 'guesty_enabled_sorts', ['type' => 'array', 'sanitize_callback' => [$this, 'sanitize_filters']]);
        register_setting('guesty-settings-group', 'guesty_additional_css');
    }

    public function sanitize_filters($input) {
        if (!is_array($input)) return [];
        return array_map('sanitize_text_field', $input);
    }

    public function sanitize_custom_icons($input) {
        if (!is_array($input)) return [];
        $clean = [];
        foreach ($input as $key => $val) {
            $clean[sanitize_text_field($key)] = sanitize_text_field($val);
        }
        return $clean;
    }

    public function settings_page() {
        $active_tab = 'api'; 

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guesty_admin_nonce'])) {
            if (!wp_verify_nonce($_POST['guesty_admin_nonce'], 'guesty_admin_action')) {
                wp_die('Security check failed. Please refresh the page and try again.');
            }
        }
        
        if (isset($_POST['force_guesty_refresh'])) {
            update_option('guesty_is_manually_cleared', 'no'); 
            $this->api->clear_data_cache();
            $this->api->log('Manual Force Refresh Triggered via Dashboard.', 'INFO');
            $this->api->reset_cron_job(); 
            $this->api->get_listings(true);
            $_POST['view_guesty_data'] = 1; 
            echo '<div class="notice notice-success is-dismissible"><p>Data Synchronized Successfully!</p></div>';
            $active_tab = 'data';
        }

        if (isset($_POST['clear_guesty_data'])) {
            update_option('guesty_is_manually_cleared', 'yes'); 
            $this->api->clear_data_cache();
            wp_clear_scheduled_hook('guesty_background_cache_refresh'); 
            $this->api->log('Manual Data Cache Clear Triggered via Dashboard. Cache Warming Paused.', 'INFO');
            echo '<div class="notice notice-success is-dismissible"><p>Guesty Property Data Cache Cleared Successfully! No units will show on the frontend until you click Sync Guesty Data again.</p></div>';
            $active_tab = 'api';
        }

        if (isset($_POST['toggle_guesty_visibility'])) {
            $id = sanitize_text_field($_POST['toggle_guesty_visibility']);
            $hidden = get_option('guesty_hidden_listings', []);
            if (in_array($id, $hidden)) $hidden = array_diff($hidden, [$id]); else $hidden[] = $id;
            update_option('guesty_hidden_listings', array_values($hidden));
            $_POST['view_guesty_data'] = 1; 
            $active_tab = 'data';
        }

        if (isset($_POST['toggle_guesty_reviews'])) {
            $id = sanitize_text_field($_POST['toggle_guesty_reviews']);
            $hidden_revs = get_option('guesty_hidden_reviews', []);
            if (in_array($id, $hidden_revs)) $hidden_revs = array_diff($hidden_revs, [$id]); else $hidden_revs[] = $id;
            update_option('guesty_hidden_reviews', array_values($hidden_revs));
            $_POST['view_guesty_data'] = 1; 
            $active_tab = 'data';
        }

        if (isset($_POST['save_guesty_order'])) {
            if (isset($_POST['guesty_unit_order']) && is_array($_POST['guesty_unit_order'])) {
                $orders = [];
                foreach ($_POST['guesty_unit_order'] as $id => $val) {
                    if (intval($val) > 0) $orders[sanitize_text_field($id)] = intval($val);
                }
                update_option('guesty_custom_order', $orders);
            }

            $badges = [];
            if (isset($_POST['guesty_badge']) && is_array($_POST['guesty_badge'])) {
                foreach ($_POST['guesty_badge'] as $id => $badge_data) {
                    $text = sanitize_text_field($badge_data['text']);
                    if (!empty($text)) {
                        $badges[sanitize_text_field($id)] = [
                            'text' => $text,
                            'color' => sanitize_text_field($badge_data['color']), 
                            'text_color' => isset($badge_data['text_color']) ? sanitize_text_field($badge_data['text_color']) : '#000000',
                            'style' => sanitize_text_field($badge_data['style'])
                        ];
                    }
                }
            }
            update_option('guesty_custom_badges', $badges);

            $show_reviews = isset($_POST['guesty_show_reviews']) ? 'yes' : 'no';
            update_option('guesty_show_reviews', $show_reviews);

            echo '<div class="notice notice-success is-dismissible"><p>Unit management settings (Order, Custom Badges & Reviews) saved successfully!</p></div>';
            $_POST['view_guesty_data'] = 1; 
            $active_tab = 'data';
        }

        if (isset($_POST['view_guesty_data'])) $active_tab = 'data';

        if (isset($_POST['clear_guesty_logs'])) {
            delete_option('guesty_vrbo_logs');
            echo '<div class="notice notice-success is-dismissible"><p>Debug logs cleared successfully!</p></div>';
            $_POST['view_guesty_logs'] = 1; 
            $active_tab = 'logs';
        }
        
        if (isset($_POST['view_guesty_logs'])) $active_tab = 'logs';

        if (isset($_POST['revoke_guesty_token'])) {
            $this->api->clear_token_cache();
            echo '<div class="notice notice-success is-dismissible"><p>Access token successfully deleted. A new one will be requested on the next API call.</p></div>';
            $active_tab = 'token';
        }
        
        if (isset($_POST['view_guesty_token'])) $active_tab = 'token';

        $show_data_panel = in_array($active_tab, ['data', 'logs', 'token']);

        include GUESTY_ALC_PATH . 'admin/views/admin-settings.php';
    }

    public function render_token_info() {
        $token = get_transient('guesty_access_token');
        $timeout = get_option('_transient_timeout_guesty_access_token');
        
        echo '<h3 style="margin-top:0;">Current API Access Token</h3>';
        echo '<p class="description">Tokens are generated securely and cached for 23 hours.</p>';
        
        if (!$token) {
            echo '<div class="notice notice-warning inline"><p>No active token found. It will be generated automatically on the next API call.</p></div>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped" style="margin-top: 15px;"><tbody>';
            echo '<tr><th style="width: 200px;">Status</th><td><span style="color: #16a34a; font-weight: bold;">Active</span></td></tr>';
            
            if ($timeout) {
                $expires_in = max(0, $timeout - time());
                $hours = floor($expires_in / 3600);
                $minutes = floor(($expires_in % 3600) / 60);
                echo '<tr><th>Time Until Expiration</th><td>' . $hours . ' hours, ' . $minutes . ' minutes</td></tr>';
                echo '<tr><th>Exact Expiration Time</th><td>' . wp_date('M j, Y - g:i:s A', $timeout) . '</td></tr>';
            }
            
            $masked_token = strlen($token) > 20 ? substr($token, 0, 10) . '..............................' . substr($token, -10) : '***';
            echo '<tr><th>Token Value (Masked)</th><td><code style="word-break: break-all;">' . esc_html($masked_token) . '</code></td></tr>';
            echo '</tbody></table>';
            
            echo '<form method="post" action="" style="margin-top: 15px;">';
            wp_nonce_field('guesty_admin_action', 'guesty_admin_nonce');
            submit_button('Delete Token (Force New)', 'delete', 'revoke_guesty_token', false);
            echo '</form>';
        }
    }

    public function render_logs_table() {
        $logs = get_option('guesty_vrbo_logs', []);
        $logs = array_reverse($logs);

        echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">';
        echo '<h3 style="margin:0;">System Debug Logs</h3>';
        echo '<form method="post" action="" onsubmit="return confirm(\'Are you sure you want to clear all debug logs?\');" style="margin:0;">';
        wp_nonce_field('guesty_admin_action', 'guesty_admin_nonce');
        echo '<button type="submit" name="clear_guesty_logs" class="button button-link-delete" style="color: #dc3232; border-color: #dc3232;">Clear All Logs</button>';
        echo '</form>';
        echo '</div>';
        
        if (empty($logs)) {
            echo '<div class="notice notice-info inline"><p>No logs recorded yet. Run a sync to trigger an API call.</p></div>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">';
        echo '<thead><tr><th style="width: 180px;">Timestamp</th><th style="width: 100px;">Status</th><th>Message / Raw Response</th></tr></thead><tbody>';

        foreach ($logs as $log) {
            $color = '#4b5563'; 
            if ($log['type'] === 'ERROR') $color = '#dc2626'; 
            if ($log['type'] === 'SUCCESS') $color = '#16a34a'; 
            if ($log['type'] === 'INFO') $color = '#2563eb'; 
            if ($log['type'] === 'WARNING') $color = '#ca8a04'; 

            echo '<tr>';
            echo '<td style="font-size: 12px; color: #646970;">' . esc_html($log['time']) . '</td>';
            echo '<td><span style="display: inline-block; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 11px; background: ' . $color . '15; color: ' . $color . ';">' . esc_html($log['type']) . '</span></td>';
            echo '<td><pre style="white-space: pre-wrap; font-size: 12px; margin: 0; max-height: 200px; overflow-y: auto; background: #f6f7f7; padding: 8px; border: 1px solid #dcdcde;">' . esc_html($log['message']) . '</pre></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    public function render_cached_data_table() {
        $listings = get_transient('guesty_listings_data');
        if (empty($listings)) {
            echo '<div class="notice notice-warning inline"><p>No cached listing data found. Either no properties exist, or the cache is empty. Try Syncing the data.</p></div>';
            return;
        }

        $hidden_listings = get_option('guesty_hidden_listings', []);
        $hidden_reviews = get_option('guesty_hidden_reviews', []);
        $custom_order = get_option('guesty_custom_order', []);
        $custom_badges = get_option('guesty_custom_badges', []);
        $pet_icon = get_option('guesty_pet_badge_icon', 'ph-paw-print');

        if (!is_array($hidden_listings)) $hidden_listings = [];
        if (!is_array($hidden_reviews)) $hidden_reviews = [];
        if (!is_array($custom_order)) $custom_order = [];
        if (!is_array($custom_badges)) $custom_badges = [];

        echo '<h3 style="margin-top:0;">Currently Cached Properties (' . count($listings) . ' total active units)</h3>';
        echo '<p class="description">Use the toggles below to manually hide specific active properties from the website, or hide their individual reviews.</p>';
        
        echo '<form method="post" action="">';
        wp_nonce_field('guesty_admin_action', 'guesty_admin_nonce');
        echo '<input type="hidden" name="view_guesty_data" value="1">'; 

        echo '<div style="background: #f9f9f9; border-left: 4px solid #2271b1; padding: 15px; margin-top: 20px; margin-bottom: 20px; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">';
        echo '<h4 style="margin-top: 0; margin-bottom: 10px; font-size: 14px;">Global Review Settings</h4>';
        $global_rev_checked = get_option('guesty_show_reviews', 'yes') === 'yes' ? 'checked' : '';
        echo '<label><input type="checkbox" name="guesty_show_reviews" value="yes" ' . $global_rev_checked . '> <strong>Show Ratings & Reviews globally</strong></label>';
        echo '<p class="description" style="margin-left: 24px; margin-top: 5px;">Uncheck this to hide all star ratings across your website. (You can also hide them per-unit below).</p>';
        echo '</div>';
        
        echo '<div style="margin: 15px 0; display: flex; justify-content: flex-end;">';
        echo '<button type="submit" name="save_guesty_order" value="1" class="button button-primary">Save Unit Settings</button>';
        echo '</div>';

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th style="width: 100px;">Image</th><th>Property Details</th><th style="width: 220px;">Custom Badge</th><th style="width: 120px;">Display Position</th><th style="text-align: center; width: 120px;">Review Vis.</th><th style="width: 120px; text-align: center;">Website Vis.</th></tr></thead><tbody>';

        foreach ($listings as $listing) {
            $img_src = !empty($listing['image']) ? esc_url($listing['image']) : 'https://via.placeholder.com/100x75?text=No+Img';
            
            $is_hidden = in_array($listing['id'], $hidden_listings);
            $status_color = $is_hidden ? '#dc2626' : '#16a34a'; 
            $status_text = $is_hidden ? 'Hidden' : 'Shown';
            $button_text = $is_hidden ? 'Show Unit' : 'Hide Unit';

            $is_rev_hidden = in_array($listing['id'], $hidden_reviews);
            $rev_color = $is_rev_hidden ? '#dc2626' : '#2563eb'; 
            $rev_status_text = $is_rev_hidden ? 'Reviews Hidden' : 'Reviews Shown';
            $rev_btn_text = $is_rev_hidden ? 'Show Reviews' : 'Hide Reviews';
            
            $current_pos = isset($custom_order[$listing['id']]) ? $custom_order[$listing['id']] : '';
            
            $b_text = isset($custom_badges[$listing['id']]['text']) ? $custom_badges[$listing['id']]['text'] : '';
            $b_color = isset($custom_badges[$listing['id']]['color']) ? $custom_badges[$listing['id']]['color'] : '#ffffff';
            $b_text_color = isset($custom_badges[$listing['id']]['text_color']) ? $custom_badges[$listing['id']]['text_color'] : '#000000';
            $b_style = isset($custom_badges[$listing['id']]['style']) ? $custom_badges[$listing['id']]['style'] : 'diagonal';

            $pet_indicator = $listing['allows_pets'] ? '<br><span style="display: inline-flex; align-items: center; gap: 4px; color: #16a34a; font-size: 12px; margin-top: 4px; font-weight: 600;"><i class="ph ' . esc_attr($pet_icon) . '"></i> Pet Friendly</span>' : '';

            echo '<tr>';
            echo '<td><img src="' . $img_src . '" style="width: 100px; height: 75px; object-fit: cover; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);" alt="img" /></td>';
            echo '<td><strong>' . esc_html($listing['title']) . '</strong><br><span style="color: #646970; font-size: 12px;">ID: ' . esc_html($listing['id']) . '</span><br><span style="color: #646970; font-size: 12px;">Type: ' . esc_html($listing['type']) . '</span><br><span style="color: #646970; font-size: 12px;">Loc: ' . esc_html($listing['city']) . ', ' . esc_html($listing['country']) . '</span>' . $pet_indicator . '</td>';
            
            echo '<td style="vertical-align: middle;">';
            echo '<div style="display: flex; gap: 5px; margin-bottom: 5px;">';
            echo '<input type="text" name="guesty_badge[' . esc_attr($listing['id']) . '][text]" value="' . esc_attr($b_text) . '" placeholder="e.g. New!" style="flex-grow: 1; font-size: 13px; min-width: 0;" />';
            echo '<input type="color" name="guesty_badge[' . esc_attr($listing['id']) . '][text_color]" value="' . esc_attr($b_text_color) . '" style="height: 28px; width: 32px; padding: 2px; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; flex-shrink: 0;" title="Text Color" />';
            echo '</div>';
            echo '<div style="display: flex; gap: 5px;">';
            echo '<input type="color" name="guesty_badge[' . esc_attr($listing['id']) . '][color]" value="' . esc_attr($b_color) . '" style="height: 28px; width: 32px; padding: 2px; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; flex-shrink: 0;" title="Background Color" />';
            echo '<select name="guesty_badge[' . esc_attr($listing['id']) . '][style]" style="flex-grow: 1; font-size: 12px; height: 28px; line-height: 1; min-width: 0;">';
            echo '<option value="diagonal" ' . selected($b_style, 'diagonal', false) . '>Diagonal</option>';
            echo '<option value="straight" ' . selected($b_style, 'straight', false) . '>Straight</option>';
            echo '</select>';
            echo '</div>';
            echo '</td>';

            echo '<td style="vertical-align: middle;">';
            echo '<select name="guesty_unit_order[' . esc_attr($listing['id']) . ']" class="guesty-order-select">';
            echo '<option value="">- Auto/Random -</option>';
            for ($i = 1; $i <= count($listings); $i++) {
                $selected = ($current_pos == $i) ? 'selected' : '';
                echo '<option value="' . $i . '" ' . $selected . '>' . $i . '</option>';
            }
            echo '</select></td>';

            echo '<td style="text-align: center; vertical-align: middle;">';
            if ($listing['reviews'] > 0) {
                echo '<strong style="font-size: 14px;">' . esc_html(number_format((float)$listing['rating'], 1)) . '</strong> <span style="color: #646970; font-size: 12px;">(' . esc_html($listing['reviews']) . ' revs)</span><br>';
                echo '<span style="display: inline-block; padding: 2px 8px; border-radius: 12px; font-weight: bold; font-size: 10px; background: ' . $rev_color . '15; color: ' . $rev_color . '; margin: 4px 0 6px 0;">' . $rev_status_text . '</span><br>';
                echo '<button type="submit" name="toggle_guesty_reviews" value="' . esc_attr($listing['id']) . '" class="button button-small">' . $rev_btn_text . '</button>';
            } else {
                echo '<span style="color: #9ca3af; font-style: italic;">No Reviews</span>';
            }
            echo '</td>';
            
            echo '<td style="text-align: center; vertical-align: middle;">';
            echo '<span style="display: inline-block; padding: 4px 10px; border-radius: 12px; font-weight: bold; background: ' . $status_color . '15; color: ' . $status_color . '; margin-bottom: 8px;">' . $status_text . '</span><br>';
            echo '<button type="submit" name="toggle_guesty_visibility" value="' . esc_attr($listing['id']) . '" class="button button-small">' . $button_text . '</button>';
            echo '</td></tr>';
        }

        echo '</tbody></table>';

        echo '<div style="margin: 15px 0; display: flex; justify-content: flex-end;">';
        echo '<button type="submit" name="save_guesty_order" value="1" class="button button-primary">Save Unit Settings</button>';
        echo '</div>';
        echo '</form>';
        
        echo "
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selects = document.querySelectorAll('.guesty-order-select');
            function updateOptions() {
                const selectedValues = Array.from(selects).map(s => s.value).filter(v => v !== '');
                selects.forEach(select => {
                    const currentVal = select.value;
                    Array.from(select.options).forEach(opt => {
                        if (opt.value !== '' && opt.value !== currentVal && selectedValues.includes(opt.value)) {
                            opt.disabled = true;
                        } else {
                            opt.disabled = false;
                        }
                    });
                });
            }
            selects.forEach(s => s.addEventListener('change', updateOptions));
            updateOptions();
        });
        </script>";
    }
}
