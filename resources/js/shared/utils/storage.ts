/**
 * Browser storage can be unavailable (private mode, blocked site data), so every
 * access is guarded and failures fall back to "nothing stored".
 */
export function readStorage(storage: 'local' | 'session', key: string): string | null {
    try {
        return (storage === 'local' ? window.localStorage : window.sessionStorage).getItem(key);
    } catch {
        return null;
    }
}

export function writeStorage(storage: 'local' | 'session', key: string, value: string | null): void {
    try {
        const target = storage === 'local' ? window.localStorage : window.sessionStorage;

        if (value === null) {
            target.removeItem(key);
        } else {
            target.setItem(key, value);
        }
    } catch {
        // Preference simply isn't remembered.
    }
}
