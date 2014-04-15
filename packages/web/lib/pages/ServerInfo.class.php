<?php
/**	Class Name: ServerInfo
	FOGPage lives in: {fogwebdir}/lib/fog
	Lives in: {fogwebdir}/lib/pages
	Description: This is an extension of the FOGPage Class
	This is only used when a user clicks on the free space
	pie chart.  This class displays the server information.

	Useful for:
	Obtaining information about the server.
*/
class ServerInfo extends FOGPage
{
	// Base variables
	var $name = 'Hardware Information';
	var $node = 'hwinfo';
	var $id = 'id';
	// Menu Items
	var $menu = array(
	);
	var $subMenu = array(
	);
	// Pages
	public function index()
	{   
        $this->home();
	}
    public function home()
    {
		$StorageNode = new StorageNode($_REQUEST['id']);
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
        if ($StorageNode != null)
		{
            if ($ret = $this->FOGCore->fetchURL('http://' . $StorageNode->get('ip') . '/fog/status/hw.php'))
			{
				$arRet = explode( "\n", $ret );
				$section = 0; //general
				$arGeneral = array();
				$arFS = array();
				$arNIC = array();
				foreach( $arRet as $line ) 
				{
					$line = trim( $line );
					if ( $line == "@@start" ) {}
					else if ( $line == "@@general" )
						$section = 0;
					else if ( $line == "@@fs" ) 
						$section = 1;
					else if ( $line == "@@nic" ) 
						$section = 2;
					else if ( $line == "@@end" ) 
						$section = 3;
                    else
                    { 
						if ( $section == 0 )
							$arGeneral[] = $line;												
						else if ( $section == 1 )
							$arFS[] = $line;
						else if ( $section == 2 )
							$arNIC[] = $line;
                    }
				}
				for($i=0;$i<count($arNIC);$i++)
				{
					$arNicParts = explode( "$$", $arNIC[$i] );
					if (count($arNicParts) == 5) 
					{
						$kb = 1024;
						$mb = 1024* $kb;
						$gb = $mb * $kb;
						$tb = $gb * $kb;
						$pb = $tb * $kb;
						$eb = $pb * $kb;
						$zb = $eb * $kb;
						$yb = $zb * $kb;
						if ($arNicParts[1] >= $yb){$NICTransSized[] = round($arNicParts[1]/$yb,2).' YiB';}
						else if ($arNicParts[1] >= $zb && $arNicParts[1] < $yb){$NICTransSized[] = round($arNicParts[1]/$zb,2).' ZiB';}
						else if ($arNicParts[1] >= $eb && $arNicParts[1] < $zb){$NICTransSized[] = round($arNicParts[1]/$eb,2).' EiB';}
						else if ($arNicParts[1] >= $pb && $arNicParts[1] < $eb){$NICTransSized[] = round($arNicParts[1]/$pb,2).' PiB';}
						else if ($arNicParts[1] >= $tb && $arNicParts[1] < $pb){$NICTransSized[] = round($arNicParts[1]/$tb,2).' TiB';}
						else if ($arNicParts[1] >= $gb && $arNicParts[1] < $tb){$NICTransSized[] = round($arNicParts[1]/$gb,2).' GiB';}
						else if ($arNicParts[1] >= $mb && $arNicParts[1] < $gb){$NICTransSized[] = round($arNicParts[1]/$mb,2).' MiB';}
						else if ($arNicParts[1] >= $kb && $arNicParts[1] < $mb){$NICTransSized[] = round($arNicParts[1]/$kb,2).' KiB';}
						else if ($arNicParts[1] < $kb){$NICTranSized .= round($arNicParts[1],2).' iB';}
						if ($arNicParts[2] >= $yb){$NICRecSized[] = round($arNicParts[2]/$yb,2).' YiB';}
						else if ($arNicParts[2] >= $zb && $arNicParts[2] < $yb){$NICRecSized[] = round($arNicParts[2]/$zb,2).' ZiB';}
						else if ($arNicParts[2] >= $eb && $arNicParts[2] < $zb){$NICRecSized[] = round($arNicParts[2]/$eb,2).' EiB';}
						else if ($arNicParts[2] >= $pb && $arNicParts[2] < $eb){$NICRecSized[] = round($arNicParts[2]/$pb,2).' PiB';}
						else if ($arNicParts[2] >= $tb && $arNicParts[2] < $pb){$NICRecSized[] = round($arNicParts[2]/$tb,2).' TiB';}
						else if ($arNicParts[2] >= $gb && $arNicParts[2] < $tb){$NICRecSized[] = round($arNicParts[2]/$gb,2).' GiB';}
						else if ($arNicParts[2] >= $mb && $arNicParts[2] < $gb){$NICRecSized[] = round($arNicParts[2]/$mb,2).' MiB';}
						else if ($arNicParts[2] >= $kb && $arNicParts[2] < $mb){$NICRecSized[] = round($arNicParts[2]/$kb,2).' KiB';}
						else if ($arNicParts[2] < $kb){$NICRecSized[] = round($arNicParts[2],2).' iB';}
						$NICErrInfo[] = $arNicParts[3];
						$NICDropInfo[] = $arNicParts[4];
						$NICTrans[] = $arNicParts[0].' '._('TX');
						$NICRec[] = $arNicParts[0].' '._('RX');
						$NICErr[] =	$arNicParts[0].' '._('Errors');
						$NICDro[] = $arNicParts[0].' '._('Dropped');
					}
				}
				if(count($arGeneral)>=1)
				{
					$fields = array(
						'<b>'._('General Information') => '&nbsp;',
						_('Storage Node') => $StorageNode->get('name'),
						_('IP') => $StorageNode->get('ip'),
						_('Kernel') => $arGeneral[0],
						_('Hostname') => $arGeneral[1],
						_('Uptime') => $arGeneral[2],
						_('CPU Type') => $arGeneral[3],
						_('CPU Count') => $arGeneral[4],
						_('CPU Model') => $arGeneral[5],
						_('CPU Speed') => $arGeneral[6],
						_('CPU Cache') => $arGeneral[7],
						_('Total Memory') => $arGeneral[8],
						_('Used Memory') => $arGeneral[9],
						_('Free Memory') => $arGeneral[10],
						'<b>'._('File System Information').'</b>' => '&nbsp;',
						_('Total Disk Space') => $arFS[0],
						_('Used Disk Space') => $arFS[1],
						'<b>'._('Network Information').'</b>' => '&nbsp;',
					);
					$i = 0;
					foreach($NICTrans AS $txtran)
					{
						$ethName = explode(' ',$NICTrans[$i]);
						$fields['<b>'.$ethName[0].' '._('Information').'</b>'] = '&nbsp;';
						$fields[$NICTrans[$i]] = $NICTransSized[$i];
						$fields[$NICRec[$i]] = $NICRecSized[$i];
						$fields[$NICErr[$i]] = $NICErrInfo[$i];
						$fields[$NICDro[$i]] = $NICDropInfo[$i];
						$i++;
					}
				}
				foreach($fields AS $field => $input)
				{
					$this->data[] = array(
						'field' => $field,
						'input' => $input,
					);
				}
				// Hook
				$this->HookManager->processEvent('SERVER_INFO_DISP', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
				// Output
				$this->render();
			}
			else
				print "\n\t\t\t".'<p>'._('Unable to pull server information!').'</p>';
		}
		else
			print "\n\t\t\t".'<p>'._('Invalid Server Information!').'</p>';
    }
}
// Register page with FOGPageManager
$FOGPageManager->register(new ServerInfo());
