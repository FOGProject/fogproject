<?php
/** \class FOGPage
	The pages all use this.  It's what prints out
	tables and headers, titles.  Basically everything
	displayed to the user in the GUI is using this
	class file.
*/
abstract class FOGPage extends FOGBase
{
	// Name
	public $name = '';
	// Node Variable
	public $node = '';
	// ID Variable - name of ID variable used in Page
	// LEGACY: This is for LEGACY support - all of these will be 'id' eventually
	public $id = '';
	// Menu Items
	// TODO: Finish
	public $menu = array(
	);
	// Sub Menu Items - when ID Variable is set
	// TODO: Finish
	public $subMenu = array(
	);
	// Variables
	// Page title
	public $titleEnabled = true;
	public $title;
	// Render engine
	public $headerData = array();
	public $data = array();
	public $templates = array();
	public $attributes = array();
	public $searchFormURL = '';	// If set, allows a search page using FOGAjaxSearch JQuery function
	private $wrapper = 'td';
	private $result;
	// Method & Form
	protected $post = false;	// becomes true if POST request
	protected $ajax = false;	// becomes true if AJAX request
	protected $request = array();
	protected $formAction;
	protected $formPostAction;
	// __construct
	public function __construct($name = '')
	{
		// FOGBase contstructor
		parent::__construct();
		if (!$this->FOGUser)
			$this->FOGUser = (!empty($_SESSION['FOG_USER']) ? unserialize($_SESSION['FOG_USER']) : null);
		// Set name
		if (!empty($name))
			$this->name = $name;
		// Set title
		$this->title = _($this->name);
		// Make these key's accessible in $this->request
		$this->request = $this->REQUEST = $this->DB->sanitize($_REQUEST);
		$this->REQUEST['id'] = $_REQUEST[$this->id];
		$this->request['id'] = $_REQUEST[$this->id];
		// Methods
		$this->post = $this->FOGCore->isPOSTRequest();
		$this->ajax = $this->FOGCore->isAJAXRequest();
		// Default form target
		$this->formAction = sprintf('%s?%s', $_SERVER['PHP_SELF'], $_SERVER['QUERY_STRING']);
	}
	// Default index page
	public function index()
	{
		printf('Index page of: %s%s', get_class($this), (count($args) ? ', Arguments = ' . implode(', ', array_map(create_function('$key, $value', 'return $key." : ".$value;'), array_keys($args), array_values($args))) : ''));
	}
	public function set($key, $value)
	{
		$this->$key = $value;
		return $this;
	}
	public function get($key)
	{
		return $this->$key;
	}
	public function __toString()
	{
		$this->process();
	}
	public function render()
	{
		print $this->process();
	}
	public function process()
	{
		try
		{
			// Error checking
			if (!count($this->templates))
				throw new Exception('Requires templates to process');
			// Variables
			//$result = '';
			// Is AJAX Request?
			if ($this->FOGCore->isAJAXRequest())
			{
				// JSON output
				$result[] = @json_encode(array(
					'data'		=> $this->data,
					'templates'	=> $this->templates,
					'headerData' => $this->headerData,
					'title' => $this->title,
					'attributes'	=> $this->attributes,
				));
			}
			else
			{
				// HTML output
				if ($this->searchFormURL)
				{
					$result[] = sprintf('%s<form method="POST" action="%s" id="search-wrapper"><input id="%s-search" class="search-input placeholder" type="text" value="" placeholder="%s" autocomplete="off" '.(preg_match('#mobile#i',$_SERVER['PHP_SELF']) ? 'name="host-search"' : '').'/> <input id="%s-search-submit" class="search-submit" type="'.(preg_match('#mobile#i',$_SERVER['PHP_SELF']) ? 'submit' : 'button').'" value="'.(preg_match('#mobile#i',$_SERVER['PHP_SELF']) ? _('Search') : '').'" /></form>',
						"\n\t\t\t",
						$this->searchFormURL,
						(substr($this->node, -1) == 's' ? substr($this->node, 0, -1) : $this->node),	// TODO: Store this in class as variable
						sprintf('%s %s', ucwords((substr($this->node, -1) == 's' ? substr($this->node, 0, -1) : $this->node)), _('Search')),
						(substr($this->node, -1) == 's' ? substr($this->node, 0, -1) : $this->node)	// TODO: Store this in class as variable
					);
				}
				// Table -> Header Row
				$result[] = sprintf('%s<table width="%s" cellpadding="0" cellspacing="0" border="0"%s>%s<thead>%s<tr class="header">%s</tr>%s</thead>%s<tbody>%s',
					"\n\n\t\t\t",
					'100%',
					($this->searchFormURL ? ' id="search-content"' : ($this->node == 'tasks' && ($_REQUEST['sub'] == 'active' || !$_REQUEST['sub']) ? ' id="active-tasks"' : '')),
					"\n\t\t\t\t",
					"\n\t\t\t\t\t",
					$this->buildHeaderRow(),
					"\n\t\t\t\t",
					"\n\t\t\t\t",
					"\n\t\t\t\t\t",
					"\n\t\t\t"
				);
				// Rows
				if (count($this->data))
				{
					// Data found
					foreach ($this->data AS $rowData)
					{
						$result[] = sprintf('<tr id="%s-%s" class="%s">%s</tr>%s',
							(substr($this->node, -1) == 's' ? substr($this->node, 0, -1) : $this->node),
							$rowData['id'],
							(++$i % 2 ? 'alt1' : ((!$_REQUEST['sub'] && $this->FOGCore->getSetting('FOG_VIEW_DEFAULT_SCREEN') == 'list') || in_array($_REQUEST['sub'],array('list','search')) ? 'alt2' : '')),
							$this->buildRow($rowData),
							"\n\t\t\t\t\t"
						);
					}
					// Set message
					if (!$this->searchFormURL && in_array($_REQUEST['sub'],array('search','list')))
						$this->FOGCore->setMessage(sprintf('%s %s%s found', count($this->data), ucwords($this->node), (count($this->data) == 1 ? '' : (substr($this->node, -1) == 's' ? '' : 's'))));
				}
				else
				{
					// No data found
					$result[] = sprintf('<tr><td colspan="%s" class="no-active-tasks">%s</td></tr>',
						count($this->templates),
						($this->data['error'] ? (is_array($this->data['error']) ? '<p>' . implode('</p><p>', $this->data['error']) . '</p>' : $this->data['error']) : _('No results found'))
					);
				}
				// Table close
				$result[] = sprintf('%s</tbody>%s</table>%s', "\n\t\t\t\t", "\n\t\t\t", "\n\n\t\t\t");
			}
			// Return output
			return implode("\n",$result);
		}
		catch (Exception $e)
		{
			return $e->getMessage();
		}
	}
	public function buildHeaderRow()
	{
		// Loop data
		if ($this->headerData)
		{
			foreach ($this->headerData AS $i => $content)
			{
				// Create attributes data
				foreach ((array)$this->attributes[$i] as $attributeName => $attributeValue)
					$attributes[] = sprintf('%s="%s"', $attributeName, $attributeValue);
				// Push into results array
				$result[] = sprintf('<%s%s>%s</%s>',	$this->wrapper,
									(count($attributes) ? ' ' . implode(' ', $attributes) : ''),
									$content,
									$this->wrapper);
				// Reset
				unset($attributes);
			}
			// Return result
			return "\n\t\t\t\t\t\t" . implode("\n\t\t\t\t\t\t", $result) . "\n\t\t\t\t\t";
		}
	}
	public function buildRow($data)
	{
		// Loop template data
		foreach ($this->templates AS $i => $template)
		{
			// Create find and replace arrays for data
			foreach ($data AS $dataName => $dataValue)
			{
				// Legacy - remove when converted
				$dataFind[] = '#%' . $dataName . '%#';
				$dataReplace[] = $dataValue;
				// New
				$dataFind[] = '#\$\{' . $dataName . '\}#';
				$dataReplace[] = $dataValue;
			}
			foreach (array('node', 'sub', 'tab') AS $extraData)
			{
				// Legacy - remove when converted
				$dataFind[] = '#%' . $extraData . '%#';
				$dataReplace[] = $GLOBALS[$extraData];
				// New
				$dataFind[] = '#\$\{' . $extraData . '\}#';
				$dataReplace[] = $GLOBALS[$extraData];
			}
			// Create attributes data
			foreach ((array)$this->attributes[$i] as $attributeName => $attributeValue)
				$attributes[] = sprintf('%s="%s"',$attributeName,preg_replace($dataFind,$dataReplace,$attributeValue));
			// Replace variables in template with data -> wrap in $this->wrapper -> push into $result
			$result[] = sprintf('<%s%s>%s</%s>',	$this->wrapper,
								(count($attributes) ? ' ' . implode(' ', $attributes) : ''),
								preg_replace($dataFind, $dataReplace, $template),
								$this->wrapper);
			// Reset
			unset($attributes, $dataFind, $dataReplace);
		}
		// Return result
		return "\n\t\t\t\t\t\t" . implode("\n\t\t\t\t\t\t", $result) . "\n\t\t\t\t\t";
	}
}
