<?php
class FOGRollingURL {
    public $url = false;
    public $method = 'GET';
    public $post_data = null;
    public $headers = null;
    public $options = null;
    public function __construct($url, $method = 'GET', $post_data = null, $headers = null, $options = null) {
        $this->url = $url;
        $this->method = $method;
        $this->post_data = $post_data;
        $this->headers = $headers;
        $this->options = $options;
    }
    public function __destruct() {
        unset($this->url,$this->method,$this->post_data,$this->headers,$this->options);
    }
}
