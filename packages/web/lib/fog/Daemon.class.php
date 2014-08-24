<?php
/** Class Name: Daemon
        Class for managing Daemon exclusive functions.
*/
class Daemon
{
	public $TTY;
	public $interface = NULL;
	public $interfaceSettingName = NULL;
	public $DaemonName;
	private $config;
	private $mysqli;
	

	/** Construct daemon class
	new Daemon($DaemonName,$InterfaceSettingName)
	$DaemonName = string: First part of deviceoutput constant, used to find TTY and display wait info
	$interfaceSettingName = string: name of the constant to get appropriate interface from. Constant isn't available until config has been constructed.
	*/
	public function __construct($DaemonName,$interfaceSettingName) {
		require_once( WEBROOT . "/lib/fog/Config.class.php" );
		$this->config = new Config();
		$this->TTY = constant(strtoupper($DaemonName).'DEVICEOUTPUT');
		$this->DaemonName = ucfirst(strtolower($DaemonName));
		$this->interfaceSettingName = $interfaceSettingName;
	}
	public function __destruct() {
		unset($this->config);
		unset($this->mysqli);
	}
	/** clear_screen()
		Clears the screen for information.
	*/
	public function clear_screen()
	{
		$this->out(chr(27)."[2J".chr(27)."[;H");
	}

	/** wait_db_ready()
	        Waits until mysql is ready to accept connections.
	*/
	public function wait_db_ready()
	{
		$this->mysqli = @new mysqli(DATABASE_HOST,DATABASE_USERNAME,DATABASE_PASSWORD,DATABASE_NAME); // try connection
		while ($this->mysqli->connect_errno)
		{ // no mysql answer..
			$this->out("FOGService:{$this->DaemonName} - Waiting for mysql to be available..\n");
			sleep(10); // wait some time
		        @$this->mysqli->connect(DATABASE_HOST,DATABASE_USERNAME,DATABASE_PASSWORD,DATABASE_NAME); // try again before loop continues
		}
		return;
	}

	/** wait_interface_ready()
		Waits for the network interface to be ready so services operate.
		This requires FOGCore!!!!
	*/
	public function wait_interface_ready()
	{
	        if ($this->interface == NULL)
	        {
	                $this->out("Getting interface name.. ");
	                $this->interface = $GLOBALS['FOGCore']->getSetting('FOG_UDPCAST_INTERFACE');
	                $this->out($this->interface."\n");
	        }
	        
		
		while (true)
		{
			$retarr = array();
			exec("ifconfig {$this->interface}",$retarr);
                        foreach ($retarr as $line)
                        {
                                $line = strstr($line,'inet addr:');
                                if ($line !== false)
                                {
                                        $line = substr(explode(" ",$line)[1],5);
                                        $this->out("Interface now ready, with IPAddr $line\n");
                                        break 2;
                                }
                        }
			$this->out("Interface not ready, waiting..\n");
			sleep(10);
		}
	}
	// The below functions are from the FOG Service Scripts Data writing and checking.
	/** out($sting, $device=NULL)
		prints the information to the service console files.
	*/
	public function out($string,$device=NULL)
	{	if ($device === NULL) {$device = $this->TTY;}
		file_put_contents($this->TTY,$string,FILE_APPEND);
	}
	/** getBanner()
        Prints the FOG banner
	*/
	function getBanner()
	{ 
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
