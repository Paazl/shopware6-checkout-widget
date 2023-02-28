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

            this.PaazlCheckoutApiCredentialsService.validateSandboxApi().then(response => {
                if (response.type === 'error') {
                    // show error
                    this.createNotificationError({
                        title: this.$tc('paazl-checkout.general.api.messages.error.title'),
                        message: response.message
                    });
                } else {
                    // show success
                    this.createNotificationSuccess({
                        title: this.$tc('paazl-checkout.general.api.messages.success.title'),
                        message: this.$tc('paazl-checkout.general.api.messages.success.message')
                    });
                }

                this.isLoading = false;
            });
        }
    }
})
