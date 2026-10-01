import { createI18n } from 'vue-i18n';
import { readStorage } from '../utils/storage';
import en from './en';
import fa from './fa';

export type Locale = 'fa' | 'en';

export const LOCALE_STORAGE_KEY = 'locale';

function initialLocale(): Locale {
    const stored = readStorage('local', LOCALE_STORAGE_KEY);

    return stored === 'en' || stored === 'fa' ? stored : 'fa';
}

export const i18n = createI18n({
    legacy: false,
    locale: initialLocale(),
    fallbackLocale: 'fa',
    messages: { fa, en },
});
