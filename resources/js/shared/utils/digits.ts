const PERSIAN_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
const ARABIC_DIGITS = '٠١٢٣٤٥٦٧٨٩';

/** Convert Latin digits to Persian digits (for display in Farsi). */
export function toPersianDigits(value: string): string {
    return value.replace(/\d/g, (digit) => PERSIAN_DIGITS[Number(digit)] ?? digit);
}

/** Convert Persian and Arabic digits to Latin digits (for values sent to the API). */
export function toLatinDigits(value: string): string {
    return value.replace(/[۰-۹٠-٩]/g, (digit) => {
        const persianIndex = PERSIAN_DIGITS.indexOf(digit);

        return String(persianIndex >= 0 ? persianIndex : ARABIC_DIGITS.indexOf(digit));
    });
}

/** Keep only digits (any script), converted to Latin. */
export function onlyLatinDigits(value: string): string {
    return toLatinDigits(value).replace(/\D/g, '');
}
