export default class StringUtils {
    /**
     *
     * @param value
     * @returns {boolean}
     */
    isNullOrEmpty(value)
    {
        return value === undefined || value === null || value === '';
    }
}
