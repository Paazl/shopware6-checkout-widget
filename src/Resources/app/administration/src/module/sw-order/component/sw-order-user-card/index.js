import template from './sw-order-user-card.html.twig';
import './sw-order-user-card.scss';
const { Component,Mixin } = Shopware;

Component.override('sw-order-user-card', {
    template,
    inject: [
        'configService'
    ],
    mixins: [
        Mixin.getByName('notification')
    ],

    methods: {
        onPaazlRetry(){
            let headers = this.configService.getBasicHeaders();

            let data = new FormData();
            data.append('currentOrder',JSON.stringify(this.currentOrder));
            return this.configService.httpClient.post('/paazl/updatePaazlData', data, {headers}).then((response) => {
                if (response.data.type === 'error') {
                    this.createNotificationError({
                        title: response.data.type,
                        message: response.data.message
                    });
                    return;
                }
                this.createNotificationSuccess({
                    title: response.data.type,
                    message: response.data.message
                });
            });
        }
    }
});
