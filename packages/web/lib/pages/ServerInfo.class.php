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
				foreach((array)$arRet AS $line) 
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
						$NICTransSized[] = $this->formatByteSize($arNicParts[1]);
						$NICRecSized[] = $this->formatByteSize($arNicParts[2]);
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
					foreach((array)$NICTrans AS $txtran)
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
				foreach((array)$fields AS $field => $input)
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
