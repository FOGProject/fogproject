<?php
/**
 * The home mobile page representation.
 *
 * PHP version 5
 *
 * @category HomeMobile
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The home mobile page representation.
 *
 * @category HomeMobile
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HomeMobile extends FOGPage
{
    /**
     * The node this page uses.
     *
     * @var string
     */
    public $node = 'home';
    /**
     * Initializes the HomeMobile page.
     *
     * @param string $name The name to initialize with.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Dashboard';
        parent::__construct($this->name);
        unset($this->headerData);
        $this->attributes = array(
            array(),
        );
        $this->templates = array(
            '${page_desc}',
        );
        $this->data = array();
    }
    /**
     * The basic page to present.
     *
     * @return void
     */
    public function index()
    {
        printf('<h1>%s</h1>', _('Welcome to FOG Mobile'));
        $this->data[] = array(
            'page_desc' => sprintf(
                '%s %s, %s.',
                _('This light weight interface for'),
                _('FOG allows for access via mobile'),
                _('low power devices')
            )
        );
        self::$HookManager
            ->processEvent(
                'HOMEMOBILE',
                array(
                    'headerData' => &$this->headerData,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes,
                    'data' => &$this->data
                )
            );
        $this->render();
    }
}
