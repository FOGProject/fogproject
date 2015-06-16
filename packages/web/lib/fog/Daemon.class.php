<?php
class Daemon {
	/** @var $TTY the console to output to/from */
	public $TTY;
	/** @var $interface the interface to connect with */
	public $interface = NULL;
	/** @var $interfaceSettingName the interface name */
	public $interfaceSettingName = NULL;
	/** @var $DaemonName the name of the Daemon */
	public $DaemonName;
	/** @var $config the configuration class */
	private $config;
	/** @var $mysqli the mysqli connection */
	private $mysqli;
	/** @function __construct() Construct daemon class
	 * @param $DaemonName the name to test
	 * @param $interfaceSettingName the interface name
	 * @return void
	 */
	public function __construct($DaemonName,$interfaceSettingName) {
		require_once(WEBROOT."/lib/fog/Config.class.php");
		$this->config = new Config();
		$this->TTY = constant(strtoupper($DaemonName).'DEVICEOUTPUT');
		$this->DaemonName = ucfirst(strtolower($DaemonName));
		$this->interfaceSettingName = $interfaceSettingName;
	}
	/** @function __destruct()
	 * @return void
	 */
	public function __destruct() {
		unset($this->config);
		unset($this->mysqli);
	}
	/** @function clear_screen() Clears the screen for information.
	 * @return void
	 */
	public function clear_screen() {
		$this->out(chr(27)."[2J".chr(27)."[;H");
	}
	/** @function wait_db_ready() Waits until mysql is ready to accept connections.
	 * @return void
	 */
	public function wait_db_ready() {
		$this->mysqli = @new mysqli(DATABASE_HOST,DATABASE_USERNAME,DATABASE_PASSWORD,DATABASE_NAME); // try connection
		while ($this->mysqli->connect_errno) {
			$this->out("FOGService:{$this->DaemonName} - Waiting for mysql to be available..\n");
			sleep(10); // wait some time
			@$this->mysqli->connect(DATABASE_HOST,DATABASE_USERNAME,DATABASE_PASSWORD,DATABASE_NAME); // try again before loop continues
		}
		return;
	}
	/** @function wait_interface_ready() Waits for the network interface to be ready
	 * so the system can report if it will work or not
	 * @return void
	 */
	public function wait_interface_ready() {
		if ($this->interface == NULL) {
			$this->out("Getting interface name.. ");
			$this->FOGCore = $GLOBALS['FOGCore'];
			$this->out("$this->interface\n");
		}
		while (true) {
			$retarr = array();
			$retarr = $this->FOGCore->getIPAddress();
			foreach ($retarr as $line) {
				if ($line !== false) {
					$this->out("Interface now ready, with IPAddr $line\n");
					break 2;
				}
			}
			$this->out("Interface not ready, waiting..\n");
			sleep(10);
		}
	}
	/** @function out()	prints the information to the service console files.
	 * @param $string the data to put to log
	 * @param $device the screen to print to defaults to default tty
	 * @return void
	 */
	public function out($string,$device=NULL) {
		if ($device === NULL) {$device = $this->TTY;}
		file_put_contents($this->TTY,$string,FILE_APPEND);
	}
	/** @function getBanner() Prints the FOG banner
	 * @return the banner
	 */
	public function getBanner() {
		$str  = "        ___           ___           ___      \n";
		$str .= "       /\  \         /\  \         /\  \     \n";
		$str .= "      /::\  \       /::\  \       /::\  \    \n";
		$str .= "     /:/\:\  \     /:/\:\  \     /:/\:\  \   \n";
		$str .= "    /::\-\:\  \   /:/  \:\  \   /:/  \:\  \  \n";
		$str .= "   /:/\:\ \:\__\ /:/__/ \:\__\ /:/__/_\:\__\ \n";
		$str .= "   \/__\:\ \/__/ \:\  \ /:/  / \:\  /\ \/__/ \n";
		$str .= "        \:\__\    \:\  /:/  /   \:\ \:\__\   \n";
		$str .= "         \/__/     \:\/:/  /     \:\/:/  /   \n";
		$str .= "                    \::/  /       \::/  /    \n";
		$str .= "                     \/__/         \/__/     \n";
		$str .= "\n";
		$str .= "  ###########################################\n";
		$str .= "  #     Free Computer Imaging Solution      #\n";
		$str .= "  #                                         #\n";
		$str .= "  #     Created by:                         #\n";
		$str .= "  #         Chuck Syperski                  #\n";
		$str .= "  #         Jian Zhang                      #\n";
		$str .= "  #         Tom Elliott                     #\n";
		$str .= "  #                                         #\n";
		$str .= "  #     GNU GPL Version 3                   #\n";
		$str .= "  ###########################################\n";
		$str .= "\n";
		return $str;
	}
}
