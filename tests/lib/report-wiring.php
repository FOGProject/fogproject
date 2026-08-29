<?php
/**
 * The wiring every ADR 0030 report has to get right, checked once.
 *
 * A report is discovered by FILENAME. lib/reports/*.report.php becomes a
 * menu entry with underscores turned into spaces, so the file name, the
 * class name, the `f` parameter the JS switches on, the REPORT_NODES key
 * and the xgettext registration all have to agree -- and any one of them
 * out of step gives a menu entry that opens an empty page, with no error
 * anywhere. The column keys getList() emits have to match the ones the
 * DataTables definition asks for, or cells render blank rather than
 * erroring. And the permission fails DANGEROUSLY rather than visibly:
 * reports inherit the `report` node, which is the defect ADR 0023 opens
 * with.
 *
 * All of that is identical for every report, so it is written once here.
 * The per-report file keeps only what is actually that report's -- its
 * counting rules, its default window, its own traps.
 *
 * PHP version 7.4+
 */

/**
 * The checks shared by every report built on ADR 0030's panel helpers.
 */
class FogReportWiring
{
    /**
     * Run the shared wiring checks for one report.
     *
     * @param FogChecks $t       the check collector
     * @param string    $web     path to packages/web
     * @param string    $slug    file base name, e.g. 'snapin_report'
     * @param string    $class   unqualified class name, e.g. 'Snapin_Report'
     * @param string    $node    the permission node it must resolve to
     * @param string    $tableId the DataTables element id, without the '#'
     * @param array     $opts    'listem' => the router class a server-side
     *                           grid delegates to, and 'raw' => the columns
     *                           deliberately not escaped in the browser.
     *                           Both have to be DECLARED: a report that
     *                           silently stopped escaping a column, or
     *                           quietly started serving its own rows, is
     *                           exactly what this file exists to catch.
     *
     * @return string the report's source with comments stripped, for the
     *                caller's own report-specific assertions
     */
    public static function check(
        $t,
        $web,
        $slug,
        $class,
        $node,
        $tableId,
        array $opts = []
    ) {
        $report = $web . '/lib/reports/' . $slug . '.report.php';
        $t->check("$slug: the report file exists", is_readable($report));
        $src = is_readable($report) ? file_get_contents($report) : '';

        // Comments stripped before anything is searched for. The prose above
        // a method names every symbol below it, so a search over the raw
        // file is satisfied by the documentation of a rule rather than by
        // the rule -- including the table names a report must NOT query.
        $code = '';
        foreach (token_get_all($src) as $token) {
            if (is_array($token)) {
                if (T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0]) {
                    continue;
                }
                $code .= $token[1];
                continue;
            }
            $code .= $token;
        }

        $label = str_replace('_', ' ', $slug);
        $fq = 'FOG\\' . $class;
        $t->check(
            "$slug: the class name matches the file name, so the autoloader finds it",
            class_exists($fq)
        );
        $t->check(
            "$slug: it extends ReportManagement, so it appears in the menu at all",
            class_exists($fq) && is_subclass_of($fq, 'FOG\ReportManagement')
        );

        $js = (string)@file_get_contents(
            $web . '/management/js/fog/report/fog.report.file.js'
        );
        $t->check(
            "$slug: the JS switches on '$label', what the filename decodes to",
            false !== strpos($js, "case '" . $label . "':")
        );

        $page = (string)@file_get_contents(
            $web . '/lib/pages/reportmanagement.page.php'
        );
        $t->check(
            "$slug: the menu label is registered for xgettext",
            false !== strpos($page, "_('" . ucwords($label) . "');")
        );

        // The columns, read out of both sides rather than assumed, so
        // adding one to either and not the other fails here.
        $wanted = [];
        $block = '';
        $at = strpos($js, "case '" . $label . "':");
        if (false !== $at) {
            $block = substr($js, $at, strpos($js, 'break;', $at) - $at);
            // Digits and underscores included. A column named `other1`
            // or `primary_user` did not match [a-zA-Z]+, so the scan
            // silently returned FEWER columns than the grid has -- and
            // every count downstream of it (headers, escaping) then agreed
            // with the wrong number. A gate that under-counts passes.
            if (preg_match_all("/\{data: '([a-zA-Z0-9_]+)'/", $block, $m)) {
                $wanted = $m[1];
            }
        }
        $t->check("$slug: the JS names its columns", count($wanted) > 0);

