import template from './sw-order-delivery-metadata.html.twig';

const { Component } = Shopware;

Component.override('sw-order-delivery-metadata', {
    template,
});
