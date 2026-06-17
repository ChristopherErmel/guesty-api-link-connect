<?php if (!defined('ABSPATH')) exit; ?>
<script src="https://unpkg.com/@phosphor-icons/web"></script>

<style>
    /* Admin UI Enhancements */
    #gvs-tab-docs:not(.button-primary) { background-color: #f0f6fc; border-color: #72aee6; color: #2271b1; }
    #gvs-tab-docs:hover:not(.button-primary) { background-color: #e6f0f9; border-color: #2271b1; color: #2271b1; }
    
    #gvs-tab-shortcode:not(.button-primary) { background-color: #f6f0fc; border-color: #b872e6; color: #8222b1; }
    #gvs-tab-shortcode:hover:not(.button-primary) { background-color: #f0e6f9; border-color: #8222b1; color: #8222b1; }
    
    .gvs-style-section-header {
        background: #f8f9fa;
        padding: 10px 15px;
        border-left: 4px solid #2271b1;
        margin-top: 30px;
        margin-bottom: 15px;
        font-size: 15px;
        color: #1d2327;
        border-radius: 0 4px 4px 0;
    }

    .gvs-doc-section { margin-bottom: 30px; padding-bottom: 25px; border-bottom: 1px solid #e5e7eb; }
    .gvs-doc-section h4 { font-size: 17px; margin-top: 0; color: #1d2327; margin-bottom: 12px; }
    .gvs-doc-section p { color: #50575e; font-size: 14px; line-height: 1.6; }
    .gvs-doc-section ul { list-style-type: disc; padding-left: 20px; color: #50575e; font-size: 14px; line-height: 1.6; }
    .gvs-doc-section li { margin-bottom: 8px; }
    .gvs-doc-section code { background: #f0f0f1; padding: 3px 6px; border-radius: 3px; font-size: 13px; color: #d63384; }
</style>

<div class="wrap">
    <h1>Guesty API Link Connect</h1>
    <p class="description" style="margin-bottom: 20px;">Use the tabs below to configure your properties, style your shortcode, and manage the live data cache.</p>

    <!-- TOP NAVIGATION ROW -->
    <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 20px; flex-wrap: wrap; padding-bottom: 20px; border-bottom: 1px solid #ccd0d4;">
        <button type="button" id="gvs-tab-api" class="button gvs-nav-tab <?php echo (!$show_data_panel) ? 'button-primary' : ''; ?>" onclick="gvsShowPanel('api')">Settings</button>
        <button type="button" id="gvs-tab-searchbar" class="button gvs-nav-tab" onclick="gvsShowPanel('searchbar')">Search Bar</button>
        <button type="button" id="gvs-tab-filters" class="button gvs-nav-tab" onclick="gvsShowPanel('filters')">Filters</button>
        <button type="button" id="gvs-tab-styles" class="button gvs-nav-tab" onclick="gvsShowPanel('styles')">Main Style Settings</button>
        <button type="button" id="gvs-tab-foryou" class="button gvs-nav-tab" onclick="gvsShowPanel('foryou')">For You Widget</button>
        <button type="button" id="gvs-tab-css" class="button gvs-nav-tab" onclick="gvsShowPanel('css')">Additional CSS</button>
        
        <?php if (defined('GUESTY_ALC_UNIT_PAGES_ACTIVE')): ?>
            <button type="button" id="gvs-tab-unitpages" class="button gvs-nav-tab" onclick="gvsShowPanel('unitpages')" style="background-color: #f0f6fc; border-color: #2271b1; color: #2271b1; font-weight: bold;">Unit Pages</button>
        <?php endif; ?>

        <span style="border-left: 1px solid #ccd0d4; height: 30px; margin: 0 5px;"></span>
        
        <form method="post" action="" style="margin:0;">
            <?php wp_nonce_field('guesty_admin_action', 'guesty_admin_nonce'); ?>
            <button type="submit" name="view_guesty_data" id="gvs-tab-data" class="button gvs-action-tab <?php echo ($active_tab === 'data') ? 'button-primary' : ''; ?>">Unit Management</button>
        </form>
        <form method="post" action="" style="margin:0;">
            <?php wp_nonce_field('guesty_admin_action', 'guesty_admin_nonce'); ?>
            <button type="submit" name="view_guesty_token" id="gvs-tab-token" class="button gvs-action-tab <?php echo ($active_tab === 'token') ? 'button-primary' : ''; ?>">API Token Info</button>
        </form>
        <form method="post" action="" style="margin:0;">
            <?php wp_nonce_field('guesty_admin_action', 'guesty_admin_nonce'); ?>
            <button type="submit" name="view_guesty_logs" id="gvs-tab-logs" class="button gvs-action-tab <?php echo ($active_tab === 'logs') ? 'button-primary' : ''; ?>">View Debug Logs</button>
        </form>
        
        <!-- Right Aligned Distinct Buttons -->
        <div style="margin-left: auto; display: flex; align-items: center; gap: 10px;">
            <button type="button" id="gvs-tab-shortcode" class="button gvs-nav-tab" style="display: flex; align-items: center; gap: 4px;" onclick="gvsShowPanel('shortcode')">
                <span class="dashicons dashicons-editor-code" style="margin-top: 3px;"></span> Shortcode Guide
            </button>

            <button type="button" id="gvs-tab-docs" class="button gvs-nav-tab" style="display: flex; align-items: center; gap: 4px;" onclick="gvsShowPanel('docs')">
                <span class="dashicons dashicons-book-alt" style="margin-top: 3px;"></span> Documentation
            </button>

            <form method="post" action="" style="margin:0;">
                <?php wp_nonce_field('guesty_admin_action', 'guesty_admin_nonce'); ?>
                <button type="submit" name="force_guesty_refresh" class="button" style="background-color: #16a34a; border-color: #158c3f; color: #fff; display: flex; align-items: center; gap: 4px;" title="Forces a fresh pull from Guesty">
                    <span class="dashicons dashicons-update" style="margin-top: 3px;"></span> Sync Guesty Data
                </button>
            </form>
        </div>
    </div>

    <!-- OPTIONS CONFIGURATION FORM -->
    <form id="gvs-options-form" method="post" action="options.php" style="<?php echo $show_data_panel ? 'display:none;' : 'display:block;'; ?>">
        <?php settings_fields('guesty-settings-group'); ?>

        <!-- Settings Panel -->
        <div id="gvs-panel-api" class="gvs-panel" style="display: block;">
            <div style="background: #fff; padding: 25px; border: 1px solid #ccd0d4; border-radius: 8px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="margin:0;">API Credentials</h3>
                    <?php submit_button('Save All Settings', 'primary', 'submit', false); ?>
                </div>
                <p class="description" style="margin-top: -5px;">Enter your Guesty Open API keys here to establish the connection.</p>
                <table class="form-table">
                    <tr valign="top"><th scope="row">Client ID</th><td><input type="text" name="guesty_client_id" value="<?php echo esc_attr(get_option('guesty_client_id')); ?>" style="width: 400px;" /></td></tr>
                    <tr valign="top"><th scope="row">Client Secret</th><td><input type="password" name="guesty_client_secret" value="<?php echo esc_attr(get_option('guesty_client_secret')); ?>" style="width: 400px;" /></td></tr>
                </table>

                <hr style="margin: 30px 0;">

                <h3 style="margin-top:0;">Guesty Widget Linking</h3>
                <p class="description">Control where the user is redirected when they interact with the grid or search bar.</p>
                <table class="form-table">
                    <?php if (defined('GUESTY_ALC_UNIT_PAGES_ACTIVE')): ?>
                    <tr valign="top">
                        <th scope="row">Property Base URL</th>
                        <td>
                            <div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 12px 15px; border-radius: 4px; max-width: 550px;">
                                <span class="dashicons dashicons-yes-alt" style="color: #16a34a;"></span> <strong style="color: #1d2327;">Unit Pages Add-on Activated!</strong>
                                <p class="description" style="margin-top: 5px;">Your property links are now automatically being routed to your local SEO-friendly WordPress pages. You can manage the URL slug and layout styles in the new <strong>"Unit Pages"</strong> tab above.</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <tr valign="top">
                        <th scope="row">Property Base URL</th>
                        <td>
                            <input type="url" name="guesty_base_url" value="<?php echo esc_url(get_option('guesty_base_url')); ?>" style="width: 400px;" placeholder="https://yourdomain.guestybookings.com/en/properties/" />
                            <p class="description">The specific listing ID is automatically appended to this URL when a user clicks "View Cottage".</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr valign="top">
                        <th scope="row">Search Results Page URL</th>
                        <td>
                            <input type="text" name="guesty_search_results_url" value="<?php echo esc_attr(get_option('guesty_search_results_url')); ?>" style="width: 400px;" placeholder="/vacationfinder/" />
                            <p class="description">If you use a "Search Bar Only" shortcode, users will be redirected to this page to see the results. Make sure a full property grid shortcode is on that destination page!</p>
                        </td>
                    </tr>
                </table>

                <hr style="margin: 30px 0;">

                <h3 style="margin-top:0;">Pricing Display Options</h3>
                <p class="description">Control exactly how the price text and currency behaves on the property cards.</p>
                <table class="form-table">
                    <tr valign="top"><th scope="row">Price Sub-Label</th><td><input type="text" name="guesty_price_label" value="<?php echo esc_attr(get_option('guesty_price_label', 'base price')); ?>" style="width: 200px;" placeholder="e.g. / night" /></td></tr>
                    <tr valign="top"><th scope="row">Currency Display</th>
                        <td>
                            <select name="guesty_currency_display" style="width: 200px;">
                                <?php 
                                $curr_opt = get_option('guesty_currency_display', 'auto');
                                $currencies = ['auto' => 'Auto (From API)', 'CAD' => 'CAD (Canadian Dollar)', 'USD' => 'USD (US Dollar)', 'EUR' => 'EUR (Euro)', 'GBP' => 'GBP (British Pound)', 'AUD' => 'AUD (Australian Dollar)', 'hidden' => 'Hide Currency Code'];
                                foreach($currencies as $k => $v) echo "<option value='$k' ".selected($curr_opt, $k, false).">$v</option>";
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <hr style="margin: 30px 0;">
                
                <h3 style="margin-top:0;">Live Availability & Dynamic Pricing</h3>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Dynamic Pricing</th>
                        <td><label><input type="checkbox" name="guesty_dynamic_pricing" value="yes" <?php checked(get_option('guesty_dynamic_pricing', 'no'), 'yes'); ?> /> <strong>Change the shown unit price to the exact total for the selected dates.</strong></label></td>
                    </tr>
                </table>

                <hr style="margin: 30px 0;">

                <h3 style="margin-top:0;">Cache & Performance</h3>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Cache Duration</th>
                        <td>
                            <input type="number" name="guesty_cache_time_value" value="<?php echo esc_attr(get_option('guesty_cache_time_value', '2')); ?>" style="width: 80px; margin-right: 10px;" min="1" />
                            <select name="guesty_cache_time_unit" style="width: 110px;">
                                <option value="minutes" <?php selected(get_option('guesty_cache_time_unit', 'hours'), 'minutes'); ?>>Minutes</option>
                                <option value="hours" <?php selected(get_option('guesty_cache_time_unit', 'hours'), 'hours'); ?>>Hours</option>
                                <option value="days" <?php selected(get_option('guesty_cache_time_unit', 'hours'), 'days'); ?>>Days</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Enable AJAX Loading</th>
                        <td><label><input type="checkbox" name="guesty_enable_ajax" value="yes" <?php checked(get_option('guesty_enable_ajax', 'no'), 'yes'); ?> /> <strong>Use AJAX to fetch properties dynamically. <span style="color: #16a34a;">(Highly recommended for 50+ properties)</span></strong></label></td>
                    </tr>
                </table>

                <div style="margin-top:25px; display: flex; justify-content: flex-end;"><?php submit_button('Save All Settings', 'primary', 'submit', false); ?></div>
            </div>

            <div style="background: #fff; padding: 25px; border: 1px solid #ccd0d4; border-radius: 8px; border-left: 4px solid #dc3232;">
                <h3 style="margin-top:0; color: #dc3232;">Clear Cached Data</h3>
                <div style="margin-top:15px; display: flex; justify-content: flex-end;">
                    <button type="submit" name="clear_guesty_data" formaction="" formmethod="post" class="button" style="color: #dc3232; border-color: #dc3232;" onclick="return confirm('Are you sure you want to completely wipe the cached property data?');">Clear Guesty Data</button>
                </div>
            </div>
        </div>

        <!-- Search Bar Panel -->
        <div id="gvs-panel-searchbar" class="gvs-panel" style="display: none; background: #fff; padding: 25px; border: 1px solid #ccd0d4; border-radius: 8px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h3 style="margin:0;">Search Bar Settings</h3>
                <?php submit_button('Save Search Settings', 'primary', 'submit', false); ?>
            </div>
            
            <p class="description" style="font-size: 14px; margin-bottom: 20px;">
                <strong>Note:</strong> To display the search bar on your website, you must turn it on via the shortcode! Use the <strong>Shortcode Guide</strong> tab to generate your specific shortcode.
            </p>

            <h4 style="border-bottom: 1px solid #eee; padding-bottom: 5px;">Displayed Fields</h4>
            <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 25px;">
                <label style="display:inline-block; width:150px;"><input type="checkbox" name="guesty_search_field_location" value="yes" <?php checked(get_option('guesty_search_field_location', 'yes'), 'yes'); ?>> Location</label>
                <label style="display:inline-block; width:150px;"><input type="checkbox" name="guesty_search_field_dates" value="yes" <?php checked(get_option('guesty_search_field_dates', 'yes'), 'yes'); ?>> Check-in / out</label>
                <label style="display:inline-block; width:150px;"><input type="checkbox" name="guesty_search_field_guests" value="yes" <?php checked(get_option('guesty_search_field_guests', 'yes'), 'yes'); ?>> Guests</label>
                <label style="display:inline-block; width:150px;"><input type="checkbox" name="guesty_search_field_bedrooms" value="yes" <?php checked(get_option('guesty_search_field_bedrooms', 'no'), 'yes'); ?>> Bedrooms</label>
                <label style="display:inline-block; width:150px;"><input type="checkbox" name="guesty_search_field_amenity" value="yes" <?php checked(get_option('guesty_search_field_amenity', 'yes'), 'yes'); ?>> Amenity Dropdown</label>
                <label style="display:inline-block; width:150px;"><input type="checkbox" name="guesty_search_field_pets" value="yes" <?php checked(get_option('guesty_search_field_pets', 'no'), 'yes'); ?>> Pet Friendly</label>
            </div>

            <h4 style="border-bottom: 1px solid #eee; padding-bottom: 5px;">Search Bar Amenities Dropdown</h4>
            <?php
            $all_amenities = get_transient('guesty_all_amenities');
            $search_active_amenities = get_option('guesty_search_active_amenities', []);
            if (!is_array($search_active_amenities)) $search_active_amenities = [];

            if (empty($all_amenities)) {
                echo '<div style="padding: 15px; background: #f9f9f9; border-left: 4px solid #f59e0b;">No amenities found yet. Please sync Guesty data.</div>';
            } else {
                echo '<div style="max-height: 400px; overflow-y: auto; border: 1px solid #e5e7eb; padding: 15px; background: #fafafa; border-radius: 4px; display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 12px;">';
                foreach ($all_amenities as $amenity) {
                    $checked = in_array($amenity, $search_active_amenities) ? 'checked' : '';
                    echo '<div style="display: flex; align-items: center; background: #fff; border: 1px solid #ddd; padding: 8px 12px; border-radius: 6px;"><input type="checkbox" name="guesty_search_active_amenities[]" value="' . esc_attr($amenity) . '" ' . $checked . ' style="margin-right:10px;"> <span style="flex-grow: 1; font-size: 13px; font-weight: 500;">' . esc_html($amenity) . '</span></div>';
                }
                echo '</div>';
            }
            ?>
            <div style="margin-top:20px; display: flex; justify-content: flex-end;"><?php submit_button('Save Search Settings', 'primary', 'submit', false); ?></div>
        </div>

        <!-- Filters Panel -->
        <div id="gvs-panel-filters" class="gvs-panel" style="display: none; background: #fff; padding: 25px; border: 1px solid #ccd0d4; border-radius: 8px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h3 style="margin:0;">Main "All Properties" Tab</h3>
                <?php submit_button('Save Filter Settings', 'primary', 'submit', false); ?>
            </div>
            <div style="display: flex; align-items: center; background: #fff; border: 1px solid #2271b1; padding: 12px; border-radius: 6px; margin-bottom: 30px; max-width: 500px;">
                <?php $all_tab_text = get_option('guesty_all_tab_text', 'All'); $all_tab_icon = get_option('guesty_all_tab_icon', 'ph-house'); ?>
                <span style="display: flex; justify-content: center; align-items: center; width: 40px; height: 40px; background: #f0f6fc; border-radius: 6px; margin-right: 15px;"><i class="ph <?php echo esc_attr($all_tab_icon); ?>" id="preview-all-tab" style="font-size: 24px; color: #2271b1;"></i></span>
                <div style="flex-grow: 1;"><input type="text" name="guesty_all_tab_text" value="<?php echo esc_attr($all_tab_text); ?>" style="width: 100%; font-weight: bold;"></div>
                <button type="button" class="button button-secondary" style="margin-left: 15px;" onclick="gvsOpenIconPicker('all-tab', 'Main All Tab')">Change Icon</button>
                <input type="hidden" name="guesty_all_tab_icon" id="input-all-tab" value="<?php echo esc_attr($all_tab_icon); ?>">
            </div>
            <hr style="margin: 30px 0;">
            <h3 style="margin-top:0;">Frontend Filter Tabs & Icons</h3>
            <?php
            $active_filters = get_option('guesty_active_filters', []);
            $custom_icons = get_option('guesty_custom_icons', []);
            if (!is_array($active_filters)) $active_filters = [];
            if (!is_array($custom_icons)) $custom_icons = [];

            if (empty($all_amenities)) {
                echo '<div style="padding: 15px; background: #f9f9f9; border-left: 4px solid #f59e0b;">No amenities found yet.</div>';
            } else {
                echo '<div style="max-height: 500px; overflow-y: auto; border: 1px solid #e5e7eb; padding: 15px; background: #fafafa; border-radius: 4px; display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 12px;">';
                foreach ($all_amenities as $amenity) {
                    $checked = in_array($amenity, $active_filters) ? 'checked' : '';
                    $safe_id = esc_attr(md5($amenity));
                    $current_icon = isset($custom_icons[$amenity]) && !empty($custom_icons[$amenity]) ? $custom_icons[$amenity] : $this->api->get_default_icon_class_for_amenity($amenity);

                    echo '<div style="display: flex; align-items: center; background: #fff; border: 1px solid #ddd; padding: 8px 12px; border-radius: 6px;">';
                    echo '<input type="checkbox" name="guesty_active_filters[]" value="' . esc_attr($amenity) . '" ' . $checked . ' style="margin-right:10px;"> ';
                    echo '<span style="display: flex; justify-content: center; align-items: center; width: 32px; height: 32px; background: #f3f4f6; border-radius: 4px; margin-right: 12px;"><i class="ph ' . esc_attr($current_icon) . '" id="preview-' . $safe_id . '" style="font-size: 20px; color: #4b5563;"></i></span>';
                    echo '<span style="flex-grow: 1; font-size: 13px; font-weight: 600;">' . esc_html($amenity) . '</span>';
                    
                    echo '<button type="button" class="button button-small" onclick="gvsOpenIconPicker(\'' . esc_js($safe_id) . '\', \'' . esc_js($amenity) . '\')">Change Icon</button>';
                    
                    echo '<input type="hidden" name="guesty_custom_icons[' . esc_attr($amenity) . ']" id="input-' . $safe_id . '" value="' . esc_attr($current_icon) . '">';
                    echo '</div>';
                }
                echo '</div>';
            }
            ?>
            <div style="margin-top:20px; display: flex; justify-content: flex-end;"><?php submit_button('Save Filter Settings', 'primary', 'submit', false); ?></div>
        </div>

        <!-- Styles Panel -->
        <div id="gvs-panel-styles" class="gvs-panel" style="display: none; background: #fff; padding: 25px; border: 1px solid #ccd0d4; border-radius: 8px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h3 style="margin:0;">Main Style Settings</h3>
                <?php submit_button('Save Style Settings', 'primary', 'submit', false); ?>
            </div>
            
            <h4 class="gvs-style-section-header">1. General Layout & Typography</h4>
            <table class="form-table">
                <tr valign="top"><th scope="row">Main Header Title</th><td><input type="text" name="guesty_main_header" value="<?php echo esc_attr(get_option('guesty_main_header', 'Find your perfect place to stay')); ?>" style="width: 100%; max-width: 400px;" /></td></tr>
                <tr valign="top"><th scope="row">Property Count Label</th><td><input type="text" name="guesty_count_text_label" value="<?php echo esc_attr(get_option('guesty_count_text_label', 'cottages')); ?>" style="width: 150px;" /></td></tr>
                <tr valign="top"><th scope="row">Layout (Units Per Row)</th>
                    <td>
                        Desktop: <input type="number" name="guesty_units_per_row_desktop" value="<?php echo esc_attr(get_option('guesty_units_per_row_desktop', '4')); ?>" style="width: 60px; margin-right: 15px;" min="1" max="6" />
                        Tablet: <input type="number" name="guesty_units_per_row_tablet" value="<?php echo esc_attr(get_option('guesty_units_per_row_tablet', '2')); ?>" style="width: 60px; margin-right: 15px;" min="1" max="4" />
                        Mobile: <input type="number" name="guesty_units_per_row_mobile" value="<?php echo esc_attr(get_option('guesty_units_per_row_mobile', '1')); ?>" style="width: 60px;" min="1" max="2" />
                    </td>
                </tr>
                <tr valign="top"><th scope="row">Layout (Initial Rows)</th>
                    <td>
                        Desktop: <select name="guesty_rows_per_load_desktop" style="margin-right: 15px;"><?php for($i=1; $i<=8; $i++) echo "<option value='$i' ".selected(get_option('guesty_rows_per_load_desktop', '3'), $i, false).">$i</option>"; ?></select>
                        Tablet: <select name="guesty_rows_per_load_tablet" style="margin-right: 15px;"><?php for($i=1; $i<=8; $i++) echo "<option value='$i' ".selected(get_option('guesty_rows_per_load_tablet', '6'), $i, false).">$i</option>"; ?></select>
                        Mobile: <select name="guesty_rows_per_load_mobile"><?php for($i=1; $i<=8; $i++) echo "<option value='$i' ".selected(get_option('guesty_rows_per_load_mobile', '8'), $i, false).">$i</option>"; ?></select>
                    </td>
                </tr>
            </table>

            <h4 class="gvs-style-section-header">2. Filter Tabs & Sorting</h4>
            <table class="form-table">
                <tr valign="top"><th scope="row">Filter Tabs Colors</th>
                    <td>
                        Default: <input type="color" name="guesty_tab_default_color" value="<?php echo esc_attr(get_option('guesty_tab_default_color', '#6b7280')); ?>" style="margin-right: 15px;" />
                        Hover: <input type="color" name="guesty_tab_hover_color" value="<?php echo esc_attr(get_option('guesty_tab_hover_color', '#1f2937')); ?>" style="margin-right: 15px;" />
                        Active: <input type="color" name="guesty_tab_active_color" value="<?php echo esc_attr(get_option('guesty_tab_active_color', '#2563eb')); ?>" />
                    </td>
                </tr>
                <tr valign="top"><th scope="row">Enabled Sort Options</th>
                    <td>
                        <?php 
                        $all_sorts = ['name-asc' => 'Name (A-Z)', 'name-desc' => 'Name (Z-A)', 'price-desc' => 'Price (High - Low)', 'price-asc' => 'Price (Low - High)', 'beds-desc' => 'Bedrooms (High - Low)', 'beds-asc' => 'Bedrooms (Low - High)', 'guests-desc' => 'Guests (High - Low)', 'guests-asc' => 'Guests (Low - High)', 'rating-desc' => 'Highest Rated'];
                        $enabled_sorts = get_option('guesty_enabled_sorts', array_keys($all_sorts));
                        if (!is_array($enabled_sorts)) $enabled_sorts = [];
                        foreach($all_sorts as $key => $label) {
                            $checked = in_array($key, $enabled_sorts) ? 'checked' : '';
                            echo "<label style='display:inline-block; width:180px; margin-bottom: 5px;'><input type='checkbox' name='guesty_enabled_sorts[]' value='".esc_attr($key)."' $checked> ".esc_html($label)."</label>";
                        }
                        ?>
                    </td>
                </tr>
                <tr valign="top"><th scope="row">Scroll Arrows Colors</th>
                    <td>
                        Arrow Background: <input type="color" name="guesty_scroll_btn_bg" value="<?php echo esc_attr(get_option('guesty_scroll_btn_bg', '#ffffff')); ?>" style="margin-right: 15px;" />
                        Arrow Icon: <input type="color" name="guesty_scroll_btn_color" value="<?php echo esc_attr(get_option('guesty_scroll_btn_color', '#374151')); ?>" />
                    </td>
                </tr>
            </table>

            <h4 class="gvs-style-section-header">3. Buttons & Interactivity</h4>
            <table class="form-table">
                <tr valign="top"><th scope="row">View Cottage Button</th>
                    <td>
                        Text: <input type="text" name="guesty_btn_text" value="<?php echo esc_attr(get_option('guesty_btn_text', 'View Cottage')); ?>" style="width: 150px; margin-right: 15px;" />
                        Color: <input type="color" name="guesty_btn_color" value="<?php echo esc_attr(get_option('guesty_btn_color', '#0062ff')); ?>" style="margin-right: 15px;" />
                        Hover: <input type="color" name="guesty_btn_hover_color" value="<?php echo esc_attr(get_option('guesty_btn_hover_color', '#0052cc')); ?>" />
                    </td>
                </tr>
                <tr valign="top"><th scope="row">Pagination Button</th>
                    <td>
                        Text: <input type="text" name="guesty_load_more_btn_text" value="<?php echo esc_attr(get_option('guesty_load_more_btn_text', 'View More Cottages')); ?>" style="width: 150px; margin-right: 15px;" />
                        Color: <input type="color" name="guesty_load_more_btn_color" value="<?php echo esc_attr(get_option('guesty_load_more_btn_color', '#0062ff')); ?>" />
                    </td>
                </tr>
            </table>

            <h4 class="gvs-style-section-header">4. Search Bar Customization</h4>
            <table class="form-table">
                <tr valign="top"><th scope="row">Background & Text</th>
                    <td>
                        Background: <input type="color" name="guesty_search_bg" value="<?php echo esc_attr(get_option('guesty_search_bg', '#ffffff')); ?>" style="margin-right: 15px;" />
                        Text Inputs: <input type="color" name="guesty_search_text" value="<?php echo esc_attr(get_option('guesty_search_text', '#4b5563')); ?>" style="margin-right: 15px;" />
                        Labels: <input type="color" name="guesty_search_label" value="<?php echo esc_attr(get_option('guesty_search_label', '#1f2937')); ?>" />
                    </td>
                </tr>
                <tr valign="top"><th scope="row">Search Button</th>
                    <td>
                        Button BG: <input type="color" name="guesty_search_btn_bg" value="<?php echo esc_attr(get_option('guesty_search_btn_bg', get_option('guesty_btn_color', '#0062ff'))); ?>" style="margin-right: 15px;" />
                        Button Text: <input type="color" name="guesty_search_btn_text" value="<?php echo esc_attr(get_option('guesty_search_btn_text', '#ffffff')); ?>" />
                    </td>
                </tr>
            </table>

            <h4 class="gvs-style-section-header">5. Pet Friendly Overlay</h4>
            <table class="form-table">
                <tr valign="top"><th scope="row">Show Pet Badge</th><td><label><input type="checkbox" name="guesty_show_pet_badge" value="yes" <?php checked(get_option('guesty_show_pet_badge', 'yes'), 'yes'); ?> /> Show a corner icon overlay on the image of units that allow pets.</label></td></tr>
                <tr valign="top"><th scope="row">Pet Badge Icon</th>
                    <td>
                        <?php $pet_icon = get_option('guesty_pet_badge_icon', 'ph-paw-print'); ?>
                        <div style="display: flex; align-items: center; background: #fff; border: 1px solid #ccd0d4; padding: 8px; border-radius: 6px; max-width: 300px;">
                            <span style="display: flex; justify-content: center; align-items: center; width: 32px; height: 32px; background: #f0f6fc; border-radius: 4px; margin-right: 12px;"><i class="ph <?php echo esc_attr($pet_icon); ?>" id="preview-pet-badge" style="font-size: 20px; color: #2271b1;"></i></span>
                            <button type="button" class="button button-secondary" onclick="gvsOpenIconPicker('pet-badge', 'Pet Friendly Badge')">Change Icon</button>
                            <input type="hidden" name="guesty_pet_badge_icon" id="input-pet-badge" value="<?php echo esc_attr($pet_icon); ?>">
                        </div>
                    </td>
                </tr>
            </table>

            <h4 class="gvs-style-section-header">6. Custom Badges & Media</h4>
            <table class="form-table">
                <tr valign="top"><th scope="row">Custom Badge Styling</th>
                    <td>
                        Padding: <input type="number" name="guesty_badge_padding" value="<?php echo esc_attr(get_option('guesty_badge_padding', '0')); ?>" style="width: 60px; margin-right: 15px;" /> px 
                        &nbsp; | &nbsp;
                        Font Size: <input type="number" name="guesty_badge_font_size" value="<?php echo esc_attr(get_option('guesty_badge_font_size', '14')); ?>" style="width: 60px; margin-right: 15px;" /> px
                        <br><br>
                        Text Style: <select name="guesty_badge_text_transform" style="margin-right: 15px;"><option value="uppercase" <?php selected(get_option('guesty_badge_text_transform', 'uppercase'), 'uppercase'); ?>>UPPERCASE</option><option value="capitalize" <?php selected(get_option('guesty_badge_text_transform', 'uppercase'), 'capitalize'); ?>>Capitalize</option><option value="none" <?php selected(get_option('guesty_badge_text_transform', 'uppercase'), 'none'); ?>>Normal</option></select>
                        Font Weight: <select name="guesty_badge_font_weight"><option value="400" <?php selected(get_option('guesty_badge_font_weight', '400'), '400'); ?>>Normal</option><option value="600" <?php selected(get_option('guesty_badge_font_weight', '400'), '600'); ?>>Semi-Bold</option><option value="700" <?php selected(get_option('guesty_badge_font_weight', '400'), '700'); ?>>Bold</option><option value="800" <?php selected(get_option('guesty_badge_font_weight', '400'), '800'); ?>>Extra Bold</option></select>
                    </td>
                </tr>
                <tr valign="top"><th scope="row">"No Image" Fallback</th>
                    <td>
                        <?php $fallback_img = get_option('guesty_fallback_image'); ?>
                        <div style="display: flex; gap: 15px; align-items: flex-end;">
                            <div id="guesty_fallback_preview" style="width: 160px; height: 106px; background: #f0f0f1; border: 1px dashed #b4b9be; display: flex; align-items: center; justify-content: center; overflow: hidden; border-radius: 4px;">
                                <?php if ($fallback_img): ?><img src="<?php echo esc_url($fallback_img); ?>" style="width: 100%; height: 100%; object-fit: cover;"><?php else: ?><span style="color: #646970; font-size: 13px;">No Image Set</span><?php endif; ?>
                            </div>
                            <div>
                                <input type="hidden" name="guesty_fallback_image" id="guesty_fallback_image" value="<?php echo esc_attr($fallback_img); ?>" />
                                <button type="button" class="button" id="guesty_upload_fallback_btn">Select Image</button>
                                <button type="button" class="button button-link-delete" id="guesty_clear_fallback_btn" style="<?php echo empty($fallback_img) ? 'display:none;' : ''; ?>">Clear</button>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>

            <div style="margin-top:20px; display: flex; justify-content: flex-end;"><?php submit_button('Save Style Settings', 'primary', 'submit', false); ?></div>
        </div>

        <!-- FOR YOU SETTINGS PANEL -->
        <div id="gvs-panel-foryou" class="gvs-panel" style="display: none; background: #fff; padding: 25px; border: 1px solid #ccd0d4; border-radius: 8px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h3 style="margin:0;">"For You" Recommendation Widget Settings</h3>
                <?php submit_button('Save For You Settings', 'primary', 'submit', false); ?>
            </div>
            
            <p class="description" style="margin-bottom: 20px;">Use the settings below to fully customize how the <code>[guesty_for_you]</code> recommendation widget appears to your users.</p>

            <h4 class="gvs-style-section-header">1. Section Title & Icon</h4>
            <table class="form-table">
                <tr valign="top"><th scope="row">Widget Title</th><td><input type="text" name="guesty_fy_title" value="<?php echo esc_attr(get_option('guesty_fy_title', 'Recommended For You')); ?>" style="width: 100%; max-width: 400px;" /></td></tr>
                <tr valign="top"><th scope="row">Title Icon</th>
                    <td>
                        <?php $fy_icon = get_option('guesty_fy_icon', 'ph-star'); ?>
                        <div style="display: flex; align-items: center; background: #fff; border: 1px solid #ccd0d4; padding: 8px; border-radius: 6px; max-width: 300px;">
                            <span style="display: flex; justify-content: center; align-items: center; width: 32px; height: 32px; background: #f0f6fc; border-radius: 4px; margin-right: 12px;"><i class="ph <?php echo esc_attr($fy_icon); ?>" id="preview-fy-icon" style="font-size: 20px; color: <?php echo esc_attr(get_option('guesty_fy_icon_color', '#f59e0b')); ?>;"></i></span>
                            <button type="button" class="button button-secondary" onclick="gvsOpenIconPicker('fy-icon', 'For You Title Icon')">Change Icon</button>
                            <input type="hidden" name="guesty_fy_icon" id="input-fy-icon" value="<?php echo esc_attr($fy_icon); ?>">
                        </div>
                    </td>
                </tr>
                <tr valign="top"><th scope="row">Title Icon Color</th><td><input type="color" name="guesty_fy_icon_color" value="<?php echo esc_attr(get_option('guesty_fy_icon_color', '#f59e0b')); ?>" onchange="document.getElementById('preview-fy-icon').style.color = this.value;" /></td></tr>
            </table>

            <h4 class="gvs-style-section-header">2. Property Overlay Badge</h4>
            <table class="form-table">
                <tr valign="top"><th scope="row">Badge Text</th><td><input type="text" name="guesty_fy_badge_text" value="<?php echo esc_attr(get_option('guesty_fy_badge_text', 'Recommended')); ?>" style="width: 200px;" /></td></tr>
                <tr valign="top"><th scope="row">Badge Colors</th>
                    <td>
                        Background: <input type="color" name="guesty_fy_badge_bg" value="<?php echo esc_attr(get_option('guesty_fy_badge_bg', '#f59e0b')); ?>" style="margin-right: 15px;" />
                        Text: <input type="color" name="guesty_fy_badge_color" value="<?php echo esc_attr(get_option('guesty_fy_badge_color', '#ffffff')); ?>" />
                    </td>
                </tr>
            </table>

            <div style="margin-top:20px; display: flex; justify-content: flex-end;"><?php submit_button('Save For You Settings', 'primary', 'submit', false); ?></div>
        </div>

        <!-- CSS Panel -->
        <div id="gvs-panel-css" class="gvs-panel" style="display: none; background: #fff; padding: 25px; border: 1px solid #ccd0d4; border-radius: 8px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <div style="display:flex; align-items:center; gap: 15px;">
                    <h3 style="margin:0;">Additional CSS</h3>
                    <button type="button" class="button" onclick="gvsOpenSelectorModal()">Useful Selectors</button>
                </div>
                <?php submit_button('Save Additional CSS', 'primary', 'submit', false); ?>
            </div>
            <textarea name="guesty_additional_css" rows="12" style="width: 100%; font-family: monospace; background: #fafafa; border: 1px solid #ccc; padding: 10px;"><?php echo esc_textarea(get_option('guesty_additional_css', '')); ?></textarea>
            <div style="margin-top:20px; display: flex; justify-content: flex-end;"><?php submit_button('Save Additional CSS', 'primary', 'submit', false); ?></div>
        </div>

        <!-- Render the Unit Pages Panel hook if the Add-on is active -->
        <?php if (defined('GUESTY_ALC_UNIT_PAGES_ACTIVE')) { do_action('guesty_alc_render_unit_pages_panel'); } ?>
        
    </form>

    <!-- SHORTCODE & DOCS PANELS -->
    <div id="gvs-panel-shortcode" class="gvs-panel" style="display: none; background: #fff; padding: 25px; border: 1px solid #ccd0d4; border-radius: 8px;">
        <h3 style="margin-top:0;"><span class="dashicons dashicons-editor-code"></span> Advanced Shortcode Generator</h3>
        <p class="description">Generate shortcodes to display specific configurations of your property grid.</p>
        <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 20px; margin-top: 20px;">
            <table class="form-table" style="margin-top: 0;">
                <tbody>
                    <tr><th scope="row" style="padding-top: 0; padding-bottom: 20px; border-bottom: 1px solid #e5e7eb;">Shortcode Type</th><td style="padding-top: 0; padding-bottom: 20px; border-bottom: 1px solid #e5e7eb;"><label style="margin-right: 15px; font-weight: 600;"><input type="radio" name="gvs-sc-type" value="standard" checked onchange="gvsUpdateShortcode()"> Standard Property Grid</label><label style="font-weight: 600;"><input type="radio" name="gvs-sc-type" value="foryou" onchange="gvsUpdateShortcode()"> "For You" Recommendation Widget</label></td></tr>
                </tbody>
                <tbody id="gvs-sc-options-wrap" style="transition: opacity 0.2s;">
                    <tr><th scope="row" style="padding-top: 15px; padding-bottom: 10px;">Enable Search Bar</th><td style="padding-top: 15px; padding-bottom: 10px;"><label><input type="checkbox" id="gvs-sc-search" onchange="gvsUpdateShortcode()"> Show the unified search bar above the property grid.</label></td></tr>
                    <tr><th scope="row" style="padding-top: 0; padding-bottom: 10px;">Search Bar Only</th><td style="padding-top: 0; padding-bottom: 10px;"><label><input type="checkbox" id="gvs-sc-search-only" onchange="gvsUpdateShortcode()"> Only show the search bar (redirects to the Search Results Page upon clicking search).</label></td></tr>
                    <tr><th scope="row" style="padding-top: 0; padding-bottom: 10px;">Initial Tab (Optional)</th><td style="padding-top: 0; padding-bottom: 10px;"><select id="gvs-sc-start" onchange="gvsUpdateShortcode()"><option value="">- Default (All) -</option><?php $all_am = get_transient('guesty_all_amenities'); if(is_array($all_am)) { foreach($all_am as $am) echo '<option value="'.esc_attr($am).'">'.esc_html($am).'</option>'; } ?></select></td></tr>
                    <tr><th scope="row" style="padding-bottom: 10px;">Hide Tab Navigation</th><td style="padding-bottom: 10px;"><label><input type="checkbox" id="gvs-sc-hide" onchange="gvsUpdateShortcode()"> Yes, hide the filter tabs at the top.</label></td></tr>
                    <tr>
                        <th scope="row" style="padding-bottom: 0;">Search Bar Overrides</th>
                        <td style="padding-bottom: 0;">
                            <p class="description" style="margin-top: 0;">Override backend settings manually by adding these attributes to your shortcode: <br><code>show_location="yes"</code>, <code>show_dates="no"</code>, <code>show_guests="yes"</code>, <code>show_bedrooms="yes"</code>, <code>show_amenity="yes"</code>, <code>show_pets="yes"</code>.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div style="margin-top: 25px; padding-top: 20px; border-top: 1px dashed #d1d5db;">
                <h4 style="margin-top: 0; margin-bottom: 10px; color: #1d2327;">Your Generated Shortcode:</h4>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <code id="gvs-generated-shortcode" style="font-size: 16px; padding: 10px 15px; background: #fff; border: 1px solid #2271b1; color: #2271b1; font-weight: bold; border-radius: 4px; flex-grow: 1;">[guesty_perfect_stay]</code>
                    <button type="button" class="button button-primary" onclick="navigator.clipboard.writeText(document.getElementById('gvs-generated-shortcode').innerText); this.innerText='Copied!'; setTimeout(()=>this.innerText='Copy', 2000);">Copy</button>
                </div>
            </div>
        </div>
    </div>

    <!-- UPDATED DOCUMENTATION SECTION -->
    <div id="gvs-panel-docs" class="gvs-panel" style="display: none; background: #fff; padding: 25px; border: 1px solid #ccd0d4; border-radius: 8px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 style="margin:0;"><span class="dashicons dashicons-book-alt"></span> Plugin Documentation</h3>
            <button type="button" class="button button-primary" onclick="document.getElementById('gvs-changelog-modal').style.display='flex'">View Change Log</button>
        </div>
        <input type="text" id="gvs-docs-search" placeholder="Search documentation (e.g., cron, search bar, API)..." style="width:100%; max-width: 600px; padding:10px; margin-bottom:25px; font-size:16px; border-radius: 4px; border: 1px solid #ccd0d4;">
        <div id="gvs-docs-content">
            
            <div class="gvs-doc-section">
                <h4>1. Initial Setup & Integration</h4>
                <p>To connect the plugin to your Guesty account, you must enter a valid <strong>Client ID</strong> and <strong>Client Secret</strong> in the Settings tab. Once entered, hit the green <code>Sync Guesty Data</code> button at the top right of the screen.</p>
                <p>The <strong>Property Base URL</strong> dictates where the user goes when they click "View Cottage". The plugin will automatically append the specific listing ID to whatever URL you input there (e.g., <code>https://yourdomain.guestybookings.com/en/properties/</code>).</p>
            </div>

            <div class="gvs-doc-section">
                <h4>2. The Shortcode & Advanced Overrides</h4>
                <p>To place the property grid anywhere on your WordPress site, simply use: <code>[guesty_perfect_stay]</code></p>
                <p><strong>Shortcode Attribute Overrides:</strong> You can override your global Search Bar settings directly on any page by adding attributes. For example, if you want to force the location dropdown to hide, but keep the dates, use: <br><code>[guesty_perfect_stay search_bar="true" show_location="no" show_dates="yes"]</code></p>
                <p><strong>Search Bar Only & Redirects:</strong> If you want to create a standalone search widget (like on your homepage header), use <code>[guesty_perfect_stay search_only="true"]</code>. When a user clicks Search, they will be redirected to the "Search Results Page URL" defined in your Settings. The destination page will automatically read the URL parameters (e.g., <code>?gvs_guests=2</code>) and filter the grid instantly!</p>
            </div>

            <div class="gvs-doc-section">
                <h4>3. The Search Bar, Premium Calendar & Live Availability</h4>
                <p><strong>Premium Date Range Picker:</strong> The search bar utilizes a highly optimized, dual-month calendar grid (Flatpickr) that dynamically highlights the user's selected stay duration just like premium OTA platforms (Airbnb/VRBO).</p>
                <p><strong>Hybrid Caching:</strong> When a user searches by location or guests, the grid filters instantly using the local cache. However, the moment a user inputs <strong>Check-in and Check-out dates</strong>, the plugin dynamically pings Guesty's live servers to ensure absolute 100% accuracy. This prevents double-bookings while maintaining lightning-fast load times.</p>
                <p><strong>Dynamic Pricing:</strong> You can enable "Dynamic Pricing" in the Settings tab. When enabled, any date search will swap out the standard base price on the card for the exact calculated Total Price pulled directly from Guesty for that specific stay.</p>
            </div>

            <div class="gvs-doc-section">
                <h4>4. Caching, AJAX & Multiple Grids</h4>
                <p>The plugin is designed to handle massive property portfolios effortlessly.</p>
                <ul>
                    <li><strong>Multiple Instances:</strong> You can place the shortcode multiple times on a single page, and they will function entirely independently without conflicting!</li>
                    <li><strong>Background Warming & Validation:</strong> The plugin relies on WordPress transients. It automatically schedules background crons to silently rebuild the cache 5 minutes before it expires. During this sync, it proactively tests all Guesty IDs against the Live Calendar API to ensure no corrupted or inactive listings break your live site.</li>
                    <li><strong>AJAX Loading:</strong> Strongly recommended for sites with over 50 properties. When enabled, the plugin stops injecting a massive JSON payload directly into your page's HTML (protecting SEO speeds) and securely loads properties sequentially in the background.</li>
                    <li><strong>Production Tip:</strong> Disable the default <code>WP-Cron</code> and set up a true server-side cron job (via cPanel) that hits <code>wp-cron.php</code> every 5 minutes for maximum stability.</li>
                </ul>
            </div>

            <div class="gvs-doc-section">
                <h4>5. Customizing Tabs & Icons</h4>
                <p>Whenever you click "Sync Guesty Data", the plugin automatically scrapes your entire portfolio and compiles a list of every single unique amenity you offer.</p>
                <p>In the <strong>Filters</strong> tab, you can select which of these amenities should appear as clickable tabs on the frontend grid. By clicking "Change Icon", you can replace the auto-assigned icon with any of the 130+ premium Phosphor Icons built directly into the system.</p>
            </div>

            <div class="gvs-doc-section">
                <h4>6. Unit Management (Overrides)</h4>
                <p>The <strong>Unit Management</strong> tab allows you to manually dictate how specific units behave, totally independent of the Guesty API.</p>
                <ul>
                    <li><strong>Visibility:</strong> Hide units from your website without having to unlist them completely from Guesty.</li>
                    <li><strong>Custom Badges:</strong> Add personalized ribbons (like "New!", "Featured!", or "Discounted!") to any property image, complete with custom text and background colors.</li>
                    <li><strong>Review Visibility:</strong> You can toggle star ratings on or off globally, or hide them for individual units (highly useful for brand new properties that don't have enough reviews yet to look appealing).</li>
                    <li><strong>Display Position:</strong> Force your best or highest-converting properties to always load in positions 1, 2, 3, etc. Any units left blank will shuffle in automatically afterward.</li>
                </ul>
            </div>

            <div class="gvs-doc-section">
                <h4>7. "For You" Recommendation Engine</h4>
                <p>The plugin includes a smart, local-storage based recommendation engine that tracks a user's interactions (properties they click on, dates they search, locations they prefer) and scores your listings to present them with a highly personalized list of units.</p>
                <p><strong>How to use it:</strong> Place the shortcode <code>[guesty_for_you]</code> on your homepage or anywhere you want to show recommendations. By default, it remains completely invisible to brand new users. Once a user searches or clicks a unit, the widget instantly reveals itself showing their personalized top matches.</p>
                <p><em>Note: Because this feature respects user privacy and runs entirely in their browser, it will only appear once you interact with the main property grid or search bar!</em></p>
            </div>

        </div>
    </div>

    <!-- DATA / ACTION PANELS -->
    <?php if ($show_data_panel): ?>
    <div id="gvs-data-panel" style="background: #fff; padding: 25px; border: 1px solid #ccd0d4; border-radius: 8px;">
        <?php
        if (isset($_POST['view_guesty_data']) || isset($_POST['save_guesty_order']) || isset($_POST['toggle_guesty_visibility']) || isset($_POST['toggle_guesty_reviews']) || isset($_POST['force_guesty_refresh'])) { $this->render_cached_data_table(); }
        if (isset($_POST['view_guesty_logs']) || isset($_POST['clear_guesty_logs'])) { $this->render_logs_table(); }
        if (isset($_POST['view_guesty_token']) || isset($_POST['revoke_guesty_token'])) { $this->render_token_info(); }
        ?>
    </div>
    <?php endif; ?>

    <!-- MODALS -->
    <div id="gvs-changelog-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:99999; justify-content:center; align-items:center;">
        <div style="background:#fff; width:90%; max-width:800px; border-radius:8px; padding:25px; display:flex; flex-direction:column; max-height:85vh; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h3 style="margin:0;"><span class="dashicons dashicons-welcome-learn-more" style="margin-top: 3px;"></span> Plugin Change Log</h3>
                <button type="button" class="button" onclick="document.getElementById('gvs-changelog-modal').style.display='none'">Close</button>
            </div>
            <div style="overflow-y:auto; flex-grow:1; padding-right:10px;">
                <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                    <h4 style="margin: 0 0 5px 0; color: #2271b1; font-size: 16px;">Version 4.2.0 (Current)</h4>
                    <ul style="margin: 0; padding-left: 20px; color: #50575e; font-size: 13px;">
                        <li>Upgraded Flatpickr date UI to dynamically render a custom, bordered square grid perfectly matching modern OTA designs.</li>
                        <li>Locked Flatpickr dynamic dimensions to fix Saturday column cutoff issue.</li>
                        <li>Fixed top-left calendar loading glitch by appending the calendar directly below the Check-in field with relative positioning.</li>
                        <li>Added comprehensive Change Log interface to the Admin Dashboard.</li>
                    </ul>
                </div>
                <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                    <h4 style="margin: 0 0 5px 0; color: #2271b1; font-size: 16px;">Version 4.1.0</h4>
                    <ul style="margin: 0; padding-left: 20px; color: #50575e; font-size: 13px;">
                        <li>Offloaded all heavy CSS and JS out of the view files into static <code>/assets/</code> files for optimal caching.</li>
                        <li>Added "Search Bar Only" shortcode override and global "Search Results Page URL" redirect logic.</li>
                        <li>Introduced unified Flatpickr date range calendar with auto-fill via URL parameters.</li>
                    </ul>
                </div>
                <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                    <h4 style="margin: 0 0 5px 0; color: #2271b1; font-size: 16px;">Version 4.0.0</h4>
                    <ul style="margin: 0; padding-left: 20px; color: #50575e; font-size: 13px;">
                        <li>Complete Production Architecture Refactor: Splitted monolithic file into standard WordPress MVC (Model-View-Controller) structure.</li>
                        <li>API engine strictly isolated from Admin views and Frontend shortcodes.</li>
                        <li>Introduced robust <code>400 Bad Request</code> corrupted unit isolation during background nightly sync.</li>
                        <li>Increased Live Search API chunk limit from 10 to 50 for 5x faster availability checks.</li>
                    </ul>
                </div>
                <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                    <h4 style="margin: 0 0 5px 0; color: #2271b1; font-size: 16px;">Version 3.11.0</h4>
                    <ul style="margin: 0; padding-left: 20px; color: #50575e; font-size: 13px;">
                        <li>Security hardening: Added strict <code>wp_nonce</code> verification on all backend setting updates.</li>
                        <li>Frontend shortcodes upgraded to handle multiple active grids on the exact same page simultaneously.</li>
                        <li>Empty-cache failsafe introduced (shows graceful sync message instead of breaking layout).</li>
                    </ul>
                </div>
                <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                    <h4 style="margin: 0 0 5px 0; color: #2271b1; font-size: 16px;">Version 3.0.0</h4>
                    <ul style="margin: 0; padding-left: 20px; color: #50575e; font-size: 13px;">
                        <li>Introduced the frontend Search Bar with Location, Guests, Bedrooms, and Amenity mapping.</li>
                        <li>Connected the Live Availability API check (hybrid caching).</li>
                        <li>Dynamic Pricing overlay enabled for exact date quote lookups.</li>
                    </ul>
                </div>
                <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                    <h4 style="margin: 0 0 5px 0; color: #2271b1; font-size: 16px;">Version 2.0.0</h4>
                    <ul style="margin: 0; padding-left: 20px; color: #50575e; font-size: 13px;">
                        <li>Advanced caching system (WordPress transients) added to drastically lower API calls.</li>
                        <li>Custom Badge and Pet-Friendly ribbon features implemented.</li>
                        <li>Unit override and manual visibility toggles introduced.</li>
                    </ul>
                </div>
                <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                    <h4 style="margin: 0 0 5px 0; color: #2271b1; font-size: 16px;">Version 1.0.0</h4>
                    <ul style="margin: 0; padding-left: 20px; color: #50575e; font-size: 13px;">
                        <li>Initial release: Connected to Guesty Open API.</li>
                        <li>Shortcode <code>[guesty_perfect_stay]</code> created.</li>
                        <li>Basic grid, layout selection, and custom styling overrides built.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div id="gvs-icon-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:99999; justify-content:center; align-items:center;">
        <div style="background:#fff; width:90%; max-width:700px; border-radius:8px; padding:25px; display:flex; flex-direction:column; max-height:85vh; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h3 style="margin:0;">Select Icon for: <span id="gvs-icon-modal-target-name" style="color:#0062ff;"></span></h3>
                <button type="button" class="button" onclick="gvsCloseIconPicker()">Close</button>
            </div>
            <input type="text" id="gvs-icon-search" placeholder="Search icons..." style="width:100%; padding:10px; margin-bottom:15px; font-size:16px;">
            <div id="gvs-icon-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(60px, 1fr)); gap:10px; overflow-y:auto; flex-grow:1; padding-right:10px;"></div>
        </div>
    </div>

    <div id="gvs-selector-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:99999; justify-content:center; align-items:center;">
        <div style="background:#fff; width:90%; max-width:800px; border-radius:8px; padding:25px; display:flex; flex-direction:column; max-height:85vh; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h3 style="margin:0;">Useful CSS Selectors</h3>
                <button type="button" class="button" onclick="gvsCloseSelectorModal()">Close</button>
            </div>
            <input type="text" id="gvs-selector-search" placeholder="Search selectors..." style="width:100%; padding:10px; margin-bottom:15px; font-size:16px;">
            <div id="gvs-selector-list" style="display:flex; flex-direction:column; gap:10px; overflow-y:auto; flex-grow:1; padding-right:10px;"></div>
        </div>
    </div>
</div>

<script>
    // --- Global JS Functions (Accessible to inline onclicks) ---
    let gvsCurrentIconTargetId = '';
    const gvsIconList = ['ph-house','ph-star','ph-wind','ph-campfire','ph-cooking-pot','ph-oven','ph-bathtub','ph-umbrella','ph-waves','ph-dice-five','ph-game-controller','ph-anchor','ph-television','ph-warning-circle','ph-fan','ph-coffee','ph-drop','ph-washing-machine','ph-tote','ph-bed','ph-baby','ph-fire','ph-first-aid','ph-car','ph-barbell','ph-thermometer-hot','ph-wifi-high','ph-t-shirt','ph-tree','ph-plant','ph-paw-print','ph-swimming-pool','ph-snowflake','ph-mountains','ph-laptop','ph-desk','ph-briefcase','ph-binoculars','ph-lock-key','ph-shield-check','ph-speaker-hifi','ph-book-open','ph-key','ph-bicycle','ph-camera','ph-fork-knife','ph-martini','ph-wine','ph-pizza','ph-hamburger','ph-coffee-bean','ph-egg','ph-fish-simple','ph-sun','ph-moon','ph-cloud','ph-thermometer','ph-tent','ph-wheelchair','ph-toilet','ph-shower','ph-plug','ph-lightning','ph-battery-charging','ph-lamp','ph-armchair','ph-couch','ph-speaker-high','ph-radio','ph-video-camera','ph-film-strip','ph-projector-screen','ph-books','ph-newspaper','ph-bag','ph-shopping-cart','ph-credit-card','ph-money','ph-coins','ph-ticket','ph-airplane','ph-airplane-tilt','ph-train','ph-bus','ph-taxi','ph-rocket','ph-moped','ph-motorcycle','ph-police-car','ph-ambulance','ph-truck','ph-tractor','ph-push-pin','ph-map-pin','ph-compass','ph-map-trifold','ph-globe','ph-globe-hemisphere-west','ph-globe-stand','ph-signpost','ph-bell','ph-alarm','ph-calendar','ph-clock','ph-hourglass','ph-timer','ph-watch','ph-magnifying-glass','ph-magnifying-glass-plus','ph-magnifying-glass-minus','ph-info','ph-question','ph-warning','ph-check','ph-x','ph-plus','ph-minus','ph-caret-up','ph-caret-down','ph-caret-left','ph-caret-right','ph-arrow-up','ph-arrow-down','ph-arrow-left','ph-arrow-right','ph-users','ph-user','ph-buildings','ph-storefront'];

    const gvsSelectorsList = [
        { selector: '.gvs-container', desc: 'The main outer wrapper for the entire shortcode block.' },
        { selector: '.gvs-title', desc: 'The main header text (e.g. "Find your perfect place to stay").' },
        { selector: '.gvs-search-bar-container', desc: 'The outer wrapper holding the entire search bar.' },
        { selector: '.gvs-search-bar', desc: 'The pill-shaped inner background of the search bar.' },
        { selector: '.gvs-search-field', desc: 'An individual input area (Location, Dates, Guests) inside the search bar.' },
        { selector: '.gvs-search-field label', desc: 'The bold title text above the search inputs.' },
        { selector: '.gvs-search-field input, .gvs-search-field select', desc: 'The actual input and dropdown elements inside the search bar.' },
        { selector: '.gvs-search-button', desc: 'The main "Search" button at the end of the bar.' },
        { selector: '.gvs-clear-button', desc: 'The "Clear" text button used to reset the form.' },
        { selector: '.gvs-tabs-wrapper', desc: 'The container holding the filter tabs and scroll arrows.' },
        { selector: '.gvs-scroll-btn', desc: 'The left and right circular scroll arrow buttons.' },
        { selector: '.gvs-tabs', desc: 'The horizontally scrolling track holding the amenity tabs.' },
        { selector: '.gvs-tab', desc: 'An individual unselected filter tab.' },
        { selector: '.gvs-tab.active', desc: 'The currently selected/active filter tab.' },
        { selector: '.gvs-tab i.gvs-icon', desc: 'The Phosphor icon rendered inside a filter tab.' },
        { selector: '.gvs-controls-row', desc: 'The row containing the Sort dropdown and showing count.' },
        { selector: '.gvs-sort-dropdown', desc: 'The "Sort By" select menu element.' },
        { selector: '.gvs-count-text', desc: 'The "Showing X cottages" descriptive text.' },
        { selector: '.gvs-grid', desc: 'The main CSS Grid layout container holding all property cards.' },
        { selector: '.gvs-card', desc: 'An individual property unit card wrapper (controls borders/shadows).' },
        { selector: '.gvs-skeleton-card', desc: 'The temporary shimmering loading card shown during API fetch.' },
        { selector: '.gvs-card-img-wrapper', desc: 'The container locking the property image into a 3/2 aspect ratio.' },
        { selector: '.gvs-card-img', desc: 'The actual property image element.' },
        { selector: '.gvs-pet-badge', desc: 'The circular "Pets Allowed" corner icon over the image.' },
        { selector: '.gvs-badge-diagonal', desc: 'The custom text ribbon shown diagonally across the image corner.' },
        { selector: '.gvs-badge-straight', desc: 'The custom text banner shown flat across the bottom of the image.' },
        { selector: '.gvs-card-content', desc: 'The lower white half of the card holding all text.' },
        { selector: '.gvs-location', desc: 'The property title/name text.' },
        { selector: '.gvs-type', desc: 'The property City and Country text.' },
        { selector: '.gvs-specs', desc: 'The text row showing Bedrooms, Bathrooms, and Guests.' },
        { selector: '.gvs-rating', desc: 'The wrapper holding the review score and review count.' },
        { selector: '.gvs-score', desc: 'The green box containing the numerical review rating (e.g. 4.8).' },
        { selector: '.gvs-footer', desc: 'The bottom row inside a card containing the button and price.' },
        { selector: '.gvs-view-btn', desc: 'The "View Cottage" button inside the bottom of a card.' },
        { selector: '.gvs-price-wrap', desc: 'The container holding the price amount and the "base price" label.' },
        { selector: '.gvs-price', desc: 'The bold, formatted price text.' },
        { selector: '.gvs-price-label', desc: 'The small grey text beneath the price.' },
        { selector: '.gvs-load-more-wrap', desc: 'The wrapper holding the pagination load more button.' },
        { selector: '.gvs-load-more-btn', desc: 'The "View More Cottages" pagination button.' }
    ];

    function gvsOpenIconPicker(targetId, targetName) {
        gvsCurrentIconTargetId = targetId; 
        document.getElementById('gvs-icon-modal-target-name').innerText = targetName;
        document.getElementById('gvs-icon-modal').style.display = 'flex'; 
        gvsRenderIconGrid();
    }
    
    function gvsCloseIconPicker() { 
        document.getElementById('gvs-icon-modal').style.display = 'none'; 
    }
    
    function gvsSelectIcon(iconClass) {
        document.getElementById('input-' + gvsCurrentIconTargetId).value = iconClass;
        document.getElementById('preview-' + gvsCurrentIconTargetId).className = 'ph ' + iconClass;
        gvsCloseIconPicker();
    }
    
    function gvsRenderIconGrid(filter = '') {
        const grid = document.getElementById('gvs-icon-grid'); 
        grid.innerHTML = '';
        const searchStr = filter.toLowerCase();
        gvsIconList.forEach(icon => {
            if (icon.replace('ph-', '').includes(searchStr)) {
                const div = document.createElement('div');
                div.style.cssText = 'display:flex; flex-direction:column; align-items:center; justify-content:center; padding:10px; border:1px solid #e5e7eb; border-radius:6px; cursor:pointer; transition:all 0.2s;';
                div.innerHTML = `<i class="ph ${icon}" style="font-size:24px; color:#374151; margin-bottom:5px;"></i><span style="font-size:9px; color:#6b7280; text-align:center; overflow:hidden; text-overflow:ellipsis; width:100%; white-space:nowrap;">${icon.replace('ph-','')}</span>`;
                div.onclick = () => gvsSelectIcon(icon); 
                grid.appendChild(div);
            }
        });
    }

    function gvsOpenSelectorModal() {
        document.getElementById('gvs-selector-modal').style.display = 'flex';
        gvsRenderSelectorList('');
    }

    function gvsCloseSelectorModal() {
        document.getElementById('gvs-selector-modal').style.display = 'none';
    }

    function gvsRenderSelectorList(filter = '') {
        const listContainer = document.getElementById('gvs-selector-list');
        listContainer.innerHTML = '';
        const searchStr = filter.toLowerCase();

        gvsSelectorsList.forEach(item => {
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

    function gvsShowPanel(id) {
        localStorage.setItem('gvs_active_tab', id);
        document.querySelectorAll('.gvs-panel').forEach(p => p.style.display = 'none');
        document.getElementById('gvs-panel-' + id).style.display = 'block';
        document.getElementById('gvs-options-form').style.display = (id === 'docs' || id === 'shortcode') ? 'none' : 'block';
        document.querySelectorAll('.gvs-nav-tab, .gvs-action-tab').forEach(t => t.classList.remove('button-primary'));
        const activeBtn = document.getElementById('gvs-tab-' + id);
        if (activeBtn) activeBtn.classList.add('button-primary');
        const dataPanel = document.getElementById('gvs-data-panel');
        if (dataPanel) dataPanel.style.display = 'none';
    }

    function gvsUpdateShortcode() {
        const scTypeRadio = document.querySelector('input[name="gvs-sc-type"]:checked');
        const scType = scTypeRadio ? scTypeRadio.value : 'standard';
        const searchBar = document.getElementById('gvs-sc-search').checked;
        const searchOnly = document.getElementById('gvs-sc-search-only').checked;
        const startTab = document.getElementById('gvs-sc-start').value;
        const hideTabs = document.getElementById('gvs-sc-hide').checked;
        
        let sc = '';
        
        if (scType === 'foryou') {
            sc = '[guesty_for_you]';
            const optionsWrap = document.getElementById('gvs-sc-options-wrap');
            if (optionsWrap) {
                optionsWrap.style.opacity = '0.4';
                optionsWrap.style.pointerEvents = 'none';
            }
        } else {
            const optionsWrap = document.getElementById('gvs-sc-options-wrap');
            if (optionsWrap) {
                optionsWrap.style.opacity = '1';
                optionsWrap.style.pointerEvents = 'auto';
            }
            sc = '[guesty_perfect_stay';
            if (searchBar || searchOnly) sc += ` search_bar="true"`;
            if (searchOnly) sc += ` search_only="true"`;
            if (startTab !== '') sc += ` start_tab="${startTab}"`;
            if (hideTabs || searchOnly) sc += ` hide_tabs="true"`;
            sc += ']';
        }
        
        document.getElementById('gvs-generated-shortcode').innerText = sc;
    }

    // --- DOMContentLoaded Initialization ---
    document.addEventListener("DOMContentLoaded", function() {
        // Safe binding of search inputs
        const iconSearchInput = document.getElementById('gvs-icon-search');
        if (iconSearchInput) {
            iconSearchInput.addEventListener('input', (e) => { gvsRenderIconGrid(e.target.value); });
        }
        
        const selectorSearchInput = document.getElementById('gvs-selector-search');
        if (selectorSearchInput) {
            selectorSearchInput.addEventListener('input', (e) => { gvsRenderSelectorList(e.target.value); });
        }

        const urlParams = new URLSearchParams(window.location.search);
        const phpActiveTab = '<?php echo $active_tab; ?>';
        
        if (['data', 'logs', 'token'].includes(phpActiveTab)) { 
            localStorage.setItem('gvs_active_tab', phpActiveTab); 
        } else if (urlParams.has('settings-updated')) { 
            gvsShowPanel(localStorage.getItem('gvs_active_tab') || 'api'); 
        } else { 
            gvsShowPanel(localStorage.getItem('gvs_active_tab') || 'api'); 
        }

        const docsSearch = document.getElementById('gvs-docs-search');
        if (docsSearch) {
            docsSearch.addEventListener('input', function(e) {
                const term = e.target.value.toLowerCase();
                document.querySelectorAll('.gvs-doc-section').forEach(section => {
                    section.style.display = section.innerText.toLowerCase().includes(term) ? 'block' : 'none';
                });
            });
        }
    });

    // WP Media Uploader
    jQuery(document).ready(function($) {
        var mediaUploader;
        $('#guesty_upload_fallback_btn').click(function(e) {
            e.preventDefault();
            if (mediaUploader) { mediaUploader.open(); return; }
            mediaUploader = wp.media.frames.file_frame = wp.media({ title: 'Choose a Fallback Image', button: { text: 'Use this Image' }, multiple: false });
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#guesty_fallback_image').val(attachment.url);
                $('#guesty_fallback_preview').html('<img src="'+attachment.url+'" style="width: 100%; height: 100%; object-fit: cover;">');
                $('#guesty_clear_fallback_btn').show();
            });
            mediaUploader.open();
        });
        $('#guesty_clear_fallback_btn').click(function(e){
            e.preventDefault(); $('#guesty_fallback_image').val(''); $('#guesty_fallback_preview').html('<span style="color: #646970; font-size: 13px;">No Image Set</span>'); $(this).hide();
        });
    });
</script>
