<?php
/**
 * Group association between host -> group links.
 *
 * PHP version 5
 *
 * @category GroupAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Group association between host -> group links.
 *
 * @category GroupAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class GroupAssociation extends FOGController
{
    /**
     * Group association table
     *
     * @var string
     */
    protected $databaseTable = 'groupMembers';
    /**
     * Group Association fields and common names.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'gmID',
        'hostID' => 'gmHostID',
        'groupID' => 'gmGroupID',
    );
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'hostID',
        'groupID',
    );
    /**
     * Gets the group object
     *
     * @return object
     */
    public function getGroup()
    {
        return new Group($this->get('groupID'));
    }
    /**
     * Gets the host object
     *
     * @return object
     */
    public function getHost()
    {
        return new Host($this->get('hostID'));
    }
}
