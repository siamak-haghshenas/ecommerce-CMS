import { computed, ref } from 'vue';
import { readStorage, writeStorage } from '../utils/storage';

export type Theme = 'light' | 'dark';

const THEME_STORAGE_KEY = 'theme';

function initialTheme(): Theme {
    const stored = readStorage('local', THEME_STORAGE_KEY);

    if (stored === 'light' || stored === 'dark') {
        return stored;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

// Shared across every component; the Blade shell applies the same choice before first paint.
const theme = ref<Theme>(initialTheme());

export function applyDocumentTheme(value: Theme = theme.value): void {
    document.documentElement.classList.toggle('dark', value === 'dark');
}

export function useTheme() {
    const isDark = computed(() => theme.value === 'dark');

    function toggleTheme(): void {
        theme.value = isDark.value ? 'light' : 'dark';
        applyDocumentTheme(theme.value);
        writeStorage('local', THEME_STORAGE_KEY, theme.value);
    }

    return { theme, isDark, toggleTheme };
}
