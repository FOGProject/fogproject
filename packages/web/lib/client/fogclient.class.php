<?php
abstract class FOGClient extends FOGBase {
    protected $newService;
    protected $json;
    protected $Host;
    public function __construct($service = true,$encoded = false,$hostnotrequired = false,$returnmacs = false,$override = false) {
        try {
            parent::__construct();
            header('Content-Type: text/plain');
            $this->newService = isset($_REQUEST['newService']);
            $this->json = isset($_REQUEST['sub']) && $_REQUEST['sub'] == 'requestClientInfo' || isset($_REQUEST['json']);
            $this->Host = $this->getHostItem($service,$encoded,$hostnotrequired,$returnmacs,$override);
            if ($this->json) return $this->send();
            $this->send();
            if (in_array(strtolower(get_class($this)),array('autologout','displaymanager','printerclient','servicemodule'))) throw new Exception($this->send);
            $this->sendData(trim($this->send));
        } catch (Exception $e) {
            if (!$this->json) {
                echo $e->getMessage();
                exit;
            }
            return array('error'=>preg_replace('/^[#][!]\?/','',$e->getMessage()));
        }
    }
}
