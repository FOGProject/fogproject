<?php
/**
 * Sets the javascript files up for this hook.
 *
 * PHP version 5
 *
 * @category AddSiteJS
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Sets the javascript files up for this hook.
 *
 * @category AddSiteJS
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddSiteJS extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddSiteJS';
    /**
     * The description.
     *
     * @var string
     */
    public $description = 'Add Site JS files.';
    /**
     * For posterity.
     *
     * @var bool
     */
    public $active = true;
    /**
     * What plugin this works against.
     *
     * @var string
     */
    public $node = 'site';
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
        self::$HookManager
            ->register(
                'PAGE_JS_FILES',
                [$this, 'injectJSFiles']
            );
    }
    /**
     * The files we need to inject.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function injectJSFiles($arguments)
    {
        global $node;
        global $sub;
        $subset = $sub;
        if ($sub == 'membership') {
            $subset = 'edit';
        }
        $node = str_replace(
            '_',
            '-',
            $node
        );
        $subset = str_replace(
            '_',
            '-',
            $subset
        );
        switch ($node) {
            case 'site':
                if (empty($subset)) {
                    $filepaths
                        = "../lib/plugins/{$this->node}/js/fog.{$node}.js";
                } else {
                    $filepaths
                        = "../lib/plugins/{$this->node}/js/fog.{$node}.{$subset}.js";
                }
                if ($subset && !file_exists($filepaths)) {
                    $arguments['files'][]
                        = "../lib/plugins/{$this->node}/js/fog.{$node}.list.js";
                }
                break;
            case 'user':
            case 'host':
            case  'group':
                if (empty($subset)) {
                    $filepaths
                        = "../lib/plugins/{$this->node}/js/fog.{$this->node}.{$node}.js";
                } else {
                    $filepaths
                        = "../lib/plugins/{$this->node}/js/"
                        . "fog.{$this->node}.{$node}.{$subset}.js";
                }
                break;
            default:
                return;
        }
        $arguments['files'][] = $filepaths;
    }
}
