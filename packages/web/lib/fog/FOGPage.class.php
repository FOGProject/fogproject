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
		$ClassType = ucfirst($this->node);
		$Data = new $ClassType($_REQUEST['id']);
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
			if ($Data instanceof Host)
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
			if ($Data instanceof Group)
				printf($this->getClass('SnapinManager')->buildSelectBox().'</center>');
		}
		printf("\n\t\t\t%s",'<div class="advanced-settings">');
		printf("\n\t\t\t<h2>%s</h2>",_('Advanced Settings'));
		printf("\n\t\t\t%s%s%s <u>%s</u> %s%s",'<p class="hideFromDebug">','<input type="checkbox" name="shutdown" id="shutdown" value="1" autocomplete="off"><label for="shutdown">',_('Schedule'),_('Shutdown'),_('after task completion'),'</label></p>');
		if (!$TaskType->isDebug() && $TaskType->get('id') != 11)
		{
			printf("\n\t\t\t%s%s%s",'<p><input type="checkbox" name="isDebugTask" id="isDebugTask" autocomplete="off" /><label for="isDebugTask">',_('Schedule task as a debug task'),'</label></p>');
			printf("\n\t\t\t%s%s %s%s%s",'<p><input type="radio" name="scheduleType" id="scheduleInstant" value="instant" autocomplete="off" checked="checked" /><label for="scheduleInstant">',_('Schedule '),'<u>',_('Instant Deployment'),'</u></label></p>');
			printf("\n\t\t\t%s%s %s%s%s",'<p class="hideFromDebug"><input type="radio" name="scheduleType" id="scheduleSingle" value="single" autocomplete="off" /><label for="scheduleSingle">',_('Schedule '),'<u>',_('Delayed Deployment'),'</u></label></p>');
			printf("\n\t\t\t%s",'<p class="hidden hideFromDebug" id="singleOptions"><input type="text" name="scheduleSingleTime" id="scheduleSingleTime" autocomplete="off" /></p>');
			printf("\n\t\t\t%s%s %s%s%s",'<p class="hideFromDebug"><input type="radio" name="scheduleType" id="scheduleCron" value="cron" autocomplete="off"><label for="scheduleCron">',_('Schedule'),'<u>',_('Cron-style Deployment'),'</u></label></p>');
			printf("\n\t\t\t%s",'<p class="hidden hideFromDebug" id="cronOptions">');
			printf("\n\t\t\t%s",'<input type="text" name="scheduleCronMin" id="scheduleCronMin" placeholder="min" autocomplete="off" />');
			printf("\n\t\t\t%s",'<input type="text" name="scheduleCronHour" id="scheduleCronHour" placeholder="hour" autocomplete="off" />');
			printf("\n\t\t\t%s",'<input type="text" name="scheduleCronDOM" id="scheduleCronDOM" placeholder="dom" autocomplete="off" />');
			printf("\n\t\t\t%s",'<input type="text" name="scheduleCronMonth" id="scheduleCronMonth" placeholder="month" autocomplete="off" />');
			printf("\n\t\t\t%s",'<input type="text" name="scheduleCronDOW" id="scheduleCronDOW" placeholder="dow" autocomplete="off" /></p>');
		}
		else if ($TaskType->isDebug())
			printf("\n\t\t\t%s%s %s%s%s",'<p><input type="radio" name="scheduleType" id="scheduleInstant" value="instant" autocomplete="off" checked="checked" /><label for="scheduleInstant">',_('Schedule '),'<u>',_('Instant Deployment'),'</u></label></p>');
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
				'image_link' => $_SERVER['PHP_SELF'].'?node=image&sub=edit&id=${image_id}',
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
						'image_link' => $_SERVER['PHP_SELF'].'?node=image&sub=edit&id=${image_id}',
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
	/** deploy_post()
	* Make the deployment actually happen.
	*/
	public function deploy_post()
	{
		$ClassType = ucfirst($this->node);
		$Data = new $ClassType($_REQUEST['id']);
		$TaskType = new TaskType($_REQUEST['type']);
		$Snapin = $_REQUEST['snapin'] ? new Snapin($_REQUEST['snapin']) : -1;
		$enableShutdown = $_REQUEST['shutdown'] ? true : false;
		$enableSnapins = $TaskType->get('id') != '17' ? ($Snapin instanceof Snapin && $Snapin->isValid() ? $Snapin->get('id') : $Snapin) : false;
		$enableDebug = $_REQUEST['debug'] == 'true' || $_REQUEST['isDebugTask'] ? true : false;
		$scheduleDeployTime = $this->nice_date($_REQUEST['scheduleSingleTime']);
		$imagingTasks = in_array($TaskType->get('id'),array(1,2,8,15,16,17,24));
		try
		{
			if (!$TaskType || !$TaskType->isValid())
				throw new Exception(_('Task type is not valid'));
			$taskName = $TaskType->get('name').' Task';
			if ($Data && $Data->isValid())
			{
				// Error Checking
				if ($Data instanceof Host && $imagingTasks)
				{
					if(!$Data->getImage() || !$Data->getImage()->isValid())
						throw new Exception(_('You need to assign an image to the host'));
					if ($TaskType->isUpload() && $Data->getImage()->get('protected'))
						throw new Exception(_('You cannot upload to this image as it is currently protected'));
					if (!$Data->checkIfExist($TaskType->get('id')))
						throw new Exception(_('You must first upload an image to create a download task'));
				}
				else if ($Data instanceof Group && $imagingTasks)
				{
					if ($TaskType->isMulticast() && !$Data->doMembersHaveUniformImages())
						throw new Exception(_('Hosts do not contain the same image assignments'));
					unset($NoImage,$ImageExists,$Tasks);
					foreach((array)$Data->get('hosts') AS $Host)
					{
						if ($Host && $Host->isValid() && !$Host->get('pending'))
							$NoImage[] = !$Host->getImage() || !$Host->getImage()->isValid();
					}
					if (in_array(true,$NoImage))
						throw new Exception(_('One or more hosts do not have an image set'));
					foreach((array)$Data->get('hosts') AS $Host)
					{
						if ($Host && $Host->isValid() && !$Host->get('pending'))
							$ImageExists[] = !$Host->checkIfExist($TaskType->get('id'));
					}
					if (in_array(true,$ImageExists))
						throw new Exception(_('One or more hosts have an image that does not exist'));
					foreach((array)$Data->get('hosts') AS $Host)
					{
						if ($Host && $Host->isValid() && $Host->get('task') && $Host->get('task')->isValid())
							$Tasks[] = $Host->get('task');
					}
					if (count($Tasks) > 0)
						throw new Exception(_('One or more hosts are currently in a task'));
				}
				if ($TaskType->get('id') == 11 && !trim($_REQUEST['account']))
					throw New Exception(_('Password reset requires a user account to reset'));
				try
				{
					if ($_REQUEST['scheduleType'] == 'instant')
					{
						if ($Data instanceof Group)
						{
							foreach((array)$Data->get('hosts') AS $Host)
							{
								if ($Host && $Host->isValid() && !$Host->get('pending'))
								{
									if ($Host->createImagePackage($TaskType->get('id'),$taskName,$enableShutdown,$enableDebug,$enableSnapins,$Data instanceof Group,$_SESSION['FOG_USERNAME'],trim($_REQUEST['account'])))
										$success[] = sprintf('<li>%s &ndash; %s</li>',$Host->get('name'),$Host->getImage()->get('name'));
								}
							}
						}
						else if ($Data instanceof Host)
						{
							if ($Data->createImagePackage($TaskType->get('id'),$taskName,$enableShutdown,$enableDebug,$enableSnapins,$Data instanceof Group,$_SESSION['FOG_USERNAME'],trim($_REQUEST['account'])))
								$success[] = sprintf('<li>%s &ndash; %s</li>',$Data->get('name'),$Data->getImage()->get('name'));
						}
					}
					else if ($_REQUEST['scheduleType'] == 'single')
					{
						if ($scheduleDeployTime < $this->nice_date())
							throw new Exception(sprintf('%s<br>%s: %s',_('Scheduled date is in the past'),_('Date'),$scheduleDeployTime->format('Y/d/m H:i')));
						$ScheduledTask = new ScheduledTask(array(
							'taskType' => $TaskType->get('id'),
							'name' => $taskName,
							'hostID' => $Data->get('id'),
							'shutdown' => $enableShutdown,
							'other2' => $enableSnapins,
							'isGroupTask' => $Data instanceof Group,
							'type' => 'S',
							'scheduleTime' => $scheduleDeployTime->getTimestamp(),
							'other3' => $this->FOGUser->get('name'),
						));
					}
					else if ($_REQUEST['scheduleType'] == 'cron')
					{
						$ScheduledTask = new ScheduledTask(array(
							'taskType' => $TaskType->get('id'),
							'name' => $taskName,
							'hostID' => $Data->get('id'),
							'shutdown' => $enableShutdown,
							'other2' => $enableSnapins,
							'isGroupTask' => $Data instanceof Group,
							'type' => 'C',
							'other3' => $this->FOGUser->get('name'),
							'minute' => $_REQUEST['scheduleCronMin'],
							'hour' => $_REQUEST['scheduleCronHour'],
							'dayOfMonth' => $_REQUEST['scheduleCronDOM'],
							'month' => $_REQUEST['scheduleCronMonth'],
							'dayOfWeek' => $_REQUEST['scheduleCronDOW'],
						));
					}
					if ($ScheduledTask && $ScheduledTask->save())
					{
						if ($Data instanceof Group)
						{
							foreach((array)$Data->get('hosts') AS $Host)
							{
								if ($Host && $Host->isValid() && !$Host->get('pending'))
									$success[] = sprintf('<li>%s &ndash; %s</li>',$Host->get('name'),$Host->getImage()->get('name'));
							}
						}
						else if ($Data instanceof Host)
						{
							if ($Data && $Data->isValid() && !$Data->get('pending'))
								$success[] = sprintf('<li>%s &ndash; %s</li>',$Data->get('name'),$Data->getImage()->get('name'));
						}
					}
				}
				catch (Exception $e)
				{
					$error[] = sprintf('%s: %s',($Data instanceof Group ? $Host->get('name') : $Data->get('name')),$e->getMessage());
				}
			}
			// Failure
			if (count($error))
				throw new Exception('<ul><li>'.implode('</li><li>',$error).'</li></ul>');
		}
		catch (Exception $e)
		{
			// Failure
			printf('<div class="task-start-failed"><p>%s</p><p>%s</p></div>',_('Failed to create deployment tasking for the following hosts'),$e->getMessage());
		}
		
		// Success
		if (count($success))
		{
			printf('<div class="task-start-ok"><p>%s</p><p>%s%s%s</p></div>',
				sprintf(_('Successfully created tasks for deployment to the following Hosts'),($Data instanceof Group ? $Host->getImage()->get('name') : $Data->getImage()->get('name'))),
				($_REQUEST['scheduleType'] == 'cron' ? sprintf('%s: %s',_('Cron Schedule'),implode(' ',array($_REQUEST['scheduleCronMin'],$_REQUEST['scheduleCronHour'],$_REQUEST['scheduleCronDOM'],$_REQUEST['scheduleCronMonth'],$_REQUEST['scheduleCronDOW']))) : ''),
				($_REQUEST['scheduleType'] == 'single' ? sprintf('%s: %s',_('Scheduled to start at'),$scheduleDeployTime->format('Y/m/d H:i')) : ''),
				(count($success) ? sprintf('<ul>%s</ul>',implode('',$success)) : '')
			);
		}
	}
	/**
	* basictasksOptions
	*/
	public function basictasksOptions()
	{
		$ClassType = ucfirst($this->node);
		$Data = new $ClassType($_REQUEST['id']);
		unset($this->headerData);
		$this->templates = array(
			'<a href="?node=${node}&sub=${sub}&id=${'.$this->node.'_id}${task_type}"><img src="images/${task_icon}" /><br/>${task_name}</a>',
			'${task_desc}',
		);
		$this->attributes = array(
			array('class' => 'l'),
			array('style' => 'padding-left: 20px'),
		);
		printf("\n\t\t\t<!-- Basic Tasks -->");
		printf("\n\t\t\t%s",'<div id="'.$this->node.'-tasks" class="organic-tabs-hidden">');
		printf("\n\t\t\t<h2>%s</h2>",_($ClassType.' Tasks'));
		// Find TaskTypes
		$TaskTypes = $this->getClass('TaskTypeManager')->find(array('access' => array('both',$this->node),'isAdvanced' => 0), 'AND', 'id');
		// Iterate -> Print
		foreach((array)$TaskTypes AS $TaskType)
		{
			if ($TaskType && $TaskType->isValid())
			{
				$this->data[] = array(
					'node' => $this->node,
					'sub' => 'deploy',
					$this->node.'_id' => $Data->get('id'),
					'task_type' => '&type='.$TaskType->get('id'),
					'task_icon' => $TaskType->get('icon'),
					'task_name' => $TaskType->get('name'),
					'task_desc' => $TaskType->get('description'),
				);
			}
		}
		$this->data[] = array(
			'node' => $this->node,
			'sub' => 'edit',
			$this->node.'_id' => $Data->get('id'),
			'task_type' => '#'.$this->node.'-tasks" class="advanced-tasks-link',
			'task_icon' => 'host-advanced.png',
			'task_name' => _('Advanced'),
			'task_desc' => _('View advanced tasks for this').' '._($this->node),
		);
		// Hook
		$this->HookManager->processEvent(strtoupper($ClassType).'_EDIT_TASKS', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' &$this->attributes));
		// Output
		$this->render();
		unset($this->data);
		printf("\n\t\t\t%s",'<div id="advanced-tasks" class="hidden">');
		printf("\n\t\t\t<h2>%s</h2>",_('Advanced Actions'));
		// Find TaskTypes
		$TaskTypes = $this->getClass('TaskTypeManager')->find(array('access' => array('both',$this->node),'isAdvanced' => 1), 'AND', 'id');
		// Iterate -> Print
		foreach((array)$TaskTypes AS $TaskType)
		{
			if ($TaskType && $TaskType->isValid())
			{
				$this->data[] = array(
					'node' => $this->node,
					'sub' => 'deploy',
					$this->node.'_id' => $Data->get('id'),
					'task_type' => '&type='.$TaskType->get('id'),
					'task_icon' => $TaskType->get('icon'),
					'task_name' => $TaskType->get('name'),
					'task_desc' => $TaskType->get('description'),
				);
			}
		}
		// Hook
		$this->HookManager->processEvent(strtoupper($this->node).'_DATA_ADV', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' &$this->attributes));
		// Output
		$this->render();
		printf('</div>');
		printf("\n\t\t\t</div>");
		unset($this->data);
	}
	/**
	* adFieldsToDisplay()
	*/
	public function adFieldsToDisplay()
	{
		$ClassType = ucfirst($this->node);
		$Data = new $ClassType($_REQUEST['id']);
		$OUs = explode('|',$this->FOGCore->getSetting('FOG_AD_DEFAULT_OU'));
		foreach((array)$OUs AS $OU)
			$OUOptions[] = $OU;
		$OUOPtions = array_filter($OUOptions);
		if (count($OUOptions) > 1)
		{
			$OUs = array_unique((array)$OUOptions);
			$optionOU[] = '<option value=""> - '._('Please select an option').' - </option>';
			foreach($OUs AS $OU)
			{
				$opt = preg_match('#;#i',$OU) ? preg_replace('#;#i','',$OU) : $OU;
				$optionOU[] = '<option value="'.$opt.'" '.($Data instanceof Host && $Data->isValid() && $Data->get('ADOU') == $opt ? 'selected="selected"' : (preg_match('#;#i',$OU) ? 'selected="selected"' : '')).'>'.$opt.'</option>';
			}
			$OUOptions = '<select id="adOU" class="smaller" name="ou">'.implode("\n\t\t\t",$optionOU)."\n\t\t\t".'</select>';
		}
		else
			$OUOptions = '<input id="adOU" class="smaller" type="text" name="ou" value="${ad_ou}" autocomplete="off" />';
		printf("\n\t\t\t<!-- Active Directory -->");
		$this->templates = array(
			'${field}',
			'${input}',
		);
		$this->attributes = array(
			array(),
			array(),
		);
		$fields = array(
			'<input style="display:none" type="text" name="fakeusernameremembered"/>' => '<input style="display:none" type="password" name="fakepasswordremembered"/>',
			_('Join Domain after image task') => '<input id="adEnabled" type="checkbox" name="domain"${domainon} />',
			_('Domain name') => '<input id="adDomain" class="smaller" type="text" name="domainname" value="${host_dom}" autocomplete="off" />',
			_('Organizational Unit').'<br /><span class="lightColor">('._('Blank for default').')</span>' => '${host_ou}',
			_('Domain Username') => '<input id="adUsername" class="smaller" type="text"name="domainuser" value="${host_aduser}" autocomplete="off" />',
			_('Domain Password').'<br />('._('Must be encrypted').')' => '<input id="adPassword" class="smaller" type="password" name="domainpassword" value="${host_adpass}" autocomplete="off" />',
			'<input type="hidden" name="updatead" value="1" />' => '<input type="submit"value="'._('Update').'" />',
		);
		printf("\n\t\t\t%s",'<div id="'.$this->node.'-active-directory" class="organic-tabs-hidden">');
		printf("\n\t\t\t%s",'<form method="post" action="'.$this->formAction.'&tab='.$this->node.'-active-directory">');
		printf("\n\t\t\t<h2>%s</h2>",_('Active Directory'));
		foreach((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'domainon' => $Data instanceof Host && $Data->get('useAD') ? 'checked="checked"' : '',
				'host_dom' => $Data instanceof Host ? $Data->get('ADDomain') : $_REQUEST['domainname'],
				'host_ou' => $OUOptions,
				'ad_ou' => $Data instanceof Host ? $Data->get('ADOU') : $_REQUEST['ou'],
				'host_aduser' => $Data instanceof Host ? $Data->get('ADUser') : $_REQUEST['domainuser'],
				'host_adpass' => $Data instanceof Host ? $Data->get('ADPass') : $_REQUEST['domainpassword'],
			);
		}
		// Hook
		$this->HookManager->processEvent(strtoupper($ClassType).'_EDIT_AD', array('headerData' => &$this->headerData,'data' => &$this->data,'attributes' => &$this->attributes,'templates' => &$this->templates));
		// Output
		$this->render();
		unset($this->data);
		printf('</form>');
		printf('</div>');
	}

	// Delete function, this should be more or less the same for all pages.
	public function delete()
	{
		// Find
		$ClassType = ucfirst($this->node);
		$Data = new $ClassType($_REQUEST['id']);
		// Title
		$this->title = sprintf('%s: %s',_('Remove'),$Data->get('name'));
		// Header Data
		unset($this->headerData);
		// Attributes
		$this->attributes = array(
			array(),
			array(),
		);
		// Templates
		$this->templates = array(
			'${field}',
			'${input}',
		);
		$fields = array(
			sprintf('%s <b>%s</b>',_('Please confirm you want to delete'),addslashes($Data->get('name'))) => '&nbsp;',
			($Data instanceof Group ? _('Delete all hosts within group') : null) => ($Data instanceof Group ? '<input type="checkbox" name="massDelHosts" value="1" />' : null),
			($Data instanceof Image || $Data instanceof Snapin ? _('Delete file data') : null) => ($Data instanceof Image || $Data instanceof Snapin ? '<input type="checkbox" name="andFile" id="andFile" value="1" />' : null),
			'&nbsp;' => '<input type="submit" value="${label}" />',
		);
		$fields = array_filter($fields);
		foreach($fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'label' => addslashes($this->title),
			);
		}
		// Hook
		$this->HookManager->processEvent(strtoupper($this->node).'_DEL', array($ClassType => &$Data));
		printf('%s<form method="post" action="%s" class="c">',"\n\t\t\t",$this->formAction);
		$this->render();
		printf('</form>');
	}
	public function delete_post()
	{
		// Find
		$ClassType = ucfirst($this->node);
		$Data = new $ClassType($_REQUEST['id']);
		// Hook
		$this->HookManager->processEvent(strtoupper($this->node).'_DEL_POST', array($ClassType => &$Data));
		// POST
		try
		{
			if ($Data instanceof Group)
			{
				if ($_REQUEST['delHostConfirm'] == '1')
				{
					foreach((array)$Data->get('hosts') AS $Host)
					{
						if ($Host && $Host->isValid())
							$Host->destroy();
					}
				}
				// Remove hosts first
				if (isset($_REQUEST['massDelHosts']))
					$this->FOGCore->redirect('?node=group&sub=delete_hosts&id='.$Data->get('id'));
			}
			if ($Data instanceof Image || $Data instanceof Snapin)
			{
				if ($Data instanceof Image && $Data->get('protected'))
					throw new Exception(_('Image is protected, removal not allowed'));
				if (isset($_REQUEST['andFile']))
					$Data->deleteFile();
			}
			// Error checking
			if (!$Data->destroy())
				throw new Exception(_('Failed to destroy'));
			// Hook
			$this->HookManager->processEvent(strtoupper($this->node).'_DELETE_SUCCESS', array($ClassType => &$Data));
			// Log History event
			$this->FOGCore->logHistory($ClassType.' deleted: ID: '.$Data->get('id').', Name:'.$Data->get('name'));
			// Set session message
			$this->FOGCore->setMessage($ClassType.' deleted: '.$Data->get('name'));
			// Reset request
			$this->resetRequest();
			// Redirect
			$this->FOGCore->redirect('?node='.$this->node);
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent(strtoupper($this->node).'_DELETE_FAIL', array($ClassType => &$Data));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s %s: ID: %s, Name: %s',_($ClassType), _('delete failed'),$Data->get('id'),$Data->get('name')));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect
			$this->FOGCore->redirect($this->formAction);
		}
	}
}
