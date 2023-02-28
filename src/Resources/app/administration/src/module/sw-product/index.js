import './page/sw-product-detail'
import './view/sw-product-detail-paazl'

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const {Module} = Shopware;

Module.register('paazl-sw-product-detail', {
    type: 'plugin',
    name: 'PaazlCheckoutWidget',
    title: 'Paazl.title',
    description: 'Paazl.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#333',
    icon: 'default-action-settings',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },

    routeMiddleware(next, currentRoute) {
        if (currentRoute.name === 'sw.product.detail') {
            currentRoute.children.push({
                name: 'sw.product.detail.paazl',
                path: '/sw/product/detail/:id/paazl',
                component: 'sw-product-detail-paazl',
                meta: {
                    parentPath: 'sw.product.index',
                },
            });
        }
        next(currentRoute);
    },
});
