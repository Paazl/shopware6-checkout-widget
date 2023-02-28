export default class ProductService {

    /**
     *
     * @param product
     * @param {ProductAttributes} paazlAttributes
     */
    updateCustomFields(product, paazlAttributes)
    {
        if (!product.customFields) {
            product.customFields = {};
        }

        if (!paazlAttributes.hasData() && !Object.prototype.hasOwnProperty.call(product.customFields, 'paazl')) {
            return;
        }

        product.customFields.paazl = paazlAttributes.toArray();
    }

}
