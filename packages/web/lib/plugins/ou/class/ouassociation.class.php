<?php
/**
 * The association between hosts and ous.
 *
 * PHP version 5
 *
 * @category OUAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The association between hosts and ous.
 *
 * @category OUAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class OUAssociation extends FOGController
{
    /**
     * The association table.
     *
     * @var string
     */
    protected $databaseTable = 'ouAssoc';
    /**
     * The association fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'oaID',
        'ouID' => 'oaOUID',
        'hostID' => 'oaHostID'
    ];
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'ouID',
        'hostID'
    ];
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = [
        'host',
        'ou'
    ];
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = [
        'Host' => [
            'id',
            'hostID',
            'host'
        ],
        'OU' => [
            'id',
            'ouID',
            'ou'
        ]
    ];
    /**
     * Return the associated ou.
     *
     * @return object
     */
    public function getOU()
    {
        return $this->get('ou');
    }
    /**
     * Return the associated host.
     *
     * @return host
     */
    public function getHost()
    {
        return $this->get('host');
    }
}
