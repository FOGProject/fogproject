<?php
class UserCleaner extends FOGClient implements FOGClientSend {
    public function send() {
        $UserCleanups = self::getClass('UserCleanupManager')->find();
        if ($this->newService) {
            foreach ($UserCleanups AS $i => &$User) {
                if (!$i) $this->send = "#!ok\n";
                $this->send .= "#user$i={$User->get(name)}\n";
            }
            unset($User);
        } else {
            $this->send = "#!start\n";
            foreach ($UserCleanups AS $i => &$User) $this->send .= base64_encode($User->get('name'))."\n";
            unset($User);
            $this->send .= "#!end";
        }
    }
}
