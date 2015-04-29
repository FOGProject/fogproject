<?php
class EventManager extends FOGBase {
	public $logLevel = 0;
	public $data = array();
	public function register($event, $listener) {
		try {
			if (!class_exists($listener, false))
				throw new Exception('Listiner is invalid');
			if (!($listener instanceof Event))
				throw new Exception('Not a valid event listener');

			$this->log(sprintf('Registering Event Linster: Event: %s, Class: %s', $event, $className));
			
			if(!isset($this->data[$event]))
				$this->data[$event] = array();
			array_push($this->data[$event], $listener);
			return true;
		} catch (Exception $e) {
			$this->log(sprintf('Could not register v: Error: %s, Event: %s, Class: %s', $e->getMessage(), $event, $class[1]));
		}
		return false;
	}
	public function notify($event, $eventData=array()) {
		try {
			if (!is_array($eventData))
				throw new Exception('Data is invalid');
								
			$this->log(sprintf('Notifiying listeners: Event: %s, Data: %d', $event, $eventData));
			
			if(isset($this->data[$event])) {
				foreach($this->data[$event] as $className) {
					if($className->active)
						$className->onEvent($event, $eventData);
				}
			}
			return true;
		} catch (Exception $e) {
			$this->log(sprintf('Could not register v: Error: %s, Event: %s, Class: %s', $e->getMessage(), $event, $class[1]));
		}
		return false;
	}	
	public function load() {
		global $Init;
		foreach($Init->EventPaths AS $subscriberDirectory) {
			if (file_exists($subscriberDirectory)) {
				$subscriberIterator = new DirectoryIterator($subscriberDirectory);
				foreach ($subscriberIterator AS $fileInfo) {
					$file = !$fileInfo->isDot() && $fileInfo->isFile() && substr($fileInfo->getFilename(),-10) == '.event.php' ? file($fileInfo->getPathname()) : null;
					$PluginName = preg_match('#plugins#i',$subscriberDirectory) ? basename(substr($subscriberDirectory,0,-7)) : null;
					if (in_array($PluginName,$_SESSION['PluginsInstalled'])) $className = (substr($fileInfo->getFilename(),-10) == '.event.php' ? substr($fileInfo->getFilename(),0,-10) : null);
					else if ($file && !preg_match('#plugins#',$fileInfo->getPathname())) {
						$key = '$active';
						foreach($file AS $lineNumber => $line) {
							if (strpos($line,$key) !== false)
								break;
						}
						if(preg_match('#true#i',$file[$lineNumber])) $className = (substr($fileInfo->getFileName(),-10) == '.event.php' ? substr($fileInfo->getFilename(),0,-10) : null);
					}
					if ($className && !in_array($className,get_declared_classes())) $this->getClass($className);
				}
			}
		}
	}
	private function log($txt, $level = 1) {
		if (!$this->isAJAXRequest() && $this->logLevel >= $level)
			printf('[%s] %s%s', $this->nice_date()->format("d-m-Y H:i:s"), trim(preg_replace(array("#\r#", "#\n#", "#\s+#", "# ,#"), array("", " ", " ", ","), $txt)), "<br />\n");
	}
	public function isAJAXRequest() {
		return (strtolower(@$_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' ? true : false);
	}
}
