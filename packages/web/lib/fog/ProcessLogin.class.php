<?php
class ProcessLogin extends FOGBase
{
	private $username, $password, $currentUser;
	private $mobileMenu, $mainMenu, $langMenu;
	public function __construct()
	{
		parent::__construct();
	}
	
	private function getLanguages()
	{
		foreach($this->foglang['Language'] AS $lang)
			$this->langMenu .= "\n\t\t\t\t\t\t".'<option value="'.$lang.'" '.($this->transLang() == $lang ? 'selected="selected"' : '').'>'.$lang.'</option>';
	}

	private function transLang()
	{
		switch($_SESSION['locale'])
		{
			case 'en_US.UTF-8':
				return $this->foglang['Language']['en'];
			case 'it_IT.UTF-8':
				return $this->foglang['Language']['it'];
			case 'es_ES.UTF-8':
				return $this->foglang['Language']['es'];
			case 'fr_FR.UTF-8':
				return $this->foglang['Language']['fr'];
			case 'zh_CN.UTF-8':
				return $this->foglang['Language']['zh'];
<<<<<<< HEAD
=======
			case 'de_DE.UTF-8':
				return $this->foglang['Language']['de'];
>>>>>>> 5e6f2ff5445db9f6ab2678bfad76acfcacc85157
			default :
				return $this->foglang['Language']['en'];
		}
	}

	private function specLang()
	{
		switch ($_POST['ulang'])
		{
			case _('English'):
				$_POST['ulang'] = 'en_US.UTF-8';
				break;
<<<<<<< HEAD
			case _('French'):
				$_POST['ulang'] = 'fr_FR.UTF-8';
				break;
			case _('Italian'):
				$_POST['ulang'] = 'it_IT.UTF-8';
				break;
			case _('Chinese'):
				$_POST['ulang'] = 'zh_CN.UTF-8';
				break;
			case _('Spanish'):
				$_POST['ulang'] = 'es_ES.UTF-8';
				break;
=======
			case _('Français'):
				$_POST['ulang'] = 'fr_FR.UTF-8';
				break;
			case _('Italiano'):
				$_POST['ulang'] = 'it_IT.UTF-8';
				break;
			case _('中国的'):
				$_POST['ulang'] = 'zh_CN.UTF-8';
				break;
			case _('Español'):
				$_POST['ulang'] = 'es_ES.UTF-8';
				break;
			case _('Deutsch'):
				$_POST['ulang']	= 'de_DE.UTF-8';
				break;
>>>>>>> 5e6f2ff5445db9f6ab2678bfad76acfcacc85157
			default :
				$_POST['ulang'] = 'en_US.UTF-8';
				break;
		}
	}

	private function setLang()
	{
		if (isset($_POST['ulang']) && strlen(trim($_POST['ulang'])) > 0)
		{
			$this->specLang();
			$_SESSION['locale'] = $_POST['ulang'];
			putenv("LC_ALL=".$_SESSION['locale']);
			setlocale(LC_ALL,$_SESSION['locale']);
			bindtextdomain('messages','languages');
			textdomain('messages');
		}
	}

	private function setCurUser($tmpUser)
	{
		// reset session on login success
		@session_write_close();
		@session_regenerate_id(true);
		$_SESSION = array();
<<<<<<< HEAD
=======
		@session_set_cookie_params(0);
>>>>>>> 5e6f2ff5445db9f6ab2678bfad76acfcacc85157
		@session_start();
		$currentUser = $tmpUser;
		$currentUser->set('authTime', time());
		$currentUser->set('authIP',$_SERVER['REMOTE_ADDR']);
		$_SESSION['FOG_USER'] = serialize($currentUser);
		$_SESSION['FOG_USERNAME'] = $currentUser->get('name');
		$this->setRedirMode();
		$this->currentUser = $currentUser;
		// Hook
<<<<<<< HEAD
		$this->HookManager->processEvent('LoginSuccess', array('user' => &$currentUser, 'username' => $this->username, 'password' => &$this->password));
=======
		if (!preg_match('#mobile#i',$_SERVER['PHP_SELF']))
			$this->HookManager->processEvent('LoginSuccess', array('user' => &$currentUser, 'username' => $this->username, 'password' => &$this->password));
>>>>>>> 5e6f2ff5445db9f6ab2678bfad76acfcacc85157
	}

