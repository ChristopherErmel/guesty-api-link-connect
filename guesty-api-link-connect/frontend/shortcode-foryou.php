<?php if (!defined('ABSPATH')) exit; ?>

<div class="gvs-container gvs-foryou-container" id="<?php echo esc_attr($unique_wrapper_id); ?>" data-gvs-init="false" style="
    display: none !important;
    --gvs-btn-color: <?php echo esc_attr($btn_color); ?>;
    --gvs-btn-hover: <?php echo esc_attr($btn_hover_color); ?>;
    --gvs-badge-pad: <?php echo esc_attr($badge_padding); ?>px;
    --gvs-badge-fs: <?php echo esc_attr($badge_font_size); ?>px;
    --gvs-badge-tt: <?php echo esc_attr($badge_transform); ?>;
    --gvs-badge-fw: <?php echo esc_attr($badge_weight); ?>;
    --gvs-cols-m: <?php echo esc_attr($row_m); ?>;
    --gvs-cols-t: <?php echo esc_attr($row_t); ?>;
    --gvs-cols-d: <?php echo esc_attr($row_d); ?>;
">
    <h2 class="gvs-title" style="display: flex; align-items: center; gap: 10px; margin-bottom: 24px;">
        <i class="ph <?php echo esc_attr($fy_icon); ?>" style="color: <?php echo esc_attr($fy_icon_color); ?>; font-size: 28px;"></i>
        <?php echo esc_html($title); ?>
    </h2>
    
    <!-- Grid -->
    <div class="gvs-grid">
        <!-- The JS Recommendation Engine will inject the cards here -->
    </div>
</div>

<script>
    window.guestyAlcInstances = window.guestyAlcInstances || {};
    window.guestyAlcInstances['<?php echo esc_js($unique_wrapper_id); ?>'] = {
        isForYouWidget: true,
        localListings: <?php echo empty($local_listings_json) ? '[]' : $local_listings_json; ?>,
        useAjax: false,
        useUnitPages: <?php echo defined('GUESTY_ALC_UNIT_PAGES_ACTIVE') ? 'true' : 'false'; ?>,
        homeUrl: <?php echo json_encode(home_url('/')); ?>,
        unitPageSlug: <?php echo json_encode(get_option('guesty_unit_page_slug', 'property')); ?>,
        searchOnly: false,
        redirectUrl: '',
        isGlobalReviewsOn: <?php echo json_encode($show_reviews_global === 'yes'); ?>,
        customBtnText: <?php echo json_encode($btn_text); ?>,
        propertyBaseUrl: <?php echo json_encode($base_url); ?>,
        fallbackImg: <?php echo json_encode($fallback_img); ?>,
        customBadgesData: <?php echo json_encode($custom_badges); ?>,
        customCountLabel: '',
        showPetBadge: <?php echo json_encode($show_pet_badge); ?>,
        petBadgeIcon: <?php echo json_encode($pet_badge_icon); ?>,
        scStartTab: '',
        scHideTabs: true,
        customPriceLabel: <?php echo json_encode($price_label); ?>,
        customCurrencyMode: <?php echo json_encode($currency_display); ?>,
        rowDesktop: <?php echo $row_d; ?>,
        rowTablet: <?php echo $row_t; ?>,
        rowMobile: <?php echo $row_m; ?>,
        rowsLoadD: 1,
        rowsLoadT: 1,
        rowsLoadM: 1,
        
        // Pass dynamic badge settings to JavaScript
        fyBadgeText: <?php echo json_encode($fy_badge_text); ?>,
        fyBadgeBg: <?php echo json_encode($fy_badge_bg); ?>,
        fyBadgeColor: <?php echo json_encode($fy_badge_color); ?>
    };
</script>
