function pushTweakwiseEvent(event, data) {
    window.tweakwiseLayer = window.tweakwiseLayer || [];

    // Prevents an event from being pushed twice if it arrives via more than one delivery path.
    window.TWEAKWISE_PAST_EVENTS = window.TWEAKWISE_PAST_EVENTS || [];
    const eventHash = btoa(encodeURIComponent(JSON.stringify({ event: event, data: data })));
    if (window.TWEAKWISE_PAST_EVENTS.indexOf(eventHash) !== -1) {
        return;
    }
    window.TWEAKWISE_PAST_EVENTS.push(eventHash);

    window.tweakwiseLayer.push({ event: event, data: data });
}

// Build the Tweakwise item id from raw Magento ids.
function getTweakwiseProductKey(rawId, storeId) {
    if (!storeId) {
        return rawId;
    }

    return '1' + String(storeId).padStart(4, '0') + rawId;
}

function getTweakwiseItemId(rawId, storeId) {
    if (!rawId || !rawId.includes('-')) {
        return getTweakwiseProductKey(rawId, storeId);
    }

    const [firstId, secondId] = rawId.split('-', 2);
    if (!firstId || !secondId) {
        return getTweakwiseProductKey(rawId, storeId);
    }

    return getTweakwiseProductKey(firstId, storeId) + '-' + getTweakwiseProductKey(secondId, storeId);
}

function pushTweakwiseEventsData(eventsData) {
    (eventsData || []).forEach(function(eventData) {
        switch (eventData.type) {
            case 'product':
                pushTweakwiseEvent('productView', { productKey: eventData.value });
                break;
            case 'search':
                pushTweakwiseEvent('search', { searchTerm: eventData.value });
                break;
            case 'page_impression':
                pushTweakwiseEvent('pageImpression', { requestId: eventData.requestId });
                break;
            case 'addtocart_event':
                pushTweakwiseEvent('addtocart', eventData.value);
                break;
            case 'addtowishlist_event':
                pushTweakwiseEvent('addtowishlist', eventData.value);
                break;
            case 'purchase_event':
                pushTweakwiseEvent('purchase', eventData.value);
                break;
            default:
                break;
        }
    });
}

// Pending events arrive via the cart/customer sections; private-content-loaded covers both.
function handlePrivateContentLoaded(event) {
    const sectionsData = (event.detail && event.detail.data) || {};
    let mutated = false;

    ['cart', 'customer'].forEach(function(sectionName) {
        const sectionData = sectionsData[sectionName];
        if (!sectionData || !sectionData.tweakwise_events) {
            return;
        }

        pushTweakwiseEventsData(sectionData.tweakwise_events);

        // Clear consumed events from Hyva's cached private content so a later load can't repush them.
        delete sectionData.tweakwise_events;
        mutated = true;
    });

    if (!mutated || typeof hyva === 'undefined' || !hyva.getBrowserStorage) {
        return;
    }

    try {
        const browserStorage = hyva.getBrowserStorage();
        const storedContent = JSON.parse(browserStorage.getItem('mage-cache-storage') || '{}') || {};

        ['cart', 'customer'].forEach(function(sectionName) {
            if (storedContent[sectionName] && storedContent[sectionName].tweakwise_events) {
                delete storedContent[sectionName].tweakwise_events;
            }
        });

        browserStorage.setItem('mage-cache-storage', JSON.stringify(storedContent));
    } catch (error) {
        console.warn('[Tweakwise] Could not update cached private content', error);
    }
}

function Tweakwise_Hyva_Analytics(config) {
    return {
        eventsData: config.eventsData,
        bindItemClickEventsConfig: config.bindItemClickEventsConfig,
        init() {

            if (config.eventsData) {
                pushTweakwiseEventsData(this.eventsData);
            }

            // Covers both the plain form-POST add-to-cart flow and reactive AJAX cart/wishlist actions.
            window.addEventListener('private-content-loaded', handlePrivateContentLoaded);

            // bindItemClickEvents
            if (this.bindItemClickEventsConfig) {
                const bindConfig = this.bindItemClickEventsConfig;
                const productList = document.querySelector(bindConfig.productListSelector);

                if (!bindConfig.twRequestId || !productList) {
                    return;
                }

                productList.addEventListener('click', function(event) {
                    if (event.target.closest(bindConfig.productSelector)) {
                        handleItemClick(event, bindConfig);
                    }
                }, true);
            }
        }
    }
}

function handleItemClick(event, config) {
    try {
        if (!config.twRequestId) {
            return;
        }

        if (event.target.nodeName !== 'IMG' && event.target.nodeName !== 'A') {
            return;
        }

        const product = event.target.closest(config.productSelector);
        let productId;

        if (product) {
            const rawProductId = product.getAttribute('data-product-id');
            if (rawProductId) {
                const rawProductIdType = product.getAttribute('data-product-id-type');
                productId = rawProductIdType === 'tweakwise'
                    ? rawProductId
                    : getTweakwiseItemId(rawProductId, config.storeId);
            }
        }

        if (!productId) {
            return;
        }

        pushTweakwiseEvent('itemClick', { itemId: productId, requestId: config.twRequestId });
    } catch (error) {
        console.error('Error handling product click event', error);
    }
}
