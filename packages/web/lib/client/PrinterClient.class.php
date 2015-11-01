<?php
class PrinterClient extends FOGClient implements FOGClientSend {
    public function send() {
        $level = $this->Host->get('printerLevel');
        $Printers = $this->getClass('PrinterManager')->find(array('id'=>$this->Host->get('printers')));
        if ($level > 2 || $level <= 0) $level = 0;
        if (!$this->newService) {
            $level = "#!mg=$level";
            $this->send = '';
            if ($level) {
                $strtosend = "%s|%s|%s|%s|%s|%s";
                foreach ($Printers AS $i => &$Printer) {
                    if (!$Printer->isValid()) continue;
                    $this->send .= base64_encode(sprintf($strtosend,$Printer->get('file'),$Printer->get('model'),$Printer->get('name'),$Printer->get('ip'),(int)$this->Host->getDefault($Printer->get('id'))))."\n";
                    unset($Printer);
                }
                unset($Printers);
            }
            $this->send = base64_encode($level)."\n".$this->send;
        } else {
            if (!$this->getClass('PrinterAssociationManager')->count(array('printerID'=>$this->Host->get('printers')))) throw new Exception("#!np\n#mode=$mode\n");
            $modes = array(0,'a','ar');
            $mode = $modes[$this->Host->get('printerLevel')];
            if (!isset($_REQUEST['id'])) {
                $strtosend = "#printer%s=%s\n";
                $count = 0;
                foreach ($Printers AS $i => &$Printer) {
                    if (!$Printer->isValid()) continue;
                    if ($count == 0) $this->send = "#!ok\n#mode=$mode\n";
                    $count++;
                    $this->send .= sprintf($strtosend,$count,$Printer->get('id'));
                    unset($Printer);
                }
                unset($Printers,$count);
            } else {
                $Printer = $this->getClass('Printer',$_REQUEST['id']);
                if (!$Printer->isValid()) throw new Exception(_('Printer is invalid'));
                $strtosend = "#!ok\n#type=%s\n#port=%s\n#file=%s\n#model=%s\n#name=%s\n#ip=%s\n#default=%s";
                $this->send .= sprintf($strtosend,$Printer->get('config'),$Printer->get('port'),$Printer->get('file'),$Printer->get('model'),$Printer->get('name'),$Printer->get('ip'),(int)$this->Host->getDefault($Printer->get('id')));
            }
        }
    }
}
