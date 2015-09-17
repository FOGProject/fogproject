<?php
class Service extends FOGController {
    // Table
    public $databaseTable = 'globalSettings';
    // Name -> Database field name
    public $databaseFields = array(
        'id' => 'settingID',
        'name' => 'settingKey',
        'description' => 'settingDesc',
        'value' => 'settingValue',
        'category' => 'settingCategory',
    );
    // Required database fields
    public $databaseFieldsRequired = array(
        'name',
    );
    //Add a directory to be cleaned
    public function addDir($dir) {
        if ($this->getClass(DirCleanerManager)->count(array(path=>addslashes($dir))) > 0) throw new Exception($this->foglang['n/a']);
        $this->getClass(DirCleaner)
            ->set(path,$dir)
            ->save();
    }
    //Remove a directory from being cleaned
    public function remDir($dir) {
        $this->getClass(DirCleanerManager)->destroy(array(id=>$dir));
    }
    //Set the display information.
    public function setDisplay($x,$y,$r) {
        $keySettings = array(
            'FOG_SERVICE_DISPLAYMANAGER_X' => $x,
            'FOG_SERVICE_DISPLAYMANAGER_Y' => $y,
            'FOG_SERVICE_DISPLAYMANAGER_R' => $r,
        );
        foreach($keySettings AS $name => $value) $this->FOGCore->setSetting($name,$value);
    }
    //Set green fog
    public function setGreenFog($h,$m,$t) {
        if ($this->getClass(GreenFogManager)->count(array(hour=>$h,'min'=>$m))>0) throw new Exception($this->foglang[TimeExists]);
        else {
            $this->getClass(GreenFog)
                ->set(hour,$h)
                ->set('min',$m)
                ->set(action,$t)
                ->save();
        }
    }
    //Remove GreenFog event
    public function remGF($gf) {
        $this->getClass(GreenFogManager)->destroy(array(id=>$gf));
    }
    //Add Users for cleanup
    public function addUser($user) {
        if ($this->getClass(UserCleanupManager)->count(array(name=>$user))>0) throw new Exception($this->foglang[UserExists]);
        foreach ((array)$user AS $i => &$name) $this->getClass(User)->set(name,$name)->save();
        unset($name);
    }
    //Remove Cleanup user
    public function remUser($id) {
        $this->getClass(UserCleanup,$id)->destroy();
    }
    // Select option statement for exit types
    /** buildExitSelector creates select option statement for exit
     * @param $name the name to generate the select under
     * @param $selected the item to show as selected
     * @param $nullField if we need a null option
     * @return the built select statement
     */
    public static function buildExitSelector($name = '',$selected = '',$nullField = false) {
        if (empty($name)) $name = $this->get(name);
        $types = array(
            'sanboot',
            'grub',
            'grub_first_hdd',
            'grub_first_cdrom',
            'grub_first_found_windows',
            'refind_efi',
            'exit',
        );
        if ($nullField) array_unshift($types,sprintf(' - %s -',_('Please Select an option')));
        $options = sprintf('<select name="%s" autocomplete="off">',$name);
        foreach ($types AS $i => &$viewop) {
            $show = strtoupper($viewop);
            $value = $viewop;
            if ($nullField && $i == 0) {
                $show = $viewop;
                $value = '';
            }
            $options .= sprintf('<option value="%s"%s>%s</option>',$value,strtolower($selected) == $value ? 'selected' : '',$show);
        }
        unset ($viewop);
        return $options.'</select>';
    }
}
