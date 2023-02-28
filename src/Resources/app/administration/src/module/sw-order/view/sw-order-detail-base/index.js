import template from './sw-order-detail-base.html.twig';

const { Component, Utils, Mixin } = Shopware;

/**
 * @feature-deprecated (flag:FEATURE_NEXT_7530) will be dropped
 */

Component.override('sw-order-detail-base', {
    template,

    computed: {
        orderCriteria() {
            const criteria = this.$super('orderCriteria');
            criteria.addAssociation('orderCustomer.customer.defaultBillingAddress')
            return criteria;
        },
    }

});
