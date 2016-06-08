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
        self::getVersion();
        if (self::$mySchema < FOG_SCHEMA) {
            global $sub;
            if (preg_match('#/service/#',$_SERVER['SCRIPT_NAME']) || in_array($sub,array('configure','authorize','requestClientInfo'))) {
                if ($this->json) echo json_encode(array('error'=>'db'));
                else echo '#!db';
                exit;
            }
            if (!preg_match('#schema#i',htmlspecialchars($_SERVER['QUERY_STRING'],ENT_QUOTES,'utf-8'))) $this->redirect('?node=schema');
        }
        return $this;
    }
    public function getDB() {
        return self::$DB;
    }
    private static function getVersion() {
        self::$mySchema = (int)self::$DB->query('SELECT `vValue` FROM `schemaVersion`')->fetch()->get('vValue');
    }
    public function getColumns($table_name,$column_name) {
        return (array)self::$DB->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='".DATABASE_NAME." AND TABLE_NAME='$table_name' AND COLUMN_NAME='$column_name'")->fetch('','fetch_all')->get('COLUMN_NAME');
    }
}
