<?php
/**
 * The location class.
 *
 * PHP version 5
 *
 * @category Location
 * @package  FOGProject
 * @author   Lee Rowlett <nope@nope.nope>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The location class.
 *
 * @category Location
 * @package  FOGProject
 * @author   Lee Rowlett <nope@nope.nope>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HostStatus extends FOGController
{
    /**
     * The location table
     *
     * @var string
     */
    protected $databaseTable = 'hoststatus';
    /**
     * The location table fields and common names
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'hsID',
        'name' => 'hsName',
        'description' => 'hsDesc'
    );
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'name',
    );
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = array(
    );
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = array(
    );
    /**
     * Destroy this particular object.
     *
     * @param string $key the key to destroy for match
     *
     * @return bool
     */
    public function destroy($key = 'id')
    {
    }
    /**
     * Stores the item in the DB either stored or updated.
     *
     * @return object
     */
    public function save()
    {
        parent::save();
    }
}
