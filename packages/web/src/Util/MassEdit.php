<?php
/**
 * Turns a mass-edit submission into explicit per-field instructions.
 *
 * PHP version 7.4+
 *
 * @category MassEdit
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Util;

use FOG\Base\FOGBase;

/**
 * Turns a mass-edit submission into explicit per-field instructions.
 *
 * A form that edits many hosts at once and posts every field is a form that
 * overwrites everything the admin did not touch. Every field therefore needs
 * three states -- LEAVE ALONE, SET TO THIS VALUE, CLEAR -- and ADR 0038
 * decision 11 calls this the single requirement most likely to be got wrong,
 * "because the wrong version looks identical until somebody's images are
 * gone". Nothing errors. The form submits, the page says it worked, and four
 * hundred hosts quietly hold a value nobody chose.
 *
 * So the resolution is one function, it is out of band, and it fails closed.
 *
 * OUT OF BAND, meaning the ACTION IS ITS OWN CONTROL rather than a magic
 * value inside the field. The codebase currently carries the in-band version
 * next door: the group page reads the literal string `NULL`, case-insensitively,
 * as "clear this" and a blank as "leave alone" (GroupManagement.php:713-720).
 * That is rejected here for three reasons, in increasing order of weight:
 *
 *   - it is undiscoverable; nothing in the form says the word NULL is magic;
 *   - it cannot express "clear" for a control with no text box, which is why
 *     image, building and printer level had to be special-cased;
 *   - it has no escape, so a value that is legitimately the string "NULL"
 *     cannot be set at all.
 *
 * FAILS CLOSED, meaning ANYTHING NOT RECOGNIZED IS "LEAVE ALONE". A missing
 * action, an empty one, a misspelled one, a key the caller never offered, an
 * action array that did not arrive as an array -- every one of them resolves
 * to LEAVE. There is no input to this function that writes to a column the
 * submission did not explicitly ask to change, which is the property the
 * tests exist to hold down, because it is the one whose absence is invisible.
 *
 * The one case worth stating rather than discovering: SET with an empty
 * value writes empty. That is not a malformed submission being tolerated --
 * the action control and the value control are separate, so choosing SET and
 * leaving the box blank is a person saying "make it blank". CLEAR exists for
 * the controls that have no box to blank.
 *
 * @category MassEdit
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MassEdit extends FOGBase
{
    /**
     * Leave every host's current value alone. The default, always.
     *
     * @var string
     */
    const LEAVE = 'leave';

    /**
     * Write the submitted value to every host.
     *
     * @var string
     */
    const SET = 'set';

    /**
     * Write the field's empty value to every host.
     *
     * @var string
     */
    const CLEAR = 'clear';

    /**
     * Reduces a submission to one explicit instruction per offered field.
     *
     * @param array $keys    the field keys the caller is willing to act on.
     *                       Anything outside this list is discarded, so a
     *                       submission cannot name a column the caller never
     *                       offered.
     * @param mixed $actions the posted action map, key => action. Not
     *                       required to be an array: filter_input_array()
     *                       hands back null when nothing matched, and null
     *                       has to mean "change nothing" rather than throw.
     * @param mixed $values  the posted value map, key => value. Same.
     *
     * @return array key => ['action' => one of the three, 'value' => string].
     *               EVERY key in $keys is present. A caller -- core or a
     *               plugin -- never has to decide what an absent key meant,
     *               which is the ambiguity this whole class exists to remove.
     */
    public static function resolve(array $keys, $actions, $values)
    {
        $actions = is_array($actions) ? $actions : [];
        $values = is_array($values) ? $values : [];
        $allowed = [self::LEAVE, self::SET, self::CLEAR];
        $resolved = [];
        foreach ($keys as $key) {
            $key = (string)$key;
            $action = $actions[$key] ?? self::LEAVE;
            // in_array with strict comparison: '0' or 0 arriving as an
            // action must not loosely match a string constant. Everything
            // unrecognized lands on LEAVE, which is the whole safety
            // property -- a typo in a hand-made request changes nothing
            // rather than clearing a column across the selection.
            if (!is_string($action)
                || !in_array($action, $allowed, true)
            ) {
                $action = self::LEAVE;
            }
            // SET without a value key is malformed rather than deliberate:
            // the form pairs the two controls, so an action arriving with no
            // box to read from is not a person choosing to blank a field. It
            // degrades to LEAVE. A value that IS present and empty is left
            // alone here and written as empty -- see the class docblock.
            if (self::SET === $action && !array_key_exists($key, $values)) {
                $action = self::LEAVE;
            }
            $value = '';
            if (self::SET === $action) {
                $raw = $values[$key];
                // Arrays and objects are not values. A field posting
                // key[]=a&key[]=b is either a bug or somebody probing, and
                // casting either to string is a PHP notice and a garbage
                // column value.
                $value = is_scalar($raw) || null === $raw
                    ? trim((string)$raw)
                    : '';
                if (!is_scalar($raw) && null !== $raw) {
                    $action = self::LEAVE;
                }
            }
            $resolved[$key] = ['action' => $action, 'value' => $value];
        }

        return $resolved;
    }

    /**
     * Turns resolved instructions into the column map an update takes.
     *
     * @param array $resolved output of resolve()
     * @param array $spec     key => ['field' => <FOGController field name>,
     *                        'empty' => <what CLEAR writes, default ''>].
     *                        `empty` may be null, and null is written as
     *                        NULL rather than treated as absent -- that is
     *                        what a column with a foreign key needs. A key
     *                        absent from the spec is skipped: the spec is
     *                        what says a key may touch a column at all.
     *
     * @return array field name => value, ready for a manager update(). EMPTY
     *               when nothing was set or cleared -- and an empty map must
     *               NOT be handed to an update, because an UPDATE with no
     *               assignments is either a syntax error or, worse, a
     *               statement whose WHERE is the only thing left.
     */
    public static function columnUpdates(array $resolved, array $spec)
    {
        $updates = [];
        foreach ($resolved as $key => $instruction) {
            if (!isset($spec[$key]['field'])) {
                continue;
            }
            $action = $instruction['action'] ?? self::LEAVE;
            if (self::SET === $action) {
                // An array value can never be a column update: a column
                // takes one value. Guarded here rather than left to the
                // spec, because the spec is partly written by plugins and a
                // plugin naming `field` on a key whose posted value is an
                // array would otherwise write the string "Array" into it.
                if (is_array($instruction['value'])) {
                    continue;
                }
                $updates[$spec[$key]['field']] = $instruction['value'];
            } elseif (self::CLEAR === $action) {
                // Per field, because "empty" is not one value. A varchar
                // column clears to '', a plain int to 0, and a column
                // carrying a FOREIGN KEY to null -- 0 is not exempt from a
                // constraint, so clearing `hosts`.`hostImage` to 0 is
                // rejected outright unless an image with id 0 exists, which
                // none does. The answer belongs in the spec rather than in a
                // cast here.
                //
                // array_key_exists, NOT `?? ''`. Null is the whole point for
                // the foreign-key case and `??` treats it as absent, so the
                // coalesce silently turned every intended NULL back into ''
                // -- which an int column then stores as 0, i.e. exactly the
                // value that fails. A spec that genuinely omits `empty`
                // still gets ''.
                $updates[$spec[$key]['field']] =
                    array_key_exists('empty', $spec[$key])
                    ? $spec[$key]['empty']
                    : '';
            }
        }

        return $updates;
    }

    /**
     * The keys a submission actually asked to change.
     *
     * @param array $resolved output of resolve()
     *
     * @return array the keys whose action is not LEAVE
     */
    public static function touched(array $resolved)
    {
        $touched = [];
        foreach ($resolved as $key => $instruction) {
            if (self::LEAVE !== ($instruction['action'] ?? self::LEAVE)) {
                $touched[] = $key;
            }
        }

        return $touched;
    }
}
