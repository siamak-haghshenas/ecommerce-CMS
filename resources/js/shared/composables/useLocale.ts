import { computed } from 'vue';
import { i18n, LOCALE_STORAGE_KEY, type Locale } from '../i18n';
import { toPersianDigits } from '../utils/digits';
import { writeStorage } from '../utils/storage';

/** Sync <html lang/dir> with the active locale (Farsi is right-to-left). */
export function applyDocumentLocale(locale: Locale): void {
    document.documentElement.lang = locale;
    document.documentElement.dir = locale === 'fa' ? 'rtl' : 'ltr';
}

/** Intl locale per UI locale: fa-IR also switches dates to the Solar Hijri calendar. */
const INTL_LOCALES: Record<Locale, string> = { fa: 'fa-IR', en: 'en-US' };

const RELATIVE_UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 365 * 24 * 3600],
    ['month', 30 * 24 * 3600],
    ['week', 7 * 24 * 3600],
    ['day', 24 * 3600],
    ['hour', 3600],
    ['minute', 60],
];

export function useLocale() {
    const locale = computed<Locale>(() => i18n.global.locale.value);
    const isRtl = computed(() => locale.value === 'fa');
    const intlLocale = computed(() => INTL_LOCALES[locale.value]);

    function setLocale(next: Locale): void {
        i18n.global.locale.value = next;
        applyDocumentLocale(next);
        writeStorage('local', LOCALE_STORAGE_KEY, next);
    }

    function toggleLocale(): void {
        setLocale(locale.value === 'fa' ? 'en' : 'fa');
    }

    /** Show digits in the script of the active language (phone numbers, codes). */
    function localizeDigits(value: string | number): string {
        return locale.value === 'fa' ? toPersianDigits(String(value)) : String(value);
    }

    /** A number with the language's digits and thousands separators. */
    function formatNumber(value: number): string {
        return new Intl.NumberFormat(intlLocale.value).format(value);
    }

    /** Explicit parts keep Farsi dates in natural order ("شنبه ۴ مهر ۱۴۰۵"). */
    function formatDate(
        value: string | Date,
        options: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'long', year: 'numeric' },
    ): string {
        return new Intl.DateTimeFormat(intlLocale.value, options).format(new Date(value));
    }

    function formatDateTime(value: string | Date): string {
        return formatDate(value, {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    /** "3 days ago" / "۳ روز پیش". */
    function formatRelativeTime(value: string | Date): string {
        const seconds = (new Date(value).getTime() - Date.now()) / 1000;
        const format = new Intl.RelativeTimeFormat(intlLocale.value, { numeric: 'auto' });

        for (const [unit, unitSeconds] of RELATIVE_UNITS) {
            if (Math.abs(seconds) >= unitSeconds) {
                return format.format(Math.round(seconds / unitSeconds), unit);
            }
        }

        return format.format(0, 'minute');
    }

    return {
        locale,
        isRtl,
        setLocale,
        toggleLocale,
        localizeDigits,
        formatNumber,
        formatDate,
        formatDateTime,
        formatRelativeTime,
    };
}
