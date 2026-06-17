<?php if (!defined('ABSPATH')) exit; ?>

<?php if (!empty($additional_css)): ?>
<style>
    /* User Custom CSS */
    <?php echo $additional_css; ?>
</style>
<?php endif; ?>

<div class="gvs-container" id="<?php echo esc_attr($unique_wrapper_id); ?>" data-gvs-init="false" style="
    --gvs-btn-color: <?php echo esc_attr($btn_color); ?>;
    --gvs-btn-hover: <?php echo esc_attr($btn_hover_color); ?>;
    --gvs-lm-btn: <?php echo esc_attr($load_more_btn_color); ?>;
    --gvs-tab-default: <?php echo esc_attr($tab_default_color); ?>;
    --gvs-tab-hover: <?php echo esc_attr($tab_hover_color); ?>;
    --gvs-tab-active: <?php echo esc_attr($tab_active_color); ?>;
    --gvs-scroll-bg: <?php echo esc_attr($scroll_btn_bg); ?>;
    --gvs-scroll-icon: <?php echo esc_attr($scroll_btn_color); ?>;
    --gvs-badge-pad: <?php echo esc_attr($badge_padding); ?>px;
    --gvs-badge-fs: <?php echo esc_attr($badge_font_size); ?>px;
    --gvs-badge-tt: <?php echo esc_attr($badge_transform); ?>;
    --gvs-badge-fw: <?php echo esc_attr($badge_weight); ?>;
    --gvs-sb-bg: <?php echo esc_attr($search_bg); ?>;
    --gvs-sb-text: <?php echo esc_attr($search_text); ?>;
    --gvs-sb-label: <?php echo esc_attr($search_label); ?>;
    --gvs-sb-btn-bg: <?php echo esc_attr($search_btn_bg); ?>;
    --gvs-sb-btn-text: <?php echo esc_attr($search_btn_text); ?>;
    --gvs-cols-m: <?php echo esc_attr($row_m); ?>;
    --gvs-cols-t: <?php echo esc_attr($row_t); ?>;
    --gvs-cols-d: <?php echo esc_attr($row_d); ?>;
">
    <?php if (!$search_only): ?>
        <h2 class="gvs-title"><?php echo esc_html($main_header_text); ?></h2>
    <?php endif; ?>
    
    <!-- Optional Search Bar -->
    <?php if ($enable_search_bar || $search_only): ?>
    <div class="gvs-search-bar-container">
        <?php
        $search_fields_html = [];

        if ($show_location) {
            $search_fields_html[] = '<div class="gvs-search-field">
                <label>Location</label>
                <select class="gvs-search-loc">
                    <option value="">Where to?</option>' . 
                    implode('', array_map(function($loc) { return '<option value="'.esc_attr($loc).'">'.esc_html($loc).'</option>'; }, $unique_locations)) . 
                '</select>
            </div>';
        }

        if ($show_dates) {
            $search_fields_html[] = '<div class="gvs-search-field"><label>Check in</label><input type="text" class="gvs-search-checkin" placeholder="Add dates" readonly></div>';
            $search_fields_html[] = '<div class="gvs-search-field"><label>Check out</label><input type="text" class="gvs-search-checkout" placeholder="Add dates" readonly></div>';
        }

        if ($show_guests) {
            $search_fields_html[] = '<div class="gvs-search-field"><label>Guests</label><input type="number" class="gvs-search-guests" placeholder="Add guests" min="1"></div>';
        }

        if ($show_bedrooms) {
            $search_fields_html[] = '<div class="gvs-search-field"><label>Bedrooms</label><input type="number" class="gvs-search-bedrooms" placeholder="Any" min="1"></div>';
        }

        if ($show_amenity) {
            $am_options = '<option value="">Any Amenity</option>';
            foreach($search_amenities as $am) {
                $am_options .= '<option value="'.esc_attr($am).'">'.esc_html($am).'</option>';
            }
            $search_fields_html[] = '<div class="gvs-search-field"><label>Amenity</label><select class="gvs-search-amenity">' . $am_options . '</select></div>';
        }

        if ($show_pets) {
            $search_fields_html[] = '<div class="gvs-search-field"><label>Pets</label><select class="gvs-search-pets"><option value="">Any</option><option value="1">Pet Friendly</option></select></div>';
        }

        echo '<div class="gvs-search-bar">';
        if (!empty($search_fields_html)) {
            echo implode('<div class="gvs-search-divider"></div>', $search_fields_html);
        }
        echo '<button class="gvs-search-button gvs-do-search">Search</button>';
        echo '<button class="gvs-clear-button">Clear</button>';
        echo '</div>';
        ?>
    </div>
    <?php endif; ?>

    <?php if (!$search_only): ?>
    <!-- Dynamic Tabs -->
    <div class="gvs-tabs-wrapper">
        <button type="button" class="gvs-scroll-btn left gvs-scroll-left" style="display: none;" aria-label="Scroll left">
            <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
        <div class="gvs-tabs">
            <div class="gvs-tab active" data-category="All">
                <i class="ph <?php echo esc_attr($all_tab_icon); ?> gvs-icon"></i>
                <?php echo esc_html($all_tab_text); ?>
            </div>
            
            <?php foreach ($active_filters as $filter): ?>
                <?php 
                $icon_class = isset($custom_icons[$filter]) && !empty($custom_icons[$filter]) ? $custom_icons[$filter] : $this->api->get_default_icon_class_for_amenity($filter);
                ?>
                <div class="gvs-tab" data-category="<?php echo esc_attr($filter); ?>">
                    <i class="ph <?php echo esc_attr($icon_class); ?> gvs-icon"></i>
                    <?php echo esc_html($filter); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="gvs-scroll-btn right gvs-scroll-right" style="display: none;" aria-label="Scroll right">
            <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </button>
    </div>

    <!-- Controls Row: Sort & Count -->
    <div class="gvs-controls-row">
        <div class="gvs-sort-wrap">
            <select class="gvs-sort-dropdown">
                <option value="default" selected>Sort by: ---</option>
                <?php 
                $all_sort_labels = [
                    'name-asc' => 'Name (A-Z)', 'name-desc' => 'Name (Z-A)',
                    'price-desc' => 'Price (High - Low)', 'price-asc' => 'Price (Low - High)',
                    'beds-desc' => 'Bedrooms (High - Low)', 'beds-asc' => 'Bedrooms (Low - High)',
                    'guests-desc' => 'Guests (High - Low)', 'guests-asc' => 'Guests (Low - High)',
                    'rating-desc' => 'Highest Rated'
                ];
                foreach($enabled_sorts as $s_key) {
                    if(isset($all_sort_labels[$s_key])) {
                        echo "<option value='".esc_attr($s_key)."'>Sort by: " . esc_html($all_sort_labels[$s_key]) . "</option>";
                    }
                }
                ?>
            </select>
        </div>
        <div class="gvs-count-text"></div>
    </div>

    <!-- Grid -->
    <div class="gvs-grid">
        <?php 
        $skeleton_count = $row_d * $rows_load_d;
        if ($skeleton_count > 12) $skeleton_count = 12; 
        if ($skeleton_count < 4) $skeleton_count = 4;
        
        for($i = 0; $i < $skeleton_count; $i++) {
            echo '<div class="gvs-skeleton-card">
                <div class="gvs-skeleton gvs-skeleton-img"></div>
                <div class="gvs-card-content">
                    <div class="gvs-skeleton" style="width: 70%; height: 20px; margin-bottom: 8px;"></div>
                    <div class="gvs-skeleton" style="width: 40%; height: 14px; margin-bottom: 16px;"></div>
                    <div class="gvs-skeleton" style="width: 90%; height: 14px; margin-bottom: 16px;"></div>
                    <div class="gvs-skeleton" style="width: 30%; height: 16px; margin: auto auto 16px auto;"></div>
                    <div class="gvs-skeleton-footer">
                        <div class="gvs-skeleton" style="width: 110px; height: 38px; border-radius: 9999px;"></div>
                        <div style="display:flex; flex-direction:column; align-items:flex-end; gap:4px;">
                            <div class="gvs-skeleton" style="width: 70px; height: 18px;"></div>
                            <div class="gvs-skeleton" style="width: 50px; height: 12px;"></div>
                        </div></div></div></div>';
        }
        ?>
    </div>

    <!-- Pagination Load More -->
    <div class="gvs-load-more-wrap" style="display: none; text-align: center; margin-top: 40px;">
        <button class="gvs-view-btn gvs-load-more-btn" style="background-color: var(--gvs-lm-btn); padding: 12px 32px; font-size: 16px;"><?php echo esc_html($load_more_btn_text); ?></button>
    </div>
    <?php endif; ?>
