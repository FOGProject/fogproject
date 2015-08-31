<?php
class DirectoryCleanup extends FOGClient implements FOGClientSend {
    protected $send;
    public function send() {
        $DirectoryCleanups = $this->getClass(DirCleanerManager)->find();
        foreach ($DirectoryCleanups AS $i => &$DirectoryCleanup) {
            $SendEnc = base64_encode($DirectoryCleanup->get(path))."\n";
            $Send[$i] = $SendEnc;
            if ($this->newService) {
                if (!$i) $Send[$i] = "#!ok\n";
                $Send[$i] .= "#dir$i=$SendEnc";
            }
        }
        unset($DirectoryCleanup);
        $this->send = implode($Send);
    }
}