	private function setRedirMode()
	{
		$redirect = array_merge($_GET, $_POST);
		unset($redirect['upass'],$redirect['uname'],$redirect['ulang']);
		if (in_array($redirect['node'], array('login','logout')))
			unset($redirect['node']);
		foreach ($redirect AS $key => $value)
			$redirectData[] = $key.'='.$value;
		$this->FOGCore->redirect($_SERVER['PHP_SELF'].($redirectData ? '?' . implode('&',(array)$redirectData) : ''));
	}

	public function loginFail($string)
	{
		// Hook
<<<<<<< HEAD
		$this->HookManager->processEvent('LoginFail', array('username' => &$this->username, 'password' => &$this->password));
=======
		if (!preg_match('#mobile#i',$_SERVER['PHP_SELF']))
			$this->HookManager->processEvent('LoginFail', array('username' => &$this->username, 'password' => &$this->password));
>>>>>>> 5e6f2ff5445db9f6ab2678bfad76acfcacc85157
		$this->FOGCore->setMessage($string);
	}

	public function processMainLogin()
	{
		$this->setLang();
		if(isset($_POST['uname']) && isset($_POST['upass']))
		{
			$this->username = trim($_POST['uname']);
			$this->password = trim($_POST['upass']);
			// Hook
			$this->HookManager->processEvent('Login', array('username' => &$this->username, 'password' => &$this->password));
			$tmpUser = $this->FOGCore->attemptLogin($this->username, $this->password);
			try
			{
				if (!$tmpUser)
					throw new Exception(_('Invalid Login'));
				if ($tmpUser->isValid() && $tmpUser->get('type') == 0 && $tmpUser->get('type') != 1)
					$this->setCurUser($tmpUser);
				else if ($tmpUser->get('type') == 0)
					throw new Exception(_('Not allowed here!'));
			}
			catch (Exception $e)
			{
				$this->loginFail($e->getMessage());
			}
		}
	}

	public function processMobileLogin()
	{
		$this->setLang();
		if (isset($_POST['uname']) && isset($_POST['upass']))
		{
			$this->username = trim($_POST['uname']);
			$this->password = trim($_POST['upass']);
<<<<<<< HEAD
			// Hook
			$this->HookManager->processEvent('Login', array('username' => &$this->username, 'password' =>&$this->password));
=======
>>>>>>> 5e6f2ff5445db9f6ab2678bfad76acfcacc85157
			$tmpUser = $this->FOGCore->attemptLogin($this->username, $this->password);
			try
			{
				if (!$tmpUser)
					throw new Exception(_('Invalid Login'));
				if ($tmpUser->isValid())
					$this->setCurUser($tmpUser);
			}
			catch (Exception $e)
			{
				$this->loginFail($e->getMessage());
			}
		}
	}

