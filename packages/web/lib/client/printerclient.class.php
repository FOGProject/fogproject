<?php
class PrinterClient extends FOGClient implements FOGClientSend {
    private function getString($stringsend,&$Printer) {
        if (!$this->newService)
        return sprintf($stringsend,$Printer->get('port'),$Printer->get('file'),$Printer->get('model'),$Printer->get('name'),$Printer->get('ip'),(int)$this->Host->getDefault($Printer->get('id')));
        else
        return sprintf($stringsend,$Printer->get('port'),$Printer->get('file'),$Printer->get('model'),$Printer->get('name'),$Printer->get('ip'),(int)$this->Host->getDefault($Printer->get('id')),$Printer->get('configFile'));
    }
    public function send() {
        try {
            $level = $this->Host->get('printerLevel');
            $Printers = static::getClass('PrinterManager')->find(array('id'=>$this->Host->get('printers')));
            if ($level > 2 || $level <= 0) $level = 0;
            if (!$this->newService && $_REQUEST['sub'] != 'requestClientInfo') {
                $level = "#!mg=$level";
                $this->send = '';
                if ($level === 0) throw new Exception(sprintf('%s%s',base64_encode($level),"\n"));
                $strtosend = "%s|%s|%s|%s|%s|%s";
                array_map(function(&$Printer) use ($strtosend) {
                    if (!$Printer->isValid()) return;
                    $this->send .= sprintf("%s\n",base64_encode($this->getString($strtosend,$Printer)));
                    unset($Printer);
                }, (array)$Printers);
                unset($Printers);
                $this->send = sprintf("%s\n%s",base64_encode($level),$this->send);
            } else {
                if (!static::getClass('PrinterAssociationManager')->count(array('printerID'=>$this->Host->get('printers')))) {
                    if ($this->json) return array('error'=>'np','mode'=>empty($mode) ? 0 : $mode);
                    throw new Exception("#!np\n#mode=$mode\n");
                }
                $modes = array(0,'a','ar');
                $mode = $modes[$this->Host->get('printerLevel')];
                if (!isset($_REQUEST['id'])) {
                    $strtosend = "#printer%s=%s\n";
                    $i = 0;
                    $vals = array();
                    array_map(function(&$Printer) use ($strtosend,$mode,&$i,&$vals) {
                        if (!$Printer->isValid()) return;
                        if ($this->json) {
                            if (!$i) $vals['mode'] = $mode;
                            $vals["printer$i"] = (int)$Printer->get('id');
                        } else {
                            if (!$i) $this->send = "#!ok\n#mode=$mode\n";
                            $this->send .= sprintf($strtosend,$i,$Printer->get('id'));
                        }
                        $i++;
                        unset($Printer);
                    },(array)$Printers);
                    unset($Printers,$count);
                    if ($this->json) return $vals;
                } else {
                    $Printer = static::getClass('Printer',$_REQUEST['id']);
                    if (!$Printer->isValid()) throw new Exception(_('Printer is invalid'));
                    //$strtosend = "#port=%s\n#file=%s\n#model=%s\n#name=%s\n#ip=%s\n#default=%s";
                    $strtosend = "#port=%s\n#file=%s\n#model=%s\n#name=%s\n#ip=%s\n#default=%s\n#configFile=%s";
                    $this->send .= sprintf("#!ok\n#type=%s\n%s",$Printer->get('config'),$this->getString($strtosend,$Printer));
                }
            }
        } catch (Exception $e) {
            if ($this->json) return array('error'=>preg_replace('/^[#][!]\?/','',$e->getMessage()));
            throw new Exception($e->getMessage());
        }
    }
}
