<?php
/* How-To: addItems
 * ----------------
 * Add "Main Menu" items for node
 * $FOGSubMenu->addItems('node', array('Title' => 'link'));
 * Add "Node Menu" items for node, if ($_REQUEST['node'] and $_REQUEST['id'] is set)
 * $FOGSubMenu->addItems('node', array('Title' => 'link'), 'nodeid', 'Node Menu');
 * Add "Node Menu" items for node, if ($_REQUEST['node'] and $_REQUEST['id'] is set, custom external link
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
class FOGSubMenu extends FOGBase {
    private static $title;
    public $defaultSubs = array('host'=>'edit','group'=>'edit');
    public function addItems($node, $items, $ifVariable = '', $ifVariableTitle = '') {
        $variableSetter = (!$ifVariable ? self::$foglang['MainMenu'] : $ifVariableTitle);
        if (isset($_REQUEST[$ifVariable])) {
            global $$ifVariable;
            array_walk($items,function(&$link,&$title) use ($ifVariable) {
                if (!$this->isExternalLink($link)) $link = "$link&$ifVariable={$$ifVariable}";
                unset($link,$title);
            });
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
            array_walk($data,function(&$title,&$info) {
                printf("<h3>%s</h3>\n\t<p>%s</p>",$this->fixTitle($title),$info);
                unset($info,$title);
            });
        }
        $this->notes[$node][] = ob_get_clean();
    }
    public function get($node) {
        ob_start();
        if ($this->items[$node]) {
            array_walk($this->items[$node],function(&$data,&$title) use (&$node,$labelcreator) {
                self::$title = $this->fixTitle($title);
                printf('<div class="organic-tabs"><h2>%s</h2><ul>',self::$title);
                ob_start();
                array_walk($data,function(&$link,&$label) use (&$node,&$title) {
                    $string = sprintf('<li><a href="%s">%s</a></li>','%s',$label);
                    if ($this->isExternalLink($link)) printf($string,$link);
                    else if (!$link) printf($string,"?node=$node");
                    else {
                        $string = sprintf($string,"?node=$node&sub=%s");
                        $sub = $_REQUEST['sub'];
                        if (!$sub || $title == self::$foglang['MainMenu']) printf($string,$link);
                        else if ($this->defaultSubs[$node]) printf($string,"{$this->defaultSubs[$node]}&tab=$link");
                        else printf($string,"$sub&tab=$link");
                    }
                    unset($link,$label);
                });
                printf('%s</ul></div>',ob_get_clean());
                unset($data,$title);
            });
        }
        if ($this->notes[$node]) printf('<div id="sidenotes">%s</div>',implode($this->notes[$node]));
        return ob_get_clean();
    }
    public function fixTitle($title) {
        $dash = strpos('-',$title) ? '-' : ' ';
        $e = preg_split('#[\s|-]#',$title,null,PREG_SPLIT_NO_EMPTY);
        $e[0] = "<b>$e[0]</b>";
        return implode($dash,$e);
    }
    public function isExternalLink($link) {
        return (bool)(substr($link, 0, 5) == 'https' || substr($link, 0, 4) == 'http' || $link{0} == '/' ||  $link{0} == '?' || $link{0} == '#');
    }
}
