<?php
abstract class FOGClient extends FOGBase {
    protected $newService;
    protected $json;
    protected $Host;
    public function __construct($service = true,$encoded = false,$hostnotrequired = false,$returnmacs = false,$override = false) {
        try {
            parent::__construct();
            $this->newService = isset($_REQUEST['newService']);
            $this->json = (isset($_REQUEST['sub']) && $_REQUEST['sub'] == 'requestClientInfo') || isset($_REQUEST['json']);
            $this->Host = $this->getHostItem($service,$encoded,$hostnotrequired,$returnmacs,$override);
            $method = $this->json ? 'json' : 'send';
            if ((!isset($_REQUEST['sub']) || trim(strtolower($_REQUEST['sub'])) !== 'requestclientinfo') && $this->json) throw new Exception(json_encode($this->{$method}()));
            else if ($this->json) return json_encode($this->{$method}());
            else $this->{$method}();
            if (in_array(strtolower(get_class($this)),array('autologout','displaymanager','printerclient','servicemodule'))) throw new Exception($this->send);
            $this->sendData(trim($this->send));
        } catch (Exception $e) {
            if (!$this->json) return print $e->getMessage();
            json_decode($e->getMessage());
            if (json_last_error() !== JSON_ERROR_NONE) $message = json_encode(array('error'=>preg_replace('/^[#][!]?/','',$e->getMessage())));
            else $message = $e->getMessage();
            if (!(isset($_REQUEST['sub']) && trim(strtolower($_REQUEST['sub'] !== 'requestclientinfo')))) return print $message;
            return $message;
        }
    }
}
