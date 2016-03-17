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
    public $debug = false;
    public $version = 0.1;
    public $defaultSubs = array('host'=>'edit','group'=>'edit');
    public function __construct() {
        $this->items = array();
        $this->info = array();
    }
    public function addItems($node, $items, $ifVariable = '', $ifVariableTitle = '') {
        $variableSetter = (!$ifVariable ? FOGCore::$foglang['MainMenu'] : $ifVariableTitle);
        if (isset($_REQUEST[$ifVariable])) {
            foreach ((array)$items AS $title => &$link) {
                if (!$this->isExternalLink($link)) $items[$title] = "$link&$ifVariable={$GLOBALS[$ifVariable]}";
                unset($link);
            }
        }
        if (is_array($this->items[$node][$variableSetter])) {
            $this->items[$node][$variableSetter] = array_merge($this->items[$node][$variableSetter],$items);
        } else $this->items[$node][$variableSetter] = $items;
    }
    public function addNotes($node, $data, $ifVariable = '') {
        if ($ifVariable && !$_REQUEST[$ifVariable]) return;
        if (is_callable($data)) $data = $data();
        if (is_array($data)) {
            ob_start();
            foreach ($data AS $title => &$info) {
                printf("<h3>%s</h3>\n\t<p>%s</p>",$this->fixTitle($title),$info);
                unset($info);
            }
        }
        $this->notes[$node][] = ob_get_clean();
    }
    public function get($node) {
        if ($this->items[$node]) {
            ob_start();
            foreach ($this->items[$node] AS $title => &$data) {
                printf('<div class="organic-tabs"><h2>%s</h2><ul>',$this->fixTitle($title));
                ob_start();
                foreach ($data AS $label => &$link) {
                    $string = "<li><a href='%s'>$label</a></li>";
                    if ($this->isExternalLink($link)) printf($string,$link);
                    else if (!$link) printf($string,"?node=$node");
                    else {
                        $string = sprintf($string,"?node=$node&sub=%s");
                        $sub = htmlentities($_REQUEST['sub'],ENT_QUOTES,'utf-8');
                        (!$sub || $title == FOGCore::$foglang['MainMenu'] ? printf($string,$link) : ($this->defaultSubs[$node] ? printf($string,"{$this->defaultSubs[$node]}&tab=$link") : printf($string,"$sub&tab=$link")));
                    }
                    unset($link);
                }
                printf('%s</ul></div>',ob_get_clean());
                unset($data);
            }
        }
        if ($this->notes[$node]) {
            printf('<div id="sidenotes">%s</div>',implode($this->notes[$node]));
        }
        return ob_get_clean();
    }
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
    public function isExternalLink($link) {
        return (bool)(substr($link, 0, 5) == 'https' || substr($link, 0, 4) == 'http' || $link{0} == '/' ||  $link{0} == '?' || $link{0} == '#');
    }
    public function debug($txt) {
        if ($this->debug) printf("[%s] %s\n",$this->formatTime('','m/d/y H:i:s'),is_array($txt) ? print_r($txt, 1) : $txt);
    }
}
