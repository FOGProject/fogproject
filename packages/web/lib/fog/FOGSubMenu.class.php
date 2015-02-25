<?php
/****************************************************
	\class FOGSubMenu
 * 	FOG: FOGSubMenu Class
 *	Author:		Blackout
 *	Created:	3:02 PM 4/09/2010
 *	Revision:	$Revision$
 *	Last Update:	$LastChangedDate$
 ***/

/*
// FOGSubMenu How-To: addItems
// ----------------------
// Add "Main Menu" items for NODE
$FOGSubMenu->addItems('NODE', array('Title' => 'link'));
// Add "NODE Menu" items for NODE, if $nodeid (global) is set
$FOGSubMenu->addItems('NODE', array('Title' => 'link'), 'nodeid', 'NODE Menu');
// Add "NODE Menu" items for NODE, if $nodeid (global) is set, custom external link
$FOGSubMenu->addItems('NODE', array('Title' => 'http://google.com'), 'nodeid', 'NODE Menu');
// Add "NODE Menu" items for NODE, if $nodeid (global) is set, custom node link (nodeid is appended)
$FOGSubMenu->addItems('NODE', array('Title' => '?node=blah'), 'nodeid', 'NODE Menu');
// Add "NODE Menu" items for NODE, if $nodeid (global) is set, custom node link (nodeid is appended)
$FOGSubMenu->addItems('NODE', array('Title' => '/blah/index.php'), 'nodeid', 'NODE Menu');

// FOGSubMenu How-To: addNotes
// ----------------------
// Add static Note
$FOGSubMenu->addNotes('NODE', array('Title' => 'Information'), 'id variable');
// Add Note with Callback
$FOGSubMenu->addNotes('NODE', create_function('', 'return array("banana" => "chicken");'), 'id variable');
*/

class FOGSubMenu
{
	// Variables
	var $DEBUG = 0;
	var $version = 0.1;
	
	// These are used when another $sub is called
	// Include code needs to be rewritten to fix this correctly
	// i.e. Host Management -> Printers
	var $defaultSubs = array('host' => 'edit', 'group' => 'edit');
	
	// Constructor
	function __construct()
	{
		$this->items = array();
		$this->info = array();
	}
	
	// Add menu items
	public function addItems($node, $items, $ifVariable = '', $ifVariableTitle = '')
	{
		// TODO: Clean up - use this below to start
		// No check variable? then Main Menu
		//$ifVariable = ($ifVariable == '' ? 'Main Menu' : $ifVariable);
		
		// No ifVariable to check, this must be a main menu item
		if (!$ifVariable)
		{
			if (is_array($this->items[$node][$this->foglang['MainMenu']]))
				$this->items[$node][$this->foglang['MainMenu']] = array_merge($this->items[$node][$this->foglang['MainMenu']], $items);
			else
				$this->items[$node][$this->foglang['MainMenu']] = $items;
		}
		// ifVariable passed to be checked, if it is set then add to menu
		elseif (isset($GLOBALS[$ifVariable]))
		{
			foreach ($items AS $title => $link)
				if (!$this->isExternalLink($link)) $items[$title] = "$link&$ifVariable=" . $GLOBALS[$ifVariable];
			if (is_array($this->items[$node][$ifVariableTitle]))
				$this->items[$node][$ifVariableTitle] = array_merge($this->items[$node][$ifVariableTitle], $items);
			else
				$this->items[$node][$ifVariableTitle] = $items;
		}
	}
	// Add notes below menu items
	public function addNotes($node, $data, $ifVariable = '') {
		if (is_callable($data))
			$data = $data();
		if (is_array($data))
		{
			foreach ($data AS $title => $info)
				$x[] = "<h3>" . $this->fixTitle($title) . "</h3>\n\t<p>$info</p>";
		}
		if ($ifVariable == '' || $GLOBALS[$ifVariable]) $this->notes[$node][] = implode("\n", (array)$x);
	}
	
	// Get menu items & notes for $node
	public function get($node)
	{
		// Menu Items
		if ($this->items[$node])
		{
			foreach ($this->items[$node] AS $title => $data)
			{
				// HACK: Add div around submenu items for tabs
				// Blackout - 8:24 AM 30/11/2011
				//$output .= (++$i >= 2 ? '<div class="organic-tabs">' . "\n\t\t" : '');
				$output .= '<div class="organic-tabs">' . "\n\t\t";
				
				$output .= "<h2>" . $this->fixTitle($title) . "</h2>\n\t\t<ul>\n";
				foreach ($data AS $label => $link)
					$output .= "\t\t\t" . '<li><a href="' . (!$this->isExternalLink($link) ? $_SERVER['PHP_SELF'] . "?node=$node" . ($link != '' ? '&sub=' : '') . ($GLOBALS['sub'] && $title != $this->foglang['MainMenu'] ? ($this->defaultSubs[$node] ? $this->defaultSubs[$node] : $GLOBALS['sub']) . "&tab=" : '') . $link : $link) . '">' . $label . '</a></li>' . "\n";
				$output .= "\t\t</ul>\n";
				// HACK: Add div around submenu items for tabs
				// Blackout - 8:24 AM 30/11/2011
				//$output .= ($i >= 2 ? "\t\t</div>\n" : '');
				$output .= "\t\t</div>\n";
			}
		}
		// Notes
		if ($this->notes[$node])
			$output .= '<div id="sidenotes">' . "\n\t" . implode("\t\n", $this->notes[$node]) . "\n" . '</div>';
		return $output;
	}
	
	// Pretty up section titles
	public function fixTitle($title)
	{
		if (preg_match('#[[:space:]]#', $title))
		{
			$e = explode(' ', $title);
			$e[0] = "<b>$e[0]</b>";
			$title = implode(' ', $e);
		}
		else if (preg_match('#-#', $title))
		{
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
	public function debug($txt) {
		if ($this->DEBUG) print '[' . $this->nice_date()->format("m/d/y H:i:s") . "] " . htmlspecialchars(is_array($txt) ? print_r($txt, 1) : $txt) . "\n";
	}
}
