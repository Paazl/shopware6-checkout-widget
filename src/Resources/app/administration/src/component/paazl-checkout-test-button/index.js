const { Component, Mixin } = Shopware;
import template from './paazl-checkout-test-button.twig';

Component.register('paazl-checkout-test-button', {
    template,

    props: [
        'label'
    ],

    inject: [
        'PaazlCheckoutApiCredentialsService'
    ],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    computed: {
    },

    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
        },

        check() {
            this.isLoading = true;

            const apiKeyInput = document.querySelector('input[name="PaazlCheckoutWidget.config.apiKey"]');
            const secretKeyInput = document.querySelector('input[name="PaazlCheckoutWidget.config.SecretKey"]');

            const apiKey = (apiKeyInput) ? apiKeyInput.value : null;
            const secretKey = (secretKeyInput) ? secretKeyInput.value : null;

            this.PaazlCheckoutApiCredentialsService.validateSandboxApi(apiKey, secretKey).then(response => {
                // show success
                this.createNotificationSuccess({
                    title: this.$tc('paazl-checkout.general.api.messages.success.title'),
                    message: this.$tc('paazl-checkout.general.api.messages.success.message')
                });
            }).catch(() => {
                // show error
                this.createNotificationError({
                    title: this.$tc('paazl-checkout.general.api.messages.error.title'),
                    message: this.$tc('paazl-checkout.general.api.messages.error.message')
                });
            }).finally(() => {
                this.isLoading = false;
            });
        }
    }
})
