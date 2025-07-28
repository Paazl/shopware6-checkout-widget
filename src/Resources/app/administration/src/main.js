import './module/sw-product';
import './module/sw-order';
import './component/paazl-checkout-test-button';
import './component/custom-info-text';
import './service';

Shopware.Component.override('sw-single-select', () => import('./component/sw-single-select'));