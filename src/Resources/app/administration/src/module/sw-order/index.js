import './view/sw-order-list';

Shopware.Component.register('paazl-order-info-card', () => import('./component/paazl-order-info-card'));
Shopware.Component.override('sw-order-delivery-metadata', () => import('./component/sw-order-delivery-metadata'));
Shopware.Component.override('sw-order-detail-general', () => import('./view/sw-order-detail-general'));