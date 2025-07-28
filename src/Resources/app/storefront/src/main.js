window.PluginManager.register('PaazlCheckoutPlugin', () => import('./plugin/paazl-checkout.plugin'), '[data-paazl-checkout]');

// Don't refresh page for every button in Paazl widget
const changeShippingForm = document.querySelector('#changeShippingForm');
if (changeShippingForm) {
    changeShippingForm.addEventListener('submit', (e) => {
        if (e.submitter.closest('#paazl-checkout')) {
            e.preventDefault();
        }
    })
}