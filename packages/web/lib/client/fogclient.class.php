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
            $method = $this->json ? 'json' : 'send';
            $this->Host = $this->getHostItem($service,$encoded,$hostnotrequired,$returnmacs,$override);
            $validClientBrowserFiles = array(
                'jobs.php',
                'servicemodule-active.php',
                'snapins.checkin.php',
                'usertracking.report.php',
                'snapins.file.php',
                'register.php',
            );
            $scriptCheck = basename($_SERVER['SCRIPT_NAME']);
            if (($this->json || $this->newService) && !in_array($scriptCheck,$validClientBrowserFiles)) throw new Exception(_('Not Allowed Here'));
            if ((!isset($_REQUEST['sub']) || trim(strtolower($_REQUEST['sub'])) !== 'requestclientinfo') && $this->json) throw new Exception(json_encode($this->{$method}()));
            if ($this->json) return json_encode($this->{$method}());
            $this->{$method}();
            if (in_array(strtolower(get_class($this)),array('autologout','displaymanager','printerclient','servicemodule'))) throw new Exception($this->send);
            $this->sendData(trim($this->send));
        } catch (Exception $e) {
            if (!$this->json) return print $e->getMessage();
            $message = $e->getMessage();
            json_decode($message);
            if (json_last_error() !== JSON_ERROR_NONE) $message = json_encode(array('error'=>preg_replace('/^[#][!]?/','',$message)));
            if (!(isset($_REQUEST['sub']) && trim(strtolower($_REQUEST['sub'] !== 'requestclientinfo')))) return print $message;
            return $message;
        }
    }
}
