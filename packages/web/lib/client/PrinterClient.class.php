<?php
class PrinterClient extends FOGClient implements FOGClientSend {
    public function send() {
        $level = $this->Host->get(printerLevel);
        $Printers = $this->getClass(PrinterManager)->find(array(id=>$this->Host->get(printers)));
        if ($level > 2 || $level <= 0) $level = 0;
        if (!$this->newService) {
            $level = "#!mg=$level";
            $this->send = '';
            if ($level) {
                $strtosend = "%s|%s|%s|%s|%s|%s";
                foreach ($Printers AS $i => &$Printer) {
                    $this->send .= base64_encode(sprintf($strtosend,$Printer->get(port),$Printer->get(file),$Printer->get(model),$Printer->get(name),$Printer->get(ip),(int)$this->Host->getDefault($Printer->get(id))))."\n";
                }
                unset($Printer);
            }
            $this->send = base64_encode($level)."\n".$this->send;
        } else {
            if (!$this->getClass(PrinterAssociationManager)->count(array(printerID=>$this->Host->get(printers)))) throw new Exception("#!np\n#mode=$mode\n");
            $this->send .= "#!ok\n";
            $modes = array(0,'a','ar');
            $mode = $modes[$this->Host->get(printerLevel)];
            if (!isset($_REQUEST[id])) {
                $this->send .= "#mode=$mode\n";
                $strtosend = "#printer%s=%s\n";
                foreach ($Printers AS $i => &$Printer) $this->send .= sprintf($strtosend,$i,$Printer->get(id));
            } else {
                $Printer = $this->getClass(Printer,$_REQUEST[id]);
                $strtosend = "#type=%s\n#port=%s\n#file=%s\n#model=%s\n#name=%s\n#ip=%s\n#default=%s";
                foreach ($Printers AS $i => &$Printer) {
                    $this->send = sprintf($strtosend,$Printer->get(config),$Printer->get(port),$Printer->get(file),$Printer->get(model),$Printer->get(name),$Printer->get(ip),$this->Host->getDefault($Printer->get(id)));
                }
            }
        }
    }
}
