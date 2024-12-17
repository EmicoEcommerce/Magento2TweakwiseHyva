function Tweakwise_Hyva_Analytics(config) {
    return {
        value: config.value,
        type: config.type,
        init() {
            let bodyData = { type: this.type };
            bodyData.value = this.value;

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