	public function mainLoginForm()
	{
		ob_start('ob_gzhandler');
		print '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">';
		print "\n".'<html xmlns="http://www.w3.org/1999/xhtml">';
		print "\n\t<head>";
		print "\n\t\t".'<title>Login &gt; FOG &gt; Open Source Computer Cloning Solution</title>';
		print "\n\t\t".'<meta http-equiv="content-type" content="text/html; charset=utf-8" />';
		print "\n\t\t".'<meta http-equiv="x-ua-compatible" content="IE=8" />';
		print "\n\t\t<!-- Stylesheets -->";
		print "\n\t\t".'<link rel="stylesheet" type="text/css" media="all" href="css/calendar/calendar-win2k-1.css" />';
		print "\n\t\t".'<link rel="stylesheet" type="text/css" href="css/fog.css" />';
		print "\n\t</head>";
		print "\n<body>";
		print "\n\t<!-- FOG Message Boxes -->";
		print "\n\t".'<div id="loader-wrapper"><div id="loader"><div id="progress"></div></div></div>';
		print "\n\t\t<!-- Main -->";
		print "\n\t\t".'<div id="wrapper">';
		print "\n\t\t\t<!-- Header -->";
		print "\n\t\t\t".'<div id="header" class="login">';
		print "\n\t\t\t\t".'<div id="logo">';
		print "\n\t\t\t\t\t".'<h1><img src="images/fog-logo.png" alt="logo" /><sup>'.FOG_VERSION.'</sup></h1>';
		print "\n\t\t\t\t\t".'<h2>'.$this->foglang['Slogan'].'</h2>';
		print "\n\t\t\t\t</div>";
		print "\n\t\t\t</div>";
		print "\n\t\t\t<!-- Content -->";
		print "\n\t\t\t".'<div id="content" class="dashboard">';
		print "\n\t\t\t\t<h1>"._('Management Login').'</h1>';
		print "\n\t\t\t\t".'<div id="content-inner">';
		print "\n\t\t\t\t\t".'<form method="post" action="?node=login" id="login-form">';
		if ($_GET['node'] != 'logout')
		{
			foreach ($_GET AS $key => $value)
				print "\n\t\t\t\t\t\t".'<input type ="hidden" name="'.$key.'" value="'.$value.'" />';
		}
		print "\n\t\t\t\t\t\t".'<label for="username">'._('Username').'</label>';
		print "\n\t\t\t\t\t\t".'<input type="text" class="input" name="uname" id="username" />';
		print "\n\t\t\t\t\t\t".'<label for="password">'._('Password').'</label>';
		print "\n\t\t\t\t\t\t".'<input type="password" class="input" name="upass" id="password" />';
		print "\n\t\t\t\t\t\t".'<label for="language">'._('Language').'</label>';
		$this->getLanguages();
		print "\n\t\t\t\t\t\t".'<select name="ulang" id="language">'.$this->langMenu.'</select>';
		print "\n\t\t\t\t\t\t".'<label for="login-form-submit"></label>';
		print "\n\t\t\t\t\t\t".'<input type="submit" value="'._('Login').'" id="login-form-submit" />';
		print "\n\t\t\t\t\t</form>";
		print "\n\t\t\t\t\t".'<div id="login-form-info">';
		print "\n\t\t\t\t\t\t<p>"._('Estimated FOG sites').': <b><span class="icon icon-loading"></span></b></p>';
		print "\n\t\t\t\t\t\t<p>"._('Latest Version').': <b><span class="icon icon-loading"></span></b></p>';
		print "\n\t\t\t\t\t</div>";
		print "\n\t\t\t\t</div>";
		print "\n\t\t\t</div>";
		print "\n\t\t</div>";
		print "\n\t\t<!-- Footer -->";
		print "\n\t".'<div id="footer">FOG Project: Chuck Syperski, Jian Zhang, Peter Gilchrist &amp; Tom Elliott FOG Client/Prep link: <a href="?node=client">FOG Client/FOG Prep</a></div>';
		$this->FOGCore->getMessages();
		print "\n\t<!-- JavaScript -->";
		print "\n\t".'<script type="text/javascript" src="js/jquery.js"></script>';
		print "\n\t".'<script type="text/javascript" src="js/jquery.progressbar.js"></script>';
		print "\n\t".'<script type="text/javascript" src="js/fog.js"></script>';
		print "\n\t".'<script type="text/javascript" src="js/fog.login.js"></script>';
		print "\n</body>";
		print "\n</html>";
		session_write_close();
		ob_end_flush();
	}

	public function mobileLoginForm()
	{
		ob_start('ob_gzhandler');
		print "\n\t\t\t".'<center><div class="login">';
		print "\n\t\t\t\t".'<p class="loginTitle">'._('FOG Mobile Login').'</p>';
		print "\n\t\t\t\t".'<form method="post" action="?node=login">';
		print "\n\t\t\t\t\t".'<div class="loginElement">'._('Username').':</div><div class="loginElement"><input type="text" class="login" name="uname" /></div>';
		print "\n\t\t\t\t\t".'<div class="loginElement">'._('Password').':</div><div class="loginElement"><input type="password" class="login" name="upass" /></div>';
		$this->getLanguages();
		print "\n\t\t\t\t\t".'<div class="loginElement">'._('Language').':</div><div class="loginElement"><select class="login" name="ulang">'.$this->langMenu.'</select></div>';
		print "\n\t\t\t\t\t".'<p><input type="submit" value="'._('Login').'" /></p>';
		print "\n\t\t\t\t</form>";
		print "\n\t\t\t</div></center>";
		session_write_close();
		ob_end_flush();
	}
}
