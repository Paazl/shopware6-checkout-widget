const ApiService = Shopware.Classes.ApiService;

class PaazlCheckoutApiCredentialsService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '')
    {
        super(httpClient, loginService, apiEndpoint);
    }

    validateSandboxApi()
    {
        // get default headers
        const headers = this.getBasicHeaders();

        // call the api and return the response
        return this.httpClient
            .post('paazl-checkout/validate-sandbox-api', {
            }, {
                headers
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

}

export default PaazlCheckoutApiCredentialsService;
