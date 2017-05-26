<?php
/**
 * Configure global level module/services.
 * These are things like hostname changer, display, etc...
 *
 * PHP version 5
 *
 * @category ServiceConfigurationPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Configure global level module/services.
 * These are things like hostname changer, display, etc...
 *
 * @category ServiceConfigurationPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ServiceConfigurationPage extends FOGPage
{
    /**
     * The node this page works off of.
     *
     * @var string
     */
    public $node = 'service';
    /**
     * Initializes the service page.
     *
     * @param string $name The name to start with.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Service Configuration';
        parent::__construct($this->name);
        $servicelink = sprintf(
            '?node=%s',
            $this->node
        );
        $this->menu = array(
            sprintf(
                '?node=%s#home',
                $this->node
            ) => self::$foglang['Home'],
            "$servicelink#autologout" => sprintf(
                '%s %s',
                self::$foglang['Auto'],
                self::$foglang['Logout']
            ),
            "$servicelink#clientupdater" => self::$foglang['ClientUpdater'],
            "$servicelink#dircleanup" => self::$foglang['DirectoryCleaner'],
            "$servicelink#displaymanager" => sprintf(
                self::$foglang['SelManager'],
                self::$foglang['Display']
            ),
            "$servicelink#greenfog" => self::$foglang['GreenFOG'],
            "$servicelink#hostregister" => self::$foglang['HostRegistration'],
            "$servicelink#hostnamechanger" => self::$foglang['HostnameChanger'],
            "$servicelink#powermanagement" => self::$foglang['PowerManagement'],
            "$servicelink#printermanager" => sprintf(
                self::$foglang['SelManager'],
                self::$foglang['Printer']
            ),
            "$servicelink#snapinclient" => self::$foglang['SnapinClient'],
            "$servicelink#taskreboot" => self::$foglang['TaskReboot'],
            "$servicelink#usercleanup" => self::$foglang['UserCleanup'],
            "$servicelink#usertracker" => self::$foglang['UserTracker'],
        );
        self::$HookManager
            ->processEvent(
                'SUB_MENULINK_DATA',
                array(
                    'menu' => &$this->menu,
                    'submenu' => &$this->subMenu,
                    'id' => &$this->id,
                    'notes' => &$this->notes,
                    'object' => &$this->obj,
                    'servicelink' => &$servicelink
                )
            );
        $this->headerData = array(
            _('Username'),
            _('Edit'),
        );
        $this->templates = array(
            sprintf(
                '<a href="?node=%s&sub=edit">${name}</a>',
                $this->node
            ),
            sprintf(
                '<a href="?node=%s&sub=edit">'
                . '<i class="icon fa fa-pencil"></i></a>',
                $this->node
            )
        );
        $this->attributes = array(
            array(),
            array(
                'class' => 'c filter-false',
                'width' => 55
            )
        );
    }
    /**
     * The initial page to display.
     *
     * @return void
     */
    public function index()
    {
        $this->edit();
    }
    /**
     * The home elements.
     *
     * @return void
     */
    public function home()
    {
        printf(
            '<h2>%s</h2><p>%s</p><a href="?node=client">%s</a><h2>%s</h2><p>%s</p>',
            _('FOG Client Download'),
            sprintf(
                '%s. %s, %s, %s.',
                _('Use the following link to go to the client page'),
                _('There you can download utilities such as FOG Prep'),
                _('FOG Crypt'),
                _('and both the legacy and new FOG clients')
            ),
            _('Click Here'),
            _('FOG Service Configuration Information'),
            sprintf(
                '%s %s. %s. %s, %s. %s.',
                _('This will allow you to configure how services'),
                _('function on client computers'),
                _('The settings tend to be global settings which affect all hosts'),
                _('If you are looking to configure settings for a specific host'),
                _('please see the hosts service setttings section'),
                _('To get started please select an item from the left hand menu')
            )
        );
    }
    /**
     * Display the edit page.
     *
     * @return void
     */
    public function edit()
    {
        echo '<div id="tab-container"><div id="home">';
        $this->home();
        echo '</div>';
        $moduleName = self::getGlobalModuleStatus();
        $modNames = self::getGlobalModuleStatus(true);
        foreach ((array)self::getClass('ModuleManager')
            ->find() as &$Module
        ) {
            unset(
                $this->data,
                $this->headerData,
                $this->attributes,
                $this->templates
            );
            $this->attributes = array(
                array(
                    'width' => 270,
                    'class' => 'l'
                ),
                array(
                    'class' => 'c'
                ),
                array(
                    'class' => 'r'
                )
            );
            $this->templates = array(
                '${field}',
                '${input}',
                '${span}',
            );
            $fields = array(
                sprintf(
                    '%s %s?',
                    $Module->get('name'),
                    _('Enabled')
                ) => sprintf(
                    '<input type="checkbox" name="en" id="%s"%s/>'
                    . '<label for="%s"></label>',
                    $Module->get('shortName'),
                    (
                        $moduleName[$Module->get('shortName')] ?
                        ' checked' :
                        ''
                    ),
                    $Module->get('shortName')
                ),
                sprintf(
                    '%s',
                    (
                        $moduleName[$Module->get('shortName')] ?
                        sprintf(
                            '%s %s?',
                            $Module->get('name'),
                            _('Enabled as default')
                        ) :
                        ''
                    )
                ) => sprintf(
                    '%s',
                    (
                        $moduleName[$Module->get('shortName')] ?
                        sprintf(
                            '<input type="checkbox" name="defen" id="%sdef"%s/>'
                            . '<label for="%sdef"></label>',
                            $Module->get('shortName'),
                            (
                                $Module->get('isDefault') ?
                                ' checked' :
                                ''
                            ),
                            $Module->get('shortName')
                        ) :
                        ''
                    )
                )
            );
            $this->span = array(
                'span',
                sprintf(
                    '<i class="icon fa fa-question hand" title="%s"></i>',
                    $Module->get('description')
                )
            );
            array_walk($fields, $this->fieldsToData);
            $this->span = array(
                'span',
                sprintf(
                    '<input type="submit" name="updatestatus" value="%s"/>',
                    _('Update')
                )
            );
            $fields = array(
                sprintf(
                    '<input type="hidden" name="name" value="%s"/>',
                    $modNames[$Module->get('shortName')]
                ) => '&nbsp;'
            );
            array_walk($fields, $this->fieldsToData);
            unset($this->span);
            printf(
                '<!-- %s --><div id="%s"><h2>%s</h2>'
                . '<form method="post" action="?node=service&sub=edit&tab=%s">'
                . '<p>%s</p><h2>%s</h2>',
                $Module->get('name'),
                $Module->get('shortName'),
                $Module->get('name'),
                $Module->get('shortName'),
                $Module->get('description'),
                _('Service Status')
            );
            $this->render();
            echo '</form>';
            switch ($Module->get('shortName')) {
            case 'autologout':
                printf(
                    '<h2>%s</h2>'
                    . '<form method="post" action="?node=service&sub=edit&tab=%s">'
                    . '<p>%s: <input type="text" name="tme" value="%s"/></p>'
                    . '<p><input type="hidden" name='
                    . '"name" value="FOG_CLIENT_AUTOLOGOFF_MIN"/>'
                    . '<input name="updatedefaults" type="submit" value="%s"/>'
                    . '</p></form>',
                    _('Default Setting'),
                    $Module->get('shortName'),
                    _('Default log out time (in minutes)'),
                    self::getSetting('FOG_CLIENT_AUTOLOGOFF_MIN'),
                    _('Update Defaults')
                );
                break;
            case 'snapinclient':
                self::$HookManager
                    ->processEvent(
                        'SNAPIN_CLIENT_SERVICE',
                        array('page' => &$this)
                    );
                break;
            case 'clientupdater':
                unset(
                    $this->data,
                    $this->headerData,
                    $this->attributes,
                    $this->templates
                );
                self::getClass('FOGConfigurationPage')
                    ->clientupdater();
                break;
            case 'dircleanup':
                printf(
                    '%s: %s',
                    _('NOTICE'),
                    sprintf(
                        '%s. %s %s. %s %s.',
                        _('This module is only used on the old client'),
                        _('The old client is what was distributed with'),
                        _('FOG 1.2.0 and earlier'),
                        _('This module did not work past Windows XP'),
                        _('due to UAC introduced in Vista and up')
                    )
                );
                echo '<hr/>';
                unset(
                    $this->data,
                    $this->headerData,
                    $this->attributes,
                    $this->templates
                );
                $this->headerData = array(
                    _('Path'),
                    _('Remove'),
                );
                $this->attributes = array(
                    array(
                        'class' => 'l'
                    ),
                    array(
                        'class' => 'filter-false'
                    ),
                );
                $this->templates = array(
                    '${dir_path}',
                    sprintf(
                        '<input type"checkbox" id="rmdir${dir_id}" class='
                        . '"delid" name="delid" onclick='
                        . '"this.form.submit()" value='
                        . '"${dir_id}"/><label for='
                        . '"rmdir${dir_id}" class="icon fa fa-minus-'
                        . 'circle hand" title="%s">&nbsp;</label>',
                        _('Delete')
                    )
                );
                printf(
                    '<h2>%s</h2>'
                    . '<form method="post" action="%s&sub=edit&tab=%s">'
                    . '<p>%s: <input type="text" name="adddir"/></p>'
                    . '<p><input type="hidden" name="name" value="%s"/>'
                    . '<input type="submit" value="%s"/></p><h2>%s</h2>',
                    _('Add Directory'),
                    $this->formAction,
                    $Module->get('shortName'),
                    _('Directory Path'),
                    $modNames[$Module->get('shortName')],
                    _('Add Directory'),
                    _('Directories Cleaned')
                );
                foreach ((array)self::getClass('DirCleanerManager')
                    ->find() as &$DirCleaner
                ) {
                    $this->data[] = array(
                        'dir_path'=>$DirCleaner->get('path'),
                        'dir_id'=>$DirCleaner->get('id'),
                    );
                    unset($DirCleaner);
                }
                unset($DirCleaners);
                $this->render();
                echo '</form>';
                break;
            case 'displaymanager':
                unset(
                    $this->data,
                    $this->headerData,
                    $this->attributes,
                    $this->templates
                );
                $this->attributes = array(
                    array(),
                    array(),
                );
                $this->templates = array(
                    '${field}',
                    '${input}',
                );
                $disps = array(
                    'FOG_CLIENT_DISPLAYMANAGER_R',
                    'FOG_CLIENT_DISPLAYMANAGER_X',
                    'FOG_CLIENT_DISPLAYMANAGER_Y'
                );
                list(
                    $r,
                    $x,
                    $y
                ) = self::getSubObjectIDs(
                    'Service',
                    array('name' => $disps),
                    'value',
                    false,
                    'AND',
                    'name',
                    false,
                    ''
                );
                unset($disps);
                $fields = array(
                    _('Default Width') => sprintf(
                        '<input type="text" name="width" value="%s"/>',
                        $x
                    ),
                    _('Default Height') => sprintf(
                        '<input type="text" name="height" value="%s"/>',
                        $y
                    ),
                    _('Default Refresh Rate') => sprintf(
                        '<input type="text" name="refresh" value="%s"/>',
                        $r
                    ),
                    sprintf(
                        '<input type="hidden" name="name" value="%s"/>',
                        $modNames[$Module->get('shortName')]
                    ) => sprintf(
                        '<input name="updatedefaults" type="submit" value="%s"/>',
                        _('Update Defaults')
                    ),
                );
                printf(
                    '<h2>%s</h2><form method="post" action="%s&sub=edit&tab=%s">',
                    _('Default Setting'),
                    $this->formAction,
                    $Module->get('shortName')
                );
                array_walk($fields, $this->fieldsToData);
                $this->render();
                echo '</form>';
                break;
            case 'greenfog':
                printf(
                    '%s: %s',
                    _('NOTICE'),
                    sprintf(
                        '%s. %s %s. %s %s %s. %s.',
                        _('This module is only used on the old client'),
                        _('The old client is what was distributed with'),
                        _('FOG 1.2.0 and earlier'),
                        _('This module has been replaced in the new client'),
                        _('and the equivalent module for what Green FOG did'),
                        _('is now called Power Management'),
                        _('This is only here to maintain old client operations')
                    )
                );
                echo '<hr/>';
                unset(
                    $this->data,
                    $this->headerData,
                    $this->attributes,
                    $this->templates
                );
                $this->headerData = array(
                    _('Time'),
                    _('Action'),
                    _('Remove'),
                );
                $this->attributes = array(
                    array(),
                    array(),
                    array('class'=>'filter-false'),
                );
                $this->templates = array(
                    '${gf_time}',
                    '${gf_action}',
                    sprintf(
                        '<input type="checkbox" id="gfrem${gf_id}" class='
                        . '"delid" name="delid" onclick='
                        . '"this.form.submit()" value='
                        . '"${gf_id}"/><label for="gfrem${gf_id}" class='
                        . '"icon fa fa-minus-circle hand" title="%s">'
                        . '&nbsp;</label>',
                        _('Delete')
                    )
                );
                printf(
                    '<h2>%s</h2>'
                    . '<form method="post" action="%s&sub=edit&tab=%s">'
                    . '<p>%s <input class="short" type="text" name='
                    . '"h" maxlength="2" value="HH" onFocus='
                    . '"$(this).val(\'\');"/>:<input class="short" type='
                    . '"text" name="m" maxlength="2" value="MM" onFocus='
                    . '"$(this).val(\'\');"/><select name="style" size="1">'
                    . '<option value="">- %s -</option>'
                    . '<option value="s">%s</option>'
                    . '<option value="r">%s</option>'
                    . '</select></p><p>'
                    . '<input type="hidden" name="name" value="%s"/>'
                    . '<input type="submit" name="addevent" value="%s"/></p>',
                    _('Shutdown/Reboot Schedule'),
                    $this->formAction,
                    $Module->get('shortName'),
                    _('Add Event (24 Hour Format)'),
                    _('Please select an option'),
                    _('Shutdown'),
                    _('Reboot'),
                    $modNames[$Module->get('shortName')],
                    _('Add Event')
                );
                foreach ((array)self::getClass('GreenFogManager')
                    ->find() as &$GreenFog
                ) {
                    $gftime = self::niceDate(
                        sprintf(
                            '%s:%s',
                            $GreenFog->get('hour'),
                            $GreenFog->get('min')
                        )
                    )->format('H:i');
                    $this->data[] = array(
                        'gf_time' => $gftime,
                        'gf_action' => (
                            $GreenFog->get('action') == 'r' ?
                            _('Reboot') :
                            (
                                $GreenFog->get('action') == 's' ?
                                _('Shutdown') :
                                _('N/A')
                            )
                        ),
                        'gf_id' => $GreenFog->get('id'),
                    );
                    unset($GreenFog);
                }
                unset($GreenFogs);
                $this->render();
                echo '</form>';
                break;
            case 'usercleanup':
                printf(
                    '%s: %s',
                    _('NOTICE'),
                    sprintf(
                        '%s. %s %s. %s %s.',
                        _('This module is only used on the old client'),
                        _('The old client is what was distributed with'),
                        _('FOG 1.2.0 and earlier'),
                        _('This module did not work past Windows XP'),
                        _('due to UAC introduced in Vista and up.')
                    )
                );
                echo '<hr/>';
                unset(
                    $this->data,
                    $this->headerData,
                    $this->attributes,
                    $this->templates
                );
                $this->attributes = array(
                    array(),
                    array(),
                );
                $this->templates = array(
                    '${field}',
                    '${input}',
                );
                $fields = array(
                    _('Username') => '<input type="text" name="usr"/>',
                    sprintf(
                        '<input type="hidden" name="name" value="%s"/>',
                        $modNames[$Module->get('shortName')]
                    ) => sprintf(
                        '<input type="submit" name="adduser" value="%s"/>',
                        _('Add User')
                    )
                );
                printf(
                    '<h2>%s</h2><form method="post" action="%s&sub=edit&tab=%s">',
                    _('Add Protected User'),
                    $this->formAction,
                    $Module->get('shortName')
                );
                array_walk($fields, $this->fieldsToData);
                $this->render();
                unset(
                    $this->data,
                    $this->headerData,
                    $this->attributes,
                    $this->templates
                );
                $this->headerData = array(
                    _('User'),
                    _('Remove'),
                );
                $this->attributes = array(
                    array(),
                    array('class' => 'filter-false'),
                );
                $this->templates = array(
                    '${user_name}',
                    '${input}',
                );
                printf(
                    '<h2>%s</h2>',
                    _('Current Protected User Accounts')
                );
                foreach ((array)self::getClass('UserCleanupManager')
                    ->find() as &$UserCleanup
                ) {
                    $this->data[] = array(
                        'user_name' => $UserCleanup->get('name'),
                        'input' => (
                            $UserCleanup->get('id') < 7 ?
                            '' :
                            sprintf(
                                '<input type="checkbox" id='
                                . '"rmuser${user_id}" class='
                                . '"delid" name="delid" onclick='
                                . '"this.form.submit()" value='
                                . '"${user_id}"/>'
                                . '<label for="rmuser${user_id}" class='
                                . '"icon fa fa-minus-circle hand" title='
                                . '"%s"> </label>',
                                _('Delete')
                            )
                        ),
                        'user_id' => $UserCleanup->get('id'),
                    );
                    unset($UserCleanup);
                }
                unset($UserCleanups);
                $this->render();
                echo '</form>';
                break;
            }
            echo '</div>';
            unset($Module);
        }
        echo '</div>';
    }
    /**
     * Actually change the items.
     *
     * @return void
     */
    public function editPost()
    {
        $Service = self::getClass('Service')
            ->set('name', $_REQUEST['name'])
            ->load('name');
        $Module = self::getClass('Module')
            ->set('shortName', $_REQUEST['tab'])
            ->load('shortName');
        self::$HookManager
            ->processEvent(
                'SERVICE_EDIT_POST',
                array('Service' => &$Service)
            );
        $onoff = isset($_REQUEST['en']);
        $defen = isset($_REQUEST['defen']);
        try {
            if (isset($_REQUEST['updatestatus'])) {
                if ($Service) {
                    $Service->set('value', $onoff)->save();
                }
                if ($Module) {
                    $Module->set('isDefault', $defen)->save();
                }
            }
            switch ($_REQUEST['tab']) {
            case 'autologout':
                if (isset($_REQUEST['updatedefaults'])
                    && is_numeric($_REQUEST['tme'])
                ) {
                    $Service->set('value', $_REQUEST['tme']);
                }
                break;
            case 'snapinclient':
                self::$HookManager->processEvent('SNAPIN_CLIENT_SERVICE_POST');
                break;
            case 'dircleanup':
                if (trim($_REQUEST['adddir'])) {
                    $Service->addDir($_REQUEST['adddir']);
                }
                if (isset($_REQUEST['delid'])) {
                    $Service->remDir($_REQUEST['delid']);
                }
                break;
            case 'displaymanager':
                if (isset($_REQUEST['updatedefaults'])
                    && (is_numeric($_REQUEST['height'])
                    && is_numeric($_REQUEST['width'])
                    && is_numeric($_REQUEST['refresh']))
                ) {
                    $Service->setDisplay(
                        $_REQUEST['width'],
                        $_REQUEST['height'],
                        $_REQUEST['refresh']
                    );
                }
                break;
            case 'greenfog':
                if (isset($_REQUEST['addevent'])) {
                    if ((is_numeric($_REQUEST['h'])
                        && is_numeric($_REQUEST['m']))
                        && ($_REQUEST['h'] >= 0
                        && $_REQUEST['h'] <= 23)
                        && ($_REQUEST['m'] >= 0
                        && $_REQUEST['m'] <= 59)
                        && ($_REQUEST['style'] == 'r'
                        || $_REQUEST['style'] == 's')
                    ) {
                        $Service->setGreenFog(
                            $_REQUEST['h'],
                            $_REQUEST['m'],
                            $_REQUEST['style']
                        );
                    }
                }
                if (isset($_REQUEST['delid'])) {
                    $Service->remGF($_REQUEST['delid']);
                }
                break;
            case 'usercleanup':
                $addUser = trim($_REQUEST['usr']);
                if (!empty($addUser)) {
                    $Service->addUser($addUser);
                }
                if (isset($_REQUEST['delid'])) {
                    $Service->remUser($_REQUEST['delid']);
                }
                break;
            case 'clientupdater':
                self::getClass('FOGConfigurationPage')->clientupdaterPost();
                break;
            }
            if (!$Service->save()) {
                throw new Exception(_('Service update failed'));
            }
            self::$HookManager
                ->processEvent(
                    'SERVICE_EDIT_SUCCESS',
                    array('Service' => &$Service)
                );
            self::setMessage(_('Service Updated!'));
        } catch (Exception $e) {
            self::$HookManager
                ->processEvent(
                    'SERVICE_EDIT_FAIL',
                    array('Service' => &$Service)
                );
            self::setMessage($e->getMessage());
        }
        self::redirect(
            sprintf(
                '?node=%s#%s',
                $_REQUEST['node'],
                $_REQUEST['tab']
            )
        );
    }
    /**
     * Redirect search call to index.
     *
     * @return void
     */
    public function search()
    {
        $this->index();
    }
}