        // A grid served by the router pages in SQL, so its keys come from
        // the model's column map rather than from getList(). The delegation
        // is asserted instead -- a report that quietly went back to serving
        // its own rows would page the whole table into the browser.
        $listem = (string)($opts['listem'] ?? '');
        if ('' !== $listem) {
            $t->check(
                "$slug: the grid is served by Route::listem('$listem')",
                false !== strpos($code, "Route::listem('" . $listem . "')")
            );
            $t->check(
                "$slug: and it does not also hand-build the rows",
                false === strpos($code, "'data' => \$data")
            );
        } else {
            foreach ($wanted as $col) {
                $t->check(
                    "$slug: getList() emits the '$col' key the grid asks for",
                    false !== strpos($code, "'" . $col . "' =>")
                );
            }
        }
        $t->check(
            "$slug: the table id in the JS is the one the page renders",
            false !== strpos($js, '#' . $tableId)
            && false !== strpos($code, "'" . $tableId . "'")
        );
        // The whole array, not a fixed window of it. A 33-column grid's
        // header does not fit in 600 characters, and a check that silently
        // read half the array would have counted half the headers and
        // failed for a reason that is not the one it names.
        $hdrAt = strpos($code, '$this->headerData');
        $hdrEnd = false === $hdrAt ? false : strpos($code, '];', $hdrAt);
        $header = (false === $hdrAt || false === $hdrEnd)
            ? ''
            : substr($code, $hdrAt, $hdrEnd - $hdrAt);
        $t->check(
            "$slug: the header row has one cell per column",
            count($wanted) === substr_count($header, '_(')
        );

        // Columns a report deliberately does not escape in the browser --
        // a server-built anchor would render as literal markup under
        // render.text(). They have to be named by the caller AND escaped on
        // the server, which is checked rather than taken on trust.
        $raw = (array)($opts['raw'] ?? []);
        $t->check(
            "$slug: every column escapes except the ones declared raw",
            count($wanted) - count($raw) === substr_count(
                $block,
                '$.fn.dataTable.render.text()'
            )
        );
        if ([] !== $raw && '' !== $listem) {
            $router = (string)@file_get_contents(
                $web . '/src/Router/Route.php'
            );
            $at = strpos($router, "case '" . $listem . "':");
            $span = false === $at
                ? ''
                : substr($router, $at, 2000);
            foreach ($raw as $col) {
                $t->check(
                    "$slug: the raw '$col' column is escaped server-side "
                    . 'instead',
                    false !== strpos($span, "'dt' => '" . $col . "'")
                    && false !== strpos($span, 'Initiator::e(')
                );
            }
        }

        // The gate.
        $nodes = (array)constant('FOG\Auth\Authorization::REPORT_NODES');
        $t->check(
            "$slug: listed in REPORT_NODES rather than inheriting `report`",
            array_key_exists($slug, $nodes)
        );
        $t->check(
            "$slug: and it resolves to `$node`",
            $node === ($nodes[$slug] ?? null)
        );

        // The window, which is shared and must not be re-implemented.
        $t->check(
            "$slug: the window is read through the shared parser",
            false !== strpos($code, 'ReportWindow::fromRequest(')
            && false === strpos($code, 'strtotime($v)')
        );

        self::checkWindowControl($t, $web);

