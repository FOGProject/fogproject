<?php
/**
 * Displays 'reports' for the admins.
 *
 * PHP version 7.4+
 *
 * @category ReportManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Pages;

use FOG\Base\FOGPage;
use FOG\Router\HTTPResponseCodes;

/**
 * Displays 'reports' for the admins.
 *
 * @category ReportManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ReportManagement extends FOGPage
{
    /**
     * The node this page displays from.
     *
     * @var string
     */
    public $node = 'report';
    /**
     * Loads custom reports.
     *
     * @return array
     */
    public static function loadCustomReports()
    {
        // Core reports are src/Reports/<Class>.php and a plugin's are
        // <plugin>/src/Reports/<Class>.php -- one shape, one bucket name,
        // two roots (ADR 0035).
        $files = self::fastmerge(
            self::coreitems('Reports'),
            self::pluginitems('Reports')
        );
        foreach ($files as $i => &$file) {
            $base = basename($file, '.php');
            // Lowercased because this name is a contract in three directions
            // and every one of them is lowercase: the menu label, the base64
            // `f` parameter loadPageClasses() decodes back into a class name,
            // and the keys of Authorization::REPORT_NODES. It used to come
            // out lowercase for free -- the file was audit_report.report.php.
            // The PSR-4 filename is Audit_Report.php, so the case has to be
            // normalized here or the same report answers to a different URL
            // and a different permission node than it did before. A plugin's
            // report is normalized by the same line, which is why plugin
            // report classes keep core's underscored spelling (LDAP_Report)
            // rather than being renamed with the rest of the move.
            $files[$i] = strtolower(
                str_replace(
                    '_',
                    ' ',
                    $base
                )
            );
            unset($file);
        }
        @natcasesort($files);
        return $files;
    }

    /**
     * The reports that are AGGREGATIONS rather than row dumps.
     *
     * The report menu was one flat alphabetical list in which "Imaging
     * Report", which is charts over a window, sat between "History Report"
     * and "Hosts and Users", which are tables. Those are two different kinds of
     * screen and presenting them as one kind is the thing that reads as
     * unfinished (ADR 0030).
     *
     * A LIST HERE RATHER THAN A CONVENTION on the filename, because the
     * convention does not hold: `history_report` ends in `_report` and is a
     * row dump with a redirect on it, and `run_history` does not and is an
     * aggregation. Naming them is honest about that; inferring it would be
     * wrong twice on the files that ship today.
     *
     * NOT read by loading the classes. The menu is built from file names
     * precisely so that rendering a sidebar does not load fourteen classes,
     * and a `const REPORT_GROUP` on each would give that up for a label.
     *
     * A report not named here -- a plugin's -- lands under Lists, which is
     * what a FOG report has always been. A plugin that disagrees can move
     * its entry with the SUB_MENULINK_DATA hook, the same seam it used to
     * add the entry.
     *
     * @var array
     */
    const AGGREGATIONS = [
        'audit report',
        'fleet report',
        'hardware report',
        'imaging report',
        'run history',
        'snapin report',
        'storage report'
    ];

    /**
     * The report list, split into its two kinds.
     *
     * @return array group label => ordered list of report names
     */
    public static function groupedReports()
    {
        $groups = [
            'reports' => [],
            'lists' => []
        ];
        foreach (self::loadCustomReports() as $report) {
            $key = in_array(strtolower($report), self::AGGREGATIONS, true)
                ? 'reports'
                : 'lists';
            $groups[$key][] = $report;
        }

        return $groups;
    }
    /**
     * The label each shipped report is known by.
     *
     * THE MENU USED TO BE THE FILE NAME. `_(ucwords(strtolower($file)))`
     * turns `pending_mac_list.report.php` into "Pending Mac List", so the
     * sidebar could never say "MAC", never say "and" in lower case, and
     * never say anything a file name cannot spell. Worse, it disagreed with
     * the page it opened: seven of the thirteen shipped reports rendered a
     * heading that was not their menu entry -- "History Report" opened
     * "Full History", "Hosts And Users" opened "User Logins", "File
     * Deleter" opened "Files Deleted List". Two names for one screen is the
     * kind of thing that reads as unfinished.
     *
     * So the label is data. This map is the ONE definition: the sidebar
     * reads it, and every report's own $this->title reads it through
     * reportTitle(), so the two cannot drift apart again.
     *
     * WHERE THE TWO DISAGREED, THE HEADING WON. Nobody chose "Hosts And
     * Users" or "Pending Mac List" -- ucwords() produced them from a file
     * name. Somebody did choose "User Logins" and "Pending MAC Addresses",
     * which is why they were written into the pages. No label here is new:
     * every one of them is already on screen somewhere in FOG today.
     *
     * A METHOD RATHER THAN A CONST because the values are _() calls and a
     * constant expression cannot hold one. That is also what keeps these
     * msgids in the catalog: xgettext extracts from the literal call site
     * only, so the list that used to sit in a never-called
     * _reportNamesForTranslation() is now the list that is actually used --
     * one place to edit instead of two that could disagree.
     *
     * KEYED BY THE FILE NAME with underscores as spaces, lower case, which
     * is what loadCustomReports() hands back and what base64 `f` carries.
     *
     * @return array report name => label
     */
    public static function reportTitles()
    {
        if (null !== self::$_titles) {
            return self::$_titles;
        }

        $titles = [
            'audit report' => _('Audit Report'),
            'file deleter' => _('Files Deleted List'),
            'fleet report' => _('Fleet Report'),
            'hardware report' => _('Hardware Report'),
            'history report' => _('Full History'),
            'hosts and users' => _('User Logins'),
            'imaging report' => _('Imaging Report'),
            'installed software' => _('Installed Software'),
            'directory membership' => _('Directory Membership'),
            'printer deployment' => _('Printer Deployment'),
            'user sessions' => _('User Sessions'),
            'pending mac list' => _('Pending MAC Addresses'),
            'product keys' => _('Host Product Keys'),
            'run history' => _('Run History'),
            'snapin list' => _('Snapin List'),
            'snapin report' => _('Snapin Report'),
            'software report' => _('Software Report'),
            'storage report' => _('Storage Report')
        ];

        /**
         * Plugins name their own reports here.
         *
         * A bundled plugin's report is in exactly the state core's were:
         * `ou_report.report.php` renders a heading that says "Export OUs"
         * under a menu entry that says "Ou Report", because ucwords() of a
         * file name is all the sidebar had. The map above is core's own and
         * a plugin cannot add a row to it, so without this event the only
         * way to fix that is eight separate SUB_MENULINK_DATA listeners
         * rewriting a base64 key each -- which is the seam for MOVING an
         * entry, not for naming one.
         *
         * Keyed the same way as the rows above: the file name with
         * underscores as spaces, lower case.
         *
         *     $arguments['titles']['ou report'] = _('Export OUs');
         *
         * A listener that names a report which is not on disk adds an entry
         * nobody sees; one that overwrites a core key wins, which is
         * deliberate -- the same latitude every other *_DATA event gives.
         */
        self::$HookManager->processEvent(
            'REPORT_TITLE_DATA',
            ['titles' => &$titles]
        );

        self::$_titles = $titles;

        return $titles;
    }

    /**
     * The resolved title map, built once per request.
     *
     * MEMOIZED BECAUSE THE SIDEBAR ASKS PER ENTRY. titleFor() is called
     * once for every report in the menu and again by each report for its
     * own heading, so without this the hook above fires fifteen times to
     * produce the same array -- on every page in FOG, since the menu is
     * built for every node whether or not the user is on it.
     *
     * @var array|null
     */
    private static $_titles = null;

    /**
     * The label for one report, by name.
     *
     * FALLS BACK TO THE OLD DERIVATION rather than to an empty entry. A
     * report a plugin drops in is not in the map and must still get a menu
     * entry -- and the entry it gets is exactly the one it got before this
     * map existed, so nothing outside core changes.
     *
     * @param string $report the report name, e.g. 'pending mac list'
     *
     * @return string
     */
    public static function titleFor($report)
    {
        $titles = self::reportTitles();
        $key = strtolower((string) $report);

        return $titles[$key] ?? _(ucwords($key));
    }

    /**
     * The calling report's own label.
     *
     * Derived from the class name rather than passed in, because the class
     * name, the file name and the menu key are already required to agree --
     * FOGPageManager resolves the class FROM the base64 file name. Deriving
     * it means a report cannot be given the wrong key by hand.
     *
     * @return string
     */
    public static function reportTitle()
    {
        $short = static::class;
        $pos = strrpos($short, '\\');
        if (false !== $pos) {
            $short = substr($short, $pos + 1);
        }

        return self::titleFor(str_replace('_', ' ', strtolower($short)));
    }
    /**
     * The rows this report serves, as a DataTables payload.
     *
     * THE SEAM THAT MAKES AN EXPORT POSSIBLE. Every report used to end its
     * getList() with `echo ...; exit;`, which is fine for the grid and
     * leaves nothing for anything else to call: an exporting caller cannot
     * take back control from a method that exits. Splitting the fetch from
     * the emit means getList() and exportAll() below run the SAME query
     * with the same arguments, so a CSV cannot disagree with the screen it
     * was taken from.
     *
     * The shape is the DataTables envelope -- at minimum ['data' => rows],
     * plus 'recordsFiltered' and 'truncated' where the source knows them.
     * That is what Route::listem() already produces, and what the
     * ADR 0030 rollups already shape by hand.
     *
     * @return array
     */
    protected function reportRows()
    {
        return ['data' => []];
    }

    /**
     * Emit a report payload as JSON and stop.
     *
     * @param array $payload the reportRows() envelope
     *
     * @return void
     */
    protected function sendReportRows(array $payload)
    {
        header('Content-type: application/json');
        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Serve the report's rows to its grid.
     *
     * @return void
     */
    public function getList()
    {
        $this->sendReportRows($this->reportRows());
    }

    /**
     * Stream the whole report as a CSV download.
     *
     * WHY THIS EXISTS. The report toolbar's Copy/CSV/Excel/Print buttons are
     * DataTables' own, and they can only see rows the browser is holding.
     * On the eight reports that page server side that is ONE PAGE -- click
     * CSV on Hosts and Users with fifty thousand logins behind it and you
     * get twenty-five rows, in a file that looks exactly like a complete
     * one. FOG already knows this: the management export screen carries a
     * "CSV (All)" button for the same reason and says so in prose. Reports
     * had the explanation and not the button.
     *
     * POSTED, NOT NAVIGATED TO, which is the whole reason the export matches
     * what is on screen. Route::listem() reads its DataTables request --
     * search, sort, columns -- from php://input and nothing else; a GET
     * carries an empty body, so a navigated export would silently drop the
     * search box and hand back the unfiltered table. Posting the grid's own
     * dt.ajax.params() means listem is answering the identical question it
     * answers for the grid, with `length` set to -1. No second read path,
     * so no second thing to drift.
     *
     * `length = -1` is bounded, not unbounded: FOGManagerController::limit()
     * turns it into LIMIT 0, MAX_ROWS and flags `truncated`. A capped export
     * that looked complete would be the bug this method exists to fix, so
     * the cap is written into the FILE NAME -- the only channel a download
     * has once the browser has taken it.
     *
     * @return void
     */
    public function exportAll()
    {
        $payload = $this->reportRows();
        $rows = (array) ($payload['data'] ?? []);

        [$cols, $heads] = self::_exportColumns($rows);

        $filename = self::_exportFilename($payload, count($rows));

        // Drop the output-sanitising buffer so the CSV is written verbatim.
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private');
        $fh = fopen('php://output', 'w');
        // UTF-8 BOM so spreadsheet apps read accented characters correctly.
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $heads);
        foreach ($rows as $row) {
            $row = (array) $row;
            $line = [];
            foreach ($cols as $col) {
                $line[] = self::_exportCell($row[$col] ?? '');
            }
            fputcsv($fh, $line);
        }
        fclose($fh);
        exit;
    }

    /**
     * Which columns the file gets, and what they are called.
     *
     * The names arrive from the toolbar button, read off the live table, so
     * a column the user hid stays out of the file and the headings are the
     * ones they were reading.
     *
     * INTERSECTED WITH THE ROWS rather than trusted. A request can name a
     * column the row emitter deliberately did not send -- Route::listem()
     * strips a user's token and password and a host's secrets before the
     * data ever reaches here -- and answering one of those with a column of
     * blanks reads as a redaction rather than as an absence. Dropping it
     * says the truth: that column is not in this report.
     *
     * @param array $rows the rows about to be written
     *
     * @return array [$cols, $heads]
     */
    private static function _exportColumns(array $rows)
    {
        $present = count($rows) > 0 ? array_keys((array) reset($rows)) : [];
        $wanted = (array) filter_input(
            INPUT_POST,
            'cols',
            FILTER_DEFAULT,
            FILTER_REQUIRE_ARRAY
        );
        $labels = (array) filter_input(
            INPUT_POST,
            'heads',
            FILTER_DEFAULT,
            FILTER_REQUIRE_ARRAY
        );

        return self::pickExportColumns($present, $wanted, $labels);
    }

    /**
     * The column choice itself, with the request already read.
     *
     * Split from _exportColumns() only so it can be driven: filter_input()
     * cannot be fed from a test, and a rule about what leaves the server is
     * not one to check by reading the source.
     *
     * @param array $present keys the rows actually carry
     * @param array $wanted  column names the client asked for
     * @param array $labels  headings the client asked for, by position
     *
     * @return array [$cols, $heads]
     */
    public static function pickExportColumns(
        array $present,
        array $wanted,
        array $labels
    ) {
        $cols = [];
        $heads = [];
        foreach ($wanted as $i => $col) {
            // With no rows there is nothing to intersect against, and
            // nothing to disclose either -- so the requested columns are
            // taken as given. Without this an empty result set produced a
            // file with no header row at all, which reads as a failed
            // download rather than as "nothing matched".
            if ([] !== $present && !in_array($col, $present, true)) {
                continue;
            }
            $cols[] = (string) $col;
            $heads[] = (string) ($labels[$i] ?? $col);
        }
        if ([] === $cols) {
            // No column list, or none of it survived: everything the rows
            // carry, which is still a complete answer rather than an empty
            // file.
            $cols = $present;
            $heads = $present;
        }

        return [$cols, $heads];
    }

    /**
     * One cell's text.
     *
     * A grid column can carry MARKUP -- the host name column is a link, the
     * MAC column carries a vendor icon -- because the browser renders it.
     * A spreadsheet does not, so the raw value would put `<a href=...>` in
     * the cell. Stripping the tags leaves the text the person was reading,
     * which is the thing they wanted in the file.
     *
     * @param mixed $value the row value
     *
     * @return string
     */
    private static function _exportCell($value)
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        $value = (string) $value;
        if (false === strpos($value, '<')) {
            return $value;
        }

        return trim(
            html_entity_decode(
                strip_tags($value),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            )
        );
    }

    /**
     * The download's name, saying so when the file is not the whole answer.
     *
     * A truncated CSV is indistinguishable from a complete one once it is
     * on disk, and there is no page left to warn on. The name is the only
     * thing that travels with it, so a capped export says how many rows it
     * holds and how many there were.
     *
     * @param array $payload the reportRows() envelope
     * @param int   $written how many rows are being written
     *
     * @return string
     */
    private static function _exportFilename(array $payload, $written)
    {
        // static::reportTitle(), not $this->title: file() never ran on an
        // export request, so the title property is still empty here.
        $slug = strtolower(
            preg_replace(
                '#[^a-z0-9]+#i',
                '-',
                (string) static::reportTitle()
            )
        );
        $slug = trim($slug, '-') ?: 'report';
        $name = $slug . '-' . date('Y-m-d');
        if (!empty($payload['truncated'])) {
            // Literally true either way -- the file does hold the first N --
            // so it is a warning where there is more behind the cap and not
            // a false claim where there happens to be exactly N.
            $name .= '-first-' . (int) $written;
            $total = (int) ($payload['recordsFiltered'] ?? 0);
            if ($total > $written) {
                // Route::listem() knows the true total; the ADR 0030
                // rollups only know that they hit their own cap.
                $name .= '-of-' . $total;
            }
        }

        return $name . '.csv';
    }

    /**
     * Initializes the report page.
     *
     * @param string $name The name if other than this.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        set_time_limit(0);
        $this->name = _('Report Management');
        parent::__construct($this->name);
    }
}
