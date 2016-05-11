<?php
class Jobs extends FOGClient implements FOGClientSend {
    public function send() {
        try {
            $this->send = '#!nj';
            $Task = $this->Host->get('task');
            if ($Task->isValid() && $Task->isInitNeededTasking()) $this->send = '#!ok';
            if ($this->json && stripos(strtolower($_SERVER['SCRIPT_NAME']),'jobs.php')) throw new Exception($this->send);
            if ($this->json) return array('job'=>(bool)preg_match('#ok#i',preg_replace('/^[#][!]/','',$this->send)));
        } catch (Exception $e) {
            if ($this->json) {
                echo json_encode(array('error'=>preg_replace('/^[#][!]?/','',$e->getMessage())));
                exit;
            }
            throw new Exception($e->getMessage());
        }
    }
}
