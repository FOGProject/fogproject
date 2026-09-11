<?php
/**
 * This server's copy of the fog-agent releases, and the arithmetic of
 * latest, update rings and the minimum version.
 *
 * PHP version 7.4+
 *
 * @category Releases
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Agent;

use FOG\Base\FOGBase;
use FOG\Items\AgentEnrollment;
use FOG\Items\Host;

/**
 * Design 0015 sections 7 and 14 (schema 438).
 *
 * FOGAgentReleaseSync calls sync(). It downloads the signed release
 * manifest and its signature, indexes every file the manifest lists in
 * agentReleaseArtifacts, and downloads the files this server's enrolled
 * hosts need into /opt/fog/agent/versions. Agents then take the manifest
 * inside their update block and the file from the payload route, so a
 * site downloads each release once instead of once per machine, and a
 * site with no internet access can point FOG_AGENT_UPDATE_MANIFEST_URL at
 * a mirror.
 *
 * Nothing here is trusted by an agent. Every agent checks FOG Project's
 * signature over the manifest bytes and each file's hash itself, against a
 * root compiled into its own binary. This server only chooses which
 * published version runs where. The checks in this class exist so the
 * server does not download or serve junk, not to make anything safe.
 *
 * The pure functions (latest, floor, ringDelays, delayFor, parseManifest,
 * isEnvelope, neededFrom, prunable) carry every decision, so
 * tests/agent-update-rings.test.php pins them without a database.
 *
 * @category Releases
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Releases extends FOGBase
{
    /**
     * Where the manifest comes from when FOG_AGENT_UPDATE_MANIFEST_URL is
     * empty. The same address is compiled into the agent.
     */
    const DEFAULT_MANIFEST_URL = 'https://fogproject.org/version/agent-stable.json';

    /**
     * FOG_AGENT_UPDATE_RINGS when the setting cannot be read as delays.
     * A broken list falls back to these rather than to "no delay", because
     * no delay is the one reading that sends a release everywhere at once.
     */
    const DEFAULT_RINGS = [0, 3, 7];

    /**
     * FOG_AGENT_KEEP_VERSIONS when the setting is not a whole number.
     */
    const DEFAULT_KEEP = 3;

    /**
     * The longest ring delay accepted, in days.
     */
    const MAX_RING_DAYS = 365;

    /**
     * The manifest and its signature, stored together in one file so one
     * rename replaces both. Two files renamed one after the other leave a
     * moment where an agent can be handed a new manifest with the old
     * signature, and it would report signature_invalid for a server that
     * did nothing wrong.
     */
    const MANIFEST_FILE = 'manifest.json';

    /**
     * The largest manifest this server accepts. The real one is a few
     * kilobytes per release.
     */
    const MAX_MANIFEST_BYTES = 1048576;

    /**
     * A release version: three numbers and nothing else, which is every key
     * the stable manifest carries.
     *
     * No suffix, because PHP's version_compare() and the agent's semver
     * disagree about one. A lab build stamped 0.1.7-2-gf344ed3 is BELOW
     * 0.1.7 to the agent and ABOVE it to version_compare(), so comparing it
     * here would give the opposite answer to the machine that acts on it.
     */
    const VERSION_PATTERN = '/^[0-9]+\.[0-9]+\.[0-9]+$/';

    /**
     * A GOOS or GOARCH value.
     */
    const PLATFORM_PATTERN = '/^[a-z0-9]{1,16}$/';

    /**
     * The directory release files are stored in.
     *
     * @return string
     */
    public static function storeDir()
    {
        return FOG_BASE_DIR . DS . 'agent' . DS . 'versions';
    }

    /**
     * The manifest address this server downloads from.
     *
     * @return string
     */
    public static function manifestUrl()
    {
        $url = trim((string)self::getSetting('FOG_AGENT_UPDATE_MANIFEST_URL'));
        return '' === $url ? self::DEFAULT_MANIFEST_URL : $url;
    }

    /**
     * Whether a string is a release version.
     *
     * @param string $version the version, already normalized
     *
     * @return bool
     */
    public static function isVersion($version)
    {
        return 1 === preg_match(self::VERSION_PATTERN, (string)$version);
    }

    /**
     * The index key for one file.
     *
     * @param string $version the version
     * @param string $os      the GOOS
     * @param string $arch    the GOARCH
     *
     * @return string
     */
    public static function key($version, $os, $arch)
    {
        return $version . '|' . $os . '|' . $arch;
    }

    /**
     * FOG_AGENT_UPDATE_RINGS as delays in days, or null when it is not a
     * valid list. The settings page refuses to save a null answer.
     *
     * @param string $setting the setting as stored
     *
     * @return int[]|null
     */
    public static function parseRings($setting)
    {
        $out = [];
        foreach (explode(',', (string)$setting) as $part) {
            $part = trim($part);
            if (!ctype_digit($part) || (int)$part > self::MAX_RING_DAYS) {
                return null;
            }
            $out[] = (int)$part;
        }
        return $out;
    }

    /**
     * FOG_AGENT_UPDATE_RINGS as delays in days, falling back to the
     * default list when the stored value is not valid.
     *
     * @param string $setting the setting as stored
     *
     * @return int[]
     */
    public static function ringDelays($setting)
    {
        return self::parseRings($setting) ?? self::DEFAULT_RINGS;
    }

    /**
     * The delay for one host's ring.
     *
     * An empty ring is the LAST ring, and so is a ring past the end of the
     * list. A new host, or a host left behind when the list got shorter,
     * then waits longest rather than going first.
     *
     * @param string $ring   the host's agentUpdateRing
     * @param int[]  $delays the ring delays
     *
     * @return int days
     */
    public static function delayFor($ring, array $delays)
    {
        $delays = array_values($delays);
        $last = count($delays) - 1;
        if ($last < 0) {
            return 0;
        }
        $ring = trim((string)$ring);
        if ('' === $ring || !ctype_digit($ring) || (int)$ring > $last) {
            return (int)$delays[$last];
        }
        return (int)$delays[(int)$ring];
    }

    /**
     * The version Latest mode names for one host.
     *
     * The newest version whose first-seen time is at least the ring delay
     * ago. Never below the version the host runs, while that version is
     * still in the manifest: a host a pin took ahead is not taken back when
     * the pin is cleared. A running version withdrawn from the manifest does
     * not hold the host, which is how withdrawing a release rolls its hosts
     * back. When nothing is old enough, the host stays where it is.
     *
     * @param array  $firstSeen version => unix time this server first saw it
     * @param int    $delayDays the host's ring delay
     * @param string $running   the version the host runs
     * @param int    $now       unix time
     *
     * @return string '' when there is nothing to name
     */
    public static function latest(array $firstSeen, $delayDays, $running, $now)
    {
        $cutoff = (int)$now - max(0, (int)$delayDays) * 86400;
        $best = '';
        foreach ($firstSeen as $version => $seen) {
            $version = (string)$version;
            if ((int)$seen > $cutoff) {
                continue;
            }
            if ('' === $best || version_compare($version, $best, '>')) {
                $best = $version;
            }
        }
        $running = Update::normalize($running);
        if ('' !== $running
            && isset($firstSeen[$running])
            && ('' === $best || version_compare($running, $best, '>'))
        ) {
            return $running;
        }
        return $best;
    }

    /**
     * FOG_AGENT_MIN_VERSION applied to a resolved version.
     *
     * A version named below the minimum is raised to it. That covers a pin,
     * a host override and a ring delay alike. When nothing is named (Off
     * mode), a host that runs a release below the minimum is raised to it
     * too. A running version that is not a release, such as a lab build, is
     * left alone: it cannot be compared, and guessing would replace a build
     * somebody put there on purpose.
     *
     * @param string $target  the version resolved so far, '' for none
     * @param string $running the version the host runs
     * @param string $min     the minimum version, '' for none
     *
     * @return string
     */
    public static function floor($target, $running, $min)
    {
        $min = Update::normalize($min);
        if (!self::isVersion($min)) {
            return $target;
        }
        if ('' !== $target) {
            return self::isVersion($target) && version_compare($target, $min, '<')
                ? $min
                : $target;
        }
        $running = Update::normalize($running);
        if (self::isVersion($running) && version_compare($running, $min, '<')) {
            return $min;
        }
        return '';
    }

    /**
     * The file rows of a manifest this server may index.
     *
     * An entry that is not a safe row is dropped, not fatal: a version
     * string that is not a version, a platform that is not a plain word
     * (it becomes part of a path), a hash that is not sha256, a size that
     * is not positive, or an address that is not https. A body that is not
     * a manifest at all (an error page, a truncated download) throws, so
     * the sync keeps what it had.
     *
     * @param string $bytes the manifest as downloaded
     *
     * @throws \RuntimeException when the body is not a manifest
     *
     * @return array list of ['version','os','arch','sha256','size','url','security']
     */
    public static function parseManifest($bytes)
    {
        $bytes = (string)$bytes;
        if ('' === $bytes || strlen($bytes) > self::MAX_MANIFEST_BYTES) {
            throw new \RuntimeException(_('The release manifest is empty or too large'));
        }
        $doc = json_decode($bytes, true);
        if (!is_array($doc) || !is_array($doc['versions'] ?? null)) {
            throw new \RuntimeException(_('The release manifest is not readable'));
        }
        $out = [];
        foreach ($doc['versions'] as $version => $entry) {
            $version = Update::normalize((string)$version);
            if (!self::isVersion($version) || !is_array($entry)) {
                continue;
            }
            foreach ((array)($entry['artifacts'] ?? []) as $file) {
                if (!is_array($file)) {
                    continue;
                }
                $os = (string)($file['os'] ?? '');
                $arch = (string)($file['arch'] ?? '');
                $sha = strtolower((string)($file['sha256'] ?? ''));
                $size = $file['size'] ?? 0;
                $url = (string)($file['url'] ?? '');
                if (!preg_match(self::PLATFORM_PATTERN, $os)
                    || !preg_match(self::PLATFORM_PATTERN, $arch)
                    || !preg_match('/^[0-9a-f]{64}$/', $sha)
                    || !is_int($size)
                    || $size < 1
                    || 0 !== strpos($url, 'https://')
                    || strlen($url) > 1024
                    || '' === self::fileName($url)
                ) {
                    continue;
                }
                $out[] = [
                    'version' => $version,
                    'os' => $os,
                    'arch' => $arch,
                    'sha256' => $sha,
                    'size' => $size,
                    'url' => $url,
                    'security' => empty($entry['security']) ? 0 : 1
                ];
            }
        }
        return $out;
    }

    /**
     * Whether a body has the shape of a signature envelope. It says nothing
     * about whether the signature verifies; only an agent decides that.
     *
     * @param string $bytes the envelope as downloaded
     *
     * @return bool
     */
    public static function isEnvelope($bytes)
    {
        $doc = json_decode((string)$bytes, true);
        return is_array($doc)
            && is_array($doc['chain'] ?? null)
            && count($doc['chain']) > 0
            && is_string($doc['alg'] ?? null)
            && '' !== $doc['alg']
            && is_string($doc['sig'] ?? null)
            && '' !== $doc['sig'];
    }

    /**
     * The file name to store a release file under: the last part of its
     * address, when that is a plain file name.
     *
     * @param string $url the file's address
     *
     * @return string '' when the address has no usable name
     */
    public static function fileName($url)
    {
        $name = basename((string)parse_url((string)$url, PHP_URL_PATH));
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $name) ? $name : '';
    }

    /**
     * The files hosts need: each host's resolved version and the version it
     * runs, for the platform it enrolled with.
     *
     * @param array $rows  list of ['override','ring','running','os','arch']
     * @param array $fleet Update::fleet(true)
     *
     * @return array key() => true
     */
    public static function neededFrom(array $rows, array $fleet)
    {
        $out = [];
        foreach ($rows as $row) {
            $os = (string)($row['os'] ?? '');
            $arch = (string)($row['arch'] ?? '');
            if (!preg_match(self::PLATFORM_PATTERN, $os)
                || !preg_match(self::PLATFORM_PATTERN, $arch)
            ) {
                continue;
            }
            $target = Update::resolve(
                $row['override'] ?? '',
                $row['ring'] ?? '',
                $row['running'] ?? '',
                $fleet
            );
            if ('' !== $target) {
                $out[self::key($target, $os, $arch)] = true;
            }
            $running = Update::normalize($row['running'] ?? '');
            if (isset($fleet['firstSeen'][$running])) {
                $out[self::key($running, $os, $arch)] = true;
            }
        }
        return $out;
    }

    /**
     * The cached files a sync may delete.
     *
     * Kept: every file a host needs, and every file of the newest $keep
     * versions still in the manifest. A withdrawn version is not counted
     * among the newest, so its files go unless a host needs them.
     *
     * @param array $cached list of ['id','version','os','arch','inManifest']
     * @param array $needed key() => true
     * @param int   $keep   how many of the newest versions to keep
     *
     * @return int[] row ids, ascending
     */
    public static function prunable(array $cached, array $needed, $keep)
    {
        $versions = [];
        foreach ($cached as $row) {
            if (!empty($row['inManifest'])) {
                $versions[(string)$row['version']] = true;
            }
        }
        $versions = array_map('strval', array_keys($versions));
        usort(
            $versions,
            function ($a, $b) {
                return version_compare($b, $a);
            }
        );
        $newest = array_flip(array_slice($versions, 0, max(0, (int)$keep)));
        $out = [];
        foreach ($cached as $row) {
            $version = (string)$row['version'];
            if (isset($needed[self::key($version, $row['os'], $row['arch'])])) {
                continue;
            }
            if (!empty($row['inManifest']) && isset($newest[$version])) {
                continue;
            }
            $out[] = (int)$row['id'];
        }
        sort($out);
        return $out;
    }

    /**
     * Version => unix time this server first saw it, for every version in
     * the manifest it last downloaded.
     *
     * @return array
     */
    public static function firstSeen()
    {
        $rows = self::$DB->query(
            'SELECT `araVersion` AS `version`, MIN(`araFirstSeen`) AS `seen` '
            . 'FROM `agentReleaseArtifacts` WHERE `araInManifest`=:listed '
            . 'GROUP BY `araVersion`',
            [],
            [':listed' => 1]
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $out = [];
        foreach ((array)$rows as $row) {
            if (!self::validDate((string)$row['seen'])) {
                continue;
            }
            $out[(string)$row['version']] = self::niceDate((string)$row['seen'])
                ->getTimestamp();
        }
        return $out;
    }

    /**
     * Whether anything on this server asks for a release, so the daemon
     * can stay idle on the shipped default.
     *
     * @return bool
     */
    public static function wanted()
    {
        if (Update::MODE_OFF !== Update::mode()) {
            return true;
        }
        if ('' !== Update::normalize(self::getSetting('FOG_AGENT_MIN_VERSION'))) {
            return true;
        }
        $rows = self::$DB->query(
            'SELECT COUNT(*) AS `n` FROM `hosts` '
            . 'WHERE `hostAgentDesiredVersion`<>:none',
            [],
            [':none' => '']
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        return (int)($rows[0]['n'] ?? 0) > 0;
    }

    /**
     * The manifest and signature for an update block, when this server
     * holds a manifest that lists the version.
     *
     * A copy that does not list the version is left out: the agent's own
     * fetch from the origin may know a release this server has not synced.
     *
     * @param string $version the version the host is told to run
     *
     * @return array|null ['manifest' => base64, 'signature' => base64]
     */
    public static function inline($version)
    {
        if (!isset(self::firstSeen()[$version])) {
            return null;
        }
        $doc = json_decode(
            (string)@file_get_contents(self::storeDir() . DS . self::MANIFEST_FILE),
            true
        );
        if (!is_array($doc)
            || !is_string($doc['manifest'] ?? null)
            || !is_string($doc['signature'] ?? null)
            || '' === $doc['manifest']
            || '' === $doc['signature']
        ) {
            return null;
        }
        return ['manifest' => $doc['manifest'], 'signature' => $doc['signature']];
    }

    /**
     * The platform a host enrolled with, from its issued enrollment.
     *
     * @param Host $Host the host
     *
     * @return string[] [os, arch], both '' when unknown
     */
    public static function platform(Host $Host)
    {
        $rows = self::$DB->query(
            'SELECT `aeOS` AS `os`, `aeArch` AS `arch` FROM `agentEnrollment` '
            . 'WHERE `aeHostID`=:host AND `aeState`=:issued '
            . 'ORDER BY `aeID` DESC LIMIT 1',
            [],
            [
                ':host' => (int)$Host->get('id'),
                ':issued' => AgentEnrollment::STATE_ISSUED
            ]
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $row = $rows[0] ?? [];
        return [(string)($row['os'] ?? ''), (string)($row['arch'] ?? '')];
    }

    /**
     * The payload id of the cached file for a host and version, or 0.
     *
     * @param Host   $Host    the host
     * @param string $version the version it is told to run
     *
     * @return int
     */
    public static function artifactFor(Host $Host, $version)
    {
        list($os, $arch) = self::platform($Host);
        if ('' === $os || '' === $arch) {
            return 0;
        }
        $rows = self::$DB->query(
            'SELECT `araID` AS `id`, `araCachedPath` AS `path` '
            . 'FROM `agentReleaseArtifacts` '
            . 'WHERE `araVersion`=:version AND `araOS`=:os AND `araArch`=:arch '
            . 'AND `araInManifest`=:listed AND `araCachedPath`<>:none',
            [],
            [
                ':version' => (string)$version,
                ':os' => $os,
                ':arch' => $arch,
                ':listed' => 1,
                ':none' => ''
            ]
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $row = $rows[0] ?? null;
        if (!$row || !is_file((string)$row['path'])) {
            return 0;
        }
        return (int)$row['id'];
    }

    /**
     * Streams one cached file to an agent.
     *
     * Only the file of the version the host is told to run. The files are
     * public releases, so this is not about secrecy. It keeps the route to
     * what the update block offered, so a payload request means one thing.
     *
     * @param int    $id      the agentReleaseArtifacts row
     * @param string $version the version the host is told to run
     *
     * @throws \RuntimeException with an HTTP code when it cannot be served
     *
     * @return void
     */
    public static function stream($id, $version)
    {
        $rows = self::$DB->query(
            'SELECT `araVersion` AS `version`, `araCachedPath` AS `path` '
            . 'FROM `agentReleaseArtifacts` WHERE `araID`=:id',
            [],
            [':id' => (int)$id]
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $row = $rows[0] ?? null;
        if (!$row || '' === (string)$row['path']) {
            throw new \RuntimeException('no such release file', 404);
        }
        if ('' === (string)$version || Update::normalize($row['version']) !== $version) {
            throw new \RuntimeException('not the version this host is told to run', 404);
        }
        $base = realpath(self::storeDir());
        $path = realpath((string)$row['path']);
        if (false === $base
            || false === $path
            || 0 !== strpos($path, $base . DS)
            || !is_readable($path)
        ) {
            throw new \RuntimeException('the release file is missing', 404);
        }
        $fh = fopen($path, 'rb');
        if (false === $fh) {
            throw new \RuntimeException('cannot read the release file', 503);
        }
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-store');
        fpassthru($fh);
        fclose($fh);
    }

    /**
     * One sync: download the manifest, index it, fetch the files hosts
     * need, and prune what nothing keeps.
     *
     * @param callable $say takes one line for the service log
     *
     * @throws \RuntimeException when the manifest cannot be had
     *
     * @return array ['files' => n, 'downloaded' => n, 'failed' => n, 'pruned' => n]
     */
    public static function sync(callable $say)
    {
        $dir = self::storeDir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            throw new \RuntimeException(sprintf('%s: %s', _('Cannot create'), $dir));
        }
        $url = self::manifestUrl();
        // Two requests, not one call with two addresses: a batch can answer
        // in the order the transfers finish, and a signature read as the
        // manifest would fail every sync where it arrived first.
        $manifest = (string)(self::$FOGURLRequests->process(
            $url,
            'GET',
            null,
            false,
            false,
            false,
            false,
            60
        )[0] ?? '');
        $signature = (string)(self::$FOGURLRequests->process(
            $url . '.sig',
            'GET',
            null,
            false,
            false,
            false,
            false,
            60
        )[0] ?? '');
        $files = self::parseManifest($manifest);
        if (!self::isEnvelope($signature)) {
            throw new \RuntimeException(
                sprintf('%s: %s.sig', _('The release signature is not readable'), $url)
            );
        }
        self::_storeManifest($dir, $manifest, $signature);
        $now = self::niceDate()->format('Y-m-d H:i:s');
        self::_index($files, $now);

        $fleet = Update::fleet(true);
        $needed = self::neededFrom(self::_hostRows(), $fleet);
        $downloaded = 0;
        $failed = 0;
        foreach (self::_rows() as $row) {
            $key = self::key($row['version'], $row['os'], $row['arch']);
            if (empty($row['inManifest']) || !isset($needed[$key])) {
                continue;
            }
            if ('' !== $row['path'] && is_file($row['path'])) {
                continue;
            }
            try {
                self::_download($dir, $row, $now);
                $say(sprintf('%s %s', _('Downloaded'), $row['url']));
                $downloaded++;
            } catch (\RuntimeException $e) {
                $say($e->getMessage());
                $failed++;
            }
        }

        $keep = trim((string)self::getSetting('FOG_AGENT_KEEP_VERSIONS'));
        $keep = ctype_digit($keep) ? (int)$keep : self::DEFAULT_KEEP;
        $cached = array_filter(
            self::_rows(),
            function ($row) {
                return '' !== $row['path'];
            }
        );
        $pruned = 0;
        foreach (self::prunable(array_values($cached), $needed, $keep) as $id) {
            foreach ($cached as $row) {
                if ((int)$row['id'] === $id) {
                    self::_forget($dir, $row);
                    $say(sprintf('%s %s', _('Removed'), $row['path']));
                    $pruned++;
                }
            }
        }
        return [
            'files' => count($files),
            'downloaded' => $downloaded,
            'failed' => $failed,
            'pruned' => $pruned
        ];
    }

    /**
     * Writes the manifest and signature as one file, by rename.
     *
     * @param string $dir       the store directory
     * @param string $manifest  the manifest bytes
     * @param string $signature the signature bytes
     *
     * @throws \RuntimeException when it cannot be written
     *
     * @return void
     */
    private static function _storeManifest($dir, $manifest, $signature)
    {
        $body = json_encode(
            [
                'manifest' => base64_encode($manifest),
                'signature' => base64_encode($signature)
            ]
        );
        $final = $dir . DS . self::MANIFEST_FILE;
        if (is_file($final) && @file_get_contents($final) === $body) {
            return;
        }
        $tmp = $final . '.part';
        if (false === @file_put_contents($tmp, $body) || !@rename($tmp, $final)) {
            @unlink($tmp);
            throw new \RuntimeException(sprintf('%s: %s', _('Cannot write'), $final));
        }
        @chmod($final, 0644);
    }

    /**
     * Replaces the index with the manifest's files, in one transaction so
     * a poll never sees a moment where no version is listed.
     *
     * A version keeps the first-seen time of its first row. A file whose
     * hash changed loses its cached copy, which is then fetched again.
     *
     * @param array  $files parseManifest() rows
     * @param string $now   the sync time
     *
     * @return void
     */
    private static function _index(array $files, $now)
    {
        self::$DB->query('START TRANSACTION');
        try {
            self::$DB->query(
                'UPDATE `agentReleaseArtifacts` SET `araInManifest`=:unlisted',
                [],
                [':unlisted' => 0]
            );
            foreach ($files as $file) {
                self::$DB->query(
                    'INSERT INTO `agentReleaseArtifacts` '
                    . '(`araVersion`,`araOS`,`araArch`,`araSHA256`,`araSize`,'
                    . '`araURL`,`araSecurity`,`araInManifest`,`araFirstSeen`) '
                    . 'VALUES (:version,:os,:arch,:sha,:size,:url,:security,'
                    . ':listed,:now) '
                    . 'ON DUPLICATE KEY UPDATE '
                    . '`araCachedPath`=IF(`araSHA256`=VALUES(`araSHA256`),'
                    . '`araCachedPath`,\'\'),'
                    . '`araCachedAt`=IF(`araSHA256`=VALUES(`araSHA256`),'
                    . '`araCachedAt`,NULL),'
                    . '`araSHA256`=VALUES(`araSHA256`),'
                    . '`araSize`=VALUES(`araSize`),'
                    . '`araURL`=VALUES(`araURL`),'
                    . '`araSecurity`=VALUES(`araSecurity`),'
                    . '`araInManifest`=VALUES(`araInManifest`)',
                    [],
                    [
                        ':version' => $file['version'],
                        ':os' => $file['os'],
                        ':arch' => $file['arch'],
                        ':sha' => $file['sha256'],
                        ':size' => $file['size'],
                        ':url' => $file['url'],
                        ':security' => $file['security'],
                        ':listed' => 1,
                        ':now' => $now
                    ]
                );
            }
            self::$DB->query('COMMIT');
        } catch (\Exception $e) {
            self::$DB->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Every enrolled host's override, ring, running version and platform.
     *
     * @return array
     */
    private static function _hostRows()
    {
        return (array)self::$DB->query(
            'SELECT DISTINCT `h`.`hostAgentDesiredVersion` AS `override`, '
            . '`h`.`hostAgentUpdateRing` AS `ring`, '
            . '`h`.`hostAgentVersion` AS `running`, '
            . '`e`.`aeOS` AS `os`, `e`.`aeArch` AS `arch` '
            . 'FROM `hosts` `h` INNER JOIN `agentEnrollment` `e` '
            . 'ON `e`.`aeHostID`=`h`.`hostID` AND `e`.`aeState`=:issued '
            . 'WHERE `h`.`hostAgentFingerprint`<>:none',
            [],
            [':issued' => AgentEnrollment::STATE_ISSUED, ':none' => '']
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
    }

    /**
     * Every index row.
     *
     * @return array
     */
    private static function _rows()
    {
        return (array)self::$DB->query(
            'SELECT `araID` AS `id`, `araVersion` AS `version`, `araOS` AS `os`, '
            . '`araArch` AS `arch`, `araSHA256` AS `sha256`, `araSize` AS `size`, '
            . '`araURL` AS `url`, `araInManifest` AS `inManifest`, '
            . '`araCachedPath` AS `path` FROM `agentReleaseArtifacts` '
            . 'WHERE `araID`>:none',
            [],
            [':none' => 0]
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
    }

    /**
     * Downloads one file, and records it only when its size and hash match
     * the manifest.
     *
     * @param string $dir the store directory
     * @param array  $row an index row
     * @param string $now the sync time
     *
     * @throws \RuntimeException when the file cannot be had
     *
     * @return void
     */
    private static function _download($dir, array $row, $now)
    {
        $name = self::fileName($row['url']);
        $versionDir = $dir . DS . $row['version'];
        if ('' === $name
            || (!is_dir($versionDir) && !@mkdir($versionDir, 0755, true))
        ) {
            throw new \RuntimeException(sprintf('%s: %s', _('Cannot store'), $row['url']));
        }
        $final = $versionDir . DS . $name;
        $tmp = $final . '.part';
        $fh = @fopen($tmp, 'wb');
        if (false === $fh) {
            throw new \RuntimeException(sprintf('%s: %s', _('Cannot write'), $tmp));
        }
        self::$FOGURLRequests->process(
            $row['url'],
            'GET',
            null,
            false,
            false,
            false,
            $fh,
            900
        );
        fclose($fh);
        clearstatcache(true, $tmp);
        $good = is_file($tmp)
            && filesize($tmp) === (int)$row['size']
            && hash_equals((string)$row['sha256'], (string)hash_file('sha256', $tmp));
        if (!$good || !@rename($tmp, $final)) {
            @unlink($tmp);
            throw new \RuntimeException(
                sprintf('%s: %s', _('Size or hash does not match the manifest'), $row['url'])
            );
        }
        @chmod($final, 0644);
        self::$DB->query(
            'UPDATE `agentReleaseArtifacts` SET `araCachedPath`=:path, '
            . '`araCachedAt`=:now WHERE `araID`=:id',
            [],
            [':path' => $final, ':now' => $now, ':id' => (int)$row['id']]
        );
    }

    /**
     * Deletes one cached file, and its version directory once empty.
     *
     * @param string $dir the store directory
     * @param array  $row an index row
     *
     * @return void
     */
    private static function _forget($dir, array $row)
    {
        $base = realpath($dir);
        $path = realpath((string)$row['path']);
        if (false !== $base && false !== $path && 0 === strpos($path, $base . DS)) {
            @unlink($path);
            @rmdir(dirname($path));
        }
        self::$DB->query(
            'UPDATE `agentReleaseArtifacts` SET `araCachedPath`=:none, '
            . '`araCachedAt`=NULL WHERE `araID`=:id',
            [],
            [':none' => '', ':id' => (int)$row['id']]
        );
    }
}
