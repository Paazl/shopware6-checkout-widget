import template from "./sw-product-detail-paazl.html.twig";
import ProductAttributes from '../../../../core/models/ProductAttributes';
import StringUtils from '../../../../core/service/utils/string-utils.service';
import ProductService from '../../../../core/service/product/product.service';

const {mapState} = Shopware.Component.getComponentHelper();

Shopware.Component.register('sw-product-detail-paazl', {

    template,

    inject: ['repositoryFactory'],

    metaInfo() {
        return {
            title: 'Paazl',
        };
    },

    data() {
        return {
            parentNumberOfProcessingDays: '',
            productNumberOfProcessingDays: '',
        }
    },

    mounted() {
        this.mountedComponent();
    },

    computed: {

        ...mapState('swProductDetail', [
            'product',
        ]),

        ...mapState('context', {
            languageId: state => state.api.languageId,
            systemLanguageId: state => state.api.systemLanguageId,
        }),

        typeNONE() {
            return '0';
        },

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        productService() {
            return new ProductService();
        },

        stringUtils() {
            return new StringUtils();
        },

        hasParentProduct() {
            return (!this.stringUtils.isNullOrEmpty(this.product.parentId));
        },

    },


    methods: {

        mountedComponent() {
            this.readPaazlData();
        },

        onNumberOfProcessingDaysChanged(newValue) {
            this.updateData(newValue);
        },

        checkInheritance() {
            this.readPaazlData();
            return this.stringUtils.isNullOrEmpty(this.productNumberOfProcessingDays);
        },

        removeInheritance() {
            if (!this.stringUtils.isNullOrEmpty(this.parentNumberOfProcessingDays)) {
                this.updateData(this.parentNumberOfProcessingDays);
            } else {
                this.updateData(this.typeNONE);
            }
        },

        restoreInheritance() {
            this.updateData('');
        },

        async readPaazlData() {
            this.parentNumberOfProcessingDays = '';
            this.productNumberOfProcessingDays = '';
            if (!this.product) {
                return;
            }

            if (this.hasParentProduct) {
                this.productRepository.get(this.product.parentId, Shopware.Context.api).then(parent => {
                    const parentAtts = new ProductAttributes(parent);
                    this.parentNumberOfProcessingDays = parentAtts.getNumberOfProcessingDays();
                    if (this.stringUtils.isNullOrEmpty(this.parentNumberOfProcessingDays)) {
                        this.parentNumberOfProcessingDays = this.typeNONE;
                    }
                });
            }

            const paazlAttributes = new ProductAttributes(await this.productRepository.get(this.$route.params.id, Shopware.Context.api));

            this.productNumberOfProcessingDays = paazlAttributes.getNumberOfProcessingDays();

            if (!this.hasParentProduct && this.stringUtils.isNullOrEmpty(this.productNumberOfProcessingDays)) {
                this.productNumberOfProcessingDays = this.typeNONE;
            }
        },

        updateData(newProductNumberOfProcessingDays) {

            this.productNumberOfProcessingDays = newProductNumberOfProcessingDays;

            if (!this.product) {
                return;
            }

            const paazlAttributes = new ProductAttributes(this.product)

            if (newProductNumberOfProcessingDays !== '') {
                paazlAttributes.setNumberOfProcessingDays(newProductNumberOfProcessingDays);
            } else {
                paazlAttributes.clearNumberOfProcessingDays();
            }

            this.productService.updateCustomFields(this.product, paazlAttributes);
        },
    },

});
