<?php
/**
 * The module association class.
 *
 * PHP version 5
 *
 * @category ModuleAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The module association class.
 *
 * @category ModuleAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ModuleAssociation extends FOGController
{
    /**
     * The module association table name.
     *
     * @var string
     */
    protected $databaseTable = 'moduleStatusByHost';
    /**
     * The module association field and common names.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'msID',
        'hostID' => 'msHostID',
        'moduleID' => 'msModuleID',
        'state' => 'msState',
    );
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'hostID',
        'moduleID',
    );
    /**
     * Returns the module object.
     *
     * @return object
     */
    public function getModule()
    {
        return new Module($this->get('moduleID'));
    }
    /**
     * Returns the host object.
     *
     * @return object
     */
    public function getHost()
    {
        return new Host($this->get('hostID'));
    }
}
