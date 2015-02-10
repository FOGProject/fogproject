<?php
/** Class Name: FOGPageManager
	Extends FOGBase
	This is what actually displays
	the information relevant to the proper page.
*/
class FOGPageManager extends FOGBase
{
	private $pageTitle;
	private $nodes = array();
	private $classVariable = 'node';
	private $methodVariable = 'sub';
	private $classValue;
	private $methodValue;
	private $arguments;
	private $plugin_checked;
	// Construct
	public function __construct()
	{
		// FOGBase Constructor
		parent::__construct();
		// Save class & method values into class - used many times through out
		$this->classValue = ($GLOBALS[$this->classVariable] ? preg_replace('#[^\w]#', '_', urldecode($GLOBALS[$this->classVariable])) : (preg_match('#mobile#i',$_SERVER['PHP_SELF']) ? 'homes' : 'home'));
		$this->methodValue = preg_replace('#[^\w]#', '_', urldecode($GLOBALS[$this->methodVariable]));	// No default value as we want to detect an empty string for 'list' or 'search' default page
		// Hook in to allow search pages to be adjusted as needed.
		$this->HookManager->processEvent('SEARCH_PAGES',array('searchPages' => &$this->searchPages));
	}
	// Util functions - easy access to class & child class data
	public function getFOGPageClass()
	{
		return $this->nodes[$this->classValue];
	}
	public function getFOGPageName()
	{
		return $this->getFOGPageClass()->name;
	}
	public function getFOGPageTitle()
	{
		return $this->getFOGPageClass()->title;
	}
	public function isFOGPageTitleEnabled()
	{
		return ($this->getFOGPageClass()->titleEnabled == true && !empty($this->getFOGPageClass()->title));
	}
	// Register FOGPage
	public function register($class)
	{
		try
		{
			if (!$class)
				throw new Exception($this->foglang['InvalidClass']);
			if (!($class instanceof FOGPage))
				throw new Exception($this->foglang['NotExtended']);
			// INFO
			$this->info('Adding FOGPage: %s, Node: %s', array(get_class($class), $class->node));
			$this->nodes[$class->node] = $class;
		}
		catch (Exception $e)
		{
			$this->debug('Failed to add Page: Node: %s, Page Class: %s, Error: %s', array($this->classValue, $class, $e->getMessage()));
		}
		return $this;
	}
	// Call FOGPage->method based on $this->classValue and $this->methodValue
	public function render()
	{
		if (in_array($_REQUEST['node'],array('client','schemaupdater')) || in_array($_REQUEST['sub'],array('configure','authorize')) || ($this->FOGUser && $this->FOGUser->isValid() && $this->FOGUser->isLoggedIn()))
		{
			$this->loadPageClasses();
			try
			{
				// Variables
				$class = $this->getFOGPageClass();	// Class that will be used
				$method = $this->methodValue;		// Method that will be called in the above class. This value changes while $this->methodValue remains constant.
				// Error checking
				if (!array_key_exists($this->classValue, $this->nodes))
					throw new Exception('No FOGPage Class found for this node.');
				// Figure out which method to call - default to index() if method is not found
				if (empty($method) || !method_exists($class, $method))
				{
					if (!empty($method) && $method != 'list')
						$this->debug('Class: %s, Method: %s, Error: Method not found in class, defaulting to index()', array(get_class($class), $method));
					$method = 'index';
				}
				// FOG - Default view override
				if ($this->methodValue != 'list' && $method == 'index' && $this->FOGCore->getSetting('FOG_VIEW_DEFAULT_SCREEN') != 'list' && method_exists($class, 'search') && in_array($class->node,$this->searchPages))
					$method = 'search';
				// POST - Append '_post' to method name if request method is POST and the method exists
				if ($this->FOGCore->isPOSTRequest() && method_exists($class, $method . '_post'))
					$method = $method . '_post';
				// AJAX - Append '_ajax' to method name if request is ajax and the method exists
				if ($this->FOGCore->isAJAXRequest() && method_exists($class, $method . '_ajax'))
					$method = $method . '_ajax';
				// Arguments
				$this->arguments = (!empty($GLOBALS[$class->id]) ? array('id' => $GLOBALS[$class->id]) : array());
				// Render result to variable - we do this so we can send HTTP Headers in a class method
				ob_start();
				(!$this->FOGCore->isPOSTRequest() ? $this->resetRequest() : $this->setRequest());
				call_user_func(array($class, $method));
				$result = ob_get_clean();
				$this->resetRequest();
				return $result;
			}
			catch (Exception $e)
			{
				$this->debug('Failed to Render Page: Node: %s, Error: %s', array($this->classValue, $e->getMessage()));
			}
		}
		return false;
	}
	// Load FOGPage classes
	private function loadPageClasses()
	{
		if ($this->isLoaded('PageClasses'))
			return;
		// This variable is required as each class file uses it
		global $Init;
		foreach($Init->PagePaths as $path)
		{
			$this->plugin_checked = false;
			if (file_exists($path))
			{
				$iterator = new DirectoryIterator($path);
				foreach ($iterator as $fileInfo)
				{
					$PluginName = preg_match('#plugins#i',$path) ? basename(substr($path,0,-6)) : null;
					if ($PluginName && !$this->plugin_checked)
					{
						$Plugin = current((array)$this->getClass('PluginManager')->find(array('name' => $PluginName, 'installed' => 1)));
						$this->plugin_checked = true;
					}
					if ($Plugin)
						$className = (!$fileInfo->isDot() && $fileInfo->isFile() && substr($fileInfo->getFilename(),-10) == '.class.php' ? substr($fileInfo->getFilename(),0,-10) : null);
					else if (!preg_match('#plugins#i',$path))
						$className = (!$fileInfo->isDot() && $fileInfo->isFile() && substr($fileInfo->getFilename(),-10) == '.class.php' ? substr($fileInfo->getFilename(),0,-10) : null);
					if ($className)
					{
						$class = new $className();
						($_REQUEST['node'] == $class->node ? $this->register($class) : (!$_REQUEST['node'] && $class->node = 'home' ? $this->register($class) : $class = null));
					}
				}
			}
		}
	}
}
