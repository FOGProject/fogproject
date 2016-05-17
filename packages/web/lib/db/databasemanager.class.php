<?php
class DatabaseManager extends FOGCore {
    public function establish() {
        if (self::$DB) return $this;
        self::$DB = FOGCore::getClass('PDODB');
        if (!isset($_POST['export']) && $this->getVersion() < FOG_SCHEMA && !preg_match('#schemaupdater#i',htmlspecialchars($_SERVER['QUERY_STRING'],ENT_QUOTES,'utf-8'))) $this->redirect('?node=schemaupdater');
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
