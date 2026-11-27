<?php
/**
 * Lets the boot menu's filter_input() read of `username` see a scenario.
 *
 * IpxeBootMenu::falseTasking() reads the menu login with filter_input(),
 * which reads the SAPI's own request and has nothing to read under the CLI.
 * PHP resolves an unqualified function call inside a namespace to the
 * namespaced function first, so this one answers for FOG\Boot only. It is
 * narrow on purpose: any other field, and any other input type, goes to the
 * real filter_input(), so no other read in the menu changes. The scenario
 * sets the value through $_REQUEST (see bootmenu-render.php).
 */

namespace FOG\Boot;

/**
 * @param int   $type    INPUT_POST, INPUT_GET, ...
 * @param string $name   The field.
 * @param int   $filter  The filter.
 * @param mixed $options The options.
 *
 * @return mixed
 */
function filter_input($type, $name, $filter = FILTER_DEFAULT, $options = 0)
{
    if ('username' === $name && INPUT_POST === $type) {
        return $_REQUEST['username'] ?? null;
    }
    return \filter_input($type, $name, $filter, $options);
}
