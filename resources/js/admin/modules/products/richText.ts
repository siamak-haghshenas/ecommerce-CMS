/**
 * Plain text of the description HTML, the same way the server reads it for the SEO analysis
 * (App\Support\RichText::toPlainText): block ends become line breaks, tags are removed.
 */
export function htmlToText(html: string): string {
    const withBreaks = html.replace(/<br\s*\/?>|<\/(p|h[1-6]|li|blockquote|tr|pre)>/gi, '\n');
    const text = new DOMParser().parseFromString(withBreaks, 'text/html').body.textContent ?? '';

    return text.replace(/\n{3,}/g, '\n\n').trim();
}
