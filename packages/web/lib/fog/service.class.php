<?php
class Service extends FOGController {
    protected $databaseTable = 'globalSettings';
    protected $databaseFields = array(
        'id' => 'settingID',
        'name' => 'settingKey',
        'description' => 'settingDesc',
        'value' => 'settingValue',
        'category' => 'settingCategory',
    );
    protected $databaseFieldsRequired = array(
        'name',
    );
    public function addDir($dir) {
        if (self::getClass(DirCleanerManager)->count(array('path'=>$dir)) > 0) throw new Exception($this->foglang['n/a']);
        self::getClass(DirCleaner)
            ->set(path,$dir)
            ->save();
    }
    public function remDir($dir) {
        self::getClass(DirCleanerManager)->destroy(array(id=>$dir));
    }
    public function setDisplay($x,$y,$r) {
        $keySettings = array(
            'FOG_SERVICE_DISPLAYMANAGER_X' => $x,
            'FOG_SERVICE_DISPLAYMANAGER_Y' => $y,
            'FOG_SERVICE_DISPLAYMANAGER_R' => $r,
        );
        foreach($keySettings AS $name => $value) $this->FOGCore->setSetting($name,$value);
    }
    public function setGreenFog($h,$m,$t) {
        if (self::getClass(GreenFogManager)->count(array(hour=>$h,'min'=>$m))>0) throw new Exception($this->foglang[TimeExists]);
        else {
            self::getClass(GreenFog)
                ->set(hour,$h)
                ->set('min',$m)
                ->set(action,$t)
                ->save();
        }
    }
    public function remGF($gf) {
        self::getClass(GreenFogManager)->destroy(array(id=>$gf));
    }
    public function addUser($user) {
        if (self::getClass(UserCleanupManager)->count(array(name=>$user))>0) throw new Exception($this->foglang[UserExists]);
        foreach ((array)$user AS $i => &$name) self::getClass(User)->set(name,$name)->save();
        unset($name);
    }
    public function remUser($id) {
        self::getClass(UserCleanup,$id)->destroy();
    }
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
