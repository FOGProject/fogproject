<?php
class UserManager extends FOGManagerController {
    public function isPasswordValid($password, $passwordConfirm) {
        try {
            if ($password != $passwordConfirm) throw new Exception('Passwords do not match');
            if (strlen($password) < $this->getSetting('FOG_USER_MINPASSLENGTH')) throw new Exception('Password too short');
            if (preg_replace('/[' . preg_quote(addSlashes($this->getSetting('FOG_USER_VALIDPASSCHARS'))) . ']/', '', $password) != '') throw new Exception('Invalid characters in password');
            return true;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
            return false;
        }
    }
}
