<?php
/**
 * Injects subnetgroup into api system.
 *
 * PHP Version 5
 *
 * @category AddSubnetGroupAPI
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Injects subnetgroup into api system.
 *
 * @category AddSubnetGroupAPI
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddSubnetGroupAPI extends Hook
{
    /**
     * The name of the hook.
     *
     * @var string
     */
    public $name = 'AddSubnetGroupAPI';
    /**
     * The description.
     *
     * @var string
     */
    public $description = 'Add SubnetGroup stuff into API system.';
    /**
     * For posterity
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node the hook works with.
     *
     * @var string
     */
    public $node = 'subnetgroup';
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        self::$HookManager->register(
            'API_VALID_CLASSES',
            [$this, 'injectAPIElements']
        )->register(
            'CUSTOMIZE_DT_COLUMNS',
            [$this, 'customizeDT']
        );
    }
    /**
     * Customize our new columns.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function customizeDT($arguments)
    {
        if ($arguments['classname'] != $this->node) {
            return;
        }
        $arguments['columns'] = [];
        foreach (self::getClass('SubnetGroupManager')
            ->getColumns() as $common => &$real
        ) {
            switch ($common) {
            case 'id':
                $arguments['columns'][] = [
                    'db' => $real,
                    'dt' => $common
                ];
                $arguments['columns'][] = [
                    'db' => $real,
                    'dt' => 'mainlink',
                    'formatter' => function ($d, $row) {
                        return '<a href="../management/index.php?node='
                            . 'subnetgroup&sub=edit&id='
                            . $row['sgID']
                            . '">'
                            . $row['sgName']
                            . '</a>';
                    }
                ];
                break;
            case 'groupID':
                $argument['columns'][] = [
                    'db' => $real,
                    'dt' => $common
                ];
                $arguments['columns'][] = [
                    'db' => $real,
                    'dt' => 'groupLink',
                    'formatter' => function ($d, $row) {
                        if (!$d) {
                            return;
                        }
                        return '<a href="../management/index.php?node=group'
                            . '&sub=edit&id='
                            . $d
                            . '">'
                            . self::getClass('Group', $d)->get('name')
                            . '</a>';
                    }
                ];
                break;
            default:
                $arguments['columns'][] = [
                    'db' => $real,
                    'dt' => $common
                ];
            }
            unset($real);
        }
    }
    /**
     * This function injects subnetgroup elements for api access.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function injectAPIElements($arguments)
    {
        $arguments['validClasses'][] = $this->node;
    }
}
