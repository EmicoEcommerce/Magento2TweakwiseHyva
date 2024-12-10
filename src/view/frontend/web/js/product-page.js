function Tweakwise_TweakwiseHyva_Pageview(data) {
    return {
        productKey: data.productKey,
        init() {
            fetch('/tweakwise/ajax/analytics', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    type: 'product',
                    productKey: this.productKey
                })
            }).catch(error => console.error('Tweakwise API call failed:', error.message));
        }
    }
}
