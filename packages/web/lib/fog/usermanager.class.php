<?php
class UserManager extends FOGManagerController {
    public function isPasswordValid($password, $passwordConfirm) {
        try {
            if ($password != $passwordConfirm) throw new Exception('Passwords do not match');
            if (strlen($password) < $this->getSetting('FOG_USER_MINPASSLENGTH')) throw new Exception('Password too short');
            if (preg_replace(sprintf('/[%s]/',preg_quote(mb_convert_encoding($this->getSetting('FOG_USER_VALIDPASSCHARS'),'UTF-8'))),'',$password)) throw new Exception(_('Invalid characters in password'));
            return true;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
            return false;
        }
    }
}
