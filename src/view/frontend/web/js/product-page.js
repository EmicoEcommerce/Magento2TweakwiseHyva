function Tweakwise_TweakwiseHyva_Pageview(data) {
    return {
        apiUrl: data.apiUrl,
        tweakwiseCookieName: data.tweakwiseCookieName,
        productKey: data.productKey,
        instanceKey: data.instanceKey,
        getCookie(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) return parts.pop().split(';').shift();
        },
        setCookie(name, value, days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            const expires = `expires=${date.toUTCString()}`;
            document.cookie = `${name}=${value}; ${expires}; path=/`;
        },
        init() {
            var profileKey = this.getCookie(this.tweakwiseCookieName);
            if (!profileKey) {
                profileKey = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
                this.setCookie(this.tweakwiseCookieName, profileKey, 365);
            }

            fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Instance-Key': this.instanceKey,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    profileKey: profileKey,
                    productKey: this.productKey
                })
            }).catch(error => console.error('Tweakwise API call failed:', error));
        }
    }
}
