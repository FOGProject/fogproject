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
    );
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
        if (isset($_REQUEST[$ifVariable])) {
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
     * @param string $node   The node to get menu for
     * @param bool   $getall Do we do all or partial?
     *
     * @throws Exception
     *
     * @return string
     */
    public function get($node, $getall = true)
    {
        ob_start();
        echo '<ul class="nav nav-tabs">';
        if ($this->items[$node]) {
            foreach ((array) $this->items[$node] as $title => &$data) {
                self::$_title = $this->fixTitle($title);
                echo '<li class="dropdown">';
                echo '<a href="#" class="dropdown-toggle" data-toggle="dropdown">';
                echo '<p>';
                echo self::$_title;
                echo '<b class="caret"></b>';
                echo '</p>';
                echo '</a>';
                echo '<ul class="dropdown-menu">';
                foreach ((array) $data as $label => &$link) {
                    if ($label == 'class') {
                        continue;
                    }
                    $string = sprintf(
                        '<li><a class="%s" href="${link}">%s</a></li>',
                        $link,
                        $label
                    );
                    if ($this->isExternalLink($link)) {
                        echo str_replace(
                            '${link}',
                            $link,
                            $string
                        );
                    } elseif (!$link) {
                        echo str_replace(
                            '${link}',
                            "?node=$node",
                            $string
                        );
                    } else {
                        global $sub;
                        $string = str_replace(
                            '${link}',
                            "?node=$node&sub=\${link}",
                            $string
                        );
                        if (!$sub || $title == self::$foglang['MainMenu']) {
                            echo str_replace(
                                '${link}',
                                $link,
                                $string
                            );
                        } elseif ($this->defaultSubs[$node]) {
                            echo str_replace(
                                '${link}',
                                "{$this->defaultSubs[$node]}&tab=$link",
                                $string
                            );
                        } else {
                            echo str_replace(
                                '${link}',
                                "$sub&tab=$link",
                                $string
                            );
                        }
                    }
                    unset($link, $label);
                }
                echo '</ul>';
                echo '</li>';
                unset($data, $title);
            }
        }
        if ($this->notes[$node]) {
            echo '<li class="dropdown">';
            echo '<a href="#" class="dropdown-toggle" data-toggle="dropdown">';
            echo '<p>';
            echo _('Notes');
            echo '<b class="caret"></b>';
            echo '</p>';
            echo '</a>';
            echo '<ul class="dropdown-menu sidenotes">';
            echo implode($this->notes[$node]);
            echo '</ul>';
            echo '</li>';
        }
        echo '</ul>';

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
        $https = (bool) (substr($link, 0, 5) == 'https');
        $http = (bool) (substr($link, 0, 4) == 'http');
        $extlink = (bool) in_array($link{0}, array('/', '?', '#'));

        return (bool) $https === true || $http === true || $extlink === true;
    }
}
