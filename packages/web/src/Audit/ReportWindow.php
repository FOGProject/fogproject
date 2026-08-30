<?php
/**
 * The time range a report was asked for.
 *
 * PHP version 7.4+
 *
 * @category ReportWindow
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Audit;

use FOG\Base\FOGBase;

/**
 * The time range a report was asked for.
 *
 * ADR 0030 decision 1 puts the window in the URL, which means every report
 * reads the same two request parameters and has to make the same three
 * decisions about them. This is those decisions, in one place, so that a
 * second report cannot make them differently from the first.
 *
 * ON FOG'S CLOCK, NOT PHP'S, and that is the whole reason this is not three
 * lines of strtotime(). The columns being compared are stamped by
 * FOGController::save() through FOGBase::niceDate(), which uses the
 * configured FOG_TZ timezone -- so a bound built with PHP's default
 * timezone is silently offset by however far apart the two are. It does not
 * error: BETWEEN just matches a shifted window, so the report quietly
 * answers a question nobody asked. Caught in the lab against a server five
 * hours off PHP's timezone, where a task created seconds earlier did not
 * appear in a window ending "now".
 *
 * A MALFORMED BOUND IS DROPPED rather than passed on, for the same reason --
 * an unparseable date reaching BETWEEN matches nothing, which looks exactly
 * like "nothing happened". Dropping it falls back to the report's default,
 * so a fat-fingered URL shows the default range instead of an empty grid.
 *
 * REVERSED BOUNDS ARE SWAPPED, not rejected. Somebody who types the two
 * dates the other way round means the range between them.
 *
 * Objects out, not strings, because the two consumers want different
 * things: ActivityWindow takes 'Y-m-d H:i:s' and ImagingStats takes
 * DateTimeInterface. Formatting here would force one of them to parse it
 * back, which is the round trip the FOG-clock note above is about.
 *
 * @category ReportWindow
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ReportWindow extends FOGBase
{
    /**
     * The window a report gets when the request does not say.
     *
     * A default that returns rows matters more than it looks: a report that
     * opens empty reads as broken. Reports override it with whatever their
     * own first question is -- "what ran today" for an activity log, "the
     * last month" for a trend.
     *
     * @var string
     */
    const DEFAULT_WINDOW = '-24 hours';
    /**
     * The window the request asked for, clamped to something a query can use.
     *
     * @param string $default a strtotime-style relative expression applied to
     *                        `now` when the request names no start.
     *
     * @return array [start, end] as DateTimeInterface on FOG's clock.
     */
    public static function fromRequest($default = self::DEFAULT_WINDOW)
    {
        $given = [
            'start' => (string) filter_input(INPUT_GET, 'start'),
            'end' => (string) filter_input(INPUT_GET, 'end'),
        ];
        // Parseability is checked BEFORE handing the string to niceDate(),
        // which throws on a date it cannot read. A form field is a value
        // that may legitimately be malformed, so it is validated; this is
        // not a try/catch standing in for an API that might not be there.
        foreach ($given as $k => $v) {
            if ('' !== $v && false === strtotime($v)) {
                $given[$k] = '';
            }
        }
        // Both bounds came off a form, so they are read in the VIEWER's
        // zone. The defaults are not: 'now' and 'now minus a fortnight'
        // are the server's own clock and have no viewer in them.
        $end = '' === $given['end']
            ? self::niceDate()
            : self::viewerDate($given['end']);
        $start = '' === $given['start']
            ? self::niceDate()->modify((string)$default)
            : self::viewerDate($given['start']);
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }
    /**
     * The window as the two strings a BETWEEN wants.
     *
     * @param string $default forwarded to fromRequest().
     *
     * @return array [start, end], both 'Y-m-d H:i:s' in FOG's timezone.
     */
    public static function stringsFromRequest($default = self::DEFAULT_WINDOW)
    {
        $fmt = 'Y-m-d H:i:s';
        [$start, $end] = self::fromRequest($default);

        return [$start->format($fmt), $end->format($fmt)];
    }
}
