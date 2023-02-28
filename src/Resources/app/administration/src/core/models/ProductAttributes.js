export default class ProductAttributes {

    constructor(productEntity)
    {
        this._numberOfProcessingDays = '';
        if (productEntity === null || !productEntity.customFields || !productEntity.customFields.paazl) {
            return;
        }
        const paazlFields = productEntity.customFields.paazl;
        this._numberOfProcessingDays = paazlFields.numberOfProcessingDays;
    }

    getNumberOfProcessingDays()
    {
        return this._numberOfProcessingDays + '';
    }

    setNumberOfProcessingDays(value)
    {
        this._numberOfProcessingDays = value;
    }

    clearNumberOfProcessingDays()
    {
        this._numberOfProcessingDays = '';
    }

    toArray()
    {
        const paazl = {};
        if (this._numberOfProcessingDays !== '') {
            paazl['numberOfProcessingDays'] = this._numberOfProcessingDays;
        }
        return paazl;
    }

    hasData()
    {
        return this._numberOfProcessingDays !== '';
    }
}
