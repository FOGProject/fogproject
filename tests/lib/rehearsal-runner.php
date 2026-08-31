<?php
/**
 * The rehearsal's schema-step runner, kept out of bin/ deliberately.
 *
 * bin/upgrade-rehearsal.php is a CLI script: it parses arguments and exits
 * before FOG has booted. A class declared in that file can therefore never be
 * autoloaded, and tests/all-classes-load.test.php requires that every class in
 * a tracked .php outside vendor/ and tests/ loads on demand -- correctly, since
 * that gate is what catches a genuinely broken class file. The class cannot
 * move to the top of the script either: it extends FOGBase, so declaring it
 * before the autoloader is registered is a fatal error.
 *
 * So it lives here, beside the other harness classes, and the script requires
 * it once FOG is up. Same reasoning as fog-schema-collector.php's "included,
 * never run": run-all.sh globs tests/*.test.php at the top level only, so
 * nothing in tests/lib/ is mistaken for a test.
 *
 * NOT the same thing as fog-schema-collector.php, which builds the step array
 * with no database at all by manufacturing the constants and classes
 * schema.php reaches for. That answers "what steps are there"; this one runs
 * them against a live server with FOG really booted, which is the only way to
 * find out what they do to data.
 *
 * PHP version 7.4+
 *
 * @category Schema
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * Runs commons/schema.php's steps the way SchemaUpdaterPage::update() does.
 *
 * Extends FOGBase rather than FOGPage so that `self::$DB`, `self::getClass()`
 * and the `$this->schema[]` the included file appends to all resolve exactly
 * as they do inside the real page, without dragging in the page chrome, the
 * authorization tiers or the jsonSend() that ends the request.
 *
 * The loop in run() is a faithful copy of update()'s, INCLUDING its skiperrs
 * list and its `break 2` on the first hard failure. Both matter here: the
 * tolerated errors are why a re-run is a no-op, and the break is exactly the
 * "stopped at step N of 12" state the revert script has to cope with.
 *
 * @category Schema
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class RehearsalRunner extends \FOG\Base\FOGBase
{
    /**
     * The step array, as commons/schema.php appends to it.
     *
     * @var array
     */
    public $schema = [];

    /**
     * Builds the step array by including commons/schema.php in this context.
     *
     * @return array
     */
    public function collect()
    {
        // Built rather than written literally, for the same reason as the boot
        // above: PHPStan resolves a literal include path against the repo,
        // where this one does not exist.
        $schemaFile = rtrim((string)constant('BASEPATH'), DS) . DS . 'commons' . DS . 'schema.php';
        include $schemaFile;

        return $this->schema;
    }

    /**
     * Runs steps [$from, $to) and stamps the version as it goes.
     *
     * @param int  $from  first step index to run
     * @param int  $to    stop before this index; null runs to the end
     * @param bool $stamp whether to record the version reached
     *
     * @return array ['ran' => int, 'landed' => int, 'errors' => array]
     */
    public function run($from, $to = null, $stamp = true)
    {
        $steps = $this->collect();
        $items = array_slice($steps, $from, null, true);
        $errors = [];
        $landed = $from;
        $ran = 0;
        // update()'s own list. A statement failing with one of these means
        // the step has already been applied, which is what makes the whole
        // loop idempotent -- and what makes a re-run after a partial
        // failure a supported thing to do rather than a second disaster.
        $skiperrs = [1050, 1054, 1060, 1061, 1062, 1091];
        foreach ($items as $version => $updates) {
            if (null !== $to && $version >= $to) {
                break;
            }
            foreach ((array)$updates as $update) {
                if (!$update) {
                    continue;
                }
                if (is_callable($update)) {
                    $result = $update();
                    if (is_string($result)) {
                        $errors[] = sprintf('step %d: %s', $version + 1, $result);
                        break 2;
                    }
                    continue;
                }
                if (false !== self::$DB->query($update)->error) {
                    if (in_array(self::$DB->errorCode, $skiperrs)) {
                        continue;
                    }
                    $errors[] = sprintf(
                        'step %d: %s',
                        $version + 1,
                        trim(strtok((string)self::$DB->error, "\n"))
                    );
                    break 2;
                }
            }
            $landed = $version + 1;
            $ran++;
        }
        if ($stamp) {
            $schema = self::getClass('Schema', 1);
            $schema->set('version', $landed)->save();
        }

        return ['ran' => $ran, 'landed' => $landed, 'errors' => $errors];
    }

    /**
     * The database handle, reachable from the harness's own helpers.
     *
     * FOGBase::$DB is protected, so global-scope code cannot touch it. A
     * subclass can, and everything below runs against the same connection the
     * schema steps do -- which matters: PDODB issues `SET SESSION sql_mode=''`
     * on connect, and a second connection with the server's own sql_mode
     * would reject seed rows the application itself accepts.
     *
     * @return object
     */
    public static function db()
    {
        return self::$DB;
    }

    /**
     * The version the database records.
     *
     * @return int
     */
    public function version()
    {
        $res = self::$DB->query('SELECT `vValue` FROM `schemaVersion` LIMIT 1');
        if (false !== $res->error) {
            return -1;
        }
        $row = $res->fetch()->get();

        return (int)(is_array($row) ? reset($row) : $row);
    }
}
