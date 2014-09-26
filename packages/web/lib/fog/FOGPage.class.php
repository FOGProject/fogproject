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
		$this->title = $this->foglang[$this->name];
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
					$result[] = sprintf('%s<form method="post" action="%s" id="search-wrapper"><input id="%s-search" class="search-input placeholder" type="text" value="" placeholder="%s" autocomplete="off" '.(preg_match('#mobile#i',$_SERVER['PHP_SELF']) ? 'name="host-search"' : '').'/> <input id="%s-search-submit" class="search-submit" type="'.(preg_match('#mobile#i',$_SERVER['PHP_SELF']) ? 'submit' : 'button').'" value="'.(preg_match('#mobile#i',$_SERVER['PHP_SELF']) ? $this->foglang['Search'] : '').'" /></form>',
						"\n\t\t\t",
						$this->searchFormURL,
						(substr($this->node, -1) == 's' ? substr($this->node, 0, -1) : $this->node),	// TODO: Store this in class as variable
						sprintf('%s %s', ucwords((substr($this->node, -1) == 's' ? substr($this->node, 0, -1) : $this->node)), $this->foglang['Search']),
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
						($this->data['error'] ? (is_array($this->data['error']) ? '<p>' . implode('</p><p>', $this->data['error']) . '</p>' : $this->data['error']) : $this->foglang['NoResults'])
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

	public function deploy()
	{
		if (in_array($_REQUEST['node'],array('host','hosts')))
		{
			$Data = new Host($_REQUEST['id']);
			$ClassType = 'Host';
		}
		if (in_array($_REQUEST['node'],array('group','groups')))
		{
			$Data = new Group($_REQUEST['id']);
			$ClassType = 'Group';
		}
		$TaskType = new TaskType(($_REQUEST['type'] ? $_REQUEST['type'] : 1));
		// Title
		$this->title = sprintf('%s %s %s %s',_('Create'),$TaskType->get('name'),_('task for'),$ClassType);
		// Deploy
		printf('%s%s%s%s',"\n\t\t\t",'<p class="c"><b>',_('Are you sure you wish to deploy task to these machines'),'</b></p>');
		printf('%s<form method="post" action="%s" id="deploy-container">',"\n\t\t\t",$this->formAction);
		printf("\n\t\t\t%s",'<div class="confirm-message">');
		if ($TaskType->get('id') == 13)
		{
			printf('<center>%s<p>%s</p>',"\n\t\t\t",_('Please select the snapin you want to deploy'));
			if ($ClassType == 'Host')
			{
				foreach((array)$Data->get('snapins') AS $Snapin)
				{
					if ($Snapin && $Snapin->isValid())
						$optionSnapin[] = sprintf('<option value="%s">%s - (%s)</option>',$Snapin->get('id'),$Snapin->get('name'),$Snapin->get('id'));
				}
				if ($optionSnapin)
					printf('%s<select name="snapin">%s</select></center>',"\n\t\t\t",implode("\n\t\t\t\t",$optionSnapin));
				else
					printf('%s</center>',_('No snapins associated'));
			}
			if ($ClassType == 'Group')
				printf($this->FOGCore->getClass('SnapinManager')->buildSelectBox().'</center>');
		}
		printf("\n\t\t\t%s",'<div class="advanced-settings">');
		printf("\n\t\t\t<h2>%s</h2>",_('Advanced Settings'));
		printf("\n\t\t\t<p>%s%s <u>%s</u> %s%s",'<input type="checkbox" name="shutdown" id="shutdown" value="1" autocomplete="off"><label for="shutdown">',_('Schedule'),_('Shutdown'),_('after task completion'),'</label></p>');
		if (!$TaskType->isDebug() && $TaskType->get('id') != 11)
		{
			printf("\n\t\t\t%s%s%s",'<p><input type="checkbox" name="isDebugTask" id="isDebugTask" autocomplete="off" /><label for="isDebugTask">',_('Schedule task as a debug task'),'</label></p>');
			printf("\n\t\t\t%s%s %s%s%s",'<p><input type="radio" name="scheduleType" id="scheduleInstant" value="instant" autocomplete="off" checked="checked" /><label for="scheduleInstant">',_('Schedule '),'<u>',_('Instant Deployment'),'</u></label></p>');
			printf("\n\t\t\t%s%s %s%s%s",'<p class="hideFromDebug"><input type="radio" name="scheduleType" id="scheduleSingle" value="single" autocomplete="off" /><label for="scheduleSingle">',_('Schedule '),'<u>',_('Delayed Deployment'),'</u></label></p>');
			printf("\n\t\t\t%s",'<p class="hidden" id="singleOptions"><input type="text" nme="scheduleSingleTime" id="scheduleSingleTime" autocomplete="off" /></p>');
			printf("\n\t\t\t%s%s %s%s%s",'<p class="hideFromDebug"><input type="radio" name="scheduleType" id="scheduleCron" value="cron" autocomplete="off"><label for="scheduleCron">',_('Schedule'),'<u>',_('Cron-style Deployment'),'</u></label></p>');
			printf("\n\t\t\t%s",'<p class="hidden" id="cronOptions">');
			printf("\n\t\t\t%s",'<input type="text" name="scheduleCronMin" id="scheduleCronMin" placeholder="min" autocomplete="off" />');
			printf("\n\t\t\t%s",'<input type="text" name="scheduleCronHour" id="scheduleCronHour" placeholder="hour" autocomplete="off" />');
			printf("\n\t\t\t%s",'<input type="text" name="scheduleCronDOM" id="scheduleCronDOM" placeholder="dom" autocomplete="off" />');
			printf("\n\t\t\t%s",'<input type="text" name="scheduleCronMonth" id="scheduleCronMonth" placeholder="month" autocomplete="off" />');
			printf("\n\t\t\t%s",'<input type="text" name="scheduleCronDOW" id="scheduleCronDOW" placeholder="dow" autocomplete="off" /></p>');
		}
		if ($TaskType->get('id') == 11)
		{
			printf("\n\t\t\t<p>%s</p>",_('Which account would you like to reset the pasword for'));
			printf("\n\t\t\t%s",'<input type="text" name="account" value="Administrator" />');
		}
		printf("\n\t\t\t</div>");
		printf("\n\t\t\t</div>");
		printf("\n\t\t\t<h2>%s</h2>",_('Hosts in Task'));
		unset($this->headerData);
		$this->attributes = array(
			array(),
			array(),
			array(),
		);
		$this->templates = array(
			'<a href="${host_link}" title="${host_title}">${host_name}</a>',
			'${host_mac}',
			'<a href="${image_link}" title="${image_title}">${image_name}</a>',
		);
		if ($Data instanceof Host)
		{
			$this->data[] = array(
				'host_link' => $_SERVER['PHP_SELF'].'?node=host&sub=edit&id=${host_id}',
				'image_link' => $_SERVER['PHP_SELF'].'?node=images&sub=edit&id=${image_id}',
				'host_id' => $Data->get('id'),
				'image_id' => $Data->getImage()->get('id'),
				'host_name' => $Data->get('name'),
				'host_mac' => $Data->get('mac'),
				'image_name' => $Data->getImage()->get('name'),
				'host_title' => _('Edit Host'),
				'image_title' => _('Edit Image'),
			);
		}
		if ($Data instanceof Group)
		{
			foreach($Data->get('hosts') AS $Host)
			{
				if ($Host && $Host->isValid())
				{
					$this->data[] = array(
						'host_link' => $_SERVER['PHP_SELF'].'?node=host&sub=edit&id=${host_id}',
						'image_link' => $_SERVER['PHP_SELF'].'?node=images&sub=edit&id=${image_id}',
						'host_id' => $Host->get('id'),
						'image_id' => $Host->getImage()->get('id'),
						'host_name' => $Host->get('name'),
						'host_mac' => $Host->get('mac'),
						'image_name' => $Host->getImage()->get('name'),
						'host_title' => _('Edit Host'),
						'image_title' => _('Edit Image'),
					);
				}
			}
		}
		// Hook
		$this->HookManager->processEvent(strtoupper($ClassType.'_DEPLOY'),array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates,'attributes' => &$this->attributes));
		// Output
		$this->render();
		printf('%s%s%s','<p class="c"><input type="submit" value="',$this->title,'" /></p>');
		printf("\n\t\t\t</form>");
	}
}
