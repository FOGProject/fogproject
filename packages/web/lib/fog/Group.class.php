<?php
/** \class Group
	Gets groups created and handling methods.
*/
class Group extends FOGController
{
	// Table
	public $databaseTable = 'groups';
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'groupID',
		'name'		=> 'groupName',
		'description'	=> 'groupDesc',
		'createdBy'	=> 'groupCreateBy',
		'createdTime'	=> 'groupDateTime',
		'building'	=> 'groupBuilding',
		'kernel'	=> 'groupKernel',
		'kernelArgs'	=> 'groupKernelArgs',
		'kernelDevice'	=> 'groupPrimaryDisk'
	);
	// Allow setting / getting of these additional fields
	public $additionalFields = array(
		'hosts',
		'hostsnotinme',
		'nogroup',
	);
	// field class associations
	public $databaseClassFieldRelationships = array(
		'GroupAssociation' => array('groupID','id','hosts','hostID')
	);
    // Overides
    private function loadHosts()
    {   
        if (!$this->isLoaded('hosts') && $this->get('id'))
        {
			// All hosts in a any group
			$GroupHostIDs = array_unique($this->getClass('GroupAssociationManager')->find('','','','','','','','hostID'));
			// Hosts not in any group
			$NoGroupIDs = $this->getClass('HostManager')->find(array('id' => $GroupHostIDs),'','','','','',true,'id');
			// Hosts in Me
			$GroupHostMeIDs = array_unique($this->getClass('GroupAssociationManager')->find(array('groupID' => $this->get('id')),'','','','','','','hostID'));
			if ($GroupHostMeIDs)
			{
				// Hosts In Me find->push
				foreach($this->getClass('HostManager')->find(array('id' => $GroupHostMeIDs)) AS $Host)
					$this->add('hosts',$Host);
				unset($Host);
				// Hosts not in this group and not existing in the other group.
				if (count($this->get('hosts')))
				{
					// Only get the list of hosts if they don't already exist in no group.
					$GroupIDs = array_unique(array_merge((array)$NoGroupIDs,(array)$GroupHostMeIDs));
					// Hosts not in me and not in the no group find->push
					foreach($this->getClass('HostManager')->find(array('id' => $GroupIDs),'','','','','',true) AS $Host)
						$this->add('hostsnotinme',$Host);
				}
			}
			// Hosts known to not be in any group
			foreach($this->getClass('HostManager')->find(array('id' => $NoGroupIDs)) AS $Host)
				$this->add('nogroup',$Host);
		}
		return $this;
	}
	public function getHostCount()
	{
		return $this->getClass('GroupAssociationManager')->count(array('groupID' => $this->get('id')));
	}
    public function get($key = '') 
    {   
        if ($this->key($key) == 'hosts' || $this->key($key) == 'hostsnotinme' || $this->key($key) == 'nogroup')
            $this->loadHosts();
        return parent::get($key);
    }   
    public function set($key, $value)
    {   
        if ($this->key($key) == 'hosts' || $this->key($key) == 'hostsnotinme' || $this->key($key) == 'nogroup')
        {   
            foreach((array)$value AS $Host)
                $newValue[] = ($Host instanceof Host ? $Host : new Host($Host));
            $value = (array)$newValue;
        }   
        // Set
        return parent::set($key, $value);
    }   

    public function add($key, $value)
    {   
        if (($this->key($key) == 'hosts' || $this->key($key) == 'hostsnotinme' || $this->key($key) == 'nogroup') && !($value instanceof Host))
        {   
            $this->loadHosts();
            $value = new Host($value);
        }   
        // Add
        return parent::add($key, $value);
    }

    public function remove($key, $object)
    {   
        if ($this->key($key) == 'hosts' || $this->key($key) == 'hostsnotinme' || $this->key($key) == 'nogroup')
            $this->loadHosts();
        // Remove
        return parent::remove($key, $object);
    }

    public function save()
    {
        parent::save();
        if ($this->isLoaded('hosts'))
        {
            // Remove all old entries.
            $this->getClass('GroupAssociationManager')->destroy(array('groupID' => $this->get('id')));
            // Create new Assocs
            foreach ((array)$this->get('hosts') AS $Host)
            {
                if (($Host instanceof Host) && $Host->isValid())
                {
                    $NewGroup = new GroupAssociation(array(
                        'groupID' => $this->get('id'),
                        'hostID' => $Host->get('id'),
                    ));
                    $NewGroup->save();
                }
            }
        }
        return $this;
    }

    public function addHost($addArray)
    {
        // Add
        foreach((array)$addArray AS $item)
            $this->add('hosts', $item);
        // Return
        return $this;
    }

    public function removeHost($removeArray)
    {
        // Iterate array (or other as array)
        foreach ((array)$removeArray AS $remove)
            $this->remove('hosts', ($remove instanceof Host ? $remove : new Host((int)$remove)));
        // Return
        return $this;
    }

	public function addImage($imageID)
	{
		if (!$imageID)
			throw new Exception(_('Select an image'));
		$Image = ($imageID instanceof Image ? $imageID : new Image((int)$imageID));
		foreach($this->get('hosts') AS $Host)
		{
			if ($Host && $Host->isValid())
			{
				if ($Host->get('task') && $Host->get('task')->isValid())
					throw new Exception(_('There is a host in a tasking'));
				if (!$Image || !$Image->isValid())
					throw new Exception(_('Image is not valid'));
				else
					$Host->set('imageID', $Image->get('id'));
				$Host->save();
			}
		}
		return $this;
	}

	public function addSnapin($snapArray)
	{
		foreach($this->get('hosts') AS $Host)
		{
			if ($Host && $Host->isValid())
				$Host->addSnapin($snapArray)->save();
		}
		return $this;
	}

	public function removeSnapin($snapArray)
	{
		foreach($this->get('hosts') AS $Host)
		{
			if ($Host && $Host->isValid())
				$Host->removeSnapin($snapArray)->save();
		}
		return $this;
	}

	public function setAD($useAD, $domain, $ou, $user, $pass)
	{
		foreach($this->get('hosts') AS $Host)
		{
			if ($Host && $Host->isValid())
				$Host->setAD($useAD,$domain,$ou,$user,$pass)->save();
		}
		return $this;
	}

	public function addPrinter($printAdd,$printDel,$level = 0)
	{
		foreach($this->get('hosts') AS $Host)
		{
			if ($Host && $Host->isValid())
			{
				$Host->set('printerLevel',$level)
					 ->addPrinter($printAdd)
					 ->removePrinter($printDel)
					 ->save();
				if ($default)
					$Host->updateDefault($default);
			}
		}
		return $this;
	}

	public function updateDefault($printerid,$onoff)
	{
		foreach($this->get('hosts') AS $Host)
		{
			if ($Host && $Host->isValid())
			{
				foreach($printerid AS $printer)
				{
					$Printer = new Printer($printer);
					if ($Printer && $Printer->isValid())
					{
						if ($Printer->get('id') == $onoff)
							$Host->updateDefault($Printer->get('id'),1);
						else
							$Host->updateDefault($Printer->get('id'),0);
					}
				}
			}
		}
		return $this;
	}

	// Custom Variables
	public function doMembersHaveUniformImages()
	{
		foreach ($this->get('hosts') AS $Host)
			$images[] = $Host->get('imageID');
		$images = array_unique($images);
		return (count($images) == 1 ? true : false);
	}
	public function destroy($field = 'id')
	{
		// Remove All Host Associations
		$this->getClass('GroupAssociationManager')->destroy(array('groupID' => $this->get('id')));
		// Return
		return parent::destroy($field);
	}
}
