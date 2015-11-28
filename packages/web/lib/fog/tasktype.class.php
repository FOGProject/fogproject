<?php
class TaskType extends FOGController {
    protected $databaseTable = 'taskTypes';
    protected $databaseFields = array(
        'id' => 'ttID',
        'name' => 'ttName',
        'description' => 'ttDescription',
        'icon' => 'ttIcon',
        'kernel' => 'ttKernel',
        'kernelArgs' => 'ttKernelArgs',
        'type' => 'ttType',
        'isAdvanced' => 'ttIsAdvanced',
        'access' => 'ttIsAccess',
    );
    protected $databaseFieldsRequired = array(
        'name',
        'icon',
    );
    public function iconlist($selected = '') {
        $selected = trim($selected);
        if (!($file = fopen('../management/scss/_variables.scss','rb'))) return _('Icon File not found');
        while (($line = fgets($file)) !== false) {
            if (!preg_match('#^\$fa-var-#',$line)) continue;
            $match = trim(preg_replace('#[$:;"\\\]|fa-var-#','',$line));
            $arr = preg_split('#\s#',$match);
            unset($match);
            $icons[trim($arr[0])] = trim($arr[1]);
            unset($arr);
        }
        fclose($file);
        if (!count($icons)) return _('No icons found');
        ksort($icons);
        ob_start();
        echo '<select name="icon" class="fa">';
        foreach ($icons AS $name => &$unicode) {
            printf('<option value="%s" data-icon="%s" data-unicode="%s"%s> %s</option>',
                $name,
                $name,
                $unicode,
                $selected == $name ? ' selected' : '',
                $name
            );
            unset($unicode);
        }
        unset($icons);
        return sprintf('%s</select>',ob_get_clean());
    }
    public function isUpload() {
        return in_array($this->get(id),array(2,16)) || preg_match('#type=(2|16|up)#i',$this->get(kernelArgs));
    }
    public function isSnapinTask() {
        return ($this->isDownload() && $this->get(id) != 17) || in_array($this->get(id),array(12,13));
    }
    public function isDownload() {
        return in_array($this->get(id),array(1,8,15,17,24)) || preg_match('#type=(1|8|15|17|24|down)#i', $this->get(kernelArgs));
    }
    public function isMulticast() {
        return $this->get(id) == 8 || preg_match('#(type=8|mc=yes)#i', $this->get(kernelArgs));
    }
    public function isDebug() {
        return in_array($this->get(id),array(15,16)) || preg_match('#mode=debug#i', $this->get(kernelArgs)) || preg_match('#mode=onlydebug#i', $this->get(kernelArgs));
    }
}
