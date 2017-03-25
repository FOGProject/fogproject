<?php
/**
 * Host page for mobile presentation.
 *
 * PHP version 5
 *
 * @category HostMobile
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Host page for mobile presentation.
 *
 * @category HostMobile
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HostMobile extends FOGPage
{
    /**
     * The node this enacts upon.
     *
     * @var string
     */
    public $node = 'host';
    /**
     * Initializes the host mobile page.
     *
     * @param string $name The name to load with.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Host Management';
        parent::__construct($this->name);
        $this->menu = array();
        $this->subMenu = array();
        $this->notes = array();
        $this->headerData = array(
            self::$foglang['ID'],
            self::$foglang['Name'],
            self::$foglang['MAC'],
            self::$foglang['Image'],
        );
        global $id;
        if ($id) {
            $this->obj = new Host($id);
        }
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
        );
        $icon = self::getClass('TaskType', 1)->get('icon');
        $this->templates = array(
            '${id}',
            '${host_name}',
            '${host_mac}',
            sprintf(
                '<a href="index.php?node=${node}&sub=deploy&id=${id}">'
                . '<i class="fa fa-%s fa-2x"></i></a>',
                $icon
            )
        );
        self::$returnData = function (&$Host) {
            $this->data[] = array(
                'id'=>$Host->get('id'),
                'host_name'=>$Host->get('name'),
                'host_mac'=>$Host->get('mac')->__toString(),
                'node' => $this->node,
            );
            unset($Host);
        };
    }
    /**
     * The page first presented.
     *
     * @return void
     */
    public function index()
    {
        $this->search();
    }
    /**
     * The deploy form.
     *
     * @return void
     */
    public function deploy()
    {
        try {
            $this->title = self::$foglang['QuickImageMenu'];
            unset($this->headerData);
            $this->attributes = array(array());
            $this->templates = array('${task_started}');
            $this->data = array();
            if (!$this->obj->getImageMemberFromHostID($_REQUEST['id'])) {
                throw new Exception(self::$foglang['ErrorImageAssoc']);
            }
            $success = $this->obj->createImagePackage(
                '1',
                sprintf(
                    '%s: %s',
                    _('Mobile'),
                    $this->obj->get('name')
                ),
                false,
                false,
                true,
                false,
                self::$FOGUser->get('name'),
                false,
                false,
                true
            );
            if (!$success) {
                throw new Exception(self::$foglang['FailedTask']);
            }
            $this->data[] = array(self::$foglang['TaskStarted'],);
        } catch (Exception $e) {
            $this->data[] = array($e->getMessage());
        }
        $this->render();
        self::redirect('?node=task');
    }
}
