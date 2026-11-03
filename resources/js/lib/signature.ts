/** Convert a plain-text signature for insertion into the rich-text step body. */
export function signatureHtml(body: string): string {
    const escaped = body
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    return `<p>${escaped.replace(/\r\n|\n\r|\r|\n/g, '<br>')}</p>`;
}
