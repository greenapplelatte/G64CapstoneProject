// assets/js/main.js
// Shared helpers. Loaded on every page through head_assets() (includes/auth.php).

// Folder the project lives in (head_assets() sets it from config/app.php; this is only a fallback)
window.BASE_URL = window.BASE_URL || '/G64CapstoneProject';

/**
 * Escape text before putting it in innerHTML or in an HTML attribute.
 * Use it on EVERY value that comes from the database or from a user:
 *     el.innerHTML = `<td>${esc(row.name)}</td>`;
 */
window.esc = window.esc || function (value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
};
