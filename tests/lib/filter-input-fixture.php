<?php
/**
 * Gives the real FOGBase::rawRequestParam() a request to read under the CLI.
 *
 * filter_input() reads the SAPI's own request and returns null under the CLI.
 * PHP resolves an unqualified call inside a namespace to the namespaced
 * function first, so this one answers for FOG\Base only. A test sets
 * $GLOBALS['filter_input_fixture'][INPUT_POST|INPUT_GET][name] to a string,
 * or to false to stand for an array value (what the real function returns
 * for one); a missing entry is null, as for an absent parameter.
 */

namespace FOG\Base;

/**
 * @param int    $type    INPUT_POST or INPUT_GET.
 * @param string $name    The field.
 * @param int    $filter  Ignored.
 * @param mixed  $options Ignored.
 *
 * @return string|false|null
 */
function filter_input($type, $name, $filter = FILTER_DEFAULT, $options = 0)
{
    return $GLOBALS['filter_input_fixture'][$type][$name] ?? null;
}
