<?php
class DatabaseManager extends FOGCore {
    public function establish() {
        if (self::$DB) return $this;
        try {
            if (!in_array(trim(strtolower(DATABASE_TYPE)),array('mysql','oracle'))) throw new Exception(_('Unkown database Type. Check the DATABASE_TYPE is set correctly in "%s/lib/fog/Config.class.php"'),BASEPATH);
        } catch (Exception $e) {
            die(sprintf('Failed: %s: Error: %s',__METHOD__,$e->getMessage()));
        }
        self::$DB = FOGCore::getClass('PDODB');
        if (!isset($_POST['export']) && $this->getVersion() < FOG_SCHEMA && !preg_match('#schema#i',htmlspecialchars($_SERVER['QUERY_STRING'],ENT_QUOTES,'utf-8'))) $this->redirect('?node=schema');
        return $this;
    }
    public function getDB() {
        return self::$DB;
    }
    public function getVersion() {
        return (int)self::$DB->query('SELECT `vValue` FROM `schemaVersion`')->fetch()->get('vValue');
    }
    public function getColumns($table_name,$column_name) {
        return (array)self::$DB->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='".DATABASE_NAME." AND TABLE_NAME='$table_name' AND COLUMN_NAME='$column_name'")->fetch('','fetch_all')->get('COLUMN_NAME');
    }
}
