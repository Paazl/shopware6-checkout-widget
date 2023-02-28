Shopware.Component.override('sw-single-select', {
    created() {
        if (this.$attrs.name === "PaazlCheckoutWidget.config.freeShipping") {
            if (this.currentValue === 'no') {
                setTimeout(function () {
                    document.getElementById('PaazlCheckoutWidget.config.startMatrix').parentElement.parentElement.style.display = 'none';
                }, 2500);
            }
        }
    },
    watch: {
        options: function () {
            if (this.$attrs.name === "PaazlCheckoutWidget.config.freeShipping") {
                if (this.currentValue === 'no') {
                    document.getElementById('PaazlCheckoutWidget.config.startMatrix').parentElement.parentElement.style.display = 'none';
                } else {
                    document.getElementById('PaazlCheckoutWidget.config.startMatrix').parentElement.parentElement.style.display = 'block';
                }
            }
        }
    },
});
