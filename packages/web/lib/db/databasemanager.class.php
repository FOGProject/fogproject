<?php
class DatabaseManager extends FOGCore {
    public $DB;
    public function establish() {
        try {
            if (!in_array(trim(strtolower(DATABASE_TYPE)),array('mysql','oracle'))) throw new Exception(_('Unkown database Type. Check the DATABASE_TYPE is set correctly in "%s/lib/fog/Config.class.php"'),BASEPATH);
        } catch (Exception $e) {
            die(sprintf('Failed: %s: Error: %s',__METHOD__,$e->getMessage()));
        }
        switch (strtolower(DATABASE_TYPE)) {
        case 'mysql':
            $this->DB = new MySQL();
            break;
        case 'oracle':
            $this->DB = new Oracle();
            break;
        default:
            break;
        }
        if ($this->getVersion() < FOG_SCHEMA && !preg_match('#schemaupdater#i',$_SERVER['PHP_SELF']) && !preg_match('#schemaupdater#i',$_SERVER['QUERY_STRING'])) $this->redirect('?node=schemaupdater');
        return $this;
    }
    public function getVersion() {
        return (int)$this->DB->query('SELECT vValue FROM schemaVersion')->fetch()->get('vValue');
    }
}
