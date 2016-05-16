<?php
class SchemaUpdaterPage extends FOGPage {
    public $node = 'schemaupdater';
    public function __construct($name = '') {
        parent::__construct($this->name);
        $newSchema = self::getClass('SchemaManager')->find();
        $newSchema = @array_shift($newSchema);
        if ($newSchema instanceof Schema && $newSchema->isValid() && $newSchema->get('version') >= FOG_SCHEMA) self::redirect('index.php');
        $this->name = 'Database Schema Installer / Updater';
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
            "\n",
            _('Alternatively, you can use the button below to obtain a copy of your current fog database.'),
            _('Export-Backup DB'),
        );
        vprintf('<p>%s</p><p>%s</p><br/><form method="post" action="%s"><p class="c"><input type="submit" name="confirm" value="%s"/></p></form><p>%s</p><div id="sidenotes"><pre><code>cd%smysqldump --allow-keywords -x -v fog > fogbackup.sql</code></pre></div><br/><p>%s</p><form method="post" action="../management/export.php"><input type="hidden" name="type" value="sql"/><p class="c"><input type="submit" name="export" value="%s"/></p></form>',$vals);
    }
    public function index_post() {
        if (!isset($_REQUEST['confirm'])) return;
        require_once(sprintf('%s%scommons%sschema.php',BASEPATH,DIRECTORY_SEPARATOR,DIRECTORY_SEPARATOR));
        $errors = array();
        try {
            if (count($this->schema) <= $this->mySchema) throw new Exception(_('Update not required!'));
            $items = array_slice($this->schema,$this->mySchema,null,true);
            foreach($items AS $version => &$updates) {
                ++$version;
                foreach($updates AS $i => &$update) {
                    if (is_callable($update)) {
                        $result = $update();
                        if (is_string($result)) $errors[] = sprintf('<p><b>Update ID:</b> %s</p><p><b>Function Error:</b> <pre>%s</pre></p><p><b>Function:</b> <pre>%s</pre></p>', "$version - $i",$result, print_r($update, 1));
                    } else if (false === self::$DB->query($update)->fetch()->get()) $errors[] = sprintf('<p><b>Update ID:</b> %s</p><p><b>Database Error:</b> <pre>%s</pre></p><p><b>Database SQL:</b> <pre>%s</pre></p>', "$version - $i",self::$DB->sqlerror(),$update);
                }
                unset($update);
            }
            unset($updates);
            self::$DB->current_db(self::$DB->returnThis());
            if (self::$DB->db_name()) {
                $newSchema = self::getClass('SchemaManager')->find();
                $newSchema = @array_shift($newSchema);
                if (!($newSchema instanceof Schema && $newSchema->isValid())) $newSchema = self::getClass('Schema');
                $newSchema->set('version',$version);
                if (!$newSchema->save() || count($this->schema) != $newSchema->get('version')) {
                    printf('<p>%s</p>',_('Install / Update Failed!'));
                    if (count($errors)) throw new Exception(sprintf('<h2>%s</h2>%s<bad>',_('The following errors occurred'),implode('<hr/>',$errors)));
                }
            }
            if (count($errors)) throw new Exception(sprintf('<h2>%s</h2>%s',_('The following errors occured'),implode('<hr/>',$errors)));
            throw new Exception(_('Install/Upgrade Successful!'));
        } catch (Exception $e) {
            if (strpos('<bad>',$e->getMessage())) die(str_replace('<bad>','',$e->getMessage()));
            printf('<p>%s</p>',$e->getMessage());
        }
        printf('<p>%s <a href="./index.php">%s</a> %s</p>',_('Click'),_('here'),_('to login'));
    }
}
