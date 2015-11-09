<?php
class SchemaUpdaterPage extends FOGPage {
    public $node = 'schemaupdater';
    public function __construct($name = '') {
        $this->name = 'Database Schema Installer / Updater';
        parent::__construct($this->name);
        $this->menu = array();
        $this->subMenu = array();
    }
    /** index()
     * The first page displayed especially when a user logs in.
     */
    public function index() {
        $this->title = _('Database Schema Installer / Updater');
        echo '<p>'._('Your FOG database schema is not up to date, either because you have updated FOG or this is a new FOG installation.  If this is a upgrade, we highly recommend that you backup your FOG database before updating the schema (this will allow you to return the previous installed version)').'.</p><p>'._('Are you sure you wish to install/update the FOG database?').'</p><br/><form method="post" action="'.$this->formAction.'"><center><input type="submit" name="confirm" value="'._('Install/Upgrade Now').'" /></center></form><p>'._('If you would like to backup your FOG database you can do so my using MySql Administrator or by running the following command in a terminal window (Applications -> System Tools -> Terminal), this will save sqldump in your home directory').'.</p><div id="sidenotes">cd ~;mysqldump --allow-keywords -x -v fog > fogbackup.sql</div><br/><p>'._('Alternatively, you can use the button below to obtain a copy of your current fog database').'.</p><form method="post" action="export.php?type=sql"><center><input type="submit" name="export" value="'._('Export-Backup DB').'" /></center></form>';
    }
    public function index_post() {
        if (isset($_REQUEST['confirm'])) {
            require_once(BASEPATH.'/commons/schema.php');
            try {
                if (count($this->schema) > $this->mySchema) {
                    $items = array_slice($this->schema,$this->mySchema,null,true);
                    foreach($items AS $version => &$updates) {
                        ++$version;
                        foreach($updates AS $i => &$update) {
                            if (is_callable($update)) {
                                $result = $update();
                                if (is_string($result)) $errors[] = sprintf('<p><b>Update ID:</b> %s</p><p><b>Function Error:</b> <pre>%s</pre></p><p><b>Function:</b> <pre>%s</pre></p>', "$version - $i",$result, print_r($update, 1));
                            } else if (!$this->DB->query($update)->fetch()->get()) $errors[] = sprintf('<p><b>Update ID:</b> %s</p><p><b>Database Error:</b> <pre>%s</pre></p><p><b>Database SQL:</b> <pre>%s</pre></p>', "$version - $i",$this->DB->sqlerror(),$update);
                        }
                        unset($update);
                    }
                    unset($updates);
                    $this->DB->current_db();
                    if ($this->DB->db_name) {
                        $newSchema = $this->getClass('SchemaManager')->find();
                        $newSchema = @array_shift($newSchema);
                        if ($newSchema && $newSchema->isValid()) $newSchema->set(version,$version);
                        if (!$newSchema->save() || count($this->schema) != $newSchema->get(version)) throw new Exception(_('Install / Update Failed!'));
                    }
                    echo '<p>'._('Install / Update Successful!').'</p>';
                    if (count($errors)) throw new Exception(sprintf('<h2>%s</h2>%s',_('The following errors occured'),implode('<hr/>',$errors)));

                } else {
                    echo '<p>'._('Update not required, your database schema is up to date').'!</p>';
                }
            } catch (Exception $e) {
                echo '<p>'.$e->getMessage().'</p>';
                exit;
            }
            echo '<p>'._('Click').' <a href="./index.php">'._('here').'</a> '._('to login').'.</p>';
        }
    }
}
