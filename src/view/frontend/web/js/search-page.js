function Tweakwise_Hyva_SearchPage(config) {
    return {
        searchQuery: config.searchQuery,
        init() {

            fetch('/tweakwise/ajax/analytics', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    type: 'search',
                    searchTerm: this.searchQuery
                })
            }).catch(error => console.error('Tweakwise API call failed:', error));
        }
    }
}
