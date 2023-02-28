export default class StringUtils {

    /**
     *
     * @param value
     * @returns {boolean}
     */
    isNullOrEmpty(value)
    {

        if (value === undefined || value === null || value === '') {
            return true;
        }

        return false;
    }

}
