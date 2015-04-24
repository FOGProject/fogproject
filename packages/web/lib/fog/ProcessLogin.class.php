<?php
class ProcessLogin extends FOGBase
{
	private $username, $password, $currentUser, $langSet;
	private $mobileMenu, $langMenu;
	private function getLanguages()
	{
		$translang = $this->transLang();
		foreach($this->foglang['Language'] AS $lang)
			$this->langMenu .= "\t\t\t\t\t\t\t\t".'<option value="'.$lang.'" '.($translang == $lang ? 'selected="selected"' : '').'>'.$lang.'</option>'."\n";
	}

	private function defaultLang()
	{
		$deflang = $this->FOGCore->getSetting('FOG_DEFAULT_LOCALE');
		foreach($this->foglang['Language'] AS $lang => $val)
		{
			if ($deflang == $lang)
				$data = array($lang,$val);
			else
				$data = array('en','English');
		}
		return $data;
	}

	private function transLang()
	{
		switch($_SESSION['locale'])
		{
			case 'en_US.UTF-8':
				return $this->foglang['Language']['en'];
			break;
			case 'it_IT.UTF-8':
				return $this->foglang['Language']['it'];
			break;
			case 'es_ES.UTF-8':
				return $this->foglang['Language']['es'];
			break;
			case 'fr_FR.UTF-8':
				return $this->foglang['Language']['fr'];
			break;
			case 'zh_CN.UTF-8':
				return $this->foglang['Language']['zh'];
			break;
			case 'de_DE.UTF-8':
				return $this->foglang['Language']['de'];
			break;
			case 'pt_BR.UTF-8':
				return $this->foglang['Language']['pt'];
			break;
			default :
				$lang = $this->defaultLang();
				return $this->foglang['Language'][$lang[0]]; 
			break;
		}
	}

	private function specLang()
	{
		switch ($_REQUEST['ulang'])
		{
			case $this->foglang['Language']['en']:
				$_REQUEST['ulang'] = 'en_US.UTF-8';
				break;
			case $this->foglang['Language']['fr']:
				$_REQUEST['ulang'] = 'fr_FR.UTF-8';
				break;
			case $this->foglang['Language']['it']:
				$_REQUEST['ulang'] = 'it_IT.UTF-8';
				break;
			case $this->foglang['Language']['zh']:
				$_REQUEST['ulang'] = 'zh_CN.UTF-8';
				break;
			case $this->foglang['Language']['es']:
				$_REQUEST['ulang'] = 'es_ES.UTF-8';
				break;
			case $this->foglang['Language']['de']:
				$_REQUEST['ulang']	= 'de_DE.UTF-8';
				break;
			case $this->foglang['Language']['pt']:
				$_REQUEST['ulang'] = 'pt_BR.UTF-8';
				break;
			default :
				$lang = $this->defaultLang();
				$_REQUEST['ulang'] = $lang[1];
				break;
		}
	}

	public function setLang()
	{
		if ($_REQUEST['ulang'])
		{
			$this->specLang();
			$_SESSION['locale'] = $_REQUEST['ulang'];
			putenv("LC_ALL=".$_SESSION['locale']);
			setlocale(LC_ALL,$_SESSION['locale']);
			bindtextdomain('messages','languages');
			textdomain('messages');
		}
	}

	private function setCurUser($tmpUser)
	{
		$currentUser = $tmpUser;
		$currentUser->set('authTime', time());
		$currentUser->set('authIP',$_SERVER['REMOTE_ADDR']);
		$_SESSION['FOG_USER'] = serialize($currentUser);
		$_SESSION['FOG_USERNAME'] = $currentUser->get('name');
		$this->setRedirMode();
		$this->currentUser = $currentUser;
		// Hook
		if (!preg_match('#/mobile/#',$_SERVER['PHP_SELF']))
			$this->HookManager->processEvent('LoginSuccess', array('user' => &$currentUser, 'username' => $this->username, 'password' => &$this->password));
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
		$this->setLang();
		// Hook
		$this->EventManager->notify('LoginFail', array(Failure=>$this->username));
		$this->HookManager->processEvent('LoginFail', array('username' => &$this->username, 'password' => &$this->password));
		$this->FOGCore->setMessage($string);
	}

