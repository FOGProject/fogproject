<?php
/**
 * The module class.
 *
 * PHP version 5
 *
 * @category Module
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The module class.
 *
 * @category Module
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Module extends FOGController
{
    /**
     * The module table name.
     *
     * @var string
     */
    protected $databaseTable = 'modules';
    /**
     * The module fields and common names.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'id',
        'name' => 'name',
        'shortName' => 'short_name',
        'description' => 'description',
        'isDefault' => 'default',
    );
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'name',
        'shortName',
    );
    /**
     * Alters valid method.
     *
     * @return bool
     */
    public function isValid()
    {
        return (bool)parent::isValid()
            && $this->get('shortName');
    }
    /**
     * Destroys the object.
     *
     * @param string $key the key to match for removal.
     *
     * @return bool|mixed
     */
    public function destroy($key = 'id')
    {
        self::getClass('ModuleAssociationManager')
            ->destroy(
                array(
                    'moduleID' => $this->get('id')
                )
            );
        return parent::destroy($key);
    }
}
