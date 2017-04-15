<?php
/**
 * The imaging log class.
 *
 * PHP version 5
 *
 * @category ImagingLog
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The imaging log class.
 *
 * @category ImagingLog
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ImagingLog extends FOGController
{
    /**
     * The imaging log table.
     *
     * @var string
     */
    protected $databaseTable = 'imagingLog';
    /**
     * The imaging log fields and common names.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'ilID',
        'hostID' => 'ilHostID',
        'start' => 'ilStartTime',
        'finish' => 'ilFinishTime',
        'image' => 'ilImageName',
        'type' => 'ilType',
        'createdBy' => 'ilCreatedBy',
    );
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'hostID',
        'start',
        'finish',
        'image',
        'type',
    );
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = array(
        'host',
        'images',
    );
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = array(
        'Host' => array(
            'id',
            'hostID',
            'host'
        ),
        'Image' => array(
            'name',
            'image',
            'images'
        )
    );
    /**
     * Return the host object.
     *
     * @return object
     */
    public function getHost()
    {
        return $this->get('host');
    }
    /**
     * Return the image object.
     *
     * @return object
     */
    public function getImage()
    {
        return $this->get('images');
    }
}
