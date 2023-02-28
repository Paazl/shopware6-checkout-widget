import Plugin from 'src/plugin-system/plugin.class';
import Debouncer from 'src/helper/debouncer.helper';
import HttpClient from 'src/service/http-client.service';
import ElementLoadingIndicatorUtil from 'src/utility/loading-indicator/element-loading-indicator.util';
import ElementReplaceHelper from 'src/helper/element-replace.helper';

export default class PaazlChangeEventPlugin extends Plugin {
    static options = {
        checkoutConfirmUrl: '',
        pickupStoreTriggerClass: '.paazl__body .panel--selected .point__label.checked',
        deliveryTriggerClass: '.paazl__body .panel--selected .option__area.checked',
        checkoutAsideSelector: '.checkout-aside-container',
    };

    init()
    {
        this._debouncedOnClick = Debouncer.debounce(this.onBodyClick.bind(this), 200);
        document.body.addEventListener('click', this.onBodyClick.bind(this));
        document.body.addEventListener('click', this.handleTabClick.bind(this));

        let pickupStoreTriggerClass = this.options.pickupStoreTriggerClass;
        let deliveryTriggerClass = this.options.deliveryTriggerClass;

        setTimeout(function () {
            if (document.querySelectorAll(pickupStoreTriggerClass).length >0) {
                document.querySelector(pickupStoreTriggerClass).click();
            }
            if (document.querySelectorAll(deliveryTriggerClass).length >0) {
                document.querySelector(deliveryTriggerClass).click();
            }
        }, 2500);

        this._asideEl = document.querySelector(this.options.checkoutAsideSelector);

        this._client = new HttpClient();
    }

    handleTabClick(e)
    {
        let pickupStoreTriggerClass = this.options.pickupStoreTriggerClass;
        let deliveryTriggerClass = this.options.deliveryTriggerClass;

        const path = e.path || (e.composedPath && e.composedPath());
        path.every((el) => {
                if (typeof el.matches === "function" && (el.matches('button.header__tab,button.tabs__navigation__tab'))) {
                if (document.querySelectorAll(pickupStoreTriggerClass).length >0) {
                    document.querySelector(pickupStoreTriggerClass).click();
                }
                if (el.getAttribute('id') === 'tab-button-delivery') {
                    document.querySelector('.confirm-shipping-address').style.display = 'block';
                }
                if (el.getAttribute('id') === 'tab-button-pickup') {
                    document.querySelector('.confirm-shipping-address').style.display = 'none';
                }
                if (document.querySelectorAll(deliveryTriggerClass).length >0) {
                    document.querySelector(deliveryTriggerClass).click();
                }
                return false;
            }
            return true;
        });
    }

    onBodyClick(e)
    {
        const path1 = e.path || (e.composedPath && e.composedPath());
        path1.every((el) => {
            if (typeof el.matches === "function" && (el.matches('article.option,article.point'))) {
                this.onClickArticle();
                return false;
            }
            return true;
        });
    }

    onClickArticle()
    {
        ElementLoadingIndicatorUtil.create(this._asideEl);
        this._client.get(this.options.checkoutConfirmUrl, this._onAjaxLoad.bind(this));
    }

    _onAjaxLoad(response)
    {
        ElementLoadingIndicatorUtil.remove(this._asideEl);
        ElementReplaceHelper.replaceFromMarkup(response, this.options.checkoutAsideSelector, false);
        window.PluginManager.initializePlugins();
    }
}
