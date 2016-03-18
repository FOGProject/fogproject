<?php
class DatabaseManager extends FOGCore {
    public function establish() {
        if (self::$DB) return $this;
        try {
            if (!in_array(trim(strtolower(DATABASE_TYPE)),array('mysql','oracle'))) throw new Exception(_('Unkown database Type. Check the DATABASE_TYPE is set correctly in "%s/lib/fog/Config.class.php"'),BASEPATH);
        } catch (Exception $e) {
            die(sprintf('Failed: %s: Error: %s',__METHOD__,$e->getMessage()));
        }
        switch (strtolower(DATABASE_TYPE)) {
        case 'mysql':
            self::$DB = FOGCore::getClass('MySQL');
            break;
        case 'oracle':
            self::$DB = FOGCore::getClass('Oracle');
            break;
        }
        if (!isset($_POST['export']) && $this->getVersion() < FOG_SCHEMA && !preg_match('#schemaupdater#i',htmlentities($_SERVER['QUERY_STRING'],ENT_QUOTES,'utf-8'))) $this->redirect('?node=schemaupdater');
        return $this;
    }
    public function getDB() {
        return self::$DB;
    }
    public function getVersion() {
        return (int)self::$DB->query('SELECT vValue FROM schemaVersion')->fetch()->get('vValue');
    }
}
