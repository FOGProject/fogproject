<?php
/**
 * System, the basic system layout.
 *
 * PHP version 7.4+
 *
 * This just presents the system variables
 *
 * @category System
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Base;

/**
 * System, the basic system layout.
 *
 * @category System
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class System
{
    const PHP_MINIMUM = '5.5.0';
    const PHP_MAXIMUM = '9';
    /**
     * Checks the php version against what we require.
     *
     * @return void
     */
    private static function _versionCompare()
    {
        $msg = '';
        if (
            !(version_compare(PHP_VERSION, self::PHP_MINIMUM, '>=')
            && version_compare(PHP_VERSION, self::PHP_MAXIMUM, '<'))
        ) {
            $msg = _('You are currently running PHP Version')
                . ': '
                . PHP_VERSION
                . ', '
                . _('FOG Needs at least')
                . ': '
                . self::PHP_MINIMUM
                . ', '
                . _('and below')
                . ': '
                . self::PHP_MAXIMUM;
        }
        if ($msg) {
            die($msg);
        }
    }
    /**
     * Constructs the system variables.
     */
    public function __construct()
    {
        self::_versionCompare();
        // THE VERSION IS GENERATED WHEN IT CAN BE, AND FALLS BACK HERE.
        //
        // commons/version.php is written from the commit graph by
        // .githooks/lib/write-version-file.sh -- on commit, checkout and
        // merge for anyone with hooks enabled, and by bin/installfog.sh for
        // an install from a clone. It is gitignored, for the same reason
        // commons/config.class.php is: a value computed from the environment
        // must not be in the merge surface.
        //
        // It was tracked until GH-1513, and that is what made a busy
        // afternoon unworkable. FOG_VERSION is `git rev-list master..HEAD
        // --count`, so it differs per branch while living on one line: any
        // two branches open at once conflicted on it. The quantity is also
        // self-perturbing -- bringing a branch up to date adds a commit,
        // which moves the count, which earns another rewrite, which has to be
        // pushed and pulled, which adds another commit. GH-1510 stopped
        // writing it per commit and per pull request; this stops writing it
        // into git at all.
        //
        // dirname(__DIR__, 2) is the web root in both layouts that matter:
        // packages/web/ in the repository, and the deployed document root,
        // since src/Base/ sits directly under both.
        //
        // The fallback below is not a stale copy of the generated value -- it
        // is the RELEASE version, and the only thing that ever rewrites it is
        // a release. A source zip with no .git, or a checkout by someone who
        // never enabled hooks, reports that: truthful, if less precise than a
        // build count.
        $generated = dirname(__DIR__, 2) . '/commons/version.php';
        if (is_readable($generated)) {
            include_once $generated;
        }
        if (!defined('FOG_VERSION')) {
            define('FOG_VERSION', '1.6.0-beta');
        }
        if (!defined('FOG_CHANNEL')) {
            define('FOG_CHANNEL', 'Beta');
        }
        // Bumped by one for every element added to $this->schema in
        // commons/schema.php, and it must never fall BELOW that element
        // count. DatabaseManager::init() and schemaNeedsDeploy() test
        // `mySchema < FOG_SCHEMA` to decide whether to send the admin (or the
        // installer's token bootstrap) to the schema updater, and the updater
        // then does the real work with `count($this->schema) <= mySchema`. So
        // this is the coarse gate and the count is the precise one -- and a
        // gate below the count means the admin is never sent to the updater
        // at all, so the precise check never gets to run and the step sits
        // there applying to nobody.
        //
        // That is not hypothetical: 18edea94f appended the element labeled
        // 330 without touching this constant, leaving it at 329, and task
        // type 26 reached no install until this bump. An earlier revision of
        // this comment said the constant had "drifted well above" the count
        // (289 at the time) and that nothing required the two to agree, which
        // read as license not to bump. They do have to agree, so tests/
        // schema-gate.test.php now fails the build when they do not.
        //
        // The invariant the test pins, and the one to keep when appending:
        // the highest `// N` label in commons/schema.php equals
        // count($this->schema) equals this constant. The labels are not
        // array indexes -- index 79 is written by a foreach over
        // $keySequences, so the numbers only line up as counts.
        //
        // Corollary worth knowing before chasing a step that "did not run": a
        // database storing a vValue ABOVE the element count -- which is what a
        // 1.5.x carried count does, see SchemaReconciler's docstring -- is
        // permanently "up to date" from the updater's point of view and will
        // never run another indexed step, whatever this constant says.
        define('FOG_SCHEMA', 430);
        define('FOG_BCACHE_VER', 364);
        define('FOG_CLIENT_VERSION', '0.13.0');
        // GH-959: iPXE lives in FOGProject/fog-ipxe and its binaries arrive as
        // a release asset. Pinned here rather than tracked as "latest" so a
        // given FOG release ships a known iPXE -- the installer uses this both
        // to pick the download and to check out the matching source when an
        // HTTPS install has to rebuild with its own CA.
        define('FOG_IPXE_VERSION', 'v2.0.0-fog.8');
        // ADR 0009: the bundled plugins live in FOGProject/fog-plugins and are
        // no longer committed here. Same shape as the iPXE pin above -- the
        // installer reads this to pick which release to download, so a given
        // FOG release ships a known set of plugins rather than whatever the
        // default branch held on the day someone installed.
        define('FOG_PLUGINS_VERSION', 'v1.6.24');
        // GH-850: FOG_BASE_DIR is now installer-driven. Initiator loads
        // commons/fogpaths.php (written from the installer's $fogprogramdir)
        // before the autoloader runs, so in a normal boot these are already
        // defined by the time this class loads.
        //
        // It cannot come from a globalSetting: getSetting() needs the cache
        // dir, which is derived from this, before the DB is up.
        //
        // These stay here, guarded, as the last-resort defaults for any entry
        // point that reaches System without going through Initiator.
        if (!defined('FOG_BASE_DIR')) {
            define('FOG_BASE_DIR', '/opt/fog');
        }
        if (!defined('FOG_CACHE_DIR')) {
            define('FOG_CACHE_DIR', FOG_BASE_DIR . DS . 'cache');
        }
        if (!defined('FOG_LOG_DIR')) {
            define('FOG_LOG_DIR', FOG_BASE_DIR . DS . 'log');
        }
    }
}
