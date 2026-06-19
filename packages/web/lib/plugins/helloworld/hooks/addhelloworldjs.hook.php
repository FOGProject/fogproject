<?php
/**
 * Injects the Hello World JavaScript for the relevant sub-page.
 *
 * The PAGE_JS_FILES event lets a plugin add JS files to the page. The
 * convention is one file per sub-page: fog.<node>.<sub>.js (e.g.
 * fog.helloworld.list.js for sub=list, fog.helloworld.edit.js for sub=edit).
 *
 * PHP version 5
 *
 * @category AddHelloWorldJS
 * @package  FOGProject
 * @author   FOG Project <info@fogproject.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Injects the Hello World JS files.
 *
 * @category AddHelloWorldJS
 * @package  FOGProject
 * @author   FOG Project <info@fogproject.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddHelloWorldJS extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddHelloWorldJS';
    /**
     * The description.
     *
     * @var string
     */
    public $description = 'Add Hello World JS files.';
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
    public $node = 'helloworld';
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
            'PAGE_JS_FILES',
            [$this, 'injectJSFiles']
        );
    }
    /**
     * Adds the per-sub-page JS file for this node.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function injectJSFiles($arguments)
    {
        global $node;
        global $sub;
        if ($node !== $this->node) {
            return;
        }
        $subset = str_replace('_', '-', (string)$sub);
        if (empty($subset)) {
            $filepath = "../lib/plugins/{$this->node}/js/fog.{$this->node}.js";
        } else {
            $filepath = "../lib/plugins/{$this->node}/js/"
                . "fog.{$this->node}.{$subset}.js";
        }
        $arguments['files'][] = $filepath;
    }
}
