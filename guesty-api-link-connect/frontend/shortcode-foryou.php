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
        <svg viewBox="0 0 24 24" width="28" height="28" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round" style="color: #f59e0b; fill: #f59e0b;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
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
        rowsLoadM: 1
    };
</script>