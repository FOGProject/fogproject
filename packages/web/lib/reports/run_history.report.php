<?php
/**
 * What ran, and when it started and finished.
 *
 * PHP version 7.4+
 *
 * @category Run_History
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

use FOG\Audit\ActivityWindow;
use FOG\Audit\ReportWindow;

/**
 * What ran, and when it started and finished.
 *
 * The consumer for ActivityWindow (ADR 0022 decision 4). That class is a
 * read-only projection over the five work-item tables -- tasks, snapin jobs,
 * snapin tasks, multicast sessions, the file delete queue -- and it shipped
 * without a caller, which is how a helper rots.
 *
 * WHY A REPORT AND NOT A PAGE. ADR 0023's activity viewer answers "what
 * HAPPENED" out of the event logs; this answers "what RAN", which is a
 * different question with a different shape. An event is a point in time and
 * a work item is a span, so they do not share a column set, and folding
 * spans into that viewer would break the one thing its design commits to --
 * one grid, one column set, a source filter that grows. A report is the
 * existing home for "pick a range, get rows": no new node, no new menu
 * plumbing, and the file name IS the menu entry.
 *
 * GATED ON `task`, NOT `report`. Reports share one permission node by
 * default, which is exactly the defect ADR 0023 opens with -- a helpdesk
 * grant for an imaging report also handed over a movement log for every
 * named employee. This is task activity, Task Management's own log pane is
 * gated on task.view, and Authorization::REPORT_NODES is the seam that
 * already exists for saying so. It narrows against the default; nothing
 * anyone holds today gets wider.
 *
 * NOT serverSide. Every other report here pages through Route::listem(),
 * which speaks the DataTables server-side protocol. This one does not have
 * one to speak: ActivityWindow returns a plain array, the bound is in its
 * query (MAX_ROWS), and the real filter is the date range rather than the
 * page. Sending one capped result and letting DataTables page it client
 * side is less code and the same number of queries.
 *
 * @category Run_History
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Run_History extends ReportManagement
{
    /**
     * How far back the report looks when nobody has said.
     *
     * A default that returns rows matters more here than it looks: a report
     * that opens empty reads as broken, and the first thing anyone does
     * with this one is ask what ran today.
     *
     * @var string
     */
    const DEFAULT_WINDOW = '-24 hours';
    /**
     * The window the request asked for, clamped to something a query can use.
     *
     * The parsing itself moved to ReportWindow when a second report needed
     * it (ADR 0030 decision 1 -- the window lives in the URL, so every
     * report reads the same two parameters). What stays here is only this
     * report's default range.
     *
     * @return array [start, end], both 'Y-m-d H:i:s' in FOG's timezone.
     */
    private static function _window()
    {
        return ReportWindow::stringsFromRequest(self::DEFAULT_WINDOW);
    }
    /**
     * The sources the request asked for, validated against the class.
     *
     * ActivityWindow whitelists these itself -- a source name becomes a
     * table name in there -- so this is not the security boundary. It is
     * here so the form and the query agree about what an empty selection
     * means, which is "all of them" rather than "none".
     *
     * @return array
     */
    private static function _sources()
    {
        $want = filter_input(
            INPUT_GET,
            'sources',
            FILTER_DEFAULT,
            FILTER_REQUIRE_ARRAY
        );

        return array_values(
            array_intersect((array)$want, ActivityWindow::sources())
        );
    }
    /**
     * Human labels for the source codes.
     *
     * A method rather than a constant so the labels can be wrapped in _().
     * xgettext extracts from the literal call site only.
     *
     * @return array
     */
    private static function _sourceLabels()
    {
        return [
            'task' => _('Tasks'),
            'snapinjob' => _('Snapin jobs'),
            'snapintask' => _('Snapin tasks'),
            'multicastsession' => _('Multicast sessions'),
            'filedeletequeue' => _('File deletions'),
        ];
    }
    /**
     * Display page.
     *
     * @return void
     */
    public function file()
    {
        $this->title = self::reportTitle();

        $this->headerData = [
            _('What'),
            _('Name'),
            _('Host'),
            _('Started'),
            _('Finished'),
            _('State')
        ];
        $this->attributes = [
            [],
            [],
            [],
            [],
            [],
            []
        ];

        [$start, $end] = self::_window();
        $chosen = self::_sources();
        $labels = self::_sourceLabels();

        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';

        // The sources picker is this report's own control; the From/To
        // pair and the submit button are the shared ones. Built first and
        // handed to the helper as its $extra so the column order is the
        // same here as on every other report.
        ob_start();
        echo '<div class="col-md-4">';
        echo self::makeLabel('col-form-label', 'run-history-sources', _('Include'));
        echo '<div id="run-history-sources">';
        foreach (ActivityWindow::sources() as $source) {
            printf(
                '<div class="form-check form-check-inline">'
                . '<input class="form-check-input" type="checkbox" '
                . 'name="sources[]" id="src-%1$s" value="%1$s"%2$s>'
                . '<label class="form-check-label" for="src-%1$s">%3$s</label>'
                . '</div>',
                \Initiator::e($source),
                // Nothing ticked means everything, so an untouched form
                // shows every source rather than an empty table.
                (count($chosen) < 1 || in_array($source, $chosen, true))
                    ? ' checked' : '',
                \Initiator::e($labels[$source] ?? $source)
            );
        }
        echo '</div>';
        echo '</div>';
        $sources = ob_get_clean();

        echo self::renderReportWindow('run-history', $start, $end, $sources);

        echo $this->render(12, 'runhistory-table');
        echo '</div>';
        echo '</div>';
    }
    /**
     * The rows this report serves.
     *
     * Split from the emit so the grid and the CSV export run the same
     * query -- see ReportManagement::exportAll().
     *
     * @return array
     */
    protected function reportRows()
    {
        [$start, $end] = self::_window();
        $rows = ActivityWindow::between($start, $end, self::_sources());

        // Ids become names here, and it is done in bounded batches rather
        // than per row. ActivityWindow returns ids because it is a
        // projection over five tables and has no business knowing how any
        // of them are displayed; resolving them one object per row is the
        // grid query-storm this codebase has already fixed twice.
        //
        // Two different techniques, because the two lookups have different
        // shapes. There are a handful of task states and they are
        // hook-overridable, so those go through the model, memoized on the
        // distinct id -- the same thing Route's statename formatter does.
        // Host ids are unbounded in a wide window, so those are ONE query
        // for the whole set rather than one model per host.
        $states = [];
        foreach ($rows as $row) {
            $id = (int)($row['state'] ?? 0);
            if ($id > 0 && !isset($states[$id])) {
                $states[$id] = (string)self::getClass('TaskState', $id)
                    ->get('name');
            }
        }
        $hostIDs = [];
        foreach ($rows as $row) {
            $id = (int)($row['subjectID'] ?? 0);
            if ($id > 0) {
                $hostIDs[$id] = $id;
            }
        }
        $hosts = [];
        if (count($hostIDs) > 0) {
            // Ids, cast to int on the way in, so the list is composed of
            // integers rather than of anything a caller supplied -- these
            // came out of the database, but the cast is what makes that
            // readable rather than something to go and check.
            $found = self::$DB->query(
                "SELECT `hostID`, `hostName` FROM `hosts` WHERE `hostID` IN ("
                . implode(',', array_map('intval', $hostIDs))
                . ")"
            )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
            foreach ((array)$found as $host) {
                $hosts[(int)$host['hostID']] = (string)$host['hostName'];
            }
        }

        $labels = self::_sourceLabels();
        $data = [];
        foreach ($rows as $row) {
            $source = (string)($row['source'] ?? '');
            $hostID = (int)($row['subjectID'] ?? 0);
            $data[] = [
                'source' => $labels[$source] ?? $source,
                'label' => (string)($row['label'] ?? ''),
                // A host that has since been deleted leaves the id with
                // nothing to resolve. Shown as the bare id rather than
                // blank: the row is still evidence that something ran, and
                // a blank cell reads as "no host" rather than "gone".
                'host' => $hostID < 1
                    ? ''
                    : ($hosts[$hostID] ?? '#' . $hostID),
                'startedAt' => (string)($row['startedAt'] ?? ''),
                // Two of the five tables record no finish time at all --
                // ADR 0022 decision 2 reports that rather than inventing
                // one -- so this column is legitimately empty for them.
                'endedAt' => (string)($row['endedAt'] ?? ''),
                'state' => $states[(int)($row['state'] ?? 0)] ?? '',
            ];
        }

        // A capped fetch is not a complete answer, and a CSV taken from
        // one looks exactly like a complete file once it is on disk. The
        // flag rides in the envelope so ReportManagement::exportAll() can
        // write the cap into the download's name. `>=` rather than `>`
        // because the rollup slices at MAX_ROWS and cannot see what it
        // dropped; the name it produces is true either way.
        return [
            'data' => $data,
            'truncated' => count($rows) >= ActivityWindow::MAX_ROWS
        ];
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\Run_History', 'Run_History');
