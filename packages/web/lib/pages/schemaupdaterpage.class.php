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
        $vals = array(
            _('Your FOG database schema is not up to date, either because you have updated FOG or this is a new FOG Installation. If this is an upgrade, there will be a database back stored on your FOG Server defaulting under the folder /home/fogDBbackups/. Should anything go wrong, this backup will enable you to return to the previous install if needed.'),
            _('Are you sure you wish to install or update the FOG database?'),
            $this->formAction,
            _('Install/Upgrade Now'),
            _('If you would like to backup your FOG database you can do so using MySQL Administrator or by running the following command in a terminal window (Applications -> System Tools -> Terminal), this will save sqldump in your home directory).'),
            _('Alternatively, you can use the button below to obtain a copy of your current fog database.'),
            _('Export-Backup DB'),
        );
        vprintf('<p>%s</p><p>%s</p><br/><form method="post" action="%s"><p class="c"><input type="submit" name="confirm" value="%s"/></p></form><p>%s</p><div id="sidenotes">cd ~;mysqldump --allowkeywords -x -v fog > fogbackup.sql</div><br/><p>%s</p><form method="post" action="export.php?type=sql"><p class="c"><input type="submit" name="export" value="%s"/></p></form>',$vals);
    }
    public function index_post() {
        if (isset($_REQUEST['confirm'])) {
            require(sprintf('%s%scommons%sschema.php',BASEPATH,DIRECTORY_SEPARATOR,DIRECTORY_SEPARATOR));
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
                    printf('<p>%s</p>',_('Install/Upgrade Successful!'));
                    if (count($errors)) throw new Exception(sprintf('<h2>%s</h2>%s',_('The following errors occured'),implode('<hr/>',$errors)));

                } else printf('<p>%s</p>',_('Update not required!'));
            } catch (Exception $e) {
                printf('<p>%s</p>',$e->getMessage());
                exit;
            }
            printf('<p>%s <a href="./index.php">%s</a> %s</p>',_('Click'),_('here'),_('to login'));
        }
    }
}
