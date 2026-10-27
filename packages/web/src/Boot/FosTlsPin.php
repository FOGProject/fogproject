<?php
/**
 * The fogpin= kernel argument the boot menus hand FOS
 *
 * PHP version 7.4+
 *
 * @category FosTlsPin
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Boot;

/**
 * The fogpin= kernel argument the boot menus hand FOS
 *
 * Its own class, with no parent, because it needs nothing from FOGBase: it
 * reads a TLS key and hashes it, and both boot menus call it the same way.
 *
 * @category FosTlsPin
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class FosTlsPin
{
    /**
     * The fogpin= kernel argument for FOS, or '' when there is none to give.
     *
     * FOS used to call this server with curl -k on every request. It now
     * verifies the server's TLS key against fogpin= (fos#102, fos ADR-0020),
     * because a FOG server's certificate is usually signed by the CA the
     * installer generated, and no CA bundle in the init carries that CA. The
     * pin is the base64 SHA-256 of the served certificate's
     * SubjectPublicKeyInfo, which is what curl --pinnedpubkey takes.
     *
     * The key is read from the server as a client sees it, at each render,
     * so a renewed or replaced certificate needs no other step. It is read
     * only when the host in $web resolves to an address on THIS machine. A
     * connection to a local address never leaves the box, so no network
     * attacker can answer it, and a poisoned name lookup that points
     * elsewhere is refused rather than pinned. A reverse proxy or a web host
     * that is another machine gets no pin either: FOS then falls back to its
     * CA bundle. A wrong pin would stop every task; a missing one costs only
     * the stronger check.
     *
     * The key is read at that address, not over loopback. The installer's
     * nginx vhost listens on <server ip>:443 only (lib/common/functions.sh),
     * so 127.0.0.1:443 answers nothing there, and an earlier loopback read
     * meant no nginx install ever sent a pin.
     *
     * Plain http has no key to pin.
     *
     * @param string $web the web= URL the kernel line carries
     *
     * @return string ' fogpin=<base64>' or ''
     */
    public static function arg($web)
    {
        $parts = parse_url((string)$web);
        if (!is_array($parts)
            || 'https' !== strtolower($parts['scheme'] ?? '')
            || empty($parts['host'])
        ) {
            return '';
        }
        $host = trim($parts['host'], '[]');
        $port = (int)($parts['port'] ?? 443);
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if (!self::isLocalAddress($ip)) {
            return '';
        }
        $local = self::_servedKeyPin($ip, $port, $host);
        if ('' === $local) {
            return '';
        }
        return ' fogpin=' . $local;
    }

    /**
     * Whether an address belongs to this machine.
     *
     * Binding a listener succeeds only on an address the kernel owns, so the
     * bind itself is the test. Port 0 lets the kernel pick a free port, and
     * the socket is closed at once. A hostname that did not resolve arrives
     * here unchanged and fails the IP check.
     *
     * @param string $ip the address to test
     *
     * @return bool
     */
    public static function isLocalAddress($ip)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        $target = false !== strpos($ip, ':') ? "[$ip]" : $ip;
        $sock = @stream_socket_server("tcp://$target:0", $errno, $errstr);
        if (false === $sock) {
            return false;
        }
        fclose($sock);
        return true;
    }

    /**
     * The base64 SHA-256 of the key a TLS server presents, or ''.
     *
     * Verification is off on purpose: this reads the key in order to pin
     * it, and the caller's local-address check is what makes it trustworthy.
     *
     * @param string $connect address to connect to
     * @param int    $port    TLS port
     * @param string $name    server name to send, so a name-based vhost
     *                        answers with the certificate FOS will see
     *
     * @return string
     */
    private static function _servedKeyPin($connect, $port, $name)
    {
        $context = stream_context_create(
            [
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                    'peer_name' => $name,
                ],
            ]
        );
        $target = false !== strpos($connect, ':') ? "[$connect]" : $connect;
        $sock = @stream_socket_client(
            sprintf('ssl://%s:%d', $target, $port),
            $errno,
            $errstr,
            2,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if (false === $sock) {
            return '';
        }
        $params = stream_context_get_params($sock);
        fclose($sock);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        $pub = $cert ? openssl_pkey_get_public($cert) : false;
        $details = $pub ? openssl_pkey_get_details($pub) : false;
        if (!is_array($details) || empty($details['key'])) {
            return '';
        }
        // The PEM body IS the DER SubjectPublicKeyInfo, base64-encoded.
        $der = base64_decode(
            preg_replace('/-----[^-]+-----|\s+/', '', $details['key'])
        );
        return base64_encode(hash('sha256', $der, true));
    }
}