</div>

<!-- Configuration instance safely decoupled into window object for JS processing -->
<script>
    window.guestyAlcInstances = window.guestyAlcInstances || {};
    window.guestyAlcInstances['<?php echo esc_js($unique_wrapper_id); ?>'] = {
        localListings: <?php echo empty($local_listings_json) ? '[]' : $local_listings_json; ?>,
        useAjax: <?php echo $use_ajax ? 'true' : 'false'; ?>,
        useUnitPages: <?php echo defined('GUESTY_ALC_UNIT_PAGES_ACTIVE') ? 'true' : 'false'; ?>,
        homeUrl: <?php echo json_encode(home_url('/')); ?>,
        unitPageSlug: <?php echo json_encode(get_option('guesty_unit_page_slug', 'property')); ?>,
        searchOnly: <?php echo json_encode($search_only); ?>,
        redirectUrl: <?php echo json_encode($redirect_url); ?>,
        customLoadMoreText: <?php echo json_encode($load_more_btn_text); ?>,
        isGlobalReviewsOn: <?php echo json_encode($show_reviews_global === 'yes'); ?>,
        customBtnText: <?php echo json_encode($btn_text); ?>,
        propertyBaseUrl: <?php echo json_encode($base_url); ?>,
        fallbackImg: <?php echo json_encode($fallback_img); ?>,
        customBadgesData: <?php echo json_encode($custom_badges); ?>,
        customCountLabel: <?php echo json_encode($count_label); ?>,
        showPetBadge: <?php echo json_encode($show_pet_badge); ?>,
        petBadgeIcon: <?php echo json_encode($pet_badge_icon); ?>,
        scStartTab: <?php echo json_encode($atts['start_tab']); ?>,
        scHideTabs: <?php echo json_encode($atts['hide_tabs'] === 'true'); ?>,
        customPriceLabel: <?php echo json_encode($price_label); ?>,
        customCurrencyMode: <?php echo json_encode($currency_display); ?>,
        rowDesktop: <?php echo $row_d; ?>,
        rowTablet: <?php echo $row_t; ?>,
        rowMobile: <?php echo $row_m; ?>,
        rowsLoadD: <?php echo $rows_load_d; ?>,
        rowsLoadT: <?php echo $rows_load_t; ?>,
        rowsLoadM: <?php echo $rows_load_m; ?>
    };
</script>