<?php
/**
 * CSRF, hopefully more secure handling centralized.
 *
 * PHP version 5
 *
 * For setting/checking CSRF tokens.
 *
 * @category CSRF
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * CSRF, hopefully more secure handling centralized.
 *
 * @category CSRF
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class CSRF
{
    private const SESSION_KEY = '_csrf_token';
    // Rotate per session (good). If you want per-form/per-request, extend this.
    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function validate(?string $provided): bool
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_string($provided)) {
            return false;
        }
        return hash_equals($_SESSION[self::SESSION_KEY], $provided);
    }

    public static function requireForStateChanging(): void
    {
        // Only enforce for non-idempotent verbs
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (!in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
            return;
        }

        // Accept either header or body param for flexibility
        $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        FOGCore::var_dump_log('Header Provided: ' . $provided);
        if ($provided === null) {
            // Common places to look (application/x-www-form-urlencoded, multipart, JSON)
            $provided = $_POST['_csrf'] ?? null;
            if ($provided === null) {
                $input = file_get_contents('php://input');
                if ($input) {
                    $json = json_decode($input, true);
                    if (is_array($json) && isset($json['_csrf'])) {
                        $provided = $json['_csrf'];
                    }
                }
            }
            FOGCore::var_dump_log('POST Provided _csrf: ' . $provided);
        }
        FOGCore::var_dump_log('Session Provided: ' . $_SESSION[self::SESSION_KEY]);

        if (!self::validate($provided)) {
            http_response_code(403);
            echo _('Forbidden (invalid CSRF token)');
            exit;
        }
    }

    // Optional defense-in-depth: Origin/Referer check for state-changing requests
    public static function checkOrigin(array $allowedOrigins): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (!in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
            return;
        }
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        $referer = $_SERVER['HTTP_REFERER'] ?? null;
        if ($origin) {
            foreach ($allowedOrigins as $allowed) {
                if (stripos($origin, $allowed) === 0) {
                    return;
                }
            }
            http_response_code(403);
            echo _('Forbidden (disallowed Origin)');
            exit;
        } elseif ($referer) {
            foreach ($allowedOrigins as $allowed) {
                if (stripos($referer, $allowed) === 0) {
                    return;
                }
            }
            http_response_code(403);
            echo _('Forbidden (disallowed Referer)');
            exit;
        }
        // If neither header is present, you can decide to be strict or lenient.
        // Often lenient to avoid breaking weird client setups.
    }
}
