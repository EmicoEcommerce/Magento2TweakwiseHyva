function pushTweakwiseEvent(event, data) {
    window.tweakwiseLayer = window.tweakwiseLayer || [];

    // Prevent the same event (e.g. addtocart/addtowishlist/purchase) from being pushed twice when
    // it arrives via more than one delivery path (customer-data section AND a later full page render).
    window.TWEAKWISE_PAST_EVENTS = window.TWEAKWISE_PAST_EVENTS || [];
    const eventHash = btoa(encodeURIComponent(JSON.stringify({ event: event, data: data })));
    if (window.TWEAKWISE_PAST_EVENTS.indexOf(eventHash) !== -1) {
        return;
    }
    window.TWEAKWISE_PAST_EVENTS.push(eventHash);

    window.tweakwiseLayer.push({ event: event, data: data });
}

// Mirrors the simple (non-grouped) part of Tweakwise\Magento2TweakwiseExport\Model\Helper::getTweakwiseId()
// for the raw Magento entity ids exposed by Hyva's native product grid template (which doesn't render the
// Tweakwise-mapped id used by the base module's own item.phtml). Deliberately does NOT attempt to send a
// group code for grouped/configurable products here: the real group code (the parent product's own
// Tweakwise id) can only be resolved with a database lookup, unavailable client-side. Sending a
// self-referencing "id-id" pair looked plausible but was actively wrong data; omitting the group code
// still tracks the click correctly at the item level, just without the "these are variants of the same
// product" signal - the safer choice given the alternative was silently corrupting Tweakwise's grouping.
function getTweakwiseProductKey(rawId, storeId) {
    if (!storeId) {
        return rawId;
    }

    return '1' + String(storeId).padStart(4, '0') + rawId;
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

// Pending addtocart/addtowishlist events get attached to the "cart"/generic "customer" customer-data
// sections respectively (see Plugin\CustomerData\AddPendingEventsToCartSection/
// AddPendingEventsToCustomerSection) rather than rendered into any page's HTML, because that HTML can be
// full-page-cached and shared across visitors. Hyva's own private-content bootstrap (see
// Hyva_Theme::page/js/private-content.phtml) already fetches/dispatches section data on every page load
// and after its own AJAX cart/wishlist actions via the native "private-content-loaded" event, both from a
// fresh server fetch and from its cached copy in browser storage - listening to that single event covers
// both cases without an extra request of our own. Mirrors Yireo_GoogleTagManager2's hyva/script-additions.phtml.
function handlePrivateContentLoaded(event) {
    const sectionsData = (event.detail && event.detail.data) || {};
    let mutated = false;

    ['cart', 'customer'].forEach(function(sectionName) {
        const sectionData = sectionsData[sectionName];
        if (!sectionData || !sectionData.tweakwise_events) {
            return;
        }

        pushTweakwiseEventsData(sectionData.tweakwise_events);

        // Remove the consumed events from Hyva's own cached private content, so a later page load
        // reading this same cached copy can't push them again.
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

            // Hyva dispatches this on every page load (from its cached private content or a fresh
            // fetch) and again after its own AJAX cart/wishlist actions - covers both add-to-cart, which
            // on Hyva is a plain (non-AJAX) form POST + redirect, and reactive AJAX flows.
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
            const idPrefix = config.productItemInfoPrefix || 'product-item-info';
            const productInfo = product.querySelector('[id^="' + idPrefix + '_"]');
            if (productInfo) {
                productId = productInfo.id.replace(idPrefix + '_', '');
            }
        }

        if (!productId) {
            let visual = event.target.closest('.visual');
            if (!visual) {
                const link = event.target.closest('a');
                if (link) {
                    visual = link.querySelector('.visual');
                }
            }
            if (visual) {
                productId = visual.getAttribute('id');
            }
        }

        // Hyva's native product grid template doesn't render the Tweakwise-mapped id at all,
        // only Magento's own raw entity id via the add-to-cart hidden input - map it here instead.
        if (!productId && product) {
            const productInput = product.querySelector('input[name="product"]');
            const rawId = productInput ? productInput.value : null;
            if (rawId) {
                productId = getTweakwiseProductKey(rawId, config.storeId);
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
