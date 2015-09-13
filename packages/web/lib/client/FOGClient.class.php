<?php
abstract class FOGClient extends FOGBase {
    protected $newService;
    protected $Host;
    public function __construct($service = true,$encoded = false,$hostnotrequired = false,$returnmacs = false,$override = false) {
        try {
            parent::__construct();
            $this->newService = isset($_REQUEST[newService]);
            $this->Host = $this->getHostItem($service,$encoded,$hostnotrequired,$returnmacs,$override);
            $this->send();
            $this->sendData($this->send);
        } catch (Exception $e) {
            echo $e->getMessage();
            exit;
        }
    }
}
