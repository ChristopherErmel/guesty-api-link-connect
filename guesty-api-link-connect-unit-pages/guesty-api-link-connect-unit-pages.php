<?php
/**
 * Plugin Name: Guesty API Link Connect - Unit Pages
 * Description: Add-on for Guesty API Link Connect. Automatically generates dedicated, SEO-friendly landing pages with real-time calendar validation for each imported unit.
 * Version: 4.17.0
 * Author: Christopher E
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define the constant so the Main Plugin knows this Add-on is alive and active
define('GUESTY_ALC_UNIT_PAGES_ACTIVE', true);

class Guesty_ALC_Unit_Pages {

    public function __construct() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('init', [$this, 'add_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'render_unit_page']);
        
        // Ensure WordPress registers this as a real page for SEO Title Tags
        add_filter('document_title_parts', [$this, 'set_custom_seo_title']);
        
        // Hook directly into the main plugin's admin interface
        add_action('guesty_alc_render_unit_pages_panel', [$this, 'render_admin_panel']);
        add_action('admin_init', [$this, 'register_settings']);

        // Live Calendar AJAX Endpoints for the Booking Widget
        add_action('wp_ajax_guesty_get_unit_calendar', [$this, 'ajax_get_unit_calendar']);
        add_action('wp_ajax_nopriv_guesty_get_unit_calendar', [$this, 'ajax_get_unit_calendar']);

        // Live Quote API for Dynamic Pricing Sidebar
        add_action('wp_ajax_guesty_get_unit_quote', [$this, 'ajax_get_unit_quote']);
        add_action('wp_ajax_nopriv_guesty_get_unit_quote', [$this, 'ajax_get_unit_quote']);
    }

    public function activate() {
        $this->add_rewrite_rules();
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    public function register_settings() {
        register_setting('guesty-settings-group', 'guesty_unit_page_slug', 'sanitize_title');
        register_setting('guesty-settings-group', 'guesty_unit_btn_color');
        register_setting('guesty-settings-group', 'guesty_unit_bg_color');
        register_setting('guesty-settings-group', 'guesty_unit_show_map');
        register_setting('guesty-settings-group', 'guesty_unit_show_times');
        register_setting('guesty-settings-group', 'guesty_unit_thumb_count');
        
        // NEW: Specific URL for the Checkout Redirect
        register_setting('guesty-settings-group', 'guesty_unit_checkout_url', 'sanitize_url');
        
        // Register the new Unit Pages specific Additional CSS
        register_setting('guesty-settings-group', 'guesty_unit_additional_css');
    }

    public function add_rewrite_rules() {
        $slug = get_option('guesty_unit_page_slug', 'property');
        // Catch any URL matching /slug/unit-name/ and pass it to index.php
        add_rewrite_rule('^' . $slug . '/([^/]+)/?$', 'index.php?guesty_unit=$matches[1]', 'top');
    }

    public function add_query_vars($vars) {
        $vars[] = 'guesty_unit';
        return $vars;
    }

    public function set_custom_seo_title($title_parts) {
        $unit_slug = get_query_var('guesty_unit');
        if (!empty($unit_slug)) {
            $listings = get_transient('guesty_listings_data');
            if (is_array($listings)) {
                foreach ($listings as $lst) {
                    if (isset($lst['slug']) && $lst['slug'] === $unit_slug) {
                        // Override the page title with the actual Property Name
                        $title_parts['title'] = $lst['title'];
                        break;
                    }
                }
            }
        }
        return $title_parts;
    }

    public function render_admin_panel() {
        ?>
        <!-- The Main Unit Pages Settings Panel -->
        <div id="gvs-panel-unitpages" class="gvs-panel" style="display: none; background: #fff; padding: 25px; border: 1px solid #ccd0d4; border-radius: 8px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h3 style="margin:0;">Unit Pages Configuration</h3>
                <?php submit_button('Save Unit Settings', 'primary', 'submit', false); ?>
            </div>
            <p class="description" style="margin-top:-5px; margin-bottom: 20px;">This add-on safely intercepts requests and generates dynamic landing pages for each of your synced properties. It also features a Live Availability Calendar to prevent users from booking blocked dates.</p>
            
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Base URL Slug</th>
                    <td>
                        <input type="text" name="guesty_unit_page_slug" value="<?php echo esc_attr(get_option('guesty_unit_page_slug', 'property')); ?>" style="width: 200px;" />
                        <p class="description">Example: "property" will create URLs like <code>/property/unit-name/</code>.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Booking Engine Base URL</th>
                    <td>
                        <input type="url" name="guesty_unit_checkout_url" value="<?php echo esc_url(get_option('guesty_unit_checkout_url', get_option('guesty_base_url'))); ?>" style="width: 400px;" placeholder="https://yourdomain.guestybookings.com/en/properties/" />
                        <p class="description">Used for the Book button redirect. Example: <code>https://ocrvacations.guestybookings.com/en/properties/</code>. The plugin will automatically append the Property ID and <code>/checkout?dates...</code> to this URL.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Page Background Color</th>
                    <td>
                        <input type="color" name="guesty_unit_bg_color" value="<?php echo esc_attr(get_option('guesty_unit_bg_color', '#ffffff')); ?>" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Page Button Color</th>
                    <td>
                        <input type="color" name="guesty_unit_btn_color" value="<?php echo esc_attr(get_option('guesty_unit_btn_color', '#0062ff')); ?>" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Image Gallery</th>
                    <td>
                        <input type="number" name="guesty_unit_thumb_count" value="<?php echo esc_attr(get_option('guesty_unit_thumb_count', '8')); ?>" style="width: 80px;" min="1" max="30" />
                        <span class="description" style="margin-left: 8px;">Number of thumbnail images to display visibly in the bottom gallery track.</span>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Show Check-in Times</th>
                    <td>
                        <label>
                            <input type="checkbox" name="guesty_unit_show_times" value="yes" <?php checked(get_option('guesty_unit_show_times', 'yes'), 'yes'); ?> />
                            Display standard Check-in and Check-out times beneath the description.
                        </label>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Show Location Map</th>
                    <td>
                        <label>
                            <input type="checkbox" name="guesty_unit_show_map" value="yes" <?php checked(get_option('guesty_unit_show_map', 'yes'), 'yes'); ?> />
                            Display a Google Map at the bottom of the unit page based on its city/country.
                        </label>
                    </td>
                </tr>
            </table>
            
            <div style="margin-top:25px; display: flex; justify-content: flex-end;"><?php submit_button('Save Unit Settings', 'primary', 'submit', false); ?></div>
        </div>

        <!-- Hidden DOM Elements for CSS Tab Injection -->
        <div id="gvs-unit-css-injection" style="display:none;">
            <hr style="margin: 30px 0; border-top: 1px solid #ccd0d4;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <div style="display:flex; align-items:center; gap: 15px;">
                    <h3 style="margin:0;">Unit Pages CSS</h3>
                    <button type="button" class="button" onclick="gvsOpenUnitSelectorModal()">Useful Selectors</button>
                </div>
            </div>
            <p class="description" style="margin-top:-5px; margin-bottom:15px;">Add custom CSS rules here specifically for the dedicated Unit Pages.</p>
            <textarea name="guesty_unit_additional_css" rows="12" style="width: 100%; font-family: monospace; background: #fafafa; border: 1px solid #ccc; padding: 10px;"><?php echo esc_textarea(get_option('guesty_unit_additional_css', '')); ?></textarea>
        </div>

        <div id="gvs-unit-selector-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:99999; justify-content:center; align-items:center;">
            <div style="background:#fff; width:90%; max-width:800px; border-radius:8px; padding:25px; display:flex; flex-direction:column; max-height:85vh; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                    <h3 style="margin:0;">Useful Selectors: Unit Pages</h3>
                    <button type="button" class="button" onclick="document.getElementById('gvs-unit-selector-modal').style.display='none'">Close</button>
                </div>
                <input type="text" id="gvs-unit-selector-search" placeholder="Search selectors (e.g. title, gallery, map)..." style="width:100%; padding:10px; margin-bottom:15px; font-size:16px;">
                <div id="gvs-unit-selector-list" style="display:flex; flex-direction:column; gap:10px; overflow-y:auto; flex-grow:1; padding-right:10px;">
                    <!-- JS injects selectors here -->
                </div>
            </div>
        </div>

        <script>
            const gvsUnitSelectorsList = [
                { selector: '.gvs-unit-wrap', desc: 'The main outer wrapper container for the entire unit page.' },
                { selector: '.gvs-unit-slider-wrapper', desc: 'The container holding both the main image and thumbnail gallery.' },
                { selector: '.swiper-main', desc: 'The main large image slider at the top.' },
                { selector: '.swiper-main img', desc: 'The main property image inside the top slider.' },
                { selector: '.swiper-thumbs', desc: 'The thumbnail slider track below the main image.' },
                { selector: '.swiper-slide-thumb-active', desc: 'The currently selected/active thumbnail image.' },
                { selector: '.swiper-button-next', desc: 'The right-arrow button on the main image slider.' },
                { selector: '.swiper-button-prev', desc: 'The left-arrow button on the main image slider.' },
                { selector: '.gvs-unit-content-grid', desc: 'The 2-column grid holding the main content (left) and the booking sidebar (right).' },
                { selector: '.gvs-unit-breadcrumbs', desc: 'The breadcrumbs navigation path at the very top (Home > Name).' },
                { selector: '.gvs-unit-title', desc: 'The main H1 property title.' },
                { selector: '.gvs-unit-section-title', desc: 'The H3 section titles (Description, Features, Amenities, Location).' },
                { selector: '.gvs-unit-divider', desc: 'The horizontal gray line separating content sections.' },
                { selector: '.gvs-expandable-text', desc: 'The paragraph container holding the property description.' },
                { selector: '.gvs-show-all-btn', desc: 'The "Show all" / "Show less" toggle buttons under grids and text.' },
                { selector: '.gvs-unit-features', desc: 'The horizontal wrapper containing Bedrooms, Guests, Bathrooms.' },
                { selector: '.gvs-unit-feature-item', desc: 'An individual feature block (icon + text).' },
                { selector: '.gvs-unit-feature-item i', desc: 'The icon inside a feature block.' },
                { selector: '.gvs-expandable-grid', desc: 'The grid container holding all the amenities.' },
                { selector: '.gvs-unit-am-item', desc: 'An individual amenity item in the grid (icon + text).' },
                { selector: '.gvs-unit-map-wrapper', desc: 'The rounded container holding the embedded Google Map.' },
                { selector: '.gvs-booking-widget', desc: 'The sticky booking form contained in the right sidebar.' },
                { selector: '.gvs-bw-title', desc: 'The "Search for available dates" title in the booking widget.' },
                { selector: '.gvs-bw-input-wrap', desc: 'The bordered wrapper around the date and guest input fields.' },
                { selector: '.gvs-bw-label', desc: 'The uppercase label (Check-in, Check-out) above the inputs.' },
                { selector: '.gvs-bw-btn', desc: 'The main "Book" button in the widget.' },
                { selector: '.gvs-bw-loader', desc: 'The spinning loading icon inside the booking button.' },
                { selector: '.gvs-bw-error', desc: 'The red error text that appears above the button if dates are missing.' },
                { selector: '.gvs-quote-grid', desc: 'The 4-column breakdown grid (Check In, Out, Nights, Guests) that appears after date selection.' },
                { selector: '.gvs-quote-line', desc: 'The flexbox row for Subtotal, Fees, and Taxes inside the quote breakdown.' },
                { selector: '.gvs-quote-total', desc: 'The bold final Total row inside the quote breakdown.' },
                { selector: '.flatpickr-day.flatpickr-disabled', desc: 'A blocked/unavailable date cell inside the popup calendar.' },
                { selector: '.gvs-lightbox', desc: 'The fullscreen image overlay background.' },
                { selector: '.gvs-lightbox-content', desc: 'The fullscreen image currently being viewed.' },
                { selector: '.gvs-lightbox-nav', desc: 'The next/prev arrow buttons in the lightbox.' },
                { selector: '.gvs-lightbox-close', desc: 'The "X" close button in the top right of the lightbox.' }
            ];

            function gvsRenderUnitSelectorList(filter = '') {
                const listContainer = document.getElementById('gvs-unit-selector-list');
                if (!listContainer) return;
                
                listContainer.innerHTML = '';
                const searchStr = filter.toLowerCase();

                gvsUnitSelectorsList.forEach(item => {
                    if (item.selector.toLowerCase().includes(searchStr) || item.desc.toLowerCase().includes(searchStr)) {
                        const div = document.createElement('div');
                        div.style.cssText = 'background:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:12px; display:flex; justify-content:space-between; align-items:center; gap:15px;';
                        
                        div.innerHTML = `
                            <div>
                                <code style="display:block; font-size:14px; color:#2563eb; font-weight:bold; margin-bottom:4px;">${item.selector}</code>
                                <span style="font-size:13px; color:#4b5563;">${item.desc}</span>
                            </div>
                            <button type="button" class="button button-small" onclick="navigator.clipboard.writeText('${item.selector}'); this.innerText='Copied!'; setTimeout(()=>this.innerText='Copy', 2000);">Copy</button>
                        `;
                        listContainer.appendChild(div);
                    }
                });
            }

            function gvsOpenUnitSelectorModal() {
                document.getElementById('gvs-unit-selector-modal').style.display = 'flex';
                gvsRenderUnitSelectorList('');
            }

            document.addEventListener('DOMContentLoaded', () => {
                const cssPanel = document.getElementById('gvs-panel-css');
                if (cssPanel) {
                    const mainTitle = cssPanel.querySelector('h3');
                    if (mainTitle) mainTitle.innerText = 'Main Widget / Search & Results CSS';
                    
                    const injectionHTML = document.getElementById('gvs-unit-css-injection').innerHTML;
                    const submitBtns = cssPanel.querySelectorAll('div');
                    
                    for (let div of submitBtns) {
                        if (div.style.justifyContent === 'flex-end') {
                            div.insertAdjacentHTML('beforebegin', injectionHTML);
                            break;
                        }
                    }
                    
                    const wrapper = document.getElementById('gvs-unit-css-injection');
                    if (wrapper) wrapper.remove();
                }

                const unitModal = document.getElementById('gvs-unit-selector-modal');
                if (unitModal) {
                    document.body.appendChild(unitModal);
                }

                const searchInput = document.getElementById('gvs-unit-selector-search');
                if (searchInput) {
                    searchInput.addEventListener('input', (e) => {
                        gvsRenderUnitSelectorList(e.target.value);
                    });
                }
            });
        </script>
        <?php
    }

    private function log($message, $type = 'ERROR') {
        $logs = get_option('guesty_vrbo_logs', []);
        if (!is_array($logs)) $logs = [];
        if (count($logs) > 100) array_shift($logs);
        $logs[] = [
            'time' => current_time('mysql'),
            'type' => $type,
            'message' => is_array($message) || is_object($message) ? print_r($message, true) : $message
        ];
        update_option('guesty_vrbo_logs', $logs, false); 
    }

    private function get_access_token() {
        $token = get_transient('guesty_access_token');
        if ($token) return $token;

        $client_id = get_option('guesty_client_id');
        $client_secret = get_option('guesty_client_secret');

        if (!$client_id || !$client_secret) return false;

        $response = wp_remote_post('https://open-api.guesty.com/oauth2/token', [
            'headers' => [ 'Accept' => 'application/json', 'Content-Type' => 'application/x-www-form-urlencoded' ],
            'body' => [ 'grant_type' => 'client_credentials', 'client_id' => $client_id, 'client_secret' => $client_secret ]
        ]);

        if (is_wp_error($response)) return false;
        if (wp_remote_retrieve_response_code($response) != 200) return false;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (is_array($body) && isset($body['access_token'])) {
            set_transient('guesty_access_token', $body['access_token'], 23 * HOUR_IN_SECONDS);
            return $body['access_token'];
        }
        return false;
    }

    /**
     * Deep Math Engine: Mathematically computes exact cascading dynamic pricing based on the settingsSnapshot.
     */
    private function evaluate_guesty_rules($snapshot, $days_array, $check_in, $check_out, $guests_count) {
        $quote_data = [
            'total' => 0, 'subtotal' => 0, 'currency' => 'CAD',
            'taxes' => 0, 'fees' => 0, 'taxes_list' => [], 'fees_list' => []
        ];

        // 1. Establish the Raw Nightly Base Rate
        $raw_subtotal = 0;
        if (is_array($days_array)) {
            foreach ($days_array as $day) {
                if (is_array($day) && isset($day['date']) && strpos($day['date'], $check_out) === false) {
                    $raw_subtotal += (float)($day['price'] ?? $day['basePrice'] ?? 0);
                    if (isset($day['currency'])) $quote_data['currency'] = $day['currency'];
                }
            }
        }

        if ($raw_subtotal <= 0) return false;
        
        // Apply Manual Channel Markup (to bypass the API 0% base override)
        $markup_pct = (float) get_option('guesty_channel_markup', 0);
        if ($markup_pct > 0) {
            $raw_subtotal = round($raw_subtotal * (1 + ($markup_pct / 100)), 2);
        }

        $checkin_ts = strtotime($check_in);
        $checkout_ts = strtotime($check_out);
        $nights = max(1, round(($checkout_ts - $checkin_ts) / DAY_IN_SECONDS));
        $guests = max(1, $guests_count);

        $bundled_total = 0;
        $unbundled_records = [];
        $all_fees_to_process = [];

        // 2. Queue Cleaning Fee Formula
        $cf = 0;
        if (isset($snapshot['cleaningFeeFormula']['value']['formula'])) {
            $cf = (float) $snapshot['cleaningFeeFormula']['value']['formula'];
        } elseif (isset($snapshot['cleaningFee'])) {
            $cf = (float) $snapshot['cleaningFee'];
        }
        
        if ($cf > 0) {
            $all_fees_to_process[] = [
                'name' => 'Cleaning fee',
                'type' => 'CLEANING',
                'isPercentage' => false,
                'value' => $cf,
                'isBundled' => false,
                'quantifier' => 'PER_STAY'
            ];
        }

        // 3. Queue Additional Fees (Aggressively intercepting manual/website overrides!)
        $add_fees = isset($snapshot['additionalFees']) ? $snapshot['additionalFees'] : ($snapshot['fees'] ?? []);
        if (is_array($add_fees)) {
            foreach ($add_fees as $f) {
                $name = $f['name'] ?? 'Fee';
                $type = strtoupper($f['type'] ?? 'FEE');
                
                $fee_value = (float)($f['value'] ?? $f['amount'] ?? $f['fee'] ?? 0);
                $is_pct = !empty($f['isPercentage']) || ((isset($f['units']) && strtoupper($f['units']) === 'PERCENTAGE'));
                $is_bundled = !empty($f['isBundled']);
                $quantifier = $f['quantifier'] ?? '';
                
                $is_enabled = !empty($f['allPlatforms']); 

                $override_found = false;
                
                // Primary Filter: Explicit Channel Configurations
                if (isset($f['channelConfigurations']) && is_array($f['channelConfigurations'])) {
                    foreach ($f['channelConfigurations'] as $cc) {
                        if (isset($cc['channel']) && in_array($cc['channel'], ['manual', 'manual_reservations', 'website', 'bookingEngine'])) {
                            $is_enabled = !empty($cc['isEnabled']);
                            if (isset($cc['value'])) $fee_value = (float)$cc['value'];
                            if (isset($cc['isPercentage'])) $is_pct = !empty($cc['isPercentage']);
                            if (isset($cc['isBundled'])) $is_bundled = !empty($cc['isBundled']);
                            $override_found = true; 
                            if (in_array($cc['channel'], ['manual', 'manual_reservations'])) break; // Highest priority for Open API
                        }
                    }
                }
                
                // Secondary Filter: Fallback Source Configurations
                if (!$override_found && isset($f['sourcesConfigurations']) && is_array($f['sourcesConfigurations'])) {
                    foreach ($f['sourcesConfigurations'] as $sc) {
                        if (isset($sc['sources']) && (in_array('manual', $sc['sources']) || in_array('website', $sc['sources']))) {
                            $is_enabled = !empty($sc['isEnabled']);
                            if (isset($sc['value'])) $fee_value = (float)$sc['value'];
                            if (isset($sc['isPercentage'])) $is_pct = !empty($sc['isPercentage']);
                            if (isset($sc['isBundled'])) $is_bundled = !empty($sc['isBundled']);
                            break;
                        }
                    }
                }

                // Completely purge the fee if the manual channel rejected it
                if (!$is_enabled) continue;

                $all_fees_to_process[] = [
                    'name' => $name,
                    'type' => $type,
                    'isPercentage' => $is_pct,
                    'value' => $fee_value,
                    'isBundled' => $is_bundled,
                    'quantifier' => $quantifier
                ];
            }
        }

        // 4. Calculate Bundled Fees (Percentages always run strictly against RAW subtotal)
        foreach ($all_fees_to_process as $fee) {
            $calc_amt = 0;
            if ($fee['isPercentage']) {
                $calc_amt = round($raw_subtotal * ($fee['value'] / 100), 2);
            } else {
                $calc_amt = $fee['value'];
                if ($fee['quantifier'] === 'PER_NIGHT') $calc_amt *= $nights;
                if ($fee['quantifier'] === 'PER_GUEST') $calc_amt *= $guests;
            }

            if ($calc_amt > 0) {
                if ($fee['isBundled']) {
                    // Bundled fees mathematically inflate the subtotal and are hidden from the UI list!
                    $bundled_total += $calc_amt;
                } else {
                    // Temporarily store evaluated percentages for later cascading against inflated subtotals
                    $unbundled_records[] = [
                        'name' => $fee['name'],
                        'type' => $fee['type'],
                        'amount' => $calc_amt,
                        'is_pct' => $fee['isPercentage'],
                        'pct_val' => $fee['value']
                    ];
                }
            }
        }

        // 5. Establish the INFLATED Subtotal
        $inflated_subtotal = $raw_subtotal + $bundled_total;
        $quote_data['subtotal'] = $inflated_subtotal;

        // 6. Calculate Unbundled Fees
        $unbundled_total = 0;
        $evaluated_unbundled_fees = [];

        foreach ($unbundled_records as $uf) {
            $calc_amt = 0;
            if ($uf['is_pct']) {
                // CASCADING EFFECT: Unbundled percentages (like 13% Booking Fee) run against the INFLATED Subtotal!
                $calc_amt = round($inflated_subtotal * ($uf['pct_val'] / 100), 2);
            } else {
                $calc_amt = $uf['amount'];
            }
            
            if ($calc_amt > 0) {
                $unbundled_total += $calc_amt;
                $quote_data['fees'] += $calc_amt;
                $quote_data['fees_list'][] = ['name' => $uf['name'], 'amount' => $calc_amt];
                $evaluated_unbundled_fees[] = ['type' => $uf['type'], 'amount' => $calc_amt];
            }
        }

        // 7. Evaluate Advanced Cascading Tax Logic
        $taxes_total = 0;
        $taxes = isset($snapshot['taxes']) && is_array($snapshot['taxes']) ? $snapshot['taxes'] : [];
        foreach ($taxes as $t) {
            $name = $t['name'] ?? 'Tax';
            $amt = (float)($t['amount'] ?? $t['value'] ?? 0);
            $is_pct = !empty($t['isPercentage']) || (($t['units'] ?? '') === 'PERCENTAGE');
            $appliedOn = $t['appliedOnFees'] ?? [];

            if ($is_pct) {
                $taxable_base = 0;
                
                // Guesty's widget treats 'AF' (Accommodation Fare) strictly as the INFLATED Subtotal
                if (empty($appliedOn) || in_array('AF', $appliedOn) || in_array('ACCOMMODATION_FARE', $appliedOn)) {
                    $taxable_base += $inflated_subtotal;
                }

                // If tax applies to fees (e.g. HST applies to everything), cascade those amounts in
                if (!empty($t['appliedToAllFees'])) {
                    $taxable_base += $unbundled_total;
                } elseif (!empty($appliedOn) && is_array($appliedOn)) {
                    foreach ($evaluated_unbundled_fees as $euf) {
                        $type = strtoupper($euf['type']);
                        if (in_array($type, $appliedOn) || 
                           ($type === 'CLEANING' && in_array('CF', $appliedOn)) ||
                           ($type === 'BOOKING_FEE' && in_array('BOOKING_FEE', $appliedOn)) ||
                           ($type === 'PET' && in_array('PET', $appliedOn)) ||
                           in_array('ADDITIONAL_CHARGE', $appliedOn)) {
                            $taxable_base += $euf['amount'];
                        }
                    }
                }
                
                $calc_amt = round($taxable_base * ($amt / 100), 2);
            } else {
                $calc_amt = $amt;
                if (($t['quantifier'] ?? '') === 'PER_NIGHT') $calc_amt *= $nights;
                if (($t['quantifier'] ?? '') === 'PER_GUEST') $calc_amt *= $guests;
            }

            if ($calc_amt > 0) {
                $taxes_total += $calc_amt;
                $quote_data['taxes'] += $calc_amt;
                $quote_data['taxes_list'][] = ['name' => $name, 'amount' => $calc_amt];
            }
        }

        // 8. Calculate Final Grand Total
        $quote_data['total'] = $quote_data['subtotal'] + $quote_data['fees'] + $quote_data['taxes'];
        
        return $quote_data;
    }

    public function ajax_get_unit_quote() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'guesty_unit_ajax_nonce')) {
            wp_send_json_error(['message' => 'Unauthorized request']);
        }

        $unit_id = trim(sanitize_text_field($_POST['unit_id'] ?? ''));
        $check_in = sanitize_text_field($_POST['check_in'] ?? '');
        $check_out = sanitize_text_field($_POST['check_out'] ?? '');
        $guests = (int) ($_POST['guests'] ?? 1);
        $coupon = sanitize_text_field($_POST['coupon'] ?? '');

        $this->log("Quote Request Initiated - Unit: {$unit_id} | In: {$check_in} | Out: {$check_out} | Guests: {$guests} | Coupon: {$coupon}", 'INFO');

        if (empty($unit_id) || empty($check_in) || empty($check_out)) {
            wp_send_json_error(['message' => 'Missing required date parameters']);
        }

        $token = $this->get_access_token();
        if (!$token) {
            wp_send_json_error(['message' => 'Invalid API credentials.']);
        }
        
        $success = false;

        $url = "https://open-api.guesty.com/v1/quotes";
        
        // Passing clean standard payload with 'manual' to align with the actual token's identity.
        $body = [
            'listingId' => $unit_id,
            'checkInDate' => $check_in,
            'checkOutDate' => $check_out,
            'checkInDateLocalized' => $check_in,
            'checkOutDateLocalized' => $check_out,
            'guestsCount' => $guests,
            'source' => 'manual',
            'channel' => 'manual_reservations'
        ];
        
        if (!empty($coupon)) {
            $body['promotionCode'] = $coupon;
        }

        $this->log("Sending Payload to Quotes API: " . wp_json_encode($body), 'INFO');

        $response = wp_remote_post($url, [
            'headers' => [ 
                'Authorization' => 'Bearer ' . $token, 
                'Content-Type' => 'application/json', 
                'Accept' => 'application/json' 
            ],
            'body' => wp_json_encode($body),
            'timeout' => 15
        ]);

        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            $body_raw = wp_remote_retrieve_body($response);
            
            $this->log("Quotes API Response HTTP {$code}: " . substr($body_raw, 0, 4000), 'INFO');

            $data = json_decode($body_raw, true);

            if ($code >= 200 && $code < 300 && is_array($data)) {
                
                // RULES EVALUATOR ENGINE
                $snapshot = $data['rates']['ratePlans'][0]['money']['money']['settingsSnapshot'] ?? 
                            $data['money']['settingsSnapshot'] ?? 
                            $data['settingsSnapshot'] ?? null;
                            
                $days_array = $data['rates']['ratePlans'][0]['days'] ?? $data['days'] ?? [];

                if ($snapshot && !empty($days_array)) {
                    $evaluated_quote = $this->evaluate_guesty_rules($snapshot, $days_array, $check_in, $check_out, $guests);
                    
                    if ($evaluated_quote && $evaluated_quote['total'] > 0) {
                        $success = true;
                        $this->log("Quote Successfully Generated via Rules Evaluator Engine. Total: {$evaluated_quote['total']} {$evaluated_quote['currency']}", 'SUCCESS');
                        wp_send_json_success(['quote' => $evaluated_quote]);
                        return; // Explicit Exit
                    }
                }
            } else {
                $err_msg = '';
                if (is_array($data)) {
                    $err_msg = $data['message'] ?? ($data['error'] ?? '');
                } else if (is_string($data)) {
                    $err_msg = $data;
                } else {
                    $err_msg = $body_raw; 
                }

                if (!is_string($err_msg)) {
                    $err_msg = wp_json_encode($err_msg);
                }
                
                $is_rule_violation = false;
                $rule_keywords = ['minimum', 'maximum', 'coupon', 'promo', 'promotion', 'invalid', 'nights required', 'too short', 'too long'];
                foreach ($rule_keywords as $keyword) {
                    if (!empty($err_msg) && stripos($err_msg, $keyword) !== false) {
                        $is_rule_violation = true;
                        break;
                    }
                }

                if ($is_rule_violation) {
                     $this->log("Quotes API Rejected due to user rule violation: {$err_msg}", 'WARNING');
                     wp_send_json_error(['message' => "Notice: " . $err_msg]);
                     return; 
                }
            }
        }

        // FAILSAFE ENGINE: Extract raw calendar rates as absolute fallback
        if (!$success) {
            $this->log("Quotes API failed or returned generic error. Triggering Calendar Fallback Engine for Unit {$unit_id}.", 'WARNING');

            $quote_data = ['total' => 0, 'subtotal' => 0, 'currency' => 'CAD', 'taxes' => 0, 'fees' => 0, 'taxes_list' => [], 'fees_list' => []];
            
            $url_cal = "https://open-api.guesty.com/v1/listings/{$unit_id}/calendar?from={$check_in}&to={$check_out}&startDate={$check_in}&endDate={$check_out}";

            $res_cal = wp_remote_get($url_cal, [
                'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json' ],
                'timeout' => 15
            ]);
            
            if (!is_wp_error($res_cal)) {
                $code_cal = wp_remote_retrieve_response_code($res_cal);
                $body_cal = wp_remote_retrieve_body($res_cal);
                
                if ($code_cal >= 200 && $code_cal < 300) {
                    $data = json_decode($body_cal, true);
                    if (is_array($data)) {
                        $days = [];
                        if (isset($data[0]) && is_array($data[0])) $days = $data; 
                        elseif (isset($data['data'])) $days = $data['data'];
                        elseif (isset($data['results'])) $days = $data['results'];
                        elseif (isset($data['days'])) $days = $data['days'];
                        
                        if (is_array($days)) {
                            if (isset($days['days']) && is_array($days['days'])) $days = $days['days'];

                            foreach ($days as $day) {
                                if (is_array($day) && isset($day['date']) && strpos($day['date'], $check_out) === false) {
                                    $quote_data['subtotal'] += (float)($day['price'] ?? ($day['basePrice'] ?? 0));
                                }
                            }

                            if ($quote_data['subtotal'] > 0) {
                                $quote_data['total'] = $quote_data['subtotal'];
                                $success = true;
                                wp_send_json_success(['quote' => $quote_data]);
                                return; 
                            }
                        }
                    }
                }
            }

            if (!$success) {
                $this->log("Quote Engine Final Failure: Unable to calculate a valid price > 0 for Unit {$unit_id}.", 'ERROR');
                wp_send_json_error(['message' => 'Failed to calculate total price for these dates. They may no longer be available.']);
            }
        }
    }

    public function ajax_get_unit_calendar() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'guesty_unit_ajax_nonce')) {
            wp_send_json_error(['message' => 'Unauthorized request']);
        }

        $unit_id = trim(sanitize_text_field($_POST['unit_id'] ?? ''));
        if (empty($unit_id)) wp_send_json_error(['message' => 'Missing Unit ID']);

        $token = $this->get_access_token();
        if (!$token) wp_send_json_error(['message' => 'Invalid API credentials. Please sync the main plugin.']);

        $blocked_dates = [];

        $url_res = "https://open-api.guesty.com/v1/reservations?listingId={$unit_id}&limit=100";
        $res_reservations = wp_remote_get($url_res, [
            'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json' ],
            'timeout' => 15
        ]);

        if (!is_wp_error($res_reservations) && wp_remote_retrieve_response_code($res_reservations) == 200) {
            $body = json_decode(wp_remote_retrieve_body($res_reservations), true);
            if (is_array($body)) {
                $items = isset($body['results']) ? $body['results'] : (isset($body['data']) ? $body['data'] : []);
                foreach ($items as $item) {
                    $item_lid = isset($item['listingId']) ? $item['listingId'] : ($item['listing']['_id'] ?? '');
                    if ($item_lid !== $unit_id && !empty($item_lid)) continue; 

                    $status = strtolower($item['status'] ?? '');
                    if (in_array($status, ['canceled', 'declined', 'available'])) continue;

                    $start = $item['checkIn'] ?? ($item['startDate'] ?? '');
                    $end = $item['checkOut'] ?? ($item['endDate'] ?? '');
                    
                    if (!empty($start) && !empty($end)) {
                        try {
                            $start_dt = new DateTime(date('Y-m-d', strtotime($start)));
                            $end_dt = new DateTime(date('Y-m-d', strtotime($end)));
                            if ($start_dt < $end_dt) {
                                $period = new DatePeriod($start_dt, new DateInterval('P1D'), $end_dt);
                                foreach ($period as $dt) $blocked_dates[] = $dt->format('Y-m-d');
                            }
                        } catch (Exception $e) {}
                    }
                }
            }
        }

        $ranges = [
            ['start' => date('Y-m-d'), 'end' => date('Y-m-d', strtotime('+180 days'))],
            ['start' => date('Y-m-d', strtotime('+181 days')), 'end' => date('Y-m-d', strtotime('+360 days'))]
        ];

        foreach ($ranges as $range) {
            $url_cal = "https://open-api.guesty.com/v1/listings/{$unit_id}/calendar?from={$range['start']}&to={$range['end']}&startDate={$range['start']}&endDate={$range['end']}";
            $res_cal = wp_remote_get($url_cal, [
                'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json' ],
                'timeout' => 15
            ]);

            if (!is_wp_error($res_cal) && wp_remote_retrieve_response_code($res_cal) == 200) {
                $body = json_decode(wp_remote_retrieve_body($res_cal), true);
                if (is_array($body)) {
                    $days = [];
                    if (isset($body[0]) && is_array($body[0])) $days = $body;
                    elseif (isset($body['data'])) $days = $body['data'];
                    elseif (isset($body['results'])) $days = $body['results'];
                    elseif (isset($body['days'])) $days = $body['days'];

                    if (is_array($days)) {
                        if (isset($days['days']) && is_array($days['days'])) $days = $days['days'];
                        foreach ($days as $day) {
                            if (!is_array($day)) continue;

                            $is_blocked = false;
                            $status = isset($day['status']) ? strtolower($day['status']) : '';

                            if ($status !== '' && $status !== 'available') $is_blocked = true;
                            elseif (isset($day['blocks']) && is_array($day['blocks'])) {
                                foreach ($day['blocks'] as $block_active) { if ($block_active === true) { $is_blocked = true; break; } }
                            } elseif (!empty($day['reservation'])) $is_blocked = true;
                            elseif (isset($day['isAvailable']) && $day['isAvailable'] === false) $is_blocked = true;

                            if ($is_blocked && !empty($day['date'])) {
                                $date_parts = explode('T', $day['date']);
                                if (isset($date_parts[0])) $blocked_dates[] = $date_parts[0];
                            }
                        }
                    }
                }
            }
        }
        
        $blocked_dates = array_values(array_unique($blocked_dates));
        wp_send_json_success(['blocked_dates' => $blocked_dates]);
    }

    public function render_unit_page() {
        $unit_slug = get_query_var('guesty_unit');
        if (empty($unit_slug)) return;

        $listings = get_transient('guesty_listings_data');
        $property = null;

        if (is_array($listings)) {
            foreach ($listings as $lst) {
                if (isset($lst['slug']) && $lst['slug'] === $unit_slug) {
                    $property = $lst; break;
                }
            }
        }

        if (!$property) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            get_template_part(404);
            exit;
        }

        global $wp_query;
        $wp_query->is_404 = false;
        $wp_query->is_page = true;
        status_header(200);

        get_header();
        $this->print_html_layout($property);
        get_footer();
        exit;
    }

    private function print_html_layout($property) {
        $btn_color = get_option('guesty_unit_btn_color', '#0062ff');
        $bg_color = get_option('guesty_unit_bg_color', '#ffffff');
        $show_map = get_option('guesty_unit_show_map', 'yes') === 'yes';
        $show_times = get_option('guesty_unit_show_times', 'yes') === 'yes';
        $thumb_count = (int) get_option('guesty_unit_thumb_count', 8);
        $base_guesty_url = get_option('guesty_base_url', '');
        
        $unit_additional_css = get_option('guesty_unit_additional_css', '');
        
        // Use the newly established Booking Engine Base URL setting, falling back to the standard base URL if empty
        $checkout_base_url = get_option('guesty_unit_checkout_url', get_option('guesty_base_url', ''));
        $checkout_link = $checkout_base_url ? (rtrim($checkout_base_url, '/') . '/' . $property['id'] . '/checkout') : '#';
        
        $images = !empty($property['pictures']) && is_array($property['pictures']) ? $property['pictures'] : [$property['image']];
        $max_guests = isset($property['accommodates']) && (int)$property['accommodates'] > 0 ? (int)$property['accommodates'] : 1;
        
        ?>
        <script src="https://unpkg.com/@phosphor-icons/web"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
        <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

        <style>
            .ast-archive-entry-banner, .ast-breadcrumbs-wrapper, .page-header { display: none !important; }
            body, .site-content, #content, .ast-container { background-color: <?php echo esc_attr($bg_color); ?> !important; }
            
            .gvs-unit-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; max-width: 1200px; margin: 40px auto; padding: 0 20px; color: #1d2327; background: transparent; }
            
            .gvs-unit-slider-wrapper { margin-bottom: 40px; }
            .swiper-main { width: 100%; height: 500px; border-radius: 12px; overflow: hidden; margin-bottom: 12px; }
            .swiper-main img { width: 100%; height: 100%; object-fit: cover; cursor: zoom-in; }
            .swiper-thumbs { width: 100%; height: 100px; box-sizing: border-box; padding: 0; }
            .swiper-thumbs .swiper-slide { height: 100%; opacity: 0.5; cursor: pointer; border-radius: 8px; overflow: hidden; transition: opacity 0.2s; }
            .swiper-thumbs .swiper-slide-thumb-active { opacity: 1; border: 2px solid <?php echo esc_attr($btn_color); ?>; }
            .swiper-thumbs .swiper-slide img { width: 100%; height: 100%; object-fit: cover; }
            
            .swiper-button-next, .swiper-button-prev { color: #fff !important; background: rgba(0,0,0,0.4); padding: 30px 20px; border-radius: 8px; }
            .swiper-button-next:hover, .swiper-button-prev:hover { background: rgba(0,0,0,0.7); }
            .swiper-button-next::after, .swiper-button-prev::after { font-size: 20px !important; font-weight: bold; }
            
            @media(max-width: 768px) { .swiper-main { height: 350px; } .swiper-thumbs { display: none; } }

            .gvs-lightbox { position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.95); display: none; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
            .gvs-lightbox.active { display: flex; }
            .gvs-lightbox-content { max-width: 80vw; max-height: 85vh; border-radius: 8px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); object-fit: contain; animation: gvs-zoom 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); user-select: none; }
            .gvs-lightbox-close { position: absolute; top: 20px; right: 30px; color: #cbd5e1; font-size: 40px; font-weight: bold; cursor: pointer; transition: color 0.2s; line-height: 1; z-index: 1000000; }
            .gvs-lightbox-close:hover { color: #fff; }
            
            .gvs-lightbox-nav { position: absolute; top: 50%; transform: translateY(-50%); color: #f8fafc; font-size: 40px; cursor: pointer; transition: color 0.2s, background 0.2s; z-index: 1000000; display: flex; align-items: center; justify-content: center; padding: 20px; user-select: none; border-radius: 8px; }
            .gvs-lightbox-nav:hover { color: #fff; background: rgba(255,255,255,0.1); }
            .gvs-lightbox-prev { left: 2vw; } .gvs-lightbox-next { right: 2vw; }
            
            @keyframes gvs-zoom { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }

            .gvs-unit-content-grid { display: grid; grid-template-columns: 1fr 380px; gap: 60px; }
            @media(max-width: 900px) { .gvs-unit-content-grid { grid-template-columns: 1fr; gap: 40px; } }

            .gvs-unit-breadcrumbs { font-size: 14px; color: #64748b; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
            .gvs-unit-breadcrumbs a { color: #64748b; text-decoration: none; }
            .gvs-unit-title { font-size: 36px; font-weight: 700; margin: 0 0 12px 0; color: #0f172a; line-height: 1.2; }
            
            .gvs-unit-section-title { font-size: 20px; font-weight: 600; margin: 30px 0 16px 0; color: #0f172a; }
            .gvs-unit-divider { height: 1px; background: #e2e8f0; width: 100%; margin: 30px 0; }
            
            .gvs-expandable-text { font-size: 16px; line-height: 1.6; color: #475569; white-space: pre-wrap; display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; transition: max-height 0.3s ease; }
            .gvs-expandable-text.expanded { -webkit-line-clamp: initial; display: block; overflow: visible; }
            
            .gvs-expandable-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; max-height: 140px; overflow: hidden; transition: max-height 0.3s ease; }
            .gvs-expandable-grid.expanded { max-height: 2000px; }

            .gvs-show-all-btn { background: transparent; color: <?php echo esc_attr($btn_color); ?>; border: none; font-weight: 600; font-size: 15px; cursor: pointer; padding: 0; margin-top: 15px; display: inline-flex; align-items: center; gap: 4px; }
            .gvs-show-all-btn:hover { text-decoration: underline; }

            .gvs-unit-features { display: flex; flex-wrap: wrap; gap: 30px; margin-bottom: 30px; }
            .gvs-unit-feature-item { display: flex; flex-direction: column; align-items: flex-start; gap: 8px; }
            .gvs-unit-feature-item i { font-size: 28px; color: #475569; }
            .gvs-unit-feature-text { font-size: 15px; font-weight: 500; color: #0f172a; }

            .gvs-unit-am-item { display: flex; align-items: center; gap: 12px; font-size: 15px; color: #334155; }
            .gvs-unit-am-item i { font-size: 24px; color: #64748b; }

            .gvs-unit-map-wrapper { width: 100%; height: 350px; border-radius: 12px; overflow: hidden; margin-top: 15px; border: 1px solid #e2e8f0; }

            .gvs-booking-widget { position: sticky; top: 100px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
            
            .gvs-bw-input-wrap { border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 16px; overflow: hidden; }
            .gvs-bw-dates { display: flex; border-bottom: 1px solid #cbd5e1; }
            .gvs-bw-date-box { flex: 1; padding: 12px; cursor: pointer; }
            .gvs-bw-date-box:first-child { border-right: 1px solid #cbd5e1; }
            .gvs-bw-label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #0f172a; margin-bottom: 4px; display: block; }
            .gvs-bw-input { width: 100%; border: none; font-size: 15px; outline: none; background: transparent; padding: 0; color: #475569; cursor: pointer; height: 20px;}
            
            .gvs-bw-guests { padding: 12px; }
            
            .gvs-quote-breakdown { display: none; margin-bottom: 20px; animation: gvs-fade-in 0.3s ease; position: relative; }
            .gvs-quote-loader-overlay { position: absolute; top:0; left:0; width:100%; height:100%; background: rgba(255,255,255,0.7); z-index: 10; display: none; align-items: center; justify-content: center; border-radius: 8px; backdrop-filter: blur(2px); }
            
            .gvs-quote-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
            .gvs-quote-item { display: flex; flex-direction: column; }
            .gvs-quote-item span { font-size: 13px; color: #64748b; margin-bottom: 2px; }
            .gvs-quote-item strong { font-size: 14px; color: #0f172a; font-weight: 700; }
            
            .gvs-quote-divider { height: 1px; background: #e2e8f0; width: 100%; margin: 15px 0; }
            
            .gvs-coupon-header { display: flex; justify-content: space-between; align-items: center; cursor: pointer; color: #0f172a; font-weight: 600; font-size: 15px; }
            .gvs-coupon-header svg { width: 12px; height: 12px; transition: transform 0.2s; }
            .gvs-coupon-content { display: none; padding-top: 15px; }
            .gvs-coupon-input-wrap { display: flex; align-items: center; border: 1px solid #cbd5e1; border-radius: 6px; overflow: hidden; }
            .gvs-coupon-input { flex-grow: 1; border: none; padding: 10px; outline: none; font-size: 14px; }
            .gvs-coupon-btn { background: #f1f5f9; border: none; border-left: 1px solid #cbd5e1; padding: 10px 15px; font-weight: 600; cursor: pointer; color: #0f172a; transition: background 0.2s; }
            .gvs-coupon-btn:hover { background: #e2e8f0; }
            
            .gvs-quote-line { display: flex; justify-content: space-between; align-items: center; font-size: 14px; color: #334155; }
            .gvs-quote-line strong { color: #0f172a; font-weight: 600; }
            .gvs-quote-total { display: flex; justify-content: space-between; align-items: center; font-size: 18px; font-weight: 700; color: #0f172a; margin-top: 5px; }
            
            .gvs-quote-accordion-wrapper { margin-bottom: 8px; }
            .gvs-quote-accordion-header { margin-bottom: 0; }
            .gvs-quote-accordion-header:hover span { color: #0f172a; }
            .gvs-quote-accordion-content { display: none; padding-bottom: 8px; }
            .gvs-quote-accordion-wrapper.open .gvs-quote-accordion-content { display: block; animation: gvs-fade-in 0.2s ease; }
            .gvs-quote-accordion-wrapper.open .gvs-quote-accordion-header svg { transform: rotate(180deg); }
            .gvs-quote-subline { display: flex; justify-content: space-between; font-size: 13px; color: #64748b; margin-top: 8px; padding-left: 10px; }
            
            .gvs-bw-btn { width: 100%; background: <?php echo esc_attr($btn_color); ?>; color: #fff; border: none; border-radius: 8px; padding: 14px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
            .gvs-bw-btn:not(:disabled):hover { opacity: 0.9; }
            
            .flatpickr-day.flatpickr-disabled, .flatpickr-day.flatpickr-disabled:hover { color: #9ca3af !important; background: #111827 !important; border-color: #111827 !important; cursor: not-allowed !important; text-decoration: line-through; }

            @keyframes gvs-fade-in { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
            <?php echo $unit_additional_css; ?>
        </style>

        <div class="gvs-unit-wrap">
            <div class="gvs-unit-slider-wrapper">
                <div class="swiper swiper-main">
                    <div class="swiper-wrapper">
                        <?php foreach($images as $img): ?><div class="swiper-slide"><img src="<?php echo esc_url($img); ?>" alt="Property Image" loading="lazy" /></div><?php endforeach; ?>
                    </div>
                    <div class="swiper-button-next"></div><div class="swiper-button-prev"></div>
                </div>
                <div class="swiper swiper-thumbs">
                    <div class="swiper-wrapper">
                        <?php foreach($images as $img): ?><div class="swiper-slide"><img src="<?php echo esc_url($img); ?>" alt="Thumbnail" loading="lazy" /></div><?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="gvs-unit-content-grid">
                <div class="gvs-unit-main">
                    <div class="gvs-unit-breadcrumbs"><a href="/">Home</a> <i class="ph ph-caret-right"></i> <?php echo esc_html($property['title']); ?></div>
                    <h1 class="gvs-unit-title"><?php echo esc_html($property['title']); ?></h1>
                    
                    <h3 class="gvs-unit-section-title" style="margin-top: 20px;">Description</h3>
                    <div class="gvs-expandable-text" id="gvs-desc-content"><?php echo !empty($property['description']) ? esc_html($property['description']) : 'A beautiful place to stay in ' . esc_html($property['city']) . '.'; ?></div>
                    <button class="gvs-show-all-btn" onclick="toggleExpand('gvs-desc-content', this)">Show all</button>

                    <?php if ($show_times): ?>
                    <div style="margin-top: 30px;">
                        <h4 style="font-size: 16px; font-weight: 700; color: #0f172a; margin: 0 0 8px 0;">Check in and out</h4>
                        <div style="font-size: 15px; color: #475569; margin-bottom: 4px;">Check in: <?php echo isset($property['checkInTime']) ? esc_html($property['checkInTime']) : '04:00 PM'; ?></div>
                        <div style="font-size: 15px; color: #475569;">Check out: <?php echo isset($property['checkOutTime']) ? esc_html($property['checkOutTime']) : '10:00 AM'; ?></div>
                    </div>
                    <?php endif; ?>

                    <div class="gvs-unit-divider"></div>
                    <h3 class="gvs-unit-section-title">Property Features</h3>
                    <div class="gvs-unit-features">
                        <div class="gvs-unit-feature-item"><i class="ph ph-bed"></i><div class="gvs-unit-feature-text"><?php echo (int)$property['bedrooms']; ?> Bedrooms</div></div>
                        <div class="gvs-unit-feature-item"><i class="ph ph-users"></i><div class="gvs-unit-feature-text"><?php echo (int)$property['accommodates']; ?> Guests</div></div>
                        <div class="gvs-unit-feature-item"><i class="ph ph-bathtub"></i><div class="gvs-unit-feature-text"><?php echo (float)$property['bathrooms']; ?> Bathrooms</div></div>
                    </div>

                    <div class="gvs-unit-divider"></div>
                    <?php if (!empty($property['raw_amenities'])): ?>
                    <h3 class="gvs-unit-section-title">Amenities</h3>
                    <div class="gvs-expandable-grid" id="gvs-am-content">
                        <?php 
                        if (class_exists('Guesty_ALC_API')) {
                            $api_engine = new Guesty_ALC_API();
                            foreach ($property['raw_amenities'] as $am) echo '<div class="gvs-unit-am-item"><i class="ph '.esc_attr($api_engine->get_default_icon_class_for_amenity($am)).'"></i> '.esc_html($am).'</div>';
                        } else {
                            foreach ($property['raw_amenities'] as $am) echo '<div class="gvs-unit-am-item"><i class="ph ph-star"></i> '.esc_html($am).'</div>';
                        }
                        ?>
                    </div>
                    <?php if (count($property['raw_amenities']) > 6): ?>
                        <button class="gvs-show-all-btn" onclick="toggleExpand('gvs-am-content', this)">Show all</button>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($show_map && !empty($property['city']) && !empty($property['country'])): ?>
                        <div class="gvs-unit-divider"></div>
                        <h3 class="gvs-unit-section-title">Location</h3>
                        <div class="gvs-unit-map-wrapper">
                            <iframe width="100%" height="100%" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://maps.google.com/maps?q=<?php echo urlencode(trim($property['city']) . ', ' . trim($property['country'])); ?>&t=&z=13&ie=UTF8&iwloc=&output=embed"></iframe>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="gvs-unit-sidebar">
                    <div class="gvs-booking-widget">
                        <div style="font-size: 22px; font-weight: 700; color: #0f172a; margin-bottom: 20px; line-height: 1.2;"><?php echo esc_html($property['title']); ?></div>
                        
                        <div class="gvs-bw-input-wrap">
                            <div class="gvs-bw-dates" id="gvs-unit-date-trigger">
                                <div class="gvs-bw-date-box"><span class="gvs-bw-label">Check-in</span><input type="text" id="gvs-unit-checkin" class="gvs-bw-input" placeholder="Add dates" readonly></div>
                                <div class="gvs-bw-date-box"><span class="gvs-bw-label">Check-out</span><input type="text" id="gvs-unit-checkout" class="gvs-bw-input" placeholder="Add dates" readonly></div>
                            </div>
                            <div class="gvs-bw-guests">
                                <span class="gvs-bw-label">Guests</span>
                                <select id="gvs-unit-guests" class="gvs-bw-input">
                                    <?php for($i=1; $i<=$max_guests; $i++) echo "<option value='$i'>$i " . ($i == 1 ? 'guest' : 'guests') . "</option>"; ?>
                                    <?php if ($max_guests == 15) echo '<option value="16">15+ guests</option>'; ?>
                                </select>
                            </div>
                        </div>

                        <div id="gvs-quote-breakdown" class="gvs-quote-breakdown">
                            <div class="gvs-quote-loader-overlay" id="gvs-quote-loader-overlay">
                                <svg class="gvs-bw-loader" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="<?php echo esc_attr($btn_color); ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation: spin 1s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                            </div>

                            <div class="gvs-quote-grid">
                                <div class="gvs-quote-item"><span>Check In</span><strong id="gvs-q-in">-</strong></div>
                                <div class="gvs-quote-item"><span>Check Out</span><strong id="gvs-q-out">-</strong></div>
                                <div class="gvs-quote-item"><span>Nights</span><strong id="gvs-q-nights">-</strong></div>
                                <div class="gvs-quote-item"><span>Guests</span><strong id="gvs-q-guests">1</strong></div>
                            </div>
                            <div class="gvs-quote-divider"></div>
                            <div>
                                <div class="gvs-coupon-header" id="gvs-coupon-trigger">I have a coupon <svg id="gvs-coupon-arrow" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></div>
                                <div class="gvs-coupon-content" id="gvs-coupon-content">
                                    <div class="gvs-coupon-input-wrap">
                                        <input type="text" id="gvs-coupon-input" class="gvs-coupon-input" placeholder="Coupon code">
                                        <button type="button" id="gvs-coupon-apply" class="gvs-coupon-btn">Apply</button>
                                    </div>
                                </div>
                            </div>
                            <div class="gvs-quote-divider"></div>

                            <div class="gvs-quote-line" style="margin-bottom: 8px;"><span>Subtotal</span><strong id="gvs-q-sub">-</strong></div>
                            
                            <div class="gvs-quote-accordion-wrapper" id="gvs-q-fees-wrap" style="display:none;">
                                <div class="gvs-quote-line gvs-quote-accordion-header" onclick="this.parentElement.classList.toggle('open')">
                                    <span style="display:flex; align-items:center; cursor:pointer;">Fees <svg style="width:14px; margin-left:4px; transition:transform 0.2s;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg></span>
                                    <strong id="gvs-q-fees">-</strong>
                                </div>
                                <div class="gvs-quote-accordion-content" id="gvs-q-fees-list"></div>
                            </div>
                            
                            <div class="gvs-quote-line" id="gvs-q-pretax-row" style="margin-bottom: 8px;"><span>Subtotal before taxes</span><strong id="gvs-q-pretax">-</strong></div>
                            <div class="gvs-quote-divider" id="gvs-q-pretax-div"></div>
                            
                            <div class="gvs-quote-accordion-wrapper" id="gvs-q-taxes-wrap" style="display:none;">
                                <div class="gvs-quote-line gvs-quote-accordion-header" onclick="this.parentElement.classList.toggle('open')">
                                    <span style="display:flex; align-items:center; cursor:pointer;">Taxes <svg style="width:14px; margin-left:4px; transition:transform 0.2s;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg></span>
                                    <strong id="gvs-q-taxes">-</strong>
                                </div>
                                <div class="gvs-quote-accordion-content" id="gvs-q-taxes-list"></div>
                            </div>
                            <div class="gvs-quote-divider" id="gvs-q-taxes-div"></div>

                            <div class="gvs-quote-total"><span>Total</span><strong id="gvs-q-tot">-</strong></div>
                        </div>
                        
                        <div id="gvs-bw-error" class="gvs-bw-error">Please select valid Check-in and Check-out dates.</div>
                        <button type="button" id="gvs-unit-book-btn" class="gvs-bw-btn" disabled style="background: #cbd5e1; cursor: not-allowed; opacity: 1;"><span class="gvs-bw-btn-text">Book</span></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lightbox Overlay -->
        <div id="gvs-lightbox" class="gvs-lightbox">
            <span class="gvs-lightbox-close">&times;</span>
            <span class="gvs-lightbox-nav gvs-lightbox-prev"><i class="ph ph-caret-left"></i></span>
            <img class="gvs-lightbox-content" id="gvs-lightbox-img" src="" alt="Full Screen Property Image">
            <span class="gvs-lightbox-nav gvs-lightbox-next"><i class="ph ph-caret-right"></i></span>
        </div>

        <script>
            function toggleExpand(id, btn) {
                const el = document.getElementById(id);
                el.classList.toggle('expanded');
                btn.innerHTML = el.classList.contains('expanded') ? 'Show less' : 'Show all';
            }

            document.addEventListener('DOMContentLoaded', () => {
                const maxThumbs = <?php echo esc_js($thumb_count); ?>;
                const allGalleryImages = <?php echo json_encode($images); ?>;
                let currentLightboxIndex = 0;

                var swiperThumbs = new Swiper(".swiper-thumbs", {
                    spaceBetween: 10, slidesPerView: 4, freeMode: true, watchSlidesProgress: true,
                    breakpoints: { 640: { slidesPerView: 5 }, 1024: { slidesPerView: Math.min(6, maxThumbs) }, 1200: { slidesPerView: maxThumbs } }
                });
                var swiperMain = new Swiper(".swiper-main", {
                    spaceBetween: 10, navigation: { nextEl: ".swiper-button-next", prevEl: ".swiper-button-prev" }, thumbs: { swiper: swiperThumbs }
                });

                const lightbox = document.getElementById('gvs-lightbox');
                const lightboxImg = document.getElementById('gvs-lightbox-img');
                const closeBtn = document.querySelector('.gvs-lightbox-close');
                const prevBtn = document.querySelector('.gvs-lightbox-prev');
                const nextBtn = document.querySelector('.gvs-lightbox-next');

                function updateLightboxImage(index) {
                    if (index >= allGalleryImages.length) currentLightboxIndex = 0;
                    else if (index < 0) currentLightboxIndex = allGalleryImages.length - 1;
                    else currentLightboxIndex = index;
                    lightboxImg.src = allGalleryImages[currentLightboxIndex];
                }

                document.querySelectorAll('.swiper-main .swiper-slide img').forEach((img, index) => {
                    img.addEventListener('click', function() {
                        currentLightboxIndex = index;
                        updateLightboxImage(currentLightboxIndex);
                        lightbox.classList.add('active');
                        document.body.style.overflow = 'hidden'; 
                    });
                });

                function closeLightbox() {
                    lightbox.classList.remove('active');
                    setTimeout(() => lightboxImg.src = '', 300); 
                    document.body.style.overflow = '';
                }

                closeBtn.addEventListener('click', closeLightbox);
                prevBtn.addEventListener('click', (e) => { e.stopPropagation(); updateLightboxImage(currentLightboxIndex - 1); });
                nextBtn.addEventListener('click', (e) => { e.stopPropagation(); updateLightboxImage(currentLightboxIndex + 1); });
                lightbox.addEventListener('click', (e) => { if (e.target === lightbox) closeLightbox(); });

                document.addEventListener('keydown', (e) => {
                    if (!lightbox.classList.contains('active')) return;
                    if (e.key === 'Escape') closeLightbox();
                    if (e.key === 'ArrowLeft') updateLightboxImage(currentLightboxIndex - 1);
                    if (e.key === 'ArrowRight') updateLightboxImage(currentLightboxIndex + 1);
                });
            });

            function loadUnitFlatpickr(callback) {
                if (window.flatpickr) { callback(); return; }
                const link = document.createElement('link'); link.rel = 'stylesheet'; link.href = 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css'; document.head.appendChild(link);
                const theme = document.createElement('style');
                theme.innerHTML = `
                    .flatpickr-calendar { font-family: inherit !important; border-radius: 12px !important; box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important; border: 1px solid #e2e8f0 !important; padding: 24px !important; background: #fff !important; width: auto !important; }
                    .flatpickr-months { margin-bottom: 20px !important; position: relative !important; display: flex !important; gap: 24px !important; align-items: center !important; }
                    .flatpickr-month { width: 280px !important; display: flex !important; align-items: center !important; justify-content: center !important; }
                    .flatpickr-months .flatpickr-prev-month, .flatpickr-months .flatpickr-next-month { border: 1px solid #cbd5e1 !important; border-radius: 6px !important; height: 32px !important; width: 32px !important; top: 50% !important; transform: translateY(-50%) !important; display: flex !important; align-items: center !important; justify-content: center !important; padding: 0 !important; color: #475569 !important; fill: #475569 !important; position: absolute !important; z-index: 10; }
                    .flatpickr-months .flatpickr-prev-month:hover, .flatpickr-months .flatpickr-next-month:hover { background: #f8fafc !important; }
                    .flatpickr-months .flatpickr-prev-month { left: 0px !important; } .flatpickr-months .flatpickr-next-month { right: 0px !important; }
                    .flatpickr-current-month { font-size: 15px !important; font-weight: 600 !important; color: #1e293b !important; position: static !important; width: auto !important; padding: 0 !important; height: auto !important; display: inline-block !important; }
                    .flatpickr-innerContainer { overflow: visible !important; }
                    .flatpickr-weekdays { display: flex !important; gap: 24px !important; width: auto !important; }
                    .flatpickr-weekdaycontainer { display: grid !important; grid-template-columns: repeat(7, 40px) !important; width: 280px !important; padding: 0 0 8px 0 !important; }
                    .flatpickr-weekday { color: #64748b !important; font-weight: 600 !important; font-size: 13px !important; text-align: center !important; width: 40px !important; flex: none !important; margin: 0 !important; }
                    .flatpickr-days { display: flex !important; gap: 24px !important; width: auto !important; border: none !important; }
                    .dayContainer { width: 280px !important; min-width: 280px !important; max-width: 280px !important; display: grid !important; grid-template-columns: repeat(7, 40px) !important; box-shadow: none !important; padding: 0 !important; }
                    .flatpickr-day { border-radius: 0 !important; color: #334155 !important; font-weight: 500 !important; height: 40px !important; line-height: 40px !important; width: 40px !important; max-width: 40px !important; border: none !important; margin: 0 !important; margin-top: 2px !important; box-sizing: border-box !important; display: flex !important; align-items: center !important; justify-content: center !important; }
                    .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange, .flatpickr-day.selected.inRange, .flatpickr-day.startRange.inRange, .flatpickr-day.endRange.inRange { background: var(--gvs-btn-color, <?php echo esc_attr($btn_color); ?>) !important; border-color: var(--gvs-btn-color, <?php echo esc_attr($btn_color); ?>) !important; color: #fff !important; box-shadow: none !important; }
                    .flatpickr-day.inRange, .flatpickr-day.prevMonthDay.inRange, .flatpickr-day.nextMonthDay.inRange, .flatpickr-day.today.inRange { background: #f1f5f9 !important; border-color: #f1f5f9 !important; box-shadow: -5px 0 0 #f1f5f9, 5px 0 0 #f1f5f9 !important; }
                    .flatpickr-day:hover { background: #e2e8f0 !important; color: #1e293b !important; }
                `;
                document.head.appendChild(theme);
                const script = document.createElement('script'); script.id = 'flatpickr-script'; script.src = 'https://cdn.jsdelivr.net/npm/flatpickr'; script.onload = callback; document.head.appendChild(script);
            }

            document.addEventListener('DOMContentLoaded', () => {
                const trigger = document.getElementById('gvs-unit-date-trigger');
                const checkin = document.getElementById('gvs-unit-checkin');
                const checkout = document.getElementById('gvs-unit-checkout');
                const guests = document.getElementById('gvs-unit-guests');
                
                const btn = document.getElementById('gvs-unit-book-btn');
                const errorMsg = document.getElementById('gvs-bw-error');

                const quoteBox = document.getElementById('gvs-quote-breakdown');
                const quoteLoader = document.getElementById('gvs-quote-loader-overlay');
                
                const qIn = document.getElementById('gvs-q-in');
                const qOut = document.getElementById('gvs-q-out');
                const qNights = document.getElementById('gvs-q-nights');
                const qGuests = document.getElementById('gvs-q-guests');
                const qSub = document.getElementById('gvs-q-sub');
                
                const qPretax = document.getElementById('gvs-q-pretax');
                const qTotal = document.getElementById('gvs-q-tot');

                const couponTrigger = document.getElementById('gvs-coupon-trigger');
                const couponContent = document.getElementById('gvs-coupon-content');
                const couponArrow = document.getElementById('gvs-coupon-arrow');
                const couponInput = document.getElementById('gvs-coupon-input');
                const couponApply = document.getElementById('gvs-coupon-apply');

                const primaryBtnColor = '<?php echo esc_js($btn_color); ?>';

                const urlParams = new URLSearchParams(window.location.search);
                const preCheckin = urlParams.get('gvs_checkin');
                const preCheckout = urlParams.get('gvs_checkout');
                const preGuests = urlParams.get('gvs_guests');
                
                if (preGuests) guests.value = preGuests;

                let fp = null;
                let disabledDatesArray = [];

                const fpAnchor = document.createElement('input');
                fpAnchor.type = 'text'; fpAnchor.style.position = 'absolute'; fpAnchor.style.visibility = 'hidden'; fpAnchor.style.width = '0'; fpAnchor.style.height = '0';
                checkin.parentNode.appendChild(fpAnchor);

                function formatDateStr(dStr) {
                    const d = new Date(dStr + 'T00:00:00'); 
                    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                }

                function formatMoney(amount, curr) {
                    return new Intl.NumberFormat(undefined, { style: 'currency', currency: curr || 'CAD', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount);
                }
                
                function buildSubline(name, amt, curr) {
                    return `<div class="gvs-quote-subline"><span>${name}</span><span>${formatMoney(amt, curr)}</span></div>`;
                }

                function disableBookBtn() {
                    btn.disabled = true;
                    btn.style.background = '#cbd5e1';
                    btn.style.cursor = 'not-allowed';
                    quoteBox.style.display = 'none';
                }

                const fetchQuoteData = async () => {
                    if (!checkin.value || !checkout.value) { disableBookBtn(); return; }

                    const d1 = new Date(checkin.value + 'T00:00:00');
                    const d2 = new Date(checkout.value + 'T00:00:00');
                    const nights = Math.round((d2 - d1) / (1000 * 60 * 60 * 24));
                    
                    qIn.innerText = formatDateStr(checkin.value);
                    qOut.innerText = formatDateStr(checkout.value);
                    qNights.innerText = nights + (nights === 1 ? ' Night' : ' Nights');
                    qGuests.innerText = guests.value;

                    quoteBox.style.display = 'block';
                    quoteLoader.style.display = 'flex';
                    
                    btn.disabled = true;
                    btn.style.background = '#cbd5e1';
                    btn.style.cursor = 'not-allowed';

                    const formData = new URLSearchParams();
                    formData.append('action', 'guesty_get_unit_quote');
                    formData.append('nonce', '<?php echo wp_create_nonce("guesty_unit_ajax_nonce"); ?>');
                    formData.append('unit_id', '<?php echo esc_js($property['id']); ?>');
                    formData.append('check_in', checkin.value);
                    formData.append('check_out', checkout.value);
                    formData.append('guests', guests.value);
                    if (couponInput.value.trim() !== '') formData.append('coupon', couponInput.value.trim());

                    try {
                        const response = await fetch("<?php echo admin_url('admin-ajax.php'); ?>", { method: 'POST', body: formData, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
                        const result = await response.json();
                        
                        if (result.success && result.data && result.data.quote) {
                            const q = result.data.quote;
                            const curr = q.currency;
                            
                            qSub.innerText = formatMoney(q.subtotal, curr);
                            
                            if (q.fees > 0) {
                                document.getElementById('gvs-q-fees-wrap').style.display = 'block';
                                document.getElementById('gvs-q-fees').innerText = formatMoney(q.fees, curr);
                                let feeHTML = '';
                                if (q.fees_list && q.fees_list.length > 0) {
                                    q.fees_list.forEach(f => { feeHTML += buildSubline(f.name, f.amount, curr); });
                                }
                                document.getElementById('gvs-q-fees-list').innerHTML = feeHTML;
                            } else {
                                document.getElementById('gvs-q-fees-wrap').style.display = 'none';
                            }
                            
                            if (q.taxes > 0) {
                                document.getElementById('gvs-q-pretax-row').style.display = 'flex';
                                document.getElementById('gvs-q-pretax-div').style.display = 'block';
                                document.getElementById('gvs-q-taxes-wrap').style.display = 'block';
                                document.getElementById('gvs-q-taxes-div').style.display = 'block';
                                
                                qPretax.innerText = formatMoney(q.subtotal + q.fees, curr);
                                document.getElementById('gvs-q-taxes').innerText = formatMoney(q.taxes, curr);
                                
                                let taxHTML = '';
                                if (q.taxes_list && q.taxes_list.length > 0) {
                                    q.taxes_list.forEach(t => { taxHTML += buildSubline(t.name, t.amount, curr); });
                                }
                                document.getElementById('gvs-q-taxes-list').innerHTML = taxHTML;
                            } else {
                                document.getElementById('gvs-q-pretax-row').style.display = 'none';
                                document.getElementById('gvs-q-pretax-div').style.display = 'none';
                                document.getElementById('gvs-q-taxes-wrap').style.display = 'none';
                                document.getElementById('gvs-q-taxes-div').style.display = 'none';
                            }
                            
                            qTotal.innerText = formatMoney(q.total, curr);

                            btn.disabled = false;
                            btn.style.background = primaryBtnColor;
                            btn.style.cursor = 'pointer';
                        } else {
                            disableBookBtn();
                            let errMsg = "Failed to calculate total price for these dates. They may no longer be available.";
                            if (result.data && result.data.message) errMsg = result.data.message;
                            else if (result.message) errMsg = result.message;
                            alert(errMsg);
                        }
                    } catch (e) {
                        console.error('Quote AJAX Engine Error:', e);
                        disableBookBtn();
                    } finally {
                        quoteLoader.style.display = 'none';
                    }
                };

                const fetchCalendarData = async () => {
                    const formData = new URLSearchParams();
                    formData.append('action', 'guesty_get_unit_calendar');
                    formData.append('nonce', '<?php echo wp_create_nonce("guesty_unit_ajax_nonce"); ?>');
                    formData.append('unit_id', '<?php echo esc_js($property['id']); ?>');
                    formData.append('_cb', new Date().getTime());

                    try {
                        const response = await fetch("<?php echo admin_url('admin-ajax.php'); ?>", { method: 'POST', body: formData, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
                        const result = await response.json();
                        if (result.success && result.data && result.data.blocked_dates) disabledDatesArray = result.data.blocked_dates;
                    } catch (e) { console.error('Failed to load live calendar data.'); }

                    disableBookBtn();

                    loadUnitFlatpickr(() => {
                        fp = flatpickr(fpAnchor, {
                            mode: "range", minDate: "today", showMonths: window.innerWidth > 768 ? 2 : 1, dateFormat: "Y-m-d", disableMobile: true, positionElement: checkin, disable: disabledDatesArray,
                            onChange: function(selectedDates, dateStr, instance) {
                                errorMsg.style.display = 'none';
                                if (selectedDates.length === 1) {
                                    checkin.value = instance.formatDate(selectedDates[0], "Y-m-d"); checkout.value = ''; disableBookBtn();
                                } else if (selectedDates.length === 2) {
                                    checkin.value = instance.formatDate(selectedDates[0], "Y-m-d"); checkout.value = instance.formatDate(selectedDates[1], "Y-m-d"); fetchQuoteData(); 
                                } else {
                                    checkin.value = ''; checkout.value = ''; disableBookBtn();
                                }
                            }
                        });

                        if (preCheckin && preCheckout) { fp.setDate([preCheckin, preCheckout]); checkin.value = preCheckin; checkout.value = preCheckout; fetchQuoteData(); } 
                        else if (preCheckin) { fp.setDate(preCheckin); checkin.value = preCheckin; }

                        trigger.addEventListener('click', () => { if (!fp.isOpen) fp.open(); });
                    });
                };

                fetchCalendarData();

                guests.addEventListener('change', () => { if (checkin.value && checkout.value) fetchQuoteData(); });

                couponTrigger.addEventListener('click', () => {
                    const isClosed = couponContent.style.display === 'none' || couponContent.style.display === '';
                    couponContent.style.display = isClosed ? 'block' : 'none';
                    couponArrow.style.transform = isClosed ? 'rotate(180deg)' : 'rotate(0deg)';
                });

                couponApply.addEventListener('click', () => { if (checkin.value && checkout.value) fetchQuoteData(); });

                // Redirect to Booking Engine Checkout
                btn.addEventListener('click', () => {
                    if (btn.disabled) return;
                    
                    const baseCheckoutUrl = <?php echo json_encode($checkout_link); ?>;
                    if (baseCheckoutUrl === '#') { 
                        alert('Please configure the Booking Engine Base URL in the Unit Pages settings tab.'); 
                        return; 
                    }
                    
                    const params = new URLSearchParams();
                    
                    // Guesty's native widget utilizes both minOccupancy and guests parameters for accuracy
                    if (guests.value) {
                        params.append('minOccupancy', guests.value);
                        params.append('guests', guests.value);
                    }
                    
                    params.append('checkIn', checkin.value);
                    params.append('checkOut', checkout.value);
                    
                    if (couponInput.value.trim() !== '') {
                        params.append('promotionCode', couponInput.value.trim()); 
                    }
                    
                    const finalUrl = baseCheckoutUrl + (params.toString() ? '?' + params.toString() : '');
                    
                    // Seamless tab redirect directly to checkout processing
                    window.location.href = finalUrl;
                });
            });
        </script>
        <?php
    }
}

new Guesty_ALC_Unit_Pages();