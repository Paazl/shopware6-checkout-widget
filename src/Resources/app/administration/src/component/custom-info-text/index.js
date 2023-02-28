const { Component, Mixin } = Shopware;
import template from './custom-info-text.twig';

Component.register('custom-info-test', {
    template,

    props: [
        'label'
    ],
})
