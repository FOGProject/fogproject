// Redirects the browser to the site root.
//
// Extracted from an inline <script> so that the Content-Security-Policy
// script-src directive no longer needs 'unsafe-inline'. Loaded only by
// page.class.php when the current user is invalid.
window.location.href = '/';
