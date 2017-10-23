<?php
/**
 * Changes access control index div data.
 *
 * PHP version 5
 *
 * @category AccessControlIndexDiv
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Changes access control index div data.
 *
 * @category AccessControlIndexDiv
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AccessControlIndexDiv extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AccessControlIndexDiv';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Changes access control index div data';
    /**
     * The active flag.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node this hook enacts with.
     *
     * @var string
     */
    public $node = 'accesscontrol';
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        self::$HookManager
            ->register(
                'INDEX_DIV_DISPLAY_CHANGE',
                array(
                    $this,
                    'indexDivDisplayChange'
                )
            );
    }
    /**
     * The data to change.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function indexDivDisplayChange($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        switch (strtolower($arguments['childClass'])) {
        case 'accesscontrolrule':
            $arguments['items'] = '';
            ob_start();
            echo '<div class="panel panel-info">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo $arguments['main']->title;
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            $arguments['main']->render(12);
            echo '</div>';
            echo '</div>';
            if (!$arguments['delNeeded']) {
                $arguments['items'] = ob_get_clean();
                return;
            }
            echo '<div class="panel panel-warning">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Delete Selected');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            echo '<form class="form-horizontal" method="post" action="'
                . $arguments['main']->formAction
                . '&sub=deletemulti">';
            echo '<div class="form-group">';
            echo '<label class="control-label col-xs-4" for="del-'
                . $arguments['main']->node
                . '">';
            echo _('Delete Selected');
            echo ' ';
            echo strtolower($arguments['childClass'])
                . 's';
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<input type="hidden" name="'
                . $arguments['main']->node
                . 'IDArray"/>';
            echo '<button type="submit" class='
                . '"btn btn-danger btn-block" id="'
                . 'del-'
                . $arguments['main']->node
                . '">';
            echo _('Delete');
            echo '</button>';
            echo '</div>';
            echo '</div>';
            echo '</form>';
            echo '</div>';
            echo '</div>';
            $arguments['items'] = ob_get_clean();
            break;
        }
    }
}