        return $code;
    }

    /**
     * The shared window control actually works.
     *
     * RENDERED, NOT GREPPED. This is a control that failed by doing
     * NOTHING -- no request, no error, no console message -- and the way it
     * failed cannot be seen in the source at all: a `datetime-local` input
     * defaults to step=60, so a value carrying a non-zero seconds component
     * fails HTML5 constraint validation, an invalid form fires no submit
     * event, and the button is dead. The default window ends at "now", so
     * the seconds were non-zero on 59 page loads out of 60.
     *
     * So the assertion is the INVARIANT, not the attribute: a value that
     * carries seconds requires step="1" beside it. Truncating the value to
     * the minute instead would also satisfy it, which is the point -- both
     * fixes are correct and the gate accepts either.
     *
     * The other two are what a GET submit needs to land back on the same
     * report: it REPLACES the query string rather than merging into it, so
     * `sub` and `f` have to be in the form, and `disableFormDefaults()`
     * preventDefaults every form on the page, so something has to navigate.
     *
     * @param FogChecks $t   the check collector
     * @param string    $web path to packages/web
     *
     * @return void
     */
    public static function checkWindowControl($t, $web)
    {
        $html = \FOG\Base\FOGPage::renderReportWindow(
            'gate',
            '2026-01-01 09:00:41',
            '2026-01-02 09:00:41'
        );

        preg_match_all(
            '/<input type="datetime-local"[^>]*>/',
            $html,
            $inputs
        );
        $t->check(
            'the window control renders both bounds',
            2 === count($inputs[0])
        );
        foreach ($inputs[0] as $input) {
            $hasSeconds = 1 === preg_match(
                '/value="[^"]*T\d\d:\d\d:\d\d"/',
                $input
            );
            $t->check(
                'a bound carrying seconds declares step="1", or it is '
                . 'invalid and the form will not submit',
                !$hasSeconds
                || false !== strpos($input, 'step="1"')
            );
        }

        foreach (['node', 'sub', 'f'] as $name) {
            $t->check(
                "the window form carries `$name`, so a submit lands back "
                . 'on the same report',
                1 === preg_match(
                    '/<input type="hidden" name="' . $name . '"/',
                    $html
                )
            );
        }

        $t->check(
            'the form is marked for the JS that has to navigate for it',
            false !== strpos($html, 'data-report-window')
        );

        $js = (string)@file_get_contents(
            $web . '/management/js/fog/report/fog.report.panels.js'
        );
        $t->check(
            'and that JS binds a submit handler which navigates',
            1 === preg_match(
                '/form\[data-report-window\]/',
                $js
            )
            && 1 === preg_match(
                '/on\(\s*\x27submit[^\x27]*\x27/',
                $js
            )
            && false !== strpos($js, 'window.location.assign')
        );
        $t->check(
            'the navigation is wired before the Chart.js bail-out, so a '
            . 'report with no chart still has a working window',
            strpos($js, 'form[data-report-window]')
            < strpos($js, "typeof Chart === 'undefined'")
        );
    }

    /**
     * Every SQL a rollup builds is safe to bind.
     *
     * A NAMED PLACEHOLDER CANNOT BE REPEATED IN ONE STATEMENT under native
     * prepares, which is what FOG uses, and the failure is silent from end
     * to end: PDODB logs it and returns false, WindowedStats::readWindow()
     * casts that to an empty array, and the zero filling every rollup does
     * turns an empty result into a full, plausible, entirely wrong answer.
     * FleetStats::_ageBucketSql() shipped exactly that -- five zero buckets
     * beside a tile saying 86 hosts -- and nothing anywhere said so.
     *
     * So it is checked here rather than per report, over every static
     * builder whose name ends in Sql. A new rollup is covered the moment it
     * follows the naming convention; it needs no new assertion.
     *
     * @param FogChecks $t     the check collector
     * @param string    $class fully qualified rollup class name
     *
     * @return void
     */
    public static function checkSql($t, $class)
    {
        $short = substr((string)strrchr($class, '\\'), 1);
        $methods = (new \ReflectionClass($class))->getMethods();

        $seen = 0;
        foreach ($methods as $m) {
            if (!$m->isStatic() || 'Sql' !== substr($m->getName(), -3)) {
                continue;
            }
            $seen++;

            // A builder parameterised by column name (InventoryStats has
            // one) still has to be checked, so its arguments are filled
            // with a harmless string -- which changes no placeholder. A
            // parameter typed to anything but a string would make that
            // wrong, so it fails the check below rather than being skipped
            // quietly: a builder nobody can call is a builder nobody is
            // checking.
            $args = [];
            $callable = true;
            foreach ($m->getParameters() as $param) {
                if ($param->isOptional()) {
                    break;
                }
                $type = $param->getType();
                if (null !== $type && 'string' !== (string)$type) {
                    $callable = false;
                    break;
                }
                $args[] = 'x';
            }
            $t->check(
                "$short::{$m->getName()}() can be exercised by the gate",
                $callable
            );
            if (!$callable) {
                continue;
            }

            $sql = (string)FogTestHarness::callStatic(
                $class,
                $m->getName(),
                $args
            );
            preg_match_all('/:[a-z_]+/i', $sql, $hits);
            $repeated = array_keys(
                array_filter(
                    array_count_values($hits[0]),
                    function ($n) {
                        return $n > 1;
                    }
                )
            );
            $t->check(
                "$short::{$m->getName()}() binds each placeholder once"
                . ([] === $repeated ? '' : ' (repeated: '
                    . implode(', ', $repeated) . ')'),
                [] === $repeated
            );
        }

        $t->check(
            "$short: the placeholder gate found builders to check",
            $seen > 0
        );
    }
}
