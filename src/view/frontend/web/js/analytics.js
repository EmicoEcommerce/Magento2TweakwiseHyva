function Tweakwise_Hyva_Analytics(config) {
    return {
        searchQuery: config.searchQuery,
        productKey: config.productKey,
        type: config.type,
        init() {
            let bodyData = { type: this.type };

            if (this.type === 'search') {
                bodyData.searchTerm = this.searchQuery;
            } else if (this.type === 'product') {
                bodyData.productKey = this.productKey;
            }

            fetch('/tweakwise/ajax/analytics', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(bodyData)
            }).catch(error => console.error('Tweakwise API call failed:', error));
        }
    }
}
