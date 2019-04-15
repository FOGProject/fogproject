<?php
/**
 * FOGSubMenu.
 *
 * PHP version 5
 *
 * This file enables side menus and notes.
 *
 * @category FOGSubMenu
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * FOGSubMenu.
 *
 * This file enables the Side menus and notes.
 * To add:
 *
 * How-to: addItems
 *
 * Add "Main Menu" items for node:
 * self::$FOGSubMenu->addItems(
 *     'node',
 *     array('Title' => 'link')
 * );
 *
 * Add "Node Menu" items for node:
 * Local Node node and id url vars are set.
 * self::$FOGSubMenu->addItems(
 *     'node',
 *     array('Title' => 'link'),
 *     'nodeid',
 *     'Node Menu'
 * );
 *
 * Add "Node Menu" items for node:
 * Node and ID url vars are set, custom external link.
 * self::$FOGSubMenu->addItems(
 *     'node',
 *     array('Title' => 'http://www.example.com'),
 *     'nodeid',
 *     'Node Menu'
 * );
 *
 * Add "Node Menu" items for node:
 * Node and ID set, custom node link, nodeid appended.
 * self::$FOGSubMenu->addItems(
 *     'node',
 *     array('Title' => '?node=blah'),
 *     'nodeid',
 *     'Node Menu'
 * );
 *
 * Add "Node Menu" items for node:
 * Node ID set, custom internal link, nodeid is appended.
 * self::$FOGSubMenu->addItems(
 *     'node',
 *     array('Title' => '/blah/index.php'),
 *     'nodeid',
 *     'Node Menu'
 * );
 *
 *
 * How-to: addNotes
 *
 * Add static note:
 * self::$FOGSubMenu->addNotes(
 *     'node',
 *     array('Title' => 'Information'),
 *     'id variable'
 * );
 *
 * Add note with callback:
 * self::$FOGSubMenu->addNotes(
 *     'node',
 *     function() {
 *         return array('banana' => 'chicken');
 *     },
 *     'id variable'
 * );
 *
 * @category FOGSubMenu
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class FOGSubMenu extends FOGBase
{
    /**
     * Stores the title.
     *
     * @var string
     */
    private static $_title;
    /**
     * Default sub items.
     *
     * @var array
     */
    public $defaultSubs = array(
        'host' => 'edit',
        'group' => 'edit',
        'user' => 'edit',
    );
    /**
     * Stores items.
     *
     * @var array
     */
    public $items = array();
    /**
     * Stores main itesm.
     *
     * @var array
     */
    public $mainitems = array();
    /**
     * Add items into the side menu stuff.
     *
     * @param string $node            node to work on
     * @param array  $items           items to add
     * @param string $ifVariable      tester variable
     * @param string $ifVariableTitle tester variable title setter
     * @param string $class           class to set with item.
     *
     * @throws exception
     * @return void
     */
    public function addMainItems(
        $node,
        $items,
        $ifVariable = '',
        $ifVariableTitle = '',
        $class = ''
    ) {
        if (!is_string($node)) {
            throw new Exception(
                _('Node must be a string')
            );
        }
        if (!is_array($items)) {
            throw new Exception(
                _('Items must be an array')
            );
        }
        if (!$ifVariable) {
            $variableSetter = self::$foglang['MainMenu'];
        } else {
            $variableSetter = $ifVariableTitle;
        }
        if (isset($_POST[$ifVariable])
            || isset($_GET[$ifVariable])
        ) {
            global $$ifVariable;
            foreach ((array) $items as $title => $link) {
                global $$ifVariable;
                if (!$this->isExternalLink($link)) {
                    $link = sprintf(
                        '%s&%s=%s',
                        $link,
                        $ifVariable,
                        $$ifVariable
                    );
                }
                unset($link, $title);
            }
        }
        if (is_array($this->mainitems[$node][$variableSetter])) {
            $this->mainitems[$node][$variableSetter] = self::fastmerge(
                $this->mainitems[$node][$variableSetter],
                $items
            );
        } else {
            $this->mainitems[$node][$variableSetter] = $items;
        }
        if (isset($class)) {
            $this->mainitems[$node][$variableSetter]['class'] = $class;
        }
        return $this->mainitems;
    }
    /**
     * Add items into the side menu stuff.
     *
     * @param string $node            node to work on
     * @param array  $items           items to add
     * @param string $ifVariable      tester variable
     * @param string $ifVariableTitle tester variable title setter
     * @param string $class           class to set with item.
     *
     * @throws exception
     * @return void
     */
    public function addItems(
        $node,
        $items,
        $ifVariable = '',
        $ifVariableTitle = '',
        $class = ''
    ) {
        if (!is_string($node)) {
            throw new Exception(
                _('Node must be a string')
            );
        }
        if (!is_array($items)) {
            throw new Exception(
                _('Items must be an array')
            );
        }
        if (!$ifVariable) {
            $variableSetter = self::$foglang['MainMenu'];
        } else {
            $variableSetter = $ifVariableTitle;
        }
        if (isset($_POST[$ifVariable])
            || isset($_GET[$ifVariable])
        ) {
            global $$ifVariable;
            foreach ((array) $items as $title => $link) {
                global $$ifVariable;
                if (!$this->isExternalLink($link)) {
                    $link = sprintf(
                        '%s&%s=%s',
                        $link,
                        $ifVariable,
                        $$ifVariable
                    );
                }
                unset($link, $title);
            }
        }
        if (is_array($this->items[$node][$variableSetter])) {
            $this->items[$node][$variableSetter] = self::fastmerge(
                $this->items[$node][$variableSetter],
                $items
            );
        } else {
            $this->items[$node][$variableSetter] = $items;
        }
        if (isset($class)) {
            $this->items[$node][$variableSetter]['class'] = $class;
        }
    }
    /**
     * Add nodes to the sub menu.
     *
     * @param string         $node       The node to work for
     * @param callable|array $data       The data can be a callback or array
     * @param string         $ifVariable The variable to test
     *
     * @throws Exception
     * @return void
     */
    public function addNotes(
        $node,
        $data,
        $ifVariable = ''
    ) {
        if (!is_string($node)) {
            throw new Exception(
                _('Node must be a string')
            );
        }
        if (!is_callable($data) && !is_array($data)) {
            throw new Exception(
                _('Data must be an array or a callable item.')
            );
        }
        if (is_callable($data)) {
            $data = $data();
        }
        if (is_array($data)) {
            ob_start();
            foreach ((array) $data as $info => &$title) {
                echo '<li>';
                echo '<b>';
                echo $this->fixTitle($title);
                echo '</b>';
                echo '<p>';
                echo $info;
                echo '</p>';
                echo '</li>';
                echo '<li class="divider"></li>';
                unset($info, $title);
            }
        }
        $this->notes[$node][] = ob_get_clean();
    }
    /**
     * Gets the data as setup.
     *
     * @param string $node The node to get menu for
     *
     * @throws Exception
     *
     * @return string
     */
    public function get($node)
    {
        ob_start();
        if (count((array)$this->notes[$node]) < 1
            && count((array)$this->items[$node]) < 1
        ) {
            return;
        }
        echo '<ul class="nav nav-tabs">';
        if ($this->notes[$node]) {
            echo '<li class="dropdown">';
            echo '<a href="#" class="dropdown-toggle" data-toggle="dropdown">';
            echo _('Info');
            echo '<b class="caret"></b>';
            echo '</a>';
            echo '<ul class="dropdown-menu sidenotes">';
            echo implode($this->notes[$node]);
            echo '</ul>';
            echo '</li>';
        }
        if ($this->items[$node]) {
            foreach ((array) $this->items[$node] as $title => &$data) {
                self::$_title = $this->fixTitle($title);
                foreach ((array) $data as $label => &$link) {
                    $hash = '';
                    if (!$this->isExternalLink($link)) {
                        $hash = $this->getTarget($link);
                        if ($hash) {
                            $link = str_replace(
                                "#$hash",
                                '',
                                $link
                            );
                        }
                    }
                    if ($label == 'class') {
                        continue;
                    }
                    $string = sprintf(
                        '<li><a class="%s" href="${link}${hash}"%s>%s</a></li>',
                        $hash ?: $node.'-'.$sub,
                        (
                            !$this->isExternalLink($link)
                            && !empty($hash) ?
                            ' data-toggle="tab"' :
                            ''
                        ),
                        $label
                    );
                    if ($this->isExternalLink($link)) {
                        echo str_replace(
                            '${hash}',
                            (
                                $hash ?
                                "#$hash" :
                                ''
                            ),
                            str_replace(
                                '${link}',
                                $link,
                                $string
                            )
                        );
                    } elseif (!$link) {
                        echo str_replace(
                            '${hash}',
                            '',
                            str_replace(
                                '${link}',
                                "?node=$node",
                                $string
                            )
                        );
                    } else {
                        global $sub;
                        $components = parse_url($link);
                        if (!isset($components['query'])) {
                            $string = str_replace(
                                '${link}',
                                "?node=$node&"
                                . 'sub=${link}',
                                $string
                            );
                        }
                        if (!$sub || $title == self::$foglang['MainMenu']) {
                            echo str_replace(
                                '${hash}',
                                (
                                    $hash ?
                                    "#$hash" :
                                    ''
                                ),
                                str_replace(
                                    '${link}',
                                    $link,
                                    $string
                                )
                            );
                        } else {
                            echo str_replace(
                                '${hash}',
                                (
                                    $hash ?
                                    "#$hash" :
                                    ''
                                ),
                                str_replace(
                                    '${link}',
                                    $link,
                                    $string
                                )
                            );
                        }
                    }
                    unset($link, $label);
                }
                unset($data, $title);
            }
        }
        echo '</ul>';

        return ob_get_clean();
    }
    /**
     * Gets the data as setup.
     *
     * @param string $node The node to get menu for
     *
     * @throws Exception
     *
     * @return string
     */
    public function getMainItems($node)
    {
        ob_start();
        if (count($this->mainitems[$node]) < 1) {
            return;
        }
        echo '<div class="col-xs-3">';
        if ($this->mainitems[$node]) {
            foreach ((array)$this->mainitems[$node] as $title => &$data) {
                echo '<div class="panel panel-info">';
                self::$_title = $this->fixTitle($title);
                echo '<div class="panel-heading">';
                echo '<h4 class="category">';
                echo self::$_title;
                echo '</h4>';
                echo '</div>';
                echo '<div class="panel-body">';
                echo '<ul class="nav nav-pills nav-stacked">';
                foreach ((array) $data as $label => &$link) {
                    $hash = '';
                    $target = '';
                    if (!$this->isExternalLink($link)) {
                        $hash = $this->getTarget($link);
                        if ($hash) {
                            $link = str_replace(
                                "#$hash",
                                '',
                                $link
                            );
                        }
                    } else {
                        $target = ' target="_blank"';
                    }
                    if ($label == 'class') {
                        continue;
                    }
                    $string = sprintf(
                        '<li><a class="%s" href="${link}${hash}"%s>%s</a></li>',
                        $hash ?: $node.'-'.$sub,
                        $target,
                        $label
                    );
                    if ($this->isExternalLink($link)) {
                        echo str_replace(
                            '${hash}',
                            (
                                $hash ?
                                "#$hash" :
                                ''
                            ),
                            str_replace(
                                '${link}',
                                $link,
                                $string
                            )
                        );
                    } elseif (!$link) {
                        echo str_replace(
                            '${hash}',
                            '',
                            str_replace(
                                '${link}',
                                "?node=$node",
                                $string
                            )
                        );
                    } else {
                        global $sub;
                        $components = parse_url($link);
                        if (!isset($components['query'])) {
                            $string = str_replace(
                                '${link}',
                                "?node=$node&"
                                . 'sub=${link}',
                                $string
                            );
                        }
                        if (!$sub || $title == self::$foglang['MainMenu']) {
                            echo str_replace(
                                '${hash}',
                                (
                                    $hash ?
                                    "#$hash" :
                                    ''
                                ),
                                str_replace(
                                    '${link}',
                                    $link,
                                    $string
                                )
                            );
                        } else {
                            echo str_replace(
                                '${hash}',
                                (
                                    $hash ?
                                    "#$hash" :
                                    ''
                                ),
                                str_replace(
                                    '${link}',
                                    $link,
                                    $string
                                )
                            );
                        }
                    }
                    unset($link, $label);
                }
                echo '</ul>';
                echo '</div>';
                unset($data, $title);
            }
            echo '</div>';
        }
        echo '</div>';
        return ob_get_clean();
    }
    /**
     * Fixes the title displayed for side menus.
     *
     * @param string $title the title to fix
     *
     * @throws Exception
     *
     * @return string
     */
    public function fixTitle($title)
    {
        if (!is_string($title)) {
            throw new Exception(_('Title must be a string'));
        }
        $dash = strpos('-', $title) ? '-' : ' ';
        $e = preg_split('#[\s|-]#', $title, null, PREG_SPLIT_NO_EMPTY);
        return implode($dash, $e);
    }
    /**
     * Gets the target element from url
     *
     * @param string $link The link to test against
     *
     * @return string
     */
    public function getTarget($link)
    {
        if (!is_string($link)) {
            throw new Exception(_('Link must be a string'));
        }
        $components = parse_url($link);
        return isset($components['fragment']) ? $components['fragment'] : '';
    }
    /**
     * Test if the link passed is for an external source.
     *
     * @param string $link The link to test against
     *
     * @throws Exception
     *
     * @return bool
     */
    public function isExternalLink($link)
    {
        if (!is_string($link)) {
            throw new Exception(_('Link must be a string'));
        }
        $components = parse_url($link);
        return !empty($components['host'])
            && strcasecmp(
                $components['host'],
                filter_input(INPUT_SERVER, 'HTTP_HOST')
            );
    }
}
