<?php
class Jobs extends FOGClient implements FOGClientSend {
    public function send() {
        $RebootTask = false;
        $this->send = '#!nj';
        $Task = $this->Host->get(task);
        if ($Task) $RebootTask = ($Task->getTaskType()->isDownload() || $Task->getTaskType()->isUpload());
        if ($RebootTask) $this->send = '#!ok';
    }
}
