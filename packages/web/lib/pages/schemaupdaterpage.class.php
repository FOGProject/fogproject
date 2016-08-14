<?php
class SchemaUpdaterPage extends FOGPage {
    public $node = 'schema';
    public function __construct($name = '') {
        parent::__construct($this->name);
        if (self::getClass('Schema',1)->get('version') >= FOG_SCHEMA) self::redirect('index.php');
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
            );
            vprintf('<div id="dbRunning" class="hidden"><p>%s</p><p>%s</p><br/><form method="post" action="%s"><p class="c"><input type="hidden" name="fogverified"/><input type="submit" name="confirm" value="%s"/></p></form><p>%s</p><div id="sidenotes"><pre><code>cd%smysqldump --allow-keywords -x -v fog > fogbackup.sql</code></pre></div><br/></div>',$vals);
            echo '<div id="dbNotRunning" class="hidden">'._('Your database connection appears to be invalid. FOG is unable to communicate with the database.  There are many reasons why this could be the case.  Please check your credentials in '.dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'fog/config.class.php. Also confirm that the database is indeed running. If credentials are correct, and the Database service is running, check to ensure your filesystem has enough space.').'</div>';
    }
    public function index_post() {
        if (!isset($_POST['fogverified'])) return;
        if (!isset($_POST['confirm'])) return;
        require sprintf('%s%scommons%sschema.php',BASEPATH,DIRECTORY_SEPARATOR,DIRECTORY_SEPARATOR);
        $errors = array();
        try {
            if (!self::$DB->link()) throw new Exception(_('No connection available'));
            if (count($this->schema) <= self::$mySchema) throw new Exception(_('Update not required!'));
            $items = array_slice($this->schema,self::$mySchema,null,true);
            foreach($items AS $version => &$updates) {
                ++$version;
                foreach($updates AS $i => &$update) {
                    if (is_callable($update)) {
                        $result = $update();
                        if (is_string($result)) $errors[] = sprintf('<p><b>Update ID:</b> %s</p><p><b>Function Error:</b> <pre>%s</pre></p><p><b>Function:</b> <pre>%s</pre></p>', "$version - $i",$result, print_r($update, 1));
                    } else if (false === self::$DB->query($update)) $errors[] = sprintf('<p><b>Update ID:</b> %s</p><p><b>Database Error:</b> <pre>%s</pre></p><p><b>Database SQL:</b> <pre>%s</pre></p>', "$version - $i",self::$DB->sqlerror(),$update);
                }
                unset($update);
            }
            unset($updates);
            self::$DB->current_db(self::$DB->returnThis());
            $newSchema = self::getClass('Schema',1)->set('version',$version);
            if (!self::$DB->db_name() || !$newSchema->save() || count($this->schema) != $newSchema->get('version')) {
                $errmsg = sprintf('<p>%s</p>',_('Install / Update Failed!'));
                if (count($errors)) $errmsg .= sprintf('<h2>%s</h2>%s',_('The following errors occurred'),implode('<hr/>',$errors));
                throw new Exception($errmsg);
            }
            printf('<p>%s</p><p>%s <a href="index.php">%s</a> %s</p>',_('Install / Update Successful!'),_('Click'),_('here'),_('to login'));
            if (count($errors)) printf('<h2>%s</h2>%s',_('The following errors occured'),implode('<hr/>',$errors));
        } catch (Exception $e) {
            printf('<p>%s</p>',$e->getMessage());
        }
    }
}
