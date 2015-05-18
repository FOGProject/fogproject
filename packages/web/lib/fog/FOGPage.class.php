<?php
abstract class FOGPage extends FOGBase {
	/** $name the name of the page */
	public $name = '';
	/** $node the node for the page also in url */
	public $node = '';
	/** $id name of the ID variable used in Page */
	public $id = 'id';
	/** $menu TODO: Finish, should contain this pages menu */
	public $menu = array();
	/** $subMenu TODO: Finish, should contain this pages sub menu */
	public $subMenu = array();
	/** $notes TODO: Finish, should contain the elements we want for notes */
	public $notes = array();
	/** $titleEnabled sets if the title is enabled for this page */
	public $titleEnabled = true;
	/** $title sets the title of this page */
	public $title;
	// Render engine
	/** $headerData the header row for tables */
	public $headerData = array();
	/** $data the data to display in the tables */
	public $data = array();
	/** $templates the template engine of what to replace */
	public $templates = array();
	/** $attirbutes the attributes of the table rows */
	public $attributes = array();
	/** $searchFormURL if set, allows a search page */
	public $searchFormURL = '';
	/** $wrapper this is the wrapper for the tables cells */
	private $wrapper = 'td';
	/** $result this is the result of the items as parsed */
	private $result;
	// Method & Form
	/** $post sets up if the form is a POST request */
	protected $post = false;
	/** $ajax sets up if the form is an AJAX request */
	protected $ajax = false;
	/** $request sets up the total of all post/get vars */
	protected $request = array();
	/** $formAction sets up the form action based on current items */
	protected $formAction;
	/** $formPostAction sets up the form action after post */
	protected $formPostAction;
	/** $childClass the child class of the page calling */
	protected $childClass;
	// __construct
	/** __construct() initiates the constructor of the pages */
	public function __construct($name = '') {
		$this->debug = false;
		$this->info = false;
		parent::__construct();
		if (!empty($name)) $this->name = $name;
		$this->title = $this->name;
		$this->delformat = "?node={$this->node}&sub=delete&{$this->id}={$_REQUEST[id]}";
		$this->linkformat = "?node={$this->node}&sub=edit&{$this->id}={$_REQUEST[id]}";
		$this->membership = "?node={$this->node}&sub=membership&{$this->id}={$_REQUEST[id]}";
		$this->request = $this->REQUEST = $this->DB->sanitize($_REQUEST);
		$this->REQUEST['id'] = $_REQUEST[$this->id];
		$this->request['id'] = $_REQUEST[$this->id];
		$this->post = $this->isPOSTRequest();
		$this->ajax = $this->isAJAXRequest();
		$this->childClass = preg_replace('#ManagementPage#', '', preg_replace('#Mobile#','',get_class($this)));
		$this->menu = array(
			'search' => $this->foglang['NewSearch'],
			'list' => sprintf($this->foglang['ListAll'],_($this->childClass.'s')),
			'add' => sprintf($this->foglang['CreateNew'],_($this->childClass)),
		);
		$this->formAction = sprintf('%s?%s', $_SERVER['PHP_SELF'], $_SERVER['QUERY_STRING']);
		$this->HookManager->processEvent('SEARCH_PAGES',array('searchPages' => &$this->searchPages));
		$this->HookManager->processEvent('SUB_MENULINK_DATA',array('menu' => &$this->menu,'submenu' => &$this->subMenu,'id' => &$this->id,'notes' => &$this->notes));
	}
	/** index() the default index for all pages that extend this class */
	public function index() {
		printf('Index page of: %s%s', get_class($this), (count($args) ? ', Arguments = ' . implode(', ', array_map(create_function('$key, $value', 'return $key." : ".$value;'), array_keys($args), array_values($args))) : ''));
	}
	/** set() sets the sent key and value for the page
	  * @param $key the key to set
	  * @param $value the value to set
	  * @return the set class with items set
	  */
	public function set($key, $value) {
		$this->$key = $value;
		return $this;
	}
	/** get() gets the data from the sent key
	  * @return the value of the key
	  */
	public function get($key) {return $this->$key;}
	/** __toString() magic function that just returns the data
	  * @return void
	  */
	public function __toString() {$this->process();}
	/** render() just prints the data
	  * @return void
	  */
	public function render() {print $this->process();}
	/** process() build the relevant html for the page
	  * @return false or the result
	  */
	public function process() {
		try {
			$defaultScreen = strtolower($_SESSION['FOG_VIEW_DEFAULT_SCREEN']);
			$result = '';
			// Error checking
			if (!count($this->templates)) throw new Exception('Requires templates to process');
			// Is AJAX Request?
			if ($this->isAJAXRequest()) {
				// JSON output
				$result = @json_encode(array(
					'data'		=> $this->data,
					'templates'	=> $this->templates,
					'headerData' => $this->headerData,
					'title' => $this->title,
					'attributes'	=> $this->attributes,
					'form' => $this->form,
				));
			} else {
				$isMobile = preg_match('#/mobile/#',$_SERVER['PHP_SELF']);
				// HTML output
				if ($this->searchFormURL) {
					$result .= sprintf('<form method="post" action="%s" id="search-wrapper"><input id="%s-search" class="search-input placeholder" type="text" value="" placeholder="%s" autocomplete="off" %s/><input id="%s-search-submit" class="search-submit" type="%s" value="%s"/></form>',
						$this->searchFormURL,
						(substr($this->node, -1) == 's' ? substr($this->node, 0, -1) : $this->node),
						sprintf('%s %s', ucwords((substr($this->node, -1) == 's' ? substr($this->node, 0, -1) : $this->node)), $this->foglang['Search']),
						$isMobile ? 'name="host-search"' : '',
						(substr($this->node, -1) == 's' ? substr($this->node, 0, -1) : $this->node),
						$isMobile ? 'submit' : 'button',
						$isMobile ? $this->foglang['Search'] : ''
					);
				}
				if ($this->form) $result .= sprintf($this->form);
				// Table -> Header Row
				$result .= sprintf('<table width="%s" cellpadding="0" cellspacing="0" border="0" id="%s"><thead><tr class="header">%s</tr></thead><tbody>',
					'100%',
					($this->searchFormURL ? 'search-content' : 'active-tasks'),
					$this->buildHeaderRow()
				);
				if (!count($this->data)) {
					// No data found
					$result .= sprintf('<tr><td colspan="%s" class="no-active-tasks">%s</td></tr></tbody></table>',
						count($this->templates),
						($this->data['error'] ? (is_array($this->data['error']) ? '<p>' . implode('</p><p>', $this->data['error']) . '</p>' : $this->data['error']) : $this->foglang['NoResults'])
					);
				} else {
					foreach ($this->data AS $rowData) {
						$result .= sprintf('<tr id="%s-%s"%s>%s</tr>',
							strtolower($this->childClass),
							$rowData['id'],
							((++$i % 2) ? ' class="alt1"' : ((!$_REQUEST['sub'] && $defaultScreen == 'list') || in_array($_REQUEST['sub'],array('list','search')) ? ' class="alt2"' : '')),
							$this->buildRow($rowData)
						);
					}
				}
				$result .= '</tbody></table>';
				if (count($this->data) || (($defaultScreen == 'search' && $_REQUEST['sub'] == 'search') || !$_REQUEST['sub'])) {
					if (in_array($_REQUEST['node'],$this->searchPages) && !$isMobile && (in_array($_REQUEST['sub'],array('list','search')) || !$_REQUEST['sub'])) {
						if ($this->childClass == 'Host') $result .= '<form method="post" action="'.sprintf('?node=%s&sub=save_group', $this->node).'" id="action-box"><input type="hidden" name="hostIDArray" value="" autocomplete="off" /><p><label for="group_new">'._('Create new group').'</label><input type="text" name="group_new" id="group_new" autocomplete="off" /></p><p class="c">'._('OR').'</p><p><label for="group">'._('Add to group').'</label>'.$this->getClass('GroupManager')->buildSelectBox().'</p><p class="c"><input type="submit" value="'._("Process Group Changes").'" /></p></form>';
						$result .= '<form method="post" class="c" id="action-boxdel" action="'.sprintf('?node=%s&sub=deletemulti',$this->node).'"><p>'._('Delete all selected items').'</p><input type="hidden" name="'.strtolower($this->childClass).'IDArray" value=""autocomplete="off" /><input type="submit" value="'._('Delete all selected '.strtolower($this->childClass).'s').'?"/></form>';
					}
				}
			}
			// Return output
			return $result;
		} catch (Exception $e) {
			return $e->getMessage();
		}
	}
	private function setAtts() {
		foreach((array)$this->attributes AS $i => $vals) {
			foreach((array)$vals AS $name => $val) $this->atts[$i] .= sprintf(' %s="%s" ',$name,($this->dataFind ? preg_replace($this->dataFind,$this->dataReplace,$val) : $val));
		}
	}
	/** buildHeaderRow() builds the header row of the tables
	  * @return the results as parsed
	  */
	public function buildHeaderRow() {
		unset($this->atts);
		$this->setAtts();
		// Loop data
		if ($this->headerData) {
			foreach ($this->headerData AS $i => $content) {
				// Push into results array
				$result .= sprintf(
					'<%s%s>%s</%s>',
					$this->wrapper,
					($this->atts[$i] ? $this->atts[$i] : ''),
					$content,
					$this->wrapper
				);
			}
			// Return result
			return $result;
		}
	}
	/** replaceNeeds() sets the template data to replace
	  * @param $data the data to enact upon
	  * @return array of the find / replace items.
	  */
	private function replaceNeeds($data) {
		unset($this->dataFind,$this->dataReplace);
		$urlvars = array('node' => $GLOBALS['node'],'sub' => $GLOBALS['sub'],'tab' => $GLOBALS['tab']);
		foreach ((array)array_merge($urlvars,$data) AS $name => $val) {
			$this->dataFind[] = '#\$\{'.$name.'\}#';
			$this->dataReplace[] = (is_object($val) ? $val->__toString() : (is_string($val) || is_numeric($val) ? $val : ''));
		}
	}
	/** buildRow() builds the row of the tables
	  * @param $data the data to build upon
	  * @return the results as parsed
	  */
	public function buildRow($data) {
		$this->replaceNeeds($data);
		// Loop template data
		foreach ($this->templates AS $i => $template) {
			// Replace variables in template with data -> wrap in $this->wrapper -> push into $result
			$result .= sprintf(
				'<%s%s>%s</%s>',
				$this->wrapper,
				($this->atts[$i] ? $this->atts[$i] : ''),
				preg_replace($this->dataFind,$this->dataReplace,$template),
				$this->wrapper
			);
		}
		// Return result
		return $result;
	}
	/** deploy() build the tasking output
	  * @return void
	  */
	public function deploy() {
		$Data = $this->obj;
		$TaskType = new TaskType(($_REQUEST['type'] ? $_REQUEST['type'] : 1));
		// Title
		$this->title = sprintf('%s %s %s %s',_('Create'),$TaskType->get('name'),_('task for'),$Data->get('name'));
		// Deploy
		printf('%s%s%s','<p class="c"><b>',_('Are you sure you wish to deploy task to these machines'),'</b></p>');
		printf('<form method="post" action="%s" id="deploy-container">',$this->formAction);
		print '<div class="confirm-message">';
		if ($TaskType->get('id') == 13) {
			printf('<center><p>%s</p>',_('Please select the snapin you want to deploy'));
			if ($Data instanceof Host) {
				foreach((array)$Data->get('snapins') AS $Snapin) {
					if ($Snapin && $Snapin->isValid()) $optionSnapin .= sprintf('<option value="%s">%s - (%s)</option>',$Snapin->get('id'),$Snapin->get('name'),$Snapin->get('id'));
				}
				if ($optionSnapin) printf('<select name="snapin">%s</select></center>',$optionSnapin);
				else printf('%s</center>',_('No snapins associated'));
			}
			if ($Data instanceof Group) printf($this->getClass('SnapinManager')->buildSelectBox().'</center>');
		}
		printf("%s",'<div class="advanced-settings">');
		printf("<h2>%s</h2>",_('Advanced Settings'));
		printf("%s%s%s <u>%s</u> %s%s",'<p class="hideFromDebug">','<input type="checkbox" name="shutdown" id="shutdown" value="1" autocomplete="off"><label for="shutdown">',_('Schedule'),_('Shutdown'),_('after task completion'),'</label></p>');
		if (!$TaskType->isDebug() && $TaskType->get('id') != 11) {
			printf("%s%s%s",'<p><input type="checkbox" name="isDebugTask" id="isDebugTask" autocomplete="off" /><label for="isDebugTask">',_('Schedule task as a debug task'),'</label></p>');
			printf("%s%s %s%s%s",'<p><input type="radio" name="scheduleType" id="scheduleInstant" value="instant" autocomplete="off" checked/><label for="scheduleInstant">',_('Schedule '),'<u>',_('Instant Deployment'),'</u></label></p>');
			printf("%s%s %s%s%s",'<p class="hideFromDebug"><input type="radio" name="scheduleType" id="scheduleSingle" value="single" autocomplete="off" /><label for="scheduleSingle">',_('Schedule '),'<u>',_('Delayed Deployment'),'</u></label></p>');
			printf("%s",'<p class="hidden hideFromDebug" id="singleOptions"><input type="text" name="scheduleSingleTime" id="scheduleSingleTime" autocomplete="off" /></p>');
			printf("%s%s %s%s%s",'<p class="hideFromDebug"><input type="radio" name="scheduleType" id="scheduleCron" value="cron" autocomplete="off"><label for="scheduleCron">',_('Schedule'),'<u>',_('Cron-style Deployment'),'</u></label></p>');
			printf("%s",'<p class="hidden hideFromDebug" id="cronOptions">');
			printf("%s",'<input type="text" name="scheduleCronMin" id="scheduleCronMin" placeholder="min" autocomplete="off" />');
			printf("%s",'<input type="text" name="scheduleCronHour" id="scheduleCronHour" placeholder="hour" autocomplete="off" />');
			printf("%s",'<input type="text" name="scheduleCronDOM" id="scheduleCronDOM" placeholder="dom" autocomplete="off" />');
			printf("%s",'<input type="text" name="scheduleCronMonth" id="scheduleCronMonth" placeholder="month" autocomplete="off" />');
			printf("%s",'<input type="text" name="scheduleCronDOW" id="scheduleCronDOW" placeholder="dow" autocomplete="off" /></p>');
		} else if ($TaskType->isDebug()) printf("%s%s %s%s%s",'<p><input type="radio" name="scheduleType" id="scheduleInstant" value="instant" autocomplete="off" checked/><label for="scheduleInstant">',_('Schedule '),'<u>',_('Instant Deployment'),'</u></label></p>');
		if ($TaskType->get('id') == 11) {
			printf("<p>%s</p>",_('Which account would you like to reset the pasword for'));
			printf("%s",'<input type="text" name="account" value="Administrator" />');
		}
		print '</div></div><h2>'._('Hosts in Task').'</h2>';
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
		if ($Data instanceof Host) {
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
		if ($Data instanceof Group) {
			foreach($Data->get('hosts') AS $Host) {
				if ($Host && $Host->isValid()) {
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
		$this->HookManager->processEvent(strtoupper($this->childClass.'_DEPLOY'),array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates,'attributes' => &$this->attributes));
		// Output
		$this->render();
		printf('%s%s%s','<p class="c"><input type="submit" value="',$this->title,'" /></p>');
		print '</form>';
	}
	/** deploy_post() actually create the deployment task
	  * @return void
	  */
	public function deploy_post() {
		$Data = $this->obj;
		$TaskType = new TaskType($_REQUEST['type']);
		$Snapin = $_REQUEST['snapin'] ? new Snapin($_REQUEST['snapin']) : -1;
		$enableShutdown = $_REQUEST['shutdown'] ? true : false;
		$enableSnapins = $TaskType->get('id') != '17' ? ($Snapin instanceof Snapin && $Snapin->isValid() ? $Snapin->get('id') : $Snapin) : false;
		$enableDebug = $_REQUEST['debug'] == 'true' || $_REQUEST['isDebugTask'] ? true : false;
		$scheduleDeployTime = $this->nice_date($_REQUEST['scheduleSingleTime']);
		$imagingTasks = in_array($TaskType->get('id'),array(1,2,8,15,16,17,24));
		try {
			if (!$TaskType || !$TaskType->isValid()) throw new Exception(_('Task type is not valid'));
			$taskName = $TaskType->get('name').' Task';
			if ($Data && $Data->isValid()) {
				// Error Checking
				if ($Data instanceof Host && $imagingTasks) {
					if(!$Data->getImage() || !$Data->getImage()->isValid()) throw new Exception(_('You need to assign an image to the host'));
					if ($TaskType->isUpload() && $Data->getImage()->get('protected')) throw new Exception(_('You cannot upload to this image as it is currently protected'));
					if (!$Data->checkIfExist($TaskType->get('id'))) throw new Exception(_('You must first upload an image to create a download task'));
				} else if ($Data instanceof Group && $imagingTasks) {
					if ($TaskType->isMulticast() && !$Data->doMembersHaveUniformImages()) throw new Exception(_('Hosts do not contain the same image assignments'));
					unset($NoImage,$ImageExists,$Tasks);
					foreach((array)$Data->get('hosts') AS $Host) {
						if ($Host && $Host->isValid() && !$Host->get('pending')) $NoImage[] = !$Host->getImage() || !$Host->getImage()->isValid();
					}
					if (in_array(true,$NoImage)) throw new Exception(_('One or more hosts do not have an image set'));
					foreach((array)$Data->get('hosts') AS $Host) {
						if ($Host && $Host->isValid() && !$Host->get('pending')) $ImageExists[] = !$Host->checkIfExist($TaskType->get('id'));
					}
					if (in_array(true,$ImageExists)) throw new Exception(_('One or more hosts have an image that does not exist'));
					foreach((array)$Data->get('hosts') AS $Host) {
						if ($Host && $Host->isValid() && $Host->get('task') && $Host->get('task')->isValid()) $Tasks[] = $Host->get('task');
					}
					if (count($Tasks) > 0) throw new Exception(_('One or more hosts are currently in a task'));
				}
				if ($TaskType->get('id') == 11 && !trim($_REQUEST['account'])) throw New Exception(_('Password reset requires a user account to reset'));
				try {
					if ($_REQUEST['scheduleType'] == 'instant') {
						if ($Data instanceof Group) {
							foreach((array)$Data->get('hosts') AS $Host) {
								if ($Host && $Host->isValid() && !$Host->get('pending')) {
									if ($Host->createImagePackage($TaskType->get('id'),$taskName,$enableShutdown,$enableDebug,$enableSnapins,$Data instanceof Group,$_SESSION['FOG_USERNAME'],trim($_REQUEST['account']))) $success[] = sprintf('<li>%s &ndash; %s</li>',$Host->get('name'),$Host->getImage()->get('name'));
								}
							}
						} else if ($Data instanceof Host) {
							if ($Data->createImagePackage($TaskType->get('id'),$taskName,$enableShutdown,$enableDebug,$enableSnapins,$Data instanceof Group,$_SESSION['FOG_USERNAME'],trim($_REQUEST['account']))) $success[] = sprintf('<li>%s &ndash; %s</li>',$Data->get('name'),$Data->getImage()->get('name'));
						}
					} else if ($_REQUEST['scheduleType'] == 'single') {
						if ($scheduleDeployTime < $this->nice_date()) throw new Exception(sprintf('%s<br>%s: %s',_('Scheduled date is in the past'),_('Date'),$scheduleDeployTime->format('Y/d/m H:i')));
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
					} else if ($_REQUEST['scheduleType'] == 'cron') {
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
					if ($ScheduledTask && $ScheduledTask->save()) {
						if ($Data instanceof Group) {
							foreach((array)$Data->get('hosts') AS $Host) {
								if ($Host && $Host->isValid() && !$Host->get('pending')) $success[] = sprintf('<li>%s &ndash; %s</li>',$Host->get('name'),$Host->getImage()->get('name'));
							}
						} else if ($Data instanceof Host) {
							if ($Data && $Data->isValid() && !$Data->get('pending')) $success[] = sprintf('<li>%s &ndash; %s</li>',$Data->get('name'),$Data->getImage()->get('name'));
						}
					}
				} catch (Exception $e) {
					$error[] = sprintf('%s: %s',($Data instanceof Group ? $Host->get('name') : $Data->get('name')),$e->getMessage());
				}
			}
			// Failure
			if (count($error)) throw new Exception('<ul><li>'.implode('</li><li>',$error).'</li></ul>');
		} catch (Exception $e) {
			// Failure
			printf('<div class="task-start-failed"><p>%s</p><p>%s</p></div>',_('Failed to create deployment tasking for the following hosts'),$e->getMessage());
		}
		// Success
		if (count($success)) {
			printf('<div class="task-start-ok"><p>%s</p><p>%s%s%s</p></div>',
				sprintf(_('Successfully created tasks for deployment to the following Hosts'),($Data instanceof Group ? $Host->getImage()->get('name') : $Data->getImage()->get('name'))),
				($_REQUEST['scheduleType'] == 'cron' ? sprintf('%s: %s',_('Cron Schedule'),implode(' ',array($_REQUEST['scheduleCronMin'],$_REQUEST['scheduleCronHour'],$_REQUEST['scheduleCronDOM'],$_REQUEST['scheduleCronMonth'],$_REQUEST['scheduleCronDOW']))) : ''),
				($_REQUEST['scheduleType'] == 'single' ? sprintf('%s: %s',_('Scheduled to start at'),$scheduleDeployTime->format('Y/m/d H:i')) : ''),
				(count($success) ? sprintf('<ul>%s</ul>',implode('',$success)) : '')
			);
		}
	}
	/** deletemulti() just presents the delete confirmation screen
	  * @return void
	  */
	public function deletemulti() {
		$this->title = _($this->childClass.'s to remove');
		unset($this->headerData);
		$this->attributes = array(
			array(),
		);
		$this->templates = array(
			'<a href="?node='.$this->node.'&sub=edit&id=${id}">${name}</a>',
		);
		foreach ((array)explode(',',$_REQUEST[strtolower($this->childClass).'IDArray']) AS $id) {
			$Obj = $this->getClass($this->childClass,$id);
			if ($Obj && $Obj->isValid() && !$Obj->get('protected')) {
				$this->data[] = array(
					'id' => $Obj->get('id'),
					'name' => $Obj->get('name'),
				);
				$_SESSION['delitems'][$this->node][] = $Obj->get('id');
				array_push($this->additional,'<p>'.$Obj->get('name').'</p>');
			}
		}
		if (count($_SESSION['delitems'])) {
			print '<div class="confirm-message">';
			print '<p>'._($this->childClass.'s to be removed').':</p>';
			$this->render();
			print '<form method="post" action="?node='.$this->node.'&sub=deleteconf">';
			print '<center><input type="submit" value="'._('Are you sure you wish to remove these items').'?"/></center>';
			print '</form>';
			print '</div>';
		} else {
			$this->FOGCore->setMessage('No items to delete<br/>None selected or item is protected');
			$this->FOGCore->redirect('?node='.$this->node);
		}
	}
	/** deleteconf() deletes the items after being confirmed.
	  * @return void
	  */
	public function deleteconf() {
		foreach($_SESSION['delitems'][$this->node] AS $id) {
			$Obj = $this->getClass($this->childClass,$id);
			if ($Obj && $Obj->isValid() && !$Obj->get('protected')) $Obj->destroy();
		}
		unset($_SESSION['delitems']);
		$this->FOGCore->setMessage('All selected items have been deleted');
		$this->FOGCore->redirect('?node='.$this->node);
	}
	/** basictasksOptions() builds the tasks list
	  * @return void
	  */
	public function basictasksOptions() {
		$Data = $this->obj;
		unset($this->headerData);
		$this->templates = array(
			'<a href="?node=${node}&sub=${sub}&id=${'.$this->node.'_id}${task_type}"><img src="'.$this->imagelink.'${task_icon}" /><br/>${task_name}</a>',
			'${task_desc}',
		);
		$this->attributes = array(
			array('class' => 'l'),
			array('style' => 'padding-left: 20px'),
		);
		printf("<!-- Basic Tasks -->");
		printf("%s",'<div id="'.$this->node.'-tasks" class="organic-tabs-hidden">');
		printf("<h2>%s</h2>",_($this->childClass.' Tasks'));
		// Find TaskTypes
		$TaskTypes = $this->getClass('TaskTypeManager')->find(array('access' => array('both',$this->node),'isAdvanced' => 0), 'AND', 'id');
		// Iterate -> Print
		foreach((array)$TaskTypes AS $TaskType) {
			if ($TaskType && $TaskType->isValid()) {
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
		$this->HookManager->processEvent(strtoupper($this->childClass).'_EDIT_TASKS', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' &$this->attributes));
		// Output
		$this->render();
		unset($this->data);
		printf("%s",'<div id="advanced-tasks" class="hidden">');
		printf("<h2>%s</h2>",_('Advanced Actions'));
		// Find TaskTypes
		$TaskTypes = $this->getClass('TaskTypeManager')->find(array('access' => array('both',$this->node),'isAdvanced' => 1), 'AND', 'id');
		// Iterate -> Print
		foreach((array)$TaskTypes AS $TaskType) {
			if ($TaskType && $TaskType->isValid()) {
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
		print '</div></div>';
		unset($this->data);
	}
	/** adFieldsToDisplay() display the Active Directory stuff 
	  * @return void
	  */
	public function adFieldsToDisplay() {
		$Data = $this->obj;
		$OUs = explode('|',$this->FOGCore->getSetting('FOG_AD_DEFAULT_OU'));
		foreach((array)$OUs AS $OU) $OUOptions[] = $OU;
		$OUOPtions = array_filter($OUOptions);
		if (count($OUOptions) > 1) {
			$OUs = array_unique((array)$OUOptions);
			$optionOU[] = '<option value=""> - '._('Please select an option').' - </option>';
			foreach($OUs AS $OU) {
				$opt = preg_match('#;#i',$OU) ? preg_replace('#;#i','',$OU) : $OU;
				$optionOU .= '<option value="'.$opt.'" '.($Data instanceof Host && $Data->isValid() && $Data->get('ADOU') == $opt ? 'selected="selected"' : (preg_match('#;#i',$OU) ? 'selected="selected"' : '')).'>'.$opt.'</option>';
			}
			$OUOptions = '<select id="adOU" class="smaller" name="ou">'.$optionOU.'</select>';
		} else $OUOptions = '<input id="adOU" class="smaller" type="text" name="ou" value="${ad_ou}" autocomplete="off" />';
		printf("<!-- Active Directory -->");
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
		print '<div id="'.$this->node.'-active-directory" class="organic-tabs-hidden">';
		printf("%s",'<form method="post" action="'.$this->formAction.'&tab='.$this->node.'-active-directory">');
		printf('<h2>%s<div id="adClear"></div></h2>',_('Active Directory'));
		foreach((array)$fields AS $field => $input) {
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'domainon' => $Data instanceof Host && $Data->get('useAD') ? 'checked' : '',
				'host_dom' => $Data instanceof Host ? $Data->get('ADDomain') : $_REQUEST['domainname'],
				'host_ou' => $OUOptions,
				'ad_ou' => $Data instanceof Host ? $Data->get('ADOU') : $_REQUEST['ou'],
				'host_aduser' => $Data instanceof Host ? $Data->get('ADUser') : $_REQUEST['domainuser'],
				'host_adpass' => $Data instanceof Host ? $Data->get('ADPass') : $_REQUEST['domainpassword'],
			);
		}
		// Hook
		$this->HookManager->processEvent(strtoupper($this->childClass).'_EDIT_AD', array('headerData' => &$this->headerData,'data' => &$this->data,'attributes' => &$this->attributes,'templates' => &$this->templates));
		// Output
		$this->render();
		unset($this->data);
		print '</form></div>';
	}
	/** adInfo() Returns AD Information to host/group
	  * @return void
	  */
	public function adInfo() {
		$Data = array(
			'domainname' => $this->FOGCore->getSetting('FOG_AD_DEFAULT_DOMAINNAME'),
			'ou' => $this->FOGCore->getSetting('FOG_AD_DEFAULT_OU'),
			'domainuser' => $this->FOGCore->getSetting('FOG_AD_DEFAULT_USER'),
			'domainpass' => $this->FOGCore->getSetting('FOG_NEW_CLIENT') ? $this->encryptpw($this->FOGCore->getSetting('FOG_AD_DEFAULT_PASSWORD')) : $this->FOGCore->getSetting('FOG_AD_DEFAULT_PASSWORD_LEGACY'),
		);
		if ($this->isAJAXRequest()) print json_encode($Data);
	}
	/** getPing() Performs the ping stuff.
	  * @return void
	  */
	public function getPing() {
		try {
			$ping = $_REQUEST['ping'];
			if (!$_SESSION['AllowAJAXTasks']) throw new Exception(_('FOG Session Invalid'));
			if (!$ping || $ping == 'undefined') throw new Exception(_('Undefined host to ping'));
			if (!HostManager::isHostnameSafe($ping)) throw new Exception(_('Invalid Hostname'));
			if (is_numeric($ping)) {
				$Host = Host($ping);
				$ping = $Host->get('name');
			}
			// Resolve Hostname
			$ip = gethostbyname($ping);
			if ($ip == $ping) throw new Exception(_('Unable to resolve hostname'));
			$result = $this->getClass('Ping',$ip)->execute();
			if ($result !== true) throw new Exception($result);
			$SendMe = true;
		} catch (Exception $e) {
			$SendMe = $e->getMessage();
		}
		if ($this->isAJAXRequest()) print $SendMe;
	}
	/** kernelfetch() the kernel fetcher stuff.
	  * @return void
	  */
	public function kernelfetch() {
		try {
			if (!$_SESSION['AllowAJAXTasks']) throw new Exception(_('FOG Session Invalid'));
			if ($_SESSION['allow_ajax_kdl'] && $_SESSION['dest-kernel-file'] && $_SESSION['tmp-kernel-file'] && $_SESSION['dl-kernel-file']) {
				if ($_REQUEST['msg'] == 'dl') {
					$fp = fopen($_SESSION['tmp-kernel-file'],'wb');
					if (!$fp) throw new Exception(_('Error: Failed to open temp file'));
					$this->FOGURLRequests->process($_SESSION['dl-kernel-file'],'GET',false,false,false,false,$fp);
					if (!file_exists($_SESSION['tmp-kernel-file'])) throw new Exception(_('Error: Failed to download kernel'));
					if (!filesize($_SESSION['tmp-kernel-file']) >  1048576) throw new Exception(_('Error: Download Failed: filesize').' - '.filesize($_SESSION['tmp-kernel-file']));
					$SendME = "##OK##";
				} else if ($_REQUEST['msg'] == 'tftp') {
					$destfile = $_SESSION['dest-kernel-file'];
					$tmpfile = $_SESSION['tmp-kernel-file'];
					unset($_SESSION['dest-kernel-file'],$_SESSION['tmp-kernel-file'],$_SESSION['dl-kernel-file']);
					$this->FOGFTP->set('host',$this->FOGCore->resolveHostname($this->FOGCore->getSetting('FOG_TFTP_HOST')))
								 ->set('username',trim($this->FOGCore->getSetting('FOG_TFTP_FTP_USERNAME')))
								 ->set('password',trim($this->FOGCore->getSetting('FOG_TFTP_FTP_PASSWORD')));
					if (!$this->FOGFTP->connect()) throw new Exception(_('Error: Unable to connect to tftp server'));
					$orig = rtrim($this->FOGCore->getSetting('FOG_TFTP_PXE_KERNEL_DIR'),'/');
					$backuppath = $orig.'/backup/';
					$orig .= '/'.$destfile;
					$backupfile = $backuppath.$destfile.$this->formatTime('','Ymd_His');
					$this->FOGFTP->mkdir($backuppath);
					$this->FOGFTP->rename($backupfile,$orig);
					if (!$this->FOGFTP->put($orig,$tmpfile,FTP_BINARY)) throw new Exception(_('Error: Failed to install new kernel'));
					@unlink($tmpfile);
					$SendME = "##OK##";
				}
			}
		} catch (Exception $e) {
			print $e->getMessage();
		}
		$this->FOGFTP->close();
		print $SendME;
	}
	/** loginInfo() login information getter
	  * @return void
	  */
	public function loginInfo() {
		$data = $this->FOGURLRequests->process(array('http://fogproject.org/globalusers','http://fogproject.org/version/version.php'),'GET');
		if (!$data[0]) $data['error-sites'] = _('Error contacting server');
		else $data['sites'] = $data[0];
		if (!$data[1]) $data['error-version'] = _('Error contacting server');
		else $data['version'] = $data[1];
		print json_encode($data);
	}
	/** getmacman() get the mac manager information
	  * @return void
	  */
	public function getmacman() {
		try {
			if (!$_SESSION['AllowAJAXTasks']) throw new Exception(_('FOG Session Invalid'));
			$prefix = $_REQUEST['prefix'];
			if (!$prefix && strlen($prefix) >= 8) throw new Exception(_('Unknown'));
			if (!$this->FOGCore->getMACLookupCount() > 0) throw new Exception('<a href="?node=about&sub=mac-list">'._('Load MAC Vendors').'</a>');
			$MAC = new MACAddress($prefix);
			if ($MAC && $MAC->isValid()) $Data = '<small>'.($mac == 'n/a' ? _('Unknown') : $this->FOGCore->getMACManufacturer($MAC->getMACPrefix())).'</small>';
		} catch (Exception $e) {
			$Data = $e->getMessage();
		}
		print $Data;
	}
	/** delete() Delete items from their respective pages.
	  * @return void
	  */
	public function delete() {
		// Find
		$Data = $this->obj;
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
		foreach($fields AS $field => $input) {
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'label' => addslashes($this->title),
			);
		}
		// Hook
		$this->HookManager->processEvent(strtoupper($this->childClass).'_DEL', array($this->childClass => &$Data));
		printf('<form method="post" action="%s" class="c">',$this->formAction);
		$this->render();
		printf('</form>');
	}
	/** configure() send the client configuration information
	  * @return void
	  */
	public function configure() {
		$Datatosend = "#!ok\n#sleep={$this->FOGCore->getSetting(FOG_SERVICE_CHECKIN_TIME)}\n#force={$this->FOGCore->getSetting(FOG_TASK_FORCE_REBOOT)}\n#maxsize={$this->FOGCore->getSetting(FOG_CLIENT_MAXSIZE)}\n#promptTime={$this->FOGCore->getSetting(FOG_GRACE_TIMEOUT)}";
		print $Datatosend;
		exit;
	}
	/** authorize() authorize the client information
	  * @return void
	  */
	public function authorize() {
		try {
			// Get the host or error out
			$Host = $this->getHostItem(true);
			// Make sure the decryption is valid
			if (!$pub_key = $this->certDecrypt($_REQUEST['sym_key'])) throw new Exception('#!ihc');
			// Store the key
			$key = trim(mb_substr($pub_key,0,32));
			// Get the token
			$token = trim(mb_substr($pub_key,33));
			// Test if the sec_tok is valid and the received token don't match error out
			
			if ($Host->get('sec_tok') && bin2hex($token) !== $Host->get('sec_tok')) throw new Exception('#!ist');
			// If there is no token defined generate one
			if (!$Host->get('sec_tok')) $Host->set('sec_tok',$this->createSecToken())->save();
			if ($Host->get('sec_tok') && !$key) throw new Exception('#!ihc');
			$key = bin2hex($key);
			$Host->set('pub_key',$key)->save();
			print '#!en='.$this->certEncrypt('#!ok'.$Host->get('sec_tok'),$Host);
		}
		catch (Exception $e) {
				print  $e->getMessage();
		}
		exit;
	}
	/** createSecToken() create security token
	  * @return trimmed hex of token
	  */
	private function createSecToken() {
		$p = new OAuthProvider();
		$token = $p->generateToken(64);
		return trim(bin2hex($token));
	}
	/** delete_post() actually delete the items
	  * @return void
	  */
	public function delete_post() {
		// Find
		$Data = $this->obj;
		// Hook
		$this->HookManager->processEvent(strtoupper($this->node).'_DEL_POST', array($this->childClass => &$Data));
		// POST
		try {
			if ($Data instanceof Group) {
				if ($_REQUEST['delHostConfirm'] == '1') {
					foreach((array)$Data->get('hosts') AS $Host) {
						if ($Host && $Host->isValid()) $Host->destroy();
					}
				}
				// Remove hosts first
				if (isset($_REQUEST['massDelHosts'])) $this->FOGCore->redirect('?node=group&sub=delete_hosts&id='.$Data->get('id'));
			}
			if ($Data instanceof Image || $Data instanceof Snapin) {
				if ($Data->get('protected')) throw new Exception($this->childClass.' '._('is protected, removal not allowed'));
				if (isset($_REQUEST['andFile'])) $Data->deleteFile();
			}
			// Error checking
			if (!$Data->destroy()) throw new Exception(_('Failed to destroy'));
			// Hook
			$this->HookManager->processEvent(strtoupper($this->childClass).'_DELETE_SUCCESS', array($this->childClass => &$Data));
			// Log History event
			$this->FOGCore->logHistory($this->childClass.' deleted: ID: '.$Data->get('id').', Name:'.$Data->get('name'));
			// Set session message
			$this->FOGCore->setMessage($this->childClass.' deleted: '.$Data->get('name'));
			// Reset request
			$this->resetRequest();
			// Redirect
			$this->FOGCore->redirect('?node='.$this->node);
		} catch (Exception $e) {
			// Hook
			$this->HookManager->processEvent(strtoupper($this->node).'_DELETE_FAIL', array($this->childClass => &$Data));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s %s: ID: %s, Name: %s',_($this->childClass), _('delete failed'),$Data->get('id'),$Data->get('name')));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect
			$this->FOGCore->redirect($this->formAction);
		}
	}
	/** search() the search methods
	  * @return void
	  */
	public function search() {
		if ($this->node == 'task' && $_REQUEST['sub'] != 'search') $this->FOGCore->redirect(sprintf('?node=%s&sub=active',$this->node));
		// Set Title
		$this->title = _('Search');
		// Set search form
		if (in_array($this->node,$this->searchPages)) $this->searchFormURL = sprintf('?node=%s&sub=search',$this->node);
		// Hook
		$this->HookManager->processEvent(strtoupper($this->childClass).'_DATA', array('data' => &$this->data, 'templates' => &$this->templates, 'headerData' => &$this->headerData,'attributes' => &$this->attributes,'title' => &$this->title,'searchFormURL' => &$this->searchFormURL));
		$this->HookManager->processEvent(strtoupper($this->childClass).'_HEADER_DATA', array('headerData' => &$this->headerData));
		// Output
		$this->render();
	}
	/** membership() the membership of specific class
	  * @return void
	  */
	public function membership() {
		$Obj = $this->childClass;
		// Find Object
		$$Obj = $this->getClass($this->childClass,$_REQUEST['id']);
		print '<!-- Membership -->';
		if ($this->childClass == 'Group') {
			print "\n\t\t\t<!-- Membership -->";
			// Hopeful implementation of all groups to add to group system in similar means to how host page does from list/search functions.
			print "\n\t\t\t".'<div id="group-membership">';
			// Create the Header data:
			$this->headerData = array(
				'',
				'<input type="checkbox" name="toggle-checkboxgroup1" class="toggle-checkbox1" />',
				_('Host Name'),
				_('Image'),
			);
			// Create the template data:
			$this->templates = array(
				'<span class="icon fa fa-question fa-1x hand" title="${host_desc}"></span>',
				'<input type="checkbox" name="host[]" value="${host_id}" class="toggle-host${check_num}" />',
				'<a href="?node=host&sub=edit&id=${host_id}" title="Edit: ${host_name} Was last deployed: ${deployed}">${host_name}</a><br /><small>${host_mac}</small>',
				'${image_name}',
			);
			// Create the attributes to build the table info:
			$this->attributes = array(
				array('width' => 22, 'id' => 'host-${host_name}'),
				array('class' => 'c', 'width' => 16),
				array(),
				array(),
			);
			// All hosts not in this group.
			foreach((array)$Group->get('hostsnotinme') AS $Host) {
				if ($Host && $Host->isValid() && !$Host->get('pending')) {
					$this->data[] = array(
						'host_id' => $Host->get('id'),
						'deployed' => $this->validDate($Host->get('deployed')) ? $this->FOGCore->formatTime($Host->get('deployed')) : 'No Data',
						'host_name' => $Host->get('name'),
						'host_mac' => $Host->get('mac')->__toString(),
						'host_desc' => $Host->get('description'),
						'image_name' => $Host->getImage()->get('name'),
						'check_num' => '1'
					);
				}
			}
			$GroupDataExists = false;
			$groupAdd = '';
			if (count($this->data) > 0) {
				$GroupDataExists = true;
				$this->HookManager->processEvent('GROUP_HOST_NOT_IN_ME',array('headerData' => &$this->headerData,'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
				$groupAdd .= "<center>".'<label for="hostMeShow">'._('Check here to see hosts not in this group').'&nbsp;&nbsp;<input type="checkbox" name="hostMeShow" id="hostMeShow" /></label><div id="hostNotInMe"><h2>'._('Modify Membership for').' '.$Group->get('name').'</h2><p>'._('Add hosts to group').' '.$Group->get('name').'</p>'.$this->process()."</div></center>";
			}
			// Reset the data for the next value
			unset($this->data);
			if ($GroupDataExists) $groupAdd .= '<br/><center><input type="submit" value="'._('Add Host(s) to Group').'" name="addHosts"/></center><br/>';
			if ($groupAdd) {
				print '<form method="post" action="'.$this->formAction.'">';
				print $groupAdd;
				print '</form>';
			}
			unset($this->data);
			$this->headerData = array(
				'<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" checked/>',
				_('Hostname'),
				_('Deployed'),
				_('Image'),
			);
			$this->attributes = array(
				array('class' => 'c','width' => 16),
				array(),
				array(),
				array(),
			);
			$this->templates = array(
				'<input type="checkbox" name="hostdel[]" value="${host_id}" class="toggle-action" checked/>',
				'<a href="?node=host&sub=edit&id=${host_id}" title="Edit: ${host_name} Was last deployed: ${deployed}">${host_name}</a><br /><small>${host_mac}</small>',
				'<small>${deployed}</small>',
				'<small>${image_name}</small>',
			);
			$imageSelector = $this->getClass('ImageManager')->buildSelectBox('','','','',true);
			foreach ((array)$Group->get('hosts') AS $Host) {
				if ($Host && $Host->isValid()) {
					$this->data[] = array(
						'host_id'   => $Host->get('id'),
						'deployed' => $this->validDate($Host->get('deployed')) ? $this->FOGCore->formatTime($Host->get('deployed')) : 'No Data',
						'host_name' => $Host->get('name'),
						'host_mac'  => $Host->get('mac'),
						'image_name' => $imageSelector,
						'selected_item'.$Host->get('imageID') => 'selected',
						'selector_name' => $Host->get('name').'_'.$Host->get('id'),
					);
				}
			}
			// Hook
			$this->HookManager->processEvent('GROUP_MEMBERSHIP', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
			print '<form method="post" action="'.$this->formAction.'">';
			// Output
			$this->render();
			if (count($this->data) > 0) {
				print '<center><input type="submit" value="'._('Update Hosts').'" name="updatehosts"/>&nbsp;&nbsp;';
				print '<input type="submit" value="'._('Delete Selected Hosts From Group').'" name="remhosts"/></center>';
			}
			print '</form></div>';
			unset($this->data);
		} else if ($this->childClass == 'Image') {
			// Set the values
			print '<div id="image-host">';
			// Create the header data:
			$this->headerData = array(
				'',
				'<input type="checkbox" name="toggle-checkboximage1" class="toggle-checkbox1" />',
				_('Host Name'),
				_('Last Deployed'),
				_('Registered'),
			);
			// Create the template data:
			$this->templates = array(
				'<i class="icon fa fa-question" title="${host_desc}"></i>',
				'<input type="checkbox" name="host[]" value="${host_id}" class="toggle-host${check_num}" />',
				'<a href="?node=host&sub=edit&id=${host_id}" title="Edit: ${host_name} Was last deployed: ${deployed}">${host_name}</a><br /><small>${host_mac}</small>',
				'${deployed}',
				'${host_reg}',
			);
			// Create the attributes data:
			$this->attributes = array(
				array('width' => 22, 'id' => 'host-${host_name}'),
				array('class' => 'c', 'width' => 16),
				array(),
				array(),
				array(),
			);
			// All hosts not with this set as the image
			foreach((array)$Image->get('hostsnotinme') AS $Host) {
				if ($Host && $Host->isValid()) {
					$this->data[] = array(
						'host_id' => $Host->get('id'),
						'deployed' => $this->validDate($Host->get('deployed')) ? $this->FOGCore->formatTime($Host->get('deployed')) : 'No Data',
						'host_name' => $Host->get('name'),
						'host_mac' => $Host->get('mac'),
						'host_desc' => $Host->get('description'),
						'check_num' => '1',
						'host_reg' => $Host->get('pending') ? _('Pending Approval') : _('Approved'),
					);
				}
			}
			$ImageDataExists = false;
			$imageAdd = '';
			if (count($this->data) > 0) {
				$ImageDataExists = true;
				$this->HookManager->processEvent('IMAGE_HOST_ASSOC',array('headerData' => &$this->headerData,'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
				$imageAdd .= "<center>".'<label for="hostMeShow">'._('Check here to see hosts not assigned with this image').'&nbsp;&nbsp;<input type="checkbox" name="hostMeShow" id="hostMeShow" /></label><div id="hostNotInMe"><h2>'._('Modify image association for').' '.$Image->get('name').'</h2><p>'._('Add hosts to image').' '.$Image->get('name').'</p>'.$this->process().'</div></center>';
			}
			// Reset the data for the next value
			unset($this->data);
			if ($ImageDataExists) $imageAdd .= '</br><center><input type="submit" value="'._('Add Image to Host(s)').'" name="addHosts"/></center></br>';
			if ($imageAdd) {
				print '<form method="post" action="'.$this->formAction.'">';
				print $imageAdd;
				print "</form>";
			}
			unset($this->data);
			// Create the header data:
			$this->headerData = array(
				'',
				'<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" checked/>',
				_('Host Name'),
				_('Last Deployed'),
				_('Registered'),
			);
			// Create the template data:
			$this->templates = array(
				'<i class="icon fa fa-question hand" title="${host_desc}"></i>',
				'<input type="checkbox" name="hostdel[]" value="${host_id}" class="toggle-action" checked/>',
				'<a href="?node=host&sub=edit&id=${host_id}" title="Edit: ${host_name} Was last deployed: ${deployed}">${host_name}</a><br /><small>${host_mac}</small>',
				'${deployed}',
				'${host_reg}',
			);
			foreach((array)$Image->get('hosts') AS $Host) {
				if ($Host && $Host->isValid()) {
					$this->data[] = array(
						'host_id' => $Host->get('id'),
						'deployed' => $this->validDate($Host->get('deployed')) ? $this->FOGCore->formatTime($Host->get('deployed')) : 'No Data',
						'host_name' => $Host->get('name'),
						'host_mac' => $Host->get('mac')->__toString(),
						'host_desc' => $Host->get('description'),
						'host_reg' => $Host->get('pending') ? _('Pending Approval') : _('Approved'),
					);
				}
			}
			// Hook
			$this->HookManager->processEvent('IMAGE_EDIT_HOST', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
			// Output
			print '<form method="post" action="'.$this->formAction.'">';
			$this->render();
			if (count($this->data) > 0) print '<center><input type="submit" value="'._('Remove image from selected hosts').'" name="remhosts"/>';
			print '</form></div>';
			unset($this->data);
		} else if ($this->childClass == 'Printer') {
			print '<div id="printer-host">';
			// Create the header data:
			$this->headerData = array(
				'',
				'<input type="checkbox" name="toggle-checkboxprinter1" class="toggle-checkbox1" />',
				_('Host Name'),
				_('Image'),
			);
			// Create the template data:
			$this->templates = array(
				'<i class="icon fa fa-question hand" title="${host_desc}"></i>',
				'<input type="checkbox" name="host[]" value="${host_id}" class="toggle-host${check_num}" />',
				'<a href="?node=host&sub=edit&id=${host_id}" title="Edit: ${host_name} Was last deployed: ${deployed}">${host_name}</a><br /><small>${host_mac}</small>',
				'${image_name}',
			);
			// All hosts not with this set as the image
			$this->attributes = array(
				array('width' => 22, 'id' => 'host-${host_name}'),
				array('class' => 'c', 'width' => 16),
				array(),
				array(),
			);
			// All hosts not with this printer
			foreach($Printer->get('hostsnotinme') AS $Host) {
				if ($Host && $Host->isValid()) {
					$this->data[] = array(
						'host_id' => $Host->get('id'),
						'deployed' => $this->validDate($Host->get('deployed')) ? $this->formatTime($Host->get('deployed')) : 'No Data',
						'host_name' => $Host->get('name'),
						'host_mac' => $Host->get('mac')->__toString(),
						'host_desc' => $Host->get('description'),
						'image_name' => $Host->getImage()->get('name'),
						'check_num' => '1',
					);
				}
			}
			$PrinterDataExists = false;
			$printAdd = '';
			if (count($this->data) > 0) {
				$PrinterDataExists = true;
				$this->HookManager->processEvent('PRINTER_HOST_ASSOC',array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates,'attributes' => &$this->attributes));
				$printAdd .= "<center>".'<label for="hostMeShow">'._('Check here to see hosts not assigned with this printer').'&nbsp;&nbsp;<input type="checkbox" name="hostMeShow" id="hostMeShow" /></label><div id="hostNotInMe"><h2>'._('Modify printer association for').' '.$Printer->get('name').'</h2><p>'._('Add hosts to printer').' '.$Printer->get('name').'</p>'.$this->process()."</div></center>";
			}
			// Reset the data for the next value
			unset($this->data);
			if ($PrinterDataExists) $printAdd .= '<br/><center><input type="submit" value="'._('Add printer to Host(s)').'" name="addHosts"/></center><br/>';
			if ($printAdd) {
				print '<form method="post" action="'.$this->formAction.'">';
				print $printAdd;
				print '</form>';
			}
			unset($this->data);
			$this->headerData = array(
				'<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" checked/>',
				_('Hostname'),
				_('Deployed'),
				_('Image'),
				'<input type="checkbox" name="toggle-alldef" class="toggle-actiondef" />&nbsp;'._('Is Default'),
			);
			$this->attributes = array(
				array('class' => 'c','width' => 16),
				array(),
				array(),
				array(),
				array('class' => 'l'),
			);
			$this->templates = array(
				'<input type="checkbox" name="hostdel[]" value="${host_id}" class="toggle-action" checked/>',
				'<a href="?node=host&sub=edit&id=${host_id}" title="Edit: ${host_name} Was last deployed: ${deployed}">${host_name}</a><br /><small>${host_mac}</small>',
				'<small>${deployed}</small>',
				'<small>${image_name}</small>',
				'<input class="default" type="checkbox" name="default[]" id="host_printer${host_id}"${is_default} value="${host_id}" /><label for="host_printer${host_id}" class="icon icon-hand" title="'._('Default Printer Selection').'">&nbsp;</label><input type="hidden" value="${host_id}" name="hostid[]"/>',
			);
			unset($this->data);
			foreach($Printer->get('hosts') AS $Host) {
				if ($Host && $Host->isValid()) {
					$this->data[] = array(
						'host_id' => $Host->get('id'),
						'deployed' => $this->validDate($Host->get('deployed')) ? $this->FOGCore->formatTime($Host->get('deployed')) : 'No Data',
						'host_name' => $Host->get('name'),
						'host_mac' => $Host->get('mac'),
						'host_desc' => $Host->get('description'),
						'image_name' => $Host->getImage()->get('name'),
						'printer_id' => $Printer->get('id'),
						'is_default' => $Host->getDefault($Printer->get('id')) ? 'checked' : '',
					);
				}
			}
			// Hook
			$this->HookManager->processEvent('PRINTER_EDIT_HOST', array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates,'attributes' => &$this->attributes));
			// Output
			print '<form method="post" action="'.$this->formAction.'">';
			$this->render();
			if (count($this->data) > 0) print '<center><input type="submit" name="updefaults" value="'._('Update defaults').'"/>&nbsp;&nbsp;<input type="submit" name="remhosts" value="'._('Remove the selected hosts').'"/>';
			print '</form></div>';
			unset($this->data);
		} else if ($this->childClass == 'Snapin') {
			print '<div id="snap-host">';
			// Create the header data:
			$this->headerData = array(
				'',
				'<input type="checkbox" name="toggle-checkboxsnapin1" class="toggle-checkbox1" />',
				_('Host Name'),
				_('Image'),
			);
			// Create the template data:
			$this->templates = array(
				'<i class="icon fa fa-question hand" title="${host_desc}"></i>',
				'<input type="checkbox" name="host[]" value="${host_id}" class="toggle-host${check_num}" />',
				'<a href="?node=host&sub=edit&id=${host_id}" title="Edit: ${host_name} Was last deployed: ${deployed}">${host_name}</a><br /><small>${host_mac}</small>',
				'${image_name}',
			);
			// Create the attributes data:
			$this->attributes = array(
				array('width' => 22, 'id' => 'host-${host_name}'),
				array('class' => 'c', 'width' => 16),
				array(),
				array(),
			);
			// All hosts not with this snapin
			foreach((array)$Snapin->get('hostsnotinme') AS $Host) {
				if ($Host && $Host->isValid()) {
					$this->data[] = array(
						'host_id' => $Host->get('id'),
						'deployed' => $this->validDate($Host->get('deployed')) ? $this->formatTime($Host->get('deployed')) : 'No Data',
						'host_name' => $Host->get('name'),
						'host_mac' => $Host->get('mac')->__toString(),
						'host_desc' => $Host->get('description'),
						'image_name' => $Host->getImage()->get('name'),
						'check_num' => '1',
					);
				}
			}
			$SnapinDataExists = false;
			$snapAdd = '';
			if (count($this->data) > 0) {
				$SnapinDataExists = true;
				$this->HookManager->processEvent('SNAPIN_HOST_ASSOC',array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates,'attributes' => &$this->attributes));
				$snapAdd .= "<center>".'<label for="hostMeShow">'._('Check here to see hosts not assigned with this snapin').'&nbsp;&nbsp;<input type="checkbox" name="hostMeShow" id="hostMeShow" /></label><div id="hostNotInMe"><h2>'._('Modify snapin association for').' '.$Snapin->get('name').'</h2><p>'._('Add hosts to snapin').' '.$Snapin->get('name').'</p>'.$this->process()."</div></center>";
			}
			// Reset the data for the next value
			unset($this->data);
			if ($SnapinDataExists) $snapAdd .= '<br/><center><input type="submit" value="'._('Add snapins to Host(s)').'" name="addHosts"/></center><br/>';
			if ($snapAdd) {
				print '<form method="post" action="'.$this->formAction.'">';
				print $snapAdd;
				print '</form>';
			}
			$this->headerData = array(
				'<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" checked/>',
				_('Hostname'),
				_('Deployed'),
				_('Image'),
			);
			$this->attributes = array(
				array('class' => 'c','width' => 16),
				array(),
				array(),
				array(),
			);
			$this->templates = array(
				'<input type="checkbox" name="hostdel[]" value="${host_id}" class="toggle-action" checked/>',
				'<a href="?node=host&sub=edit&id=${host_id}" title="Edit: ${host_name} Was last deployed: ${deployed}">${host_name}</a><br /><small>${host_mac}</small>',
				'<small>${deployed}</small>',
				'<small>${image_name}</small>',
			);
			foreach((array)$Snapin->get('hosts') AS $Host) {
				if ($Host && $Host->isValid()) {
					$this->data[] = array(
						'host_id' => $Host->get('id'),
						'deployed' => $this->validDate($Host->get('deployed')) ? $this->formatTime($Host->get('deployed')) : '',
						'host_name' => $Host->get('name'),
						'host_mac' => $Host->get('mac'),
						'image_name' => $Host->getImage()->get('name'),
						'host_desc' => $Host->get('description'),
					);
				}
			}
			// Hook
			$this->HookManager->processEvent('SNAPIN_EDIT_HOST', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
			// Output
			print '<form method="post" action="'.$this->formAction.'">';
			$this->render();
			if (count($this->data) > 0) print '<center><input type="submit" value="'._('Delete Selected Hosts From Snapin').'" name="remhosts"/></center>';
			print '</form></div>';
			unset($this->data);
		}
	}
	/** membership_post() the membership poster of specific class
	  * @return void
	  */
	public function membership_post() {
		$Obj = $this->childClass;
		// Find Object
		$$Obj = $this->getClass($this->childClass,$_REQUEST['id']);
		if (isset($_REQUEST['addHosts'])) $$Obj->addHost($_REQUEST['host']);
		if (isset($_REQUEST['remhosts'])) $$Obj->removeHost($_REQUEST['hostdel']);
		if ($this->childClass == 'Group') {
			if (isset($_REQUEST['updatehosts'])) {
				foreach((array)$Group->get('hosts') AS $Host) {
					if ($Host && $Host->isValid()) $Host->set('imageID',$_REQUEST[$Host->get('name').'_'.$Host->get('id')])->save();
				}
			}
		} else if ($this->childClass == 'Printer') {
			if (isset($_REQUEST['updefaults'])) $Printer->updateDefault($_REQUEST['hostid'],$_REQUEST['default']);
		}
		if ($$Obj->save()) {
			$this->FOGCore->setMessage($$Obj.' '._('saved successfully'));
			$this->FOGCore->redirect($this->formAction);
		}
	}
}
