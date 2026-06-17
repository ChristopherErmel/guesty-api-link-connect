// --- Global Flatpickr Loader ---
// Dynamically loads the premium Flatpickr date engine and injects a mathematically strict custom grid theme
function loadFlatpickr(callback) {
    if (window.flatpickr) {
        callback();
        return;
    }
    if (document.getElementById('flatpickr-script')) {
        const checkInterval = setInterval(() => {
            if (window.flatpickr) {
                clearInterval(checkInterval);
                callback();
            }
        }, 50);
        return;
    }

    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css';
    document.head.appendChild(link);

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
        .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange, .flatpickr-day.selected.inRange, .flatpickr-day.startRange.inRange, .flatpickr-day.endRange.inRange { background: var(--gvs-btn-color, #2563eb) !important; border-color: var(--gvs-btn-color, #2563eb) !important; color: #fff !important; box-shadow: none !important; }
        .flatpickr-day.inRange, .flatpickr-day.prevMonthDay.inRange, .flatpickr-day.nextMonthDay.inRange, .flatpickr-day.today.inRange { background: #f1f5f9 !important; border-color: #f1f5f9 !important; box-shadow: -5px 0 0 #f1f5f9, 5px 0 0 #f1f5f9 !important; }
        .flatpickr-day:hover { background: #e2e8f0 !important; color: #1e293b !important; }
    `;
    document.head.appendChild(theme);

    const script = document.createElement('script');
    script.id = 'flatpickr-script';
    script.src = 'https://cdn.jsdelivr.net/npm/flatpickr';
    script.onload = callback;
    document.head.appendChild(script);
}

// --- User Profile Tracking System ---
const GVS_PROFILE_KEY = 'gvs_user_profile';

function getGvsProfile() {
    try {
        const data = localStorage.getItem(GVS_PROFILE_KEY);
        return data ? JSON.parse(data) : { searches: [], viewed_units: [] };
    } catch (e) {
        return { searches: [], viewed_units: [] };
    }
}

function saveGvsProfile(profile) {
    localStorage.setItem(GVS_PROFILE_KEY, JSON.stringify(profile));
}

function trackPropertyClick(unitId) {
    if (!unitId) return;
    const profile = getGvsProfile();
    // Remove if already exists so we can move it to the front (most recent)
    profile.viewed_units = profile.viewed_units.filter(id => id !== unitId);
    profile.viewed_units.unshift(unitId);
    // Keep only the last 20 viewed units to save storage space
    if (profile.viewed_units.length > 20) profile.viewed_units.pop();
    saveGvsProfile(profile);
}

// Global click listener to track unit clicks anywhere on the site
document.addEventListener('click', function(e) {
    const card = e.target.closest('.gvs-card');
    if (card && card.dataset.unitId) {
        trackPropertyClick(card.dataset.unitId);
    }
});


document.addEventListener('DOMContentLoaded', function() {
        
    const allContainers = document.querySelectorAll('.gvs-container, .gvs-foryou-container');
    
    allContainers.forEach(container => {
        if (container.dataset.gvsInit === 'true') return;
        container.dataset.gvsInit = 'true';

        const config = window.guestyAlcInstances[container.id];
        if (!config) return;

        // Force hide ForYou widget instantly to prevent any Theme/Elementor overrides causing skeleton flashes
        if (config.isForYouWidget) {
            container.style.display = 'none';
        }

        const {
            localListings, useAjax, useUnitPages, homeUrl, unitPageSlug, searchOnly, redirectUrl, customLoadMoreText, isGlobalReviewsOn, customBtnText,
            propertyBaseUrl, fallbackImg, customBadgesData, customCountLabel, showPetBadge,
            petBadgeIcon, scStartTab, scHideTabs, customPriceLabel, customCurrencyMode,
            rowDesktop, rowTablet, rowMobile, rowsLoadD, rowsLoadT, rowsLoadM, isForYouWidget,
            fyBadgeText, fyBadgeBg, fyBadgeColor
        } = config;

        const ajaxUrl = typeof guestyAlcvars !== 'undefined' ? guestyAlcvars.ajaxUrl : '';
        const nonce = typeof guestyAlcvars !== 'undefined' ? guestyAlcvars.nonce : '';
        
        const grid = container.querySelector('.gvs-grid');
        const countDisplay = container.querySelector('.gvs-count-text');
        const tabs = container.querySelectorAll('.gvs-tab');
        const loadMoreWrap = container.querySelector('.gvs-load-more-wrap');
        const loadMoreBtn = container.querySelector('.gvs-load-more-btn');
        const sortSelect = container.querySelector('.gvs-sort-dropdown');
        const searchBtn = container.querySelector('.gvs-do-search');
        const clearBtn = container.querySelector('.gvs-clear-button');

        const tabsContainerWrapper = container.querySelector('.gvs-tabs-wrapper');
        const tabsContainer = container.querySelector('.gvs-tabs');
        const scrollLeftBtn = container.querySelector('.gvs-scroll-left');
        const scrollRightBtn = container.querySelector('.gvs-scroll-right');

        const checkinInput = container.querySelector('.gvs-search-checkin');
        const checkoutInput = container.querySelector('.gvs-search-checkout');

        let itemsShown = 0;
        let fpInstance = null;

        if (scHideTabs && tabsContainerWrapper) {
            tabsContainerWrapper.style.display = 'none';
        }

        const urlParams = new URLSearchParams(window.location.search);
        
        // Setup Datepicker
        if (checkinInput && checkoutInput) {
            checkinInput.style.cssText = 'width: 100%; border: none; background: transparent; outline: none; cursor: pointer; text-overflow: ellipsis; white-space: nowrap; overflow: hidden; padding: 0; box-shadow: none;';
            checkoutInput.style.cssText = 'width: 100%; border: none; background: transparent; outline: none; cursor: pointer; text-overflow: ellipsis; white-space: nowrap; overflow: hidden; padding: 0; box-shadow: none;';

            const fpAnchor = document.createElement('input');
            fpAnchor.type = 'text';
            fpAnchor.style.position = 'absolute';
            fpAnchor.style.visibility = 'hidden';
            fpAnchor.style.width = '0';
            fpAnchor.style.height = '0';
            checkinInput.parentNode.appendChild(fpAnchor);
            
            loadFlatpickr(() => {
                fpInstance = flatpickr(fpAnchor, {
                    mode: "range",
                    minDate: "today",
                    showMonths: window.innerWidth > 768 ? 2 : 1, 
                    dateFormat: "Y-m-d",
                    disableMobile: true, 
                    positionElement: checkinInput, 
                    onChange: function(selectedDates, dateStr, instance) {
                        if (selectedDates.length === 1) {
                            checkinInput.value = instance.formatDate(selectedDates[0], "Y-m-d");
                            checkoutInput.value = '';
                        } else if (selectedDates.length === 2) {
                            checkinInput.value = instance.formatDate(selectedDates[0], "Y-m-d");
                            checkoutInput.value = instance.formatDate(selectedDates[1], "Y-m-d");
                        } else {
                            checkinInput.value = '';
                            checkoutInput.value = '';
                        }
                    }
                });

                const checkinWrapper = checkinInput.closest('.gvs-search-field');
                const checkoutWrapper = checkoutInput.closest('.gvs-search-field');

                if (checkinWrapper) {
                    checkinWrapper.addEventListener('click', () => { if (!fpInstance.isOpen) fpInstance.open(); });
                    checkinWrapper.style.cursor = 'pointer';
                }
                if (checkoutWrapper) {
                    checkoutWrapper.addEventListener('click', () => { if (!fpInstance.isOpen) fpInstance.open(); });
                    checkoutWrapper.style.cursor = 'pointer';
                }

                let preCheckin = urlParams.get('gvs_checkin');
                let preCheckout = urlParams.get('gvs_checkout');
                if (preCheckin && preCheckout) {
                    fpInstance.setDate([preCheckin, preCheckout]);
                    checkinInput.value = preCheckin;
                    checkoutInput.value = preCheckout;
                } else if (preCheckin) {
                    fpInstance.setDate(preCheckin);
                    checkinInput.value = preCheckin;
                }
            });
        }
        
        // Auto-fill from URL
        if (urlParams.has('gvs_loc')) { const locEl = container.querySelector('.gvs-search-loc'); if (locEl) locEl.value = urlParams.get('gvs_loc'); }
        if (urlParams.has('gvs_guests')) { const guestsEl = container.querySelector('.gvs-search-guests'); if (guestsEl) guestsEl.value = urlParams.get('gvs_guests'); }
        if (urlParams.has('gvs_beds')) { const bedsEl = container.querySelector('.gvs-search-bedrooms'); if (bedsEl) bedsEl.value = urlParams.get('gvs_beds'); }
        if (urlParams.has('gvs_amenity')) { const amEl = container.querySelector('.gvs-search-amenity'); if (amEl) amEl.value = urlParams.get('gvs_amenity'); }
        if (urlParams.has('gvs_pets')) { const petEl = container.querySelector('.gvs-search-pets'); if (petEl) petEl.value = urlParams.get('gvs_pets'); }

        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                container.querySelectorAll('.gvs-search-field input, .gvs-search-field select').forEach(el => el.value = '');
                if (fpInstance) fpInstance.clear();
                if (!searchOnly && !isForYouWidget) {
                    const activeCategory = container.querySelector('.gvs-tab.active') ? container.querySelector('.gvs-tab.active').getAttribute('data-category') : 'All';
                    renderListings(activeCategory);
                }
            });
        }

        // Tab Scrolling
        function updateScrollButtons() {
            if (!tabsContainer || !scrollLeftBtn || !scrollRightBtn || scHideTabs) return;
            const maxScrollLeft = tabsContainer.scrollWidth - tabsContainer.clientWidth;
            scrollLeftBtn.style.display = tabsContainer.scrollLeft > 2 ? 'flex' : 'none';
            scrollRightBtn.style.display = tabsContainer.scrollLeft < maxScrollLeft - 2 ? 'flex' : 'none';
        }

        if (tabsContainer && !scHideTabs) {
            scrollLeftBtn.addEventListener('click', () => tabsContainer.scrollBy({ left: -250, behavior: 'smooth' }));
            scrollRightBtn.addEventListener('click', () => tabsContainer.scrollBy({ left: 250, behavior: 'smooth' }));
            tabsContainer.addEventListener('scroll', updateScrollButtons);
            window.addEventListener('resize', updateScrollButtons);
            setTimeout(updateScrollButtons, 150); 
        }

        function getItemsPerPage() {
            if (window.innerWidth <= 768) return rowMobile * rowsLoadM; 
            else if (window.innerWidth <= 1100) return rowTablet * rowsLoadT;
            else return rowDesktop * rowsLoadD;
        }
        
        function getSkeletonHTML(count) {
            let html = '';
            for(let i=0; i<count; i++) {
                html += `<div class="gvs-skeleton-card">
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
                            </div>
                        </div>
                    </div>
                </div>`;
            }
            return html;
        }

        // --- Recommendation Engine Logic ---
        function getRecommendedListings(limit, offset) {
            const profile = getGvsProfile();
            
            // If they have never searched or clicked anything, hide widget completely
            if (profile.searches.length === 0 && profile.viewed_units.length === 0) {
                return { items: [], total: 0, hasMore: false };
            }

            const recentSearch = profile.searches.length > 0 ? profile.searches[0] : null;
            
            let scoredListings = localListings.map(item => {
                let score = 0;
                
                // Huge boost if they literally viewed it recently (History)
                if (profile.viewed_units.includes(item.id)) {
                    score += 15;
                }
                
                // Match recent search parameters (fuzzier logic)
                if (recentSearch) {
                    if (recentSearch.loc) {
                        const locStr = ((item.city||'') + ', ' + (item.country||'')).trim().toLowerCase();
                        const justCity = (item.city||'').toLowerCase();
                        const searchLoc = recentSearch.loc.toLowerCase();
                        if (locStr.includes(searchLoc) || justCity.includes(searchLoc)) {
                            score += 8;
                        }
                    }
                    if (recentSearch.guests > 0) {
                        // Recommend units that fit them exactly or slightly larger
                        if (item.accommodates >= recentSearch.guests) {
                            score += 5;
                        }
                    }
                    if (recentSearch.pets === '1' && item.allows_pets) {
                        score += 5;
                    }
                    if (recentSearch.checkin && recentSearch.checkout) {
                        score += 2;
                    }
                }
                
                // Baseline: Give everything a score of 1 or higher so the widget never vanishes
                // Even if nothing matches perfectly, we fallback to showing highly rated items
                item._recoScore = score > 0 ? score : (item.rating || 1);
                return item;
            });

            // Filter out 0 scores (impossible now due to baseline, but safe)
            scoredListings = scoredListings.filter(i => i._recoScore > 0);
            
            // Sort by highest score first
            scoredListings.sort((a, b) => b._recoScore - a._recoScore);
            
            const results = scoredListings.slice(offset, offset + limit);
            return { items: results, total: scoredListings.length, hasMore: (offset + limit) < scoredListings.length };
        }

        async function fetchListingsData(category, sortBy, offset, limit) {
            
            // If this is the "For You" widget, hijack the fetch and use our Recommendation Engine
            if (isForYouWidget) {
                return getRecommendedListings(limit, offset);
            }

            const searchLoc = container.querySelector('.gvs-search-loc') ? container.querySelector('.gvs-search-loc').value : '';
            const searchCheckin = container.querySelector('.gvs-search-checkin') ? container.querySelector('.gvs-search-checkin').value : '';
            const searchCheckout = container.querySelector('.gvs-search-checkout') ? container.querySelector('.gvs-search-checkout').value : '';
            const searchGuests = container.querySelector('.gvs-search-guests') ? parseInt(container.querySelector('.gvs-search-guests').value) || 0 : 0;
            const searchBedrooms = container.querySelector('.gvs-search-bedrooms') ? parseInt(container.querySelector('.gvs-search-bedrooms').value) || 0 : 0;
            const searchPets = container.querySelector('.gvs-search-pets') ? container.querySelector('.gvs-search-pets').value : '';
            const searchAmenity = container.querySelector('.gvs-search-amenity') ? container.querySelector('.gvs-search-amenity').value : '';

            const hasDates = searchCheckin && searchCheckout;

            if (useAjax || hasDates) {
                const formData = new URLSearchParams();
                formData.append('action', 'guesty_load_properties');
                formData.append('nonce', nonce);
                formData.append('category', category);
                formData.append('sort_by', sortBy);
                formData.append('offset', offset);
                formData.append('limit', limit);
                formData.append('search_checkin', searchCheckin);
                formData.append('search_checkout', searchCheckout);
                formData.append('search_loc', searchLoc);
                formData.append('search_guests', searchGuests);
                formData.append('search_bedrooms', searchBedrooms);
                formData.append('search_pets', searchPets);
                formData.append('search_amenity', searchAmenity);
                
                try {
                    const response = await fetch(ajaxUrl, { method: 'POST', body: formData, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
                    const result = await response.json();
                    if (result.success) return result.data;
                    return { items: [], total: 0, hasMore: false };
                } catch (e) {
                    return { items: [], total: 0, hasMore: false };
                }
            } else {
                let filtered = localListings.filter(item => {
                    if (!item.categories.includes(category)) return false;
                    if (searchLoc) {
                        const locStr = (item.city + ', ' + item.country).trim();
                        if (locStr.toLowerCase() !== searchLoc.toLowerCase() && item.city.toLowerCase() !== searchLoc.toLowerCase()) return false;
                    }
                    if (searchGuests > 0 && item.accommodates < searchGuests) return false;
                    if (searchBedrooms > 0 && item.bedrooms < searchBedrooms) return false;
                    if (searchPets === '1' && !item.allows_pets) return false;
                    if (searchAmenity) {
                        if (!item.raw_amenities || !item.raw_amenities.some(am => am.toLowerCase() === searchAmenity.toLowerCase())) return false;
                    }
                    return true;
                });
                
                let sorted = filtered.sort((a, b) => {
                    switch (sortBy) {
                        case 'default':
                            if (a.display_order !== b.display_order) return a.display_order - b.display_order;
                            return a.id.localeCompare(b.id);
                        case 'name-asc': return a.title.localeCompare(b.title);
                        case 'name-desc': return b.title.localeCompare(a.title);
                        case 'price-asc': return a.price - b.price;
                        case 'price-desc': return b.price - a.price;
                        case 'beds-desc': return b.bedrooms - a.bedrooms;
                        case 'beds-asc': return a.bedrooms - b.bedrooms;
                        case 'guests-desc': return b.accommodates - a.accommodates;
                        case 'guests-asc': return a.accommodates - b.accommodates;
                        case 'rating-desc': return b.rating - a.rating;
                        default: return 0;
                    }
                });
                
                return { items: sorted.slice(offset, offset + limit), total: sorted.length, hasMore: (offset + limit) < sorted.length };
            }
        }

        async function renderListings(category, append = false) {
            const sortBy = sortSelect ? sortSelect.value : 'default';
            const limit = getItemsPerPage();
            
            if (!append) {
                if(grid) grid.innerHTML = getSkeletonHTML(limit);
                itemsShown = 0;
                if(loadMoreWrap) loadMoreWrap.style.display = 'none';
            } else {
                if(loadMoreBtn) {
                    loadMoreBtn.innerText = 'Loading...';
                    loadMoreBtn.style.opacity = '0.7';
                    loadMoreBtn.style.pointerEvents = 'none';
                }
            }
            
            const data = await fetchListingsData(category, sortBy, itemsShown, limit);
            
            // Handle empty recommendations cleanly
            if (isForYouWidget && data.items.length === 0) {
                container.style.display = 'none'; // Keep hidden if absolutely nothing to show
                return;
            } else if (isForYouWidget) {
                container.style.display = 'block'; // Reveal the widget smoothly!
            }

            if (!append && grid) {
                grid.innerHTML = '';
                if (countDisplay) countDisplay.textContent = `Showing ${data.total} ${customCountLabel}`;
            }
            
            if (data.items.length === 0 && !append && grid) {
                grid.innerHTML = '<p style="grid-column: 1/-1; color: #6b7280; padding: 20px 0; text-align: center;">No properties found for this search.</p>';
                if (loadMoreWrap) loadMoreWrap.style.display = 'none';
                return;
            }
            
            const searchCheckin = container.querySelector('.gvs-search-checkin') ? container.querySelector('.gvs-search-checkin').value : '';
            const searchCheckout = container.querySelector('.gvs-search-checkout') ? container.querySelector('.gvs-search-checkout').value : '';
            const searchGuestsURL = container.querySelector('.gvs-search-guests') ? container.querySelector('.gvs-search-guests').value : '';

            data.items.forEach(item => {
                let itemCurrency = item.currency || 'CAD';
                let displayCurrencyString = itemCurrency;
                
                if (customCurrencyMode !== 'auto' && customCurrencyMode !== 'hidden') {
                    itemCurrency = customCurrencyMode;
                    displayCurrencyString = customCurrencyMode;
                }

                const priceFmt = new Intl.NumberFormat(undefined, { style: 'currency', currency: itemCurrency, maximumFractionDigits: 0 }).format(item.price);
                const finalCurrencyText = customCurrencyMode === 'hidden' ? '' : ` ${displayCurrencyString}`;
                
                const isDynamic = item.is_dynamic_price || false;
                const finalPriceLabel = isDynamic ? "total" : customPriceLabel;
                
                let propertyLink = '#';
                if (useUnitPages) {
                    propertyLink = homeUrl + unitPageSlug + '/' + item.slug + '/';
                } else {
                    propertyLink = propertyBaseUrl ? (propertyBaseUrl.endsWith('/') ? propertyBaseUrl + item.id : propertyBaseUrl + '/' + item.id) : '#';
                }
                
                if (propertyLink !== '#' && searchCheckin && searchCheckout) {
                    const joinChar = propertyLink.includes('?') ? '&' : '?';
                    if (useUnitPages) {
                        propertyLink += `${joinChar}gvs_checkin=${searchCheckin}&gvs_checkout=${searchCheckout}`;
                        if (searchGuestsURL) propertyLink += `&gvs_guests=${searchGuestsURL}`;
                    } else {
                        propertyLink += `${joinChar}checkIn=${searchCheckin}&checkOut=${searchCheckout}`;
                        if (searchGuestsURL) propertyLink += `&guests=${searchGuestsURL}`;
                    }
                }

                const petIconHTML = (showPetBadge && item.allows_pets) ? `<div class="gvs-pet-badge" title="Pets Allowed"><i class="ph ${petBadgeIcon}"></i></div>` : '';
                
                let customBadgeHTML = '';
                // If it's a recommendation, override with a unique badge
                if (isForYouWidget) {
                    const bText = fyBadgeText || 'Recommended';
                    const bBg = fyBadgeBg || '#f59e0b';
                    const bColor = fyBadgeColor || '#ffffff';
                    customBadgeHTML = `<div class="gvs-badge-straight" style="background-color: ${bBg}; color: ${bColor};">${bText}</div>`;
                } else if (customBadgesData[item.id] && customBadgesData[item.id].text) {
                    const bData = customBadgesData[item.id];
                    const badgeClass = bData.style === 'straight' ? 'gvs-badge-straight' : 'gvs-badge-diagonal';
                    const tColor = bData.text_color ? bData.text_color : '#000000';
                    customBadgeHTML = `<div class="${badgeClass}" style="background-color: ${bData.color}; color: ${tColor};">${bData.text}</div>`;
                }

                const hasReviews = item.reviews > 0;
                const showThisReview = isGlobalReviewsOn && !item.hide_reviews && hasReviews;

                const ratingHTML = isGlobalReviewsOn ? `
                    <div class="gvs-rating" style="visibility: ${showThisReview ? 'visible' : 'hidden'}; min-height: 16px;">
                        ${showThisReview ? `<span class="gvs-score">${Number(item.rating).toFixed(1)}</span><span>${item.reviews} reviews</span>` : ''}
                    </div>
                ` : '';

                const specsHTML = `${item.bedrooms} Bdrms <span class="gvs-specs-dot">&#9679;</span> ${item.bathrooms} Baths <span class="gvs-specs-dot">&#9679;</span> ${item.accommodates} Guests`;
                const imgUrl = item.image ? item.image : fallbackImg;

                // Notice: We added data-unit-id="${item.id}" here to track clicks!
                const cardHTML = `
                    <a href="${propertyLink}" target="_blank" class="gvs-card" data-unit-id="${item.id}">
                        <div class="gvs-card-img-wrapper">
                            <img src="${imgUrl}" alt="${item.title}" class="gvs-card-img" loading="lazy">
                            ${customBadgeHTML}
                            ${petIconHTML}
                        </div>
                        <div class="gvs-card-content">
                            <div class="gvs-location">${item.title}</div>
                            <div class="gvs-type">${item.city}, ${item.country}</div>
                            <div class="gvs-specs">${specsHTML}</div>
                            
                            ${ratingHTML}
                            <div class="gvs-footer">
                                <div class="gvs-view-btn">${customBtnText}</div>
                                <div class="gvs-price-wrap">
                                    <div class="gvs-price">${priceFmt}${finalCurrencyText}</div>
                                    <div class="gvs-price-label">${finalPriceLabel}</div>
                                </div>
                            </div>
                        </div>
                    </a>
                `;
                if (grid) grid.insertAdjacentHTML('beforeend', cardHTML);
            });
            
            itemsShown += data.items.length;
            
            if (data.hasMore && loadMoreWrap && loadMoreBtn) {
                loadMoreWrap.style.display = 'block';
                loadMoreBtn.innerText = customLoadMoreText;
                loadMoreBtn.style.opacity = '1';
                loadMoreBtn.style.pointerEvents = 'auto';
            } else if (loadMoreWrap) {
                loadMoreWrap.style.display = 'none';
            }
        }

        let initialCategory = 'All';
        if (scStartTab !== '') {
            const targetTab = Array.from(tabs).find(t => t.getAttribute('data-category').toLowerCase() === scStartTab.toLowerCase());
            if (targetTab) {
                tabs.forEach(t => t.classList.remove('active'));
                targetTab.classList.add('active');
                initialCategory = targetTab.getAttribute('data-category');
            }
        }
        
        if (!searchOnly) {
            setTimeout(() => { renderListings(initialCategory); }, 150);
        }

        if (searchBtn) {
            searchBtn.addEventListener('click', () => {

                // --- SAVE SEARCH TO PROFILE ---
                const locVal = container.querySelector('.gvs-search-loc')?.value || '';
                const guestsVal = container.querySelector('.gvs-search-guests')?.value || 0;
                const petsVal = container.querySelector('.gvs-search-pets')?.value || '';
                const checkinVal = checkinInput?.value || '';
                const checkoutVal = checkoutInput?.value || '';
                
                if (locVal || guestsVal || checkinVal) {
                    const profile = getGvsProfile();
                    profile.searches.unshift({
                        loc: locVal,
                        guests: parseInt(guestsVal),
                        pets: petsVal,
                        checkin: checkinVal,
                        checkout: checkoutVal,
                        timestamp: Date.now()
                    });
                    if (profile.searches.length > 5) profile.searches.pop(); // keep last 5
                    saveGvsProfile(profile);
                }
                // ------------------------------

                if (searchOnly) {
                    const dest = redirectUrl || '/';
                    const params = new URLSearchParams();
                    
                    if (locVal) params.append('gvs_loc', locVal);
                    if (checkinInput?.value) params.append('gvs_checkin', checkinInput.value);
                    if (checkoutInput?.value) params.append('gvs_checkout', checkoutInput.value);
                    if (guestsVal) params.append('gvs_guests', guestsVal);
                    
                    const bedsVal = container.querySelector('.gvs-search-bedrooms')?.value;
                    if (bedsVal) params.append('gvs_beds', bedsVal);
                    
                    const amVal = container.querySelector('.gvs-search-amenity')?.value;
                    if (amVal) params.append('gvs_amenity', amVal);
                    if (petsVal) params.append('gvs_pets', petsVal);
                    
                    const joiner = dest.includes('?') ? '&' : '?';
                    window.location.href = dest + joiner + params.toString();
                } else {
                    const activeCategory = container.querySelector('.gvs-tab.active') ? container.querySelector('.gvs-tab.active').getAttribute('data-category') : 'All';
                    renderListings(activeCategory);
                }
            });
        }

        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', () => {
                const activeCategory = container.querySelector('.gvs-tab.active').getAttribute('data-category');
                renderListings(activeCategory, true);
            });
        }

        if (sortSelect) {
            sortSelect.addEventListener('change', () => {
                const activeCategory = container.querySelector('.gvs-tab.active').getAttribute('data-category');
                renderListings(activeCategory);
            });
        }

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                const category = tab.getAttribute('data-category');
                renderListings(category); 
            });
        });
    });
});
