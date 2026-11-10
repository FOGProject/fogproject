<?php
/**
 * service/hostinfo.php sends the image OS's family to FOS as osfamily.
 *
 * fogproject#2083. FOS decided "Windows, Linux or macOS" from a hardcoded
 * list of osID numbers, so every new os row needed a FOS release. The
 * server now reads the family from the os row and sends it next to osid.
 * An empty family means "not sent" to FOS, so an image with no valid OS, and
 * a task that has no image data, send osfamily='' and raise no error.
 *
 * The script runs top to bottom with no function to call. So this test cuts
 * out three pieces of the script's own source by their anchors and runs
 * them against stubs: the lines that read the image's OS, the reply field
 * that carries the family, and the loop that prints the reply. The anchors
 * exist in the code before the change, so the test fails there on the
 * values, not on a missing anchor.
 *
 * Requires no database.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace HostinfoOsFamilyTest;

require_once __DIR__ . '/lib/fog-test-harness.php';

/**
 * Stand-in OS: a valid one has an id and a family.
 */
class StubOS
{
    /**
     * The fields.
     *
     * @var array
     */
    public $fields;
    /**
     * Holds the fields.
     *
     * @param array $fields the fields
     */
    public function __construct(array $fields)
    {
        $this->fields = $fields;
    }
    /**
     * Tells whether the OS row exists.
     *
     * @return bool
     */
    public function isValid()
    {
        return !empty($this->fields['id']);
    }
    /**
     * Reads a field.
     *
     * @param string $key the field
     *
     * @return mixed
     */
    public function get($key)
    {
        return $this->fields[$key] ?? '';
    }
}

/**
 * Stand-in image: it has an osID and answers getOS().
 */
class StubImage
{
    /**
     * The OS the image is for.
     *
     * @var StubOS
     */
    public $os;
    /**
     * The osID column.
     *
     * @var int
     */
    public $osID;
    /**
     * Holds the OS.
     *
     * @param int    $osID the osID column
     * @param StubOS $os   the OS
     */
    public function __construct($osID, StubOS $os)
    {
        $this->osID = $osID;
        $this->os = $os;
    }
    /**
     * Reads a field.
     *
     * @param string $key the field
     *
     * @return mixed
     */
    public function get($key)
    {
        return 'osID' === $key ? $this->osID : '';
    }
    /**
     * Returns the OS.
     *
     * @return StubOS
     */
    public function getOS()
    {
        return $this->os;
    }
    /**
     * Mirrors Image::getOSFamily(): empty when the OS is not valid.
     *
     * @return string
     */
    public function getOSFamily()
    {
        $OS = $this->getOS();
        return $OS->isValid() ? $OS->get('family') : '';
    }
}

$t = new \FogChecks();

$source = (string)file_get_contents(
    dirname(__DIR__) . '/packages/web/service/hostinfo.php'
);

$osStart = strpos($source, "        \$osid = \$Image->get('osID');");
$osEnd = strpos($source, "        \$storage = sprintf(");
$t->check(
    'the image-OS block is found by its anchors',
    false !== $osStart && false !== $osEnd && $osStart < $osEnd
);
$osBlock = substr($source, (int)$osStart, (int)$osEnd - (int)$osStart);

$fieldStart = strpos($source, "        'osid' => \$osid,");
$fieldEnd = strpos($source, "        'storage' => \$storage,");
$t->check(
    'the osid reply field is found by its anchors',
    false !== $fieldStart && false !== $fieldEnd && $fieldStart < $fieldEnd
);
$fieldBlock = substr(
    $source,
    (int)$fieldStart,
    (int)$fieldEnd - (int)$fieldStart
);

$printStart = strpos($source, "    foreach ((array)\$repFields as \$key => \$val) {");
$printEnd = strpos($source, "    // Unset lock and update token");
$t->check(
    'the reply print loop is found by its anchors',
    false !== $printStart && false !== $printEnd && $printStart < $printEnd
);
$printBlock = substr(
    $source,
    (int)$printStart,
    (int)$printEnd - (int)$printStart
);

/**
 * Runs the pieces for one image and returns what the reply prints for the
 * OS fields.
 *
 * @param string         $osBlock    the image-OS block's source
 * @param string         $fieldBlock the reply field block's source
 * @param string         $printBlock the print loop's source
 * @param StubImage|null $Image      the image, or null when the task has
 *                                   none and the block does not run
 *
 * @return string the printed lines
 */
function reply($osBlock, $fieldBlock, $printBlock, $Image)
{
    // hostinfo.php leaves $osid unassigned too when the task needs no init.
    $osid = null;
    if (null !== $Image) {
        // The block names its variables unqualified.
        eval($osBlock);
    }
    // The field block is array elements; wrap them to evaluate them.
    $repFields = eval('return [' . $fieldBlock . '];');
    ob_start();
    eval($printBlock);
    return (string)ob_get_clean();
}

$windows = new StubImage(10, new StubOS(['id' => 10, 'family' => 'windows']));
$linux = new StubImage(50, new StubOS(['id' => 50, 'family' => 'linux']));
$noOS = new StubImage(0, new StubOS([]));

$out = reply($osBlock, $fieldBlock, $printBlock, $windows);
$t->check(
    'an image with osID 10 prints osfamily windows',
    false !== strpos(
        $out,
        "[[ -z \$osfamily ]] && export osfamily='windows'\n"
    )
);
$out = reply($osBlock, $fieldBlock, $printBlock, $linux);
$t->check(
    'an image with osID 50 prints osfamily linux',
    false !== strpos(
        $out,
        "[[ -z \$osfamily ]] && export osfamily='linux'\n"
    )
);
$out = reply($osBlock, $fieldBlock, $printBlock, $noOS);
$t->check(
    'an image with no valid OS prints an empty osfamily',
    false !== strpos($out, "[[ -z \$osfamily ]] && export osfamily=''\n")
);
$out = reply($osBlock, $fieldBlock, $printBlock, null);
$t->check(
    'a task with no image data prints an empty osfamily and raises no error',
    false !== strpos($out, "[[ -z \$osfamily ]] && export osfamily=''\n")
);
$out = reply($osBlock, $fieldBlock, $printBlock, $windows);
$t->check(
    'the osid field is still sent',
    false !== strpos($out, "[[ -z \$osid ]] && export osid='10'\n")
);

$t->finish();
