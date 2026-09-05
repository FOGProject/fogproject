<?php
/**
 * Who is calling: the host behind a verified fog-agent client certificate.
 *
 * PHP version 7.4+
 *
 * @category Principal
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
namespace FOG\Agent;

/**
 * Turns the web server's client-certificate variables into a key
 * fingerprint the router can bind to a host.
 *
 * Deliberately NOT a FOGBase: it touches no globals and no database, so
 * tests/agent-principal.test.php can drive it with certificates minted on
 * the spot and prove what it refuses. The host lookup is the router's.
 *
 * Two checks, both required, and the second is why this class exists:
 *
 *   1. The web server said SUCCESS. nginx and Apache both verify the chain
 *      and the validity dates before PHP ever runs; SSL_CLIENT_VERIFY is
 *      how they say so.
 *   2. PHP verifies the chain AGAIN, against the agent CA bundle and for
 *      the client-auth purpose. The web server's trust file is whatever
 *      the vhost was written with -- on an Apache install it is also the
 *      server's own chain, and an external-CA install can point it
 *      anywhere -- so "the server accepted it" does not prove "the FOG
 *      Agent CA issued it". This check does, and it costs one X509
 *      verification per request.
 *
 * The binding is the SPKI fingerprint, the same sha256 of the public key
 * that enrollment stored on the host, so a certificate whose key is not
 * the enrolled key is not this host's agent whatever its subject says.
 *
 * @category Principal
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Principal
{
    /**
     * The audit authSource for a write the client certificate authorized.
     * Not anonymous: the caller proved a key the server bound to a host.
     */
    const AUTH_SOURCE = 'agent';

    /**
     * sha256 of a public key's SPKI, as enrollment stores it on the host.
     *
     * One definition, shared by the CSR side (Enrollment::fingerprint) and
     * the certificate side (verify) so the two can never drift apart.
     *
     * @param mixed $pub the public key (resource before PHP 8, object after), or false
     *
     * @return string|null the hex fingerprint, null for an unusable key
     */
    public static function spkiFingerprint($pub)
    {
        if (false === $pub) {
            return null;
        }
        $details = openssl_pkey_get_details($pub);
        if (!is_array($details) || empty($details['key'])) {
            return null;
        }
        return hash('sha256', (string)$details['key']);
    }

    /**
     * The PEM the web server handed over, in the form openssl wants.
     *
     * nginx's $ssl_client_escaped_cert is URL-encoded (its raw variant
     * would break the fastcgi record on newlines); Apache's SSL_CLIENT_CERT
     * is plain PEM. Tell them apart by the newline: plain PEM always has
     * one after its BEGIN line, the escaped form carries %0A instead.
     * NOT by "-----BEGIN" -- URL escaping leaves dashes and letters alone,
     * so that marker survives escaping and tells you nothing.
     *
     * @param string $raw the variable as received
     *
     * @return string
     */
    public static function pem($raw)
    {
        $raw = (string)$raw;
        if (false === strpos($raw, "\n")) {
            $raw = rawurldecode($raw);
        }
        return $raw;
    }

    /**
     * Verifies the calling agent's certificate.
     *
     * @param array  $server the request's $_SERVER
     * @param string $bundle path to the agent CA bundle (agent CA + root)
     *
     * @return array|null ['fingerprint' => sha256 hex, 'not_after' =>
     *                    unix time] or null when anything is not right.
     *                    Null carries no reason on purpose: the caller
     *                    answers 401 either way, and the reasons are only
     *                    interesting to someone with the server's logs.
     */
    public static function verify(array $server, $bundle)
    {
        if ('SUCCESS' !== (string)($server['SSL_CLIENT_VERIFY'] ?? '')) {
            return null;
        }
        $pem = self::pem((string)($server['SSL_CLIENT_CERT'] ?? ''));
        if ('' === $pem || !is_readable($bundle)) {
            return null;
        }
        $cert = @openssl_x509_read($pem);
        if (false === $cert) {
            return null;
        }
        // Chain, dates and the client-auth purpose, against OUR CA. This
        // is check 2 in the class comment and it must stay independent
        // of what the vhost trusts.
        if (true !== openssl_x509_checkpurpose($cert, X509_PURPOSE_SSL_CLIENT, [$bundle])) {
            return null;
        }
        $parsed = openssl_x509_parse($cert);
        $fingerprint = self::spkiFingerprint(@openssl_pkey_get_public($cert));
        if (null === $fingerprint || !is_array($parsed)) {
            return null;
        }
        return [
            'fingerprint' => $fingerprint,
            'not_after' => (int)($parsed['validTo_time_t'] ?? 0),
        ];
    }
}