	public function processMainLogin()
	{
		$this->setLang();
		if(isset($_REQUEST['uname']) && isset($_REQUEST['upass']))
		{
			$this->username = trim($_REQUEST['uname']);
			$this->password = trim($_REQUEST['upass']);
			$tmpUser = $this->FOGCore->attemptLogin($this->username, $this->password);
			// Hook
			$this->HookManager->processEvent('USER_LOGGING_IN', array('User' => &$tmpUser,'username' => &$this->username, 'password' => &$this->password));
			try
			{
				if (!$tmpUser || !$tmpUser->isValid())
					throw new Exception($this->foglang['InvalidLogin']);
				if (!preg_match('#/mobile/#',$_SERVER['PHP_SELF']))
				{
					if ($tmpUser->get('type') == 0 && $tmpUser->get('type') != 1)
						$this->setCurUser($tmpUser);
					else if ($tmpUser->get('type') == 0)
						throw new Exception($this->foglang['NotAllowedHere']);
				}
				else
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
		$this->setLang();
		print "\n\t\t\t\t\t".'<form method="post" action="?node=login" id="login-form">';
		if ($_GET['node'] != 'logout')
		{
			foreach ($_GET AS $key => $value)
				print "\n\t\t\t\t\t\t".'<input type ="hidden" name="'.$key.'" value="'.$value.'" />';
		}
		print "\n\t\t\t\t\t\t".'<label for="username">'.$this->foglang['Username'].'</label>';
		print "\n\t\t\t\t\t\t".'<input type="text" class="input" name="uname" id="username" />';
		print "\n\t\t\t\t\t\t".'<label for="password">'.$this->foglang['Password'].'</label>';
		print "\n\t\t\t\t\t\t".'<input type="password" class="input" name="upass" id="password" />';
		print "\n\t\t\t\t\t\t".'<label for="language">'.$this->foglang['LanguagePhrase'].'</label>';
		$this->getLanguages();
		print "\n\t\t\t\t\t\t".'<select name="ulang" id="language">'.$this->langMenu.'</select>';
		print "\n\t\t\t\t\t\t".'<label for="login-form-submit">&nbsp;</label>';
		print "\n\t\t\t\t\t\t".'<input type="submit" value="'.$this->foglang['Login'].'" id="login-form-submit" />';
		print "\n\t\t\t\t\t</form>";
		print "\n\t\t\t\t\t".'<div id="login-form-info">';
		print "\n\t\t\t\t\t\t<p>".$this->foglang['FOGSites'].': <b><i class="icon fa fa-circle-o-notch fa-spin fa-1x"></i></b></p>';
		print "\n\t\t\t\t\t\t<p>".$this->foglang['LatestVer'].': <b><i class="icon fa fa-circle-o-notch fa-spin fa-1x"></i></b></p>';
		print "\n\t\t\t\t\t</div>";
	}

	public function mobileLoginForm()
	{
		$this->setLang();
		print "\t<center>\n";
		print "\t\t\t".'<div class="login">'."\n";
		print "\t\t\t\t".'<p class="loginTitle">'.$this->foglang['FOGMobile']."</p>\n";
		print "\t\t\t\t\t".'<form method="post" action="?node=login">'."\n";
		print "\t\t\t\t\t\t".'<div class="loginElement">'.$this->foglang['Username'].':</div><div class="loginElement"><input type="text" class="login" name="uname" /></div>'."\n";
		print "\t\t\t\t\t\t".'<div class="loginElement">'.$this->foglang['Password'].':</div><div class="loginElement"><input type="password" class="login" name="upass" /></div>'."\n";
		$this->getLanguages();
		print "\t\t\t\t\t\t".'<div class="loginElement">'.$this->foglang['LanguagePhrase'].':</div><div class="loginElement">'."\n";
		print "\t\t\t\t\t\t\t".'<select class="login" name="ulang">'."\n";
		print $this->langMenu;
		print "\t\t\t\t\t\t\t</select>\n";
		print "\t\t\t\t\t\t</div>\n";
		print "\t\t\t\t\t\t".'<p><input type="submit" value="'.$this->foglang['Login'].'" /></p>'."\n";
		print "\t\t\t\t\t</form>\n";
		print "\t\t\t\t</div>\n";
		print "\t\t\t</center>\n";
	}
}
