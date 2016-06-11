<?php
class PrinterClient extends FOGClient implements FOGClientSend {
    private static $modes = array(0,'a','ar');
    public function json() {
        $level = $this->Host->get('printerLevel');
        if (!in_array($level,array_keys(self::$modes))) $level = 0;
        $allPrinters = self::getSubObjectIDs('Printer','','name');
        if (count($this->Host->get('printers')) < 1) return array(
            'error'=>'np',
            'mode'=> self::$modes[$level] ? self::$modes[$level] : (int)self::$modes[$level],
            'allPrinters'=>$allPrinters,
            'default' => '',
            'printers' => array(
                array('error'=>'np'),
            ),
        );
        $default = self::getClass('Printer',@max(self::getSubObjectIDs('PrinterAssociation',array('hostID'=>$this->Host->get('id'),'isDefault'=>1),'printerID')));
        $default = $default->isValid() ? $default = $default->get('name') : '';
        return array(
            'mode'=> self::$modes[$level] ? self::$modes[$level] : (int)self::$modes[$level],
            'allPrinters' => $allPrinters,
            'default' => $default,
            'printers' => array_map(function(&$Printer) {
                return array(
                    'type' => $Printer->get('config'),
                    'port' => $Printer->get('port'),
                    'file' => $Printer->get('file'),
                    'model' => $Printer->get('model'),
                    'name' => $Printer->get('name'),
                    'ip' => $Printer->get('ip'),
                    'configFile' => $Printer->get('configFile'),
                );
            },(array)self::getClass('PrinterManager')->find(array('id'=>$this->Host->get('printers')))),
        );
    }
    private function getString($stringsend,&$Printer) {
        if (!$this->newService)
            return sprintf($stringsend,$Printer->get('port'),$Printer->get('file'),$Printer->get('model'),$Printer->get('name'),$Printer->get('ip'),(int)$this->Host->getDefault($Printer->get('id')));
        else
            return sprintf($stringsend,$Printer->get('port'),$Printer->get('file'),$Printer->get('model'),$Printer->get('name'),$Printer->get('ip'),(int)$this->Host->getDefault($Printer->get('id')),$Printer->get('configFile'));
    }
    public function send() {
        $level = $this->Host->get('printerLevel');
        if (!in_array($level,self::$modes)) $level = 0;
        if (!$this->newService || $level === 0) $level = (int)$level;
        if (self::getClass('PrinterAssociationManager')->count(array('printerID'=>$this->Host->get('printers'),'hostID'=>$this->Host->get('id'))) < 1) throw new Exception($this->newService ? sprintf("#!np\n#mode=%s\n",self::$modes[$level]) : sprintf("%s\n",base64_encode("#!mg=$level")));
        $Printers = (array)self::getClass('PrinterManager')->find(array('id'=>$this->Host->get('printers')));
        $default = array_filter(array_map(function(&$Printer) {
            return (int)$this->Host->getDefault($Printer->get('id'));
        },$Printers));
        if (!$this->newService) {
            $strtosend = "%s|%s|%s|%s|%s|%s";
            array_map(function(&$Printer) use ($strtosend) {
                if (!$Printer->isValid()) return;
                $this->send .= sprintf("%s\n",base64_encode($this->getString($strtosend,$Printer)));
                unset($Printer);
            },$Printers);
        } else {
            $Printer = self::getClass('Printer',isset($_REQUEST['id']) && is_numeric($_REQUEST['id']) ? $_REQUEST['id'] : 0);
            if ($Printer->isValid()) {
                $strtosend = "#port=%s\n#file=%s\n#model=%s\n#name=%s\n#ip=%s\n#default=%s\n#configFile=%s";
                $this->send = sprintf("#!ok\n#type=%s\n%s",$Printer->get('config'),$this->getString($strtosend,$Printer));
            } else if (isset($_REQUEST['id']) && !$Printer->isValid()) throw new Exception(_('Printer is invalid'));
            else {
                $index = 0;
                $strtosend = "#printer%s=%s\n";
                array_map(function(&$Printer) use ($strtosend,&$index) {
                    if (!$Printer->isValid()) return;
                    if ($index === 0) $this->send = sprintf("#!ok\n#mode=%s\n",self::$modes[$level]);
                    $this->send .= sprintf($strtosend,$index,$Printer->get('id'));
                    $index++;
                    unset($Printer);
                },$Printers);
            }
        }
    }
}
