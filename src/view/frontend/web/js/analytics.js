function Tweakwise_Hyva_Analytics(config) {
    return {
        eventsData: config.eventsData,
        bindItemClickEventsConfig: config.bindItemClickEventsConfig,
        init() {

            if (config.eventsData) {
                let bodyData = { eventsData: this.eventsData };

                fetch('/tweakwise/ajax/analytics', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(bodyData)
                }).catch(error => console.error('Tweakwise API call failed:',
                    error));
            }

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
    const productList = document.querySelector(config.productListSelector);
    if (!productList) {
        return;
    }

    productList.addEventListener('click', (event) => {
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
                //productId is the closest input with the name product of the product var
                productId = product.querySelector('input[name="product"]')?.value;
            } else {
                let visual = event.target.closest('.visual');
                if (!visual) {
                    let link = event.target.closest('a');
                    if (link) {
                        visual = link.querySelector('.visual');
                    }
                }
                if (visual) {
                    productId = visual.getAttribute('id');
                }
            }

            if (!productId) {
                return;
            }

            // Send async fetch request to the analytics endpoint
            fetch(config.analyticsEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    eventsData: [
                        {
                            type: 'itemclick',
                            value: productId,
                            requestId: config.twRequestId
                        }
                    ],
                })
            }).catch((error) => {
                console.error('Error sending analytics event', error);
            });
        } catch (error) {
            console.error('Error handling product click event', error);
        }
    });
}
