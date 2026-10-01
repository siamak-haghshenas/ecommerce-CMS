/**
 * Accept only same-app relative paths as post-login redirects, so a crafted
 * ?redirect= link cannot send users to another site.
 */
export function safeRedirect(target: unknown, fallback: string): string {
    if (typeof target !== 'string' || !target.startsWith('/') || target.startsWith('//') || target.includes('\\')) {
        return fallback;
    }

    return target;
}
