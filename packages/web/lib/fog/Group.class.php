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
	);
    // Overides
    private function loadHosts()
    {   
        if (!$this->isLoaded('hosts'))
        {   
            if ($this->get('id'))
            {   
                $GroupAssocs = $this->FOGCore->getClass('GroupAssociationManager')->find(array('groupID' => $this->get('id')));
                foreach($GroupAssocs AS $GroupAssoc)
                    $this->add('hosts', new Host($GroupAssoc->get('hostID')));
            }   
        }   
        return $this;
    }

	public function getHostCount()
	{
		$i = 0;
		foreach($this->get('hosts') AS $Host)
			$Host && $Host->isValid() ? $i++ : null;
		return $i;
	}

    public function get($key = '') 
    {   
        if ($this->key($key) == 'hosts')
            $this->loadHosts();
        return parent::get($key);
    }   

    public function set($key, $value)
    {   
        if ($this->key($key) == 'hosts')
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
        if ($this->key($key) == 'hosts' && !($value instanceof Host))
        {   
            $this->loadHosts();
            $value = new Host($value);
        }   
        // Add
        return parent::add($key, $value);
    }

    public function remove($key, $object)
    {   
        if ($this->key($key) == 'hosts')
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
            $this->FOGCore->getClass('GroupAssociationManager')->destroy(array('groupID' => $this->get('id')));
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

	// Custom Variables
	function doMembersHaveUniformImages()
	{
		foreach ($this->get('hosts') AS $Host)
			$images[] = $Host->get('imageID');
		$images = array_unique($images);
		return (count($images) == 1 ? true : false);
	}
}
