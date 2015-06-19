<?php
/** \class FOGSubMenu
 * How-To: addItems
 * ----------------
 * Add "Main Menu" items for node
 * $FOGSubMenu->addItems('node', array('Title' => 'link'));
 * Add "Node Menu" items for node, if ($_REQUEST['node'] and $_REQUEST['id'] is set)
 * $FOGSubMenu->addItems('node', array('Title' => 'link'), 'nodeid', 'Node Menu');
 * Add "Node Menu" items for node, if ($_REQUEST['node'] amd $_REQUEST['id'] is set, custom external link
 * $FOGSubMenu->addItems('node', array('Title' => 'http://www.example.com'),'nodeid','Node Menu');
 * Add "Node Menu" items for node, if ($_REQUEST['node'] and $_REQUEST['id'] is set, custom node link (nodeid is appended)
 * $FOGSubMenu->addItems('node', array('Title' => '?node=blah'), 'nodeid', 'Node Menu');
 * Add "Node Menu" items for node, if ($_REQUEST['node'] and $_REQUEST['id'] is set, custom node link (nodeid is appended)
 * $FOGSubMenu->addItems('node', array('Title' => '/blah/index.php'), 'nodeid', 'Node Menu');
 *
 * How-To: addNotes
 * ----------------
 * Add static note
 * $FOGSubMenu->addNotes('node', array('Title' => 'Information'), 'id variable');
 * Add note with callback
 * $FOGSubMenu->addNotes('node', create_function('','return array('banana' => 'chicken');'), 'id variable');
 */
class FOGSubMenu {
    // Variables
    public $DEBUG = 0;
    public $version = 0.1;
    // These are used when another $sub is called
    // Include code needs to be rewritten to fix this correctly
    // i.e. Host Management -> Printers
    public $defaultSubs = array('host' => 'edit', 'group' => 'edit');
    // Constructor
    public function __construct() {
        $this->items = array();
        $this->info = array();
    }
    // Add menu items
    public function addItems($node, $items, $ifVariable = '', $ifVariableTitle = '') {
        // TODO: Clean up - use this below to start
        // No check variable? then Main Menu
        //$ifVariable = ($ifVariable == '' ? 'Main Menu' : $ifVariable);
        // No ifVariable to check, this must be a main menu item
        if (!$ifVariable) is_array($this->items[$node][$this->foglang['MainMenu']]) ? $this->items[$node][$this->foglang['MainMenu']] = array_merge($this->items[$node][$this->foglang['MainMenu']], $items) : $this->items[$node][$this->foglang['MainMenu']] = $items;
        // ifVariable passed to be checked, if it is set then add to menu
        elseif (isset($GLOBALS[$ifVariable])) {
            foreach ($items AS $title => $link)
                if (!$this->isExternalLink($link)) $items[$title] = "$link&$ifVariable=" . $GLOBALS[$ifVariable];
            is_array($this->items[$node][$ifVariableTitle]) ? $this->items[$node][$ifVariableTitle] = array_merge($this->items[$node][$ifVariableTitle], $items) : $this->items[$node][$ifVariableTitle] = $items;
        }
    }
    // Add notes below menu items
    public function addNotes($node, $data, $ifVariable = '') {
        if (is_callable($data)) $data = $data();
        if (is_array($data)) {
            foreach ($data AS $title => $info) $x[] = "<h3>" . $this->fixTitle($title) . "</h3>\n\t<p>$info</p>";
        }
        if ($ifVariable == '' || $GLOBALS[$ifVariable]) $this->notes[$node][] = implode((array)$x);
    }
    // Get menu items & notes for $node
    public function get($node) {
        // Menu Items
        if ($this->items[$node]) {
            foreach ($this->items[$node] AS $title => $data) {
                $output .= '<div class="organic-tabs"><h2>'.$this->fixTitle($title).'</h2><ul>';
                foreach ($data AS $label => $link) $output .= '<li><a href="' . (!$this->isExternalLink($link) ? $_SERVER['PHP_SELF'] . "?node=$node" . ($link != '' ? '&sub=' : '') . ($GLOBALS['sub'] && $title != $this->foglang['MainMenu'] ? ($this->defaultSubs[$node] ? $this->defaultSubs[$node] : $GLOBALS['sub']) . "&tab=" : '') . $link : $link) . '">' . $label . '</a></li>';
                $output .= "</ul></div>";
            }
        }
        // Notes
        if ($this->notes[$node]) $output .= '<div id="sidenotes">'.implode($this->notes[$node]).'</div>';
        return $output;
    }
    // Pretty up section titles
    public function fixTitle($title) {
        if (preg_match('#[[:space:]]#', $title)) {
            $e = explode(' ', $title);
            $e[0] = "<b>$e[0]</b>";
            $title = implode(' ', $e);
        }
        else if (preg_match('#-#', $title)) {
            $e = explode('-', $title);
            $e[0] = "<b>$e[0]</b>";
            $title = implode('-', $e);
        }
        return $title;
    }
    // Test if the link is a node link or an external link
    public function isExternalLink($link) {
        if (substr($link, 0, 4) == 'http' || $link{0} == '/' ||  $link{0} == '?' || $link{0} == '#') return true;
        return false;
    }
    // Debug
    public function debug($txt) {if ($this->DEBUG) print '[' . $this->nice_date()->format("m/d/y H:i:s") . "] " . htmlspecialchars(is_array($txt) ? print_r($txt, 1) : $txt) . "\n";}
}
