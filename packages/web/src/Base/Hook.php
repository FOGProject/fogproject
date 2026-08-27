<?php
/**
 * Hooks allow customization between different aspects.
 *
 * While not everything is hookable, there is quite a lot
 * that is able to be customized.
 *
 * Most of the accessible elements are handled from the event class.
 *
 * PHP version 7.4+
 *
 * @category Hook
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Base;

/**
 * Hooks allow customization between different aspects.
 *
 * While not everything is hookable, there is quite a lot
 * that is able to be customized.
 *
 * @category Hook
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
abstract class Hook extends FOGBase
{
    /*
     * NOT `extends Event`. A hook exists to change something in flight and is
     * dispatched by HookManager::processEvent(), which calls a method the
     * listener named and hands it a payload by reference; an event exists to
     * be told something happened and is dispatched by EventManager::notify(),
     * which calls a fixed onEvent() and discards the result. Neither is a kind
     * of the other, and while the inheritance stood, `instanceof Event` -- the
     * one type check separating them -- said they were. See #1203 and
     * docs/adr/0017-hook-dispatch-contract.md.
     *
     * The boilerplate the two genuinely share ($name, $active, the log
     * settings, log() itself) comes from the Listener trait, so nothing a hook
     * relied on went away with the parent. What did go away is run() and
     * onEvent(): both are the EVENT dispatch surface, both were empty, and no
     * hook in core or in fog-plugins calls either.
     */
    use Listener;

    /**
     * Function enables reportTypes
     * to allow plugins, and all hooks really, to tie into
     * report structures.
     *
     * @param mixed $arguments the item to tie into
     *
     * @return void
     */
    public function reportTypes($arguments)
    {
        $arguments['types'][$this->node] = 4;
    }
    /**
     * Registers a set of hook callbacks, but only when this hook's plugin
     * node is actually installed.
     *
     * Replaces the install-guard + register chain boilerplate repeated in
     * nearly every plugin hook constructor. Each registration is an ordered
     * [event, method] pair; pairs run in order, and the same event may
     * appear more than once (each adds another callback), so duplicates are
     * preserved exactly as a hand-written register() chain would be.
     *
     * @param array $registrations list of [eventName, methodName] pairs
     *
     * @return void
     */
    protected function registerInstalled(array $registrations)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        foreach ($registrations as $registration) {
            list($event, $method) = $registration;
            self::$HookManager->register($event, [$this, $method]);
        }
    }
    /**
     * Appends this plugin's JS file(s) to a PAGE_JS_FILES hook payload.
     *
     * Replaces the near-identical injectJSFiles() body duplicated across
     * every plugin's add<plugin>js hook: normalize the current $node/$sub
     * globals, then for a recognized $node append one JS file (with an
     * optional list.js fallback). Unrecognized nodes are ignored, exactly
     * as the old switch default: return.
     *
     * $cases maps each handled (post-normalization) $node value to its
     * behavior:
     *   'secondary' => bool  use fog.<plugin>.<node>[.<sub>].js naming
     *                        instead of the default fog.<node>[.<sub>].js
     *   'fallback'  => bool  when a $sub-specific file is requested but
     *                        absent on disk, also append fog.<node>.list.js
     *
     * $arguments is taken by reference so the appended paths land on the
     * same files list the firing page passed in by reference.
     *
     * @param array $arguments the hook payload carrying the 'files' list
     * @param array $cases      node => ['secondary'?, 'fallback'?] behavior
     *
     * @return void
     */
    protected function injectPluginJS(&$arguments, array $cases)
    {
        global $node;
        global $sub;
        $subset = $sub;
        if ($sub == 'membership') {
            $subset = 'edit';
        }
        $node = str_replace('_', '-', $node);
        $subset = str_replace('_', '-', $subset);
        if (!array_key_exists($node, $cases)) {
            return;
        }
        $case = $cases[$node];
        $base = "../lib/plugins/{$this->node}/js/";
        if (!empty($case['secondary'])) {
            $stub = "fog.{$this->node}.{$node}";
        } else {
            $stub = "fog.{$node}";
        }
        if (empty($subset)) {
            $filepaths = $base . "{$stub}.js";
        } else {
            $filepaths = $base . "{$stub}.{$subset}.js";
        }
        if (!empty($case['fallback']) && $subset && !file_exists($filepaths)) {
            $arguments['files'][] = $base . "fog.{$node}.list.js";
        }
        $arguments['files'][] = $filepaths;
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\Hook', 'Hook');
