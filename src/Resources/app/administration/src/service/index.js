import PaazlCheckoutApiCredentialsService from "./api/paazl-checkout.api-credentials.service";

const { Application } = Shopware;

Application.addServiceProvider('PaazlCheckoutApiCredentialsService', (container) => {
    const initContainer = Application.getContainer('init');
    return new PaazlCheckoutApiCredentialsService(initContainer.httpClient, container.loginService);
});
