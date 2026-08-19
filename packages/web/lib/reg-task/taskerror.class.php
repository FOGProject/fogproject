<?php
/**
 * Records that FOS could not finish a task, and tells anyone listening.
 *
 * PHP version 7.4+
 *
 * @category TaskError
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * Records that FOS could not finish a task, and tells anyone listening.
 *
 * Until #1206 FOS reported nothing at all when imaging failed. handleError()
 * printed a banner to the console and exited, so a bad image, a mount failure
 * or a partition error was invisible to the server: HOST_IMAGE_FAIL could not
 * fire for the failure people actually mean by "the deploy failed", and the
 * only failure FOG ever heard about was a storage node problem, through
 * Blame, which re-queues rather than fails.
 *
 * Deliberately narrow. It notifies and it logs; it does NOT change the task's
 * state. There is no Failed state in taskStates -- the five are Queued,
 * Checked In, In-Progress, Complete, Cancelled -- and the two ways of not
 * adding one are both wrong on their own terms: reusing Cancelled loses the
 * difference between "an admin stopped this" and "this broke", and adding a
 * sixth means every place that enumerates states has to learn about it or a
 * failed task becomes invisible to it. That is a decision with UI and API
 * consequences and it is tracked separately on #1206; making the event fire
 * does not depend on taking it.
 *
 * @category TaskError
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class TaskError extends FOGBase
{
    /**
     * How much of the reported text is kept.
     *
     * The text is written by whatever called this endpoint, and it ends up in
     * a Slack/ntfy/pushbullet message. Bounded so a caller cannot use an
     * admin's notification channel as a paste bin.
     *
     * @var int
     */
    const MAX_REASON = 500;
    /**
     * Reports the failure.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        try {
            $reason = self::_reported('error');
            if ('' === $reason) {
                throw new \Exception(_('No error text supplied'));
            }
            self::getHostItem(false);
            $Task = self::$Host->get('task');
            if (!$Task->isValid()) {
                throw new \Exception(_('No active task found for this host'));
            }
            // Same rule as TaskQueue::_notifyImagingOutcome(): HOST_IMAGE_FAIL
            // is an imaging event, and this endpoint is reachable from a wipe
            // or an inventory task too. Firing it for one of those would be
            // the defect #1202 just removed, in the other direction. There is
            // no event for a non-imaging task failing; noted on #1206.
            if (!$Task->isImagingTask()) {
                throw new \Exception(_('Task is not an imaging task'));
            }
            $script = self::_reported('script');
            $Image = $Task->getImage();
            self::$EventManager->notify(
                'HOST_IMAGE_FAIL',
                [
                    'HostName' => self::$Host->get('name'),
                    'Host' => self::$Host,
                    'Task' => $Task,
                    'Image' => $Image,
                    'ImageName' => (
                        $Image->isValid() ?
                        $Image->get('name') :
                        ''
                    ),
                    'TaskType' => $Task->getTaskTypeText(),
                    'Reason' => (
                        '' === $script ?
                        $reason :
                        sprintf('%s (%s)', $reason, $script)
                    )
                ]
            );
            self::_record(
                sprintf(
                    'FOG: imaging failed on host %s (task %d): %s',
                    self::$Host->get('name'),
                    (int) $Task->get('id'),
                    $reason
                )
            );
        } catch (\Exception $e) {
            // A report that cannot be matched to a task is still worth a line
            // in the log -- it is the only trace that a machine tried to say
            // something -- but it is not worth an answer a caller can probe
            // with. See the ack below.
            self::_record(
                sprintf(
                    'FOG: unusable imaging failure report: %s',
                    $e->getMessage()
                )
            );
        }
        /*
         * Always the same ack, always 200, whatever happened.
         *
         * FOS calls this on its way out of handleError() and cannot act on
         * the answer -- it is about to print a banner and exit either way --
         * so there is nothing to tell it. Answering identically also means
         * the endpoint cannot be used to ask whether a given MAC has an
         * active imaging task, which a distinguishing response would allow
         * anyone who can reach the web tier to do.
         */
        echo '##';
        exit;
    }
    /**
     * Reads one bounded, single-line field from the request.
     *
     * Control characters are removed rather than escaped: this text is not
     * going into HTML, it is going into a chat message and a log line, and in
     * both of those an embedded newline lets a caller forge what looks like a
     * second message.
     *
     * @param string $field the field name
     *
     * @return string
     */
    private static function _reported($field)
    {
        $raw = filter_input(INPUT_POST, $field);
        if (null === $raw || false === $raw) {
            $raw = filter_input(INPUT_GET, $field);
        }
        return self::_sanitize((string) ($raw ?? ''));
    }
    /**
     * Makes one caller-supplied string safe to put in a message.
     *
     * Split from _reported() so it can be tested: filter_input(INPUT_POST) has
     * nothing to read under the CLI SAPI, and this is the half with the rules
     * in it.
     *
     * @param string $raw the string as it arrived
     *
     * @return string
     */
    private static function _sanitize($raw)
    {
        // \p{C} is every Unicode control and format character, which covers
        // CR, LF, NUL and the terminal escapes a console-facing error string
        // can easily contain.
        $clean = preg_replace('#\p{C}+#u', ' ', $raw);
        if (null === $clean) {
            // Invalid UTF-8 makes preg_replace return null rather than throw,
            // so fall back to the byte-wise class. Never let a malformed
            // string become an empty one silently -- the text is the whole
            // point of the report.
            $clean = preg_replace('#[[:cntrl:]]+#', ' ', $raw);
        }
        $clean = trim((string) $clean);
        if ('' === $clean) {
            return '';
        }
        // mb_substr on invalid UTF-8 can return '', which would throw the
        // report away; strlen-bound the bytes in that case instead.
        $cut = mb_substr($clean, 0, self::MAX_REASON);
        if ('' === $cut) {
            $cut = substr($clean, 0, self::MAX_REASON);
        }
        return $cut;
    }
    /**
     * Writes one line where a server operator will find it.
     *
     * Not FOGBase::log(): that writes a history row, and logHistory() returns
     * without doing anything unless a user is signed in. Nobody is signed in
     * here -- the caller is a machine in the middle of imaging -- so the only
     * durable place is the PHP error log.
     *
     * @param string $line the line to write
     *
     * @return void
     */
    private static function _record($line)
    {
        error_log($line);
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\TaskError', 'TaskError');
