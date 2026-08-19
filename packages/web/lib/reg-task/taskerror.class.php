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
 * A report lands in three places, none of which is the task's state:
 *
 *   a `taskLog` row, typed 'error' or 'warning', with the text in it. This
 *     is the one that is correlated with the task -- it carries taskID and
 *     the state the task was in, and it survives long after a log rotates;
 *   FOG's own log file, /var/log/fog/fos/fosreports.log, which the Log
 *     Viewer lists like any other because 'fos' is in FOGLogPaths;
 *   HOST_IMAGE_FAIL, so the notification plugins fire -- errors only, and
 *     imaging tasks only.
 *
 * Deliberately narrow in the one way that matters: it does NOT change the
 * task's state. There is no Failed state in taskStates -- the five are Queued,
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
     * The report types a caller may send, mapped to the TaskLog type.
     *
     * A warning is a report that FOS carried on after, so it is worth a row
     * and a log line but is not a failure and must not fire HOST_IMAGE_FAIL.
     * Anything unrecognised is treated as an error: a report FOG cannot
     * classify is not a reason to throw it away.
     *
     * @var array
     */
    const TYPES = [
        'error' => TaskLog::TYPE_ERROR,
        'warning' => TaskLog::TYPE_WARNING
    ];
    /**
     * Where the reports are written, under FOG's log directory.
     *
     * Its own subdirectory, not the top level, because the writer here is
     * the web tier and the top level is root's -- the same split ADR 0010
     * made for the plugin runner, and for the same reason: rotation renames
     * and unlinks, so directory write on the shared log directory would let
     * this delete the daemons' logs.
     *
     * @var string
     */
    const LOG_SUBDIR = 'fos';
    /**
     * The file within that directory.
     *
     * @var string
     */
    const LOG_FILE = 'fosreports.log';
    /**
     * Reports the failure.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        try {
            $text = self::_reported('text');
            if ('' === $text) {
                throw new \Exception(_('No report text supplied'));
            }
            $type = self::_reportedType();
            $script = self::_reported('script');
            if ('' !== $script) {
                $text = sprintf('%s (%s)', $text, $script);
            }
            self::getHostItem(false);
            $Task = self::$Host->get('task');
            if (!$Task->isValid()) {
                throw new \Exception(_('No active task found for this host'));
            }
            // Recorded for EVERY task type, imaging or not. A wipe that dies
            // on a bad disk is exactly as worth having in the task's log as a
            // deploy that dies on a bad image, and this is the half of the
            // report that carries no notification consequences.
            self::_logRow($Task, $type, $text);
            self::_record(
                sprintf(
                    'FOG: %s reported by host %s (task %d): %s',
                    $type,
                    self::$Host->get('name'),
                    (int) $Task->get('id'),
                    $text
                )
            );
            if (TaskLog::TYPE_ERROR !== $type) {
                // A warning means FOS carried on. Nothing failed, so nothing
                // gets told that something did.
                return;
            }
            // Same rule as TaskQueue::_notifyImagingOutcome(): HOST_IMAGE_FAIL
            // is an imaging event, and this endpoint is reachable from a wipe
            // or an inventory task too. Firing it for one of those would be
            // the defect #1202 just removed, in the other direction. There is
            // no event for a non-imaging task failing; noted on #1206. The row
            // and the log line above are written either way, so the report is
            // not lost by falling through here.
            if (!$Task->isImagingTask()) {
                return;
            }
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
                    'Reason' => $text
                ]
            );
        } catch (\Exception $e) {
            // A report that cannot be matched to a task is still worth a line
            // in the log -- it is the only trace that a machine tried to say
            // something -- but it is not worth an answer a caller can probe
            // with. See the ack below.
            self::_record(
                sprintf(
                    'FOG: unusable report from FOS: %s',
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
     * Writes the report against the task.
     *
     * taskStateID is the state the task was in when the report arrived, not
     * a new state: nothing here changes the task. It is the useful half of
     * "when did this happen" -- a failure during In-Progress and a failure
     * while still Queued are different problems.
     *
     * @param Task   $Task the task being reported against
     * @param string $type the TaskLog type constant
     * @param string $text the report body
     *
     * @return void
     */
    private static function _logRow($Task, $type, $text)
    {
        self::getClass('TaskLog')
            ->set('taskID', $Task->get('id'))
            ->set('stateID', $Task->get('stateID'))
            ->set('createdBy', 'fos')
            ->set('type', $type)
            ->set('text', $text)
            ->save();
    }
    /**
     * Reads the report type, as a TaskLog type constant.
     *
     * Unrecognised input becomes an error rather than being rejected. The
     * caller is a machine that has already failed at something; the report
     * is worth more than the label on it, and a stricter reading would throw
     * away the report of a FOS newer than this server.
     *
     * @return string
     */
    private static function _reportedType()
    {
        $sent = strtolower(self::_reported('type'));

        return self::TYPES[$sent] ?? TaskLog::TYPE_ERROR;
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
     * here -- the caller is a machine in the middle of imaging.
     *
     * Its own file, so that "what have the machines been telling us" is one
     * `tail` rather than a grep through everything else the web tier logs,
     * and so the Log Viewer can offer it by name.
     *
     * error_log() is the fallback, not the destination. The directory is
     * created by the installer, so a server whose web tree has been updated
     * but which has not been re-installed has nowhere to write yet -- and a
     * report that reaches no log at all would be the exact failure this
     * whole path exists to end.
     *
     * @param string $line the line to write
     *
     * @return void
     */
    private static function _record($line)
    {
        $stamped = sprintf(
            '[%s] %s' . PHP_EOL,
            date('Y-m-d H:i:s'),
            $line
        );
        $file = self::_logPath();
        if ('' !== $file) {
            self::_rotate($file);
            if (false !== @file_put_contents($file, $stamped, FILE_APPEND)) {
                return;
            }
        }
        error_log($line);
    }
    /**
     * The report log's path, or '' if it cannot be written.
     *
     * The directory is never created here. It is the installer's, which
     * gives it to the web user with the right SELinux label (GH-964:
     * /opt/fog inherits usr_t and httpd_t may read it but not write it, so
     * an unlabelled mkdir would produce a directory that looks right and
     * silently swallows every write on an enforcing host).
     *
     * @return string
     */
    private static function _logPath()
    {
        $dir = FOG_LOG_DIR . DS . self::LOG_SUBDIR;
        if (!is_dir($dir) || !is_writable($dir)) {
            return '';
        }

        return $dir . DS . self::LOG_FILE;
    }
    /**
     * Keeps one old copy once the file passes SERVICE_LOG_SIZE.
     *
     * The same setting the daemons rotate on, so an admin who has already
     * decided how big a FOG log may get does not have to decide again. One
     * generation rather than the daemons' five: this file gains a line per
     * failed task, not a line per poll.
     *
     * @param string $file the log file
     *
     * @return void
     */
    private static function _rotate($file)
    {
        $max = (int) self::getSetting('SERVICE_LOG_SIZE');
        if ($max < 1) {
            return;
        }
        $size = @filesize($file);
        if (false === $size || $size < $max) {
            return;
        }
        @rename($file, $file . '.1');
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\TaskError', 'TaskError');
