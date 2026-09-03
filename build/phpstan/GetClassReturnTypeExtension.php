<?php
/**
 * Tells PHPStan what FOGBase::getClass('X') returns.
 *
 * getClass() is declared `object|mixed`, so without this every method called
 * on its result is unchecked: `getClass('HostManager')->find()` -- the 1.5
 * API, gone from 1.6's FOGManagerController -- analysed clean and died on a
 * live server with "Call to undefined method" (fog-agent poll, 2026-09-03).
 * Roughly a hundred call sites in packages/web/src have that shape, so the
 * gap is not one line, it is the whole factory.
 *
 * Resolution mirrors Initiator::srcClassMap() rather than calling it:
 * lowercase basename of every packages/web/src/<Dir>/<Class>.php maps to
 * FOG\<Dir>\<Class>. Plugins (FOG\Plugins\...) are not resolved here -- they
 * are not in this repo's analysed paths -- and a name that maps to nothing
 * falls through to PHPStan's default, exactly as getClass('DateTime') does at
 * runtime.
 *
 * Registered in phpstan.neon under `services`, autoloaded through the root
 * composer.json's autoload-dev (the repo root is never deployed, so nothing
 * of this reaches a server).
 *
 * PHP version 7.4+
 *
 * @category GetClassReturnTypeExtension
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
namespace FOG\Build\PhpStan;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Resolves getClass('Name') to FOG\<Dir>\Name for PHPStan.
 *
 * @category GetClassReturnTypeExtension
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class GetClassReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
    /** @var array<string, string> lowercase short name => FQCN */
    private $map = [];

    /** @var ReflectionProvider */
    private $reflectionProvider;

    /**
     * Builds the short-name map once, from the tree on disk.
     *
     * @param ReflectionProvider $reflectionProvider PHPStan's class registry
     */
    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
        $src = dirname(__DIR__, 2) . '/packages/web/src';
        foreach (glob($src . '/*/*.php') ?: [] as $path) {
            $short = strtolower(basename($path, '.php'));
            $this->map[$short] = 'FOG\\' . basename(dirname($path)) . '\\' . basename($path, '.php');
        }
    }

    /**
     * The class whose static method this extension answers for. Subclasses
     * calling self::getClass() resolve to this declaring class, so one
     * registration covers every FOGBase descendant.
     *
     * @return string
     */
    public function getClass(): string
    {
        return \FOG\Base\FOGBase::class;
    }

    /**
     * @param MethodReflection $methodReflection the method being called
     *
     * @return bool
     */
    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return 'getClass' === $methodReflection->getName();
    }

    /**
     * The precise type when the name is a literal and the call is not the
     * `$props === true` form (which returns an array); null otherwise, which
     * hands back to PHPStan's default.
     *
     * @param MethodReflection $methodReflection the method being called
     * @param StaticCall       $methodCall       the call node
     * @param Scope            $scope            the analysis scope
     *
     * @return Type|null
     */
    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope
    ): ?Type {
        $args = $methodCall->getArgs();
        if (count($args) < 1) {
            return null;
        }
        if (isset($args[2])) {
            $props = $scope->getType($args[2]->value);
            if (!$props->isFalse()->yes()) {
                return null;
            }
        }
        $names = $scope->getType($args[0]->value)->getConstantStrings();
        if (1 !== count($names)) {
            return null;
        }
        $short = strtolower(trim($names[0]->getValue()));
        if (!isset($this->map[$short])) {
            return null;
        }
        $fqcn = $this->map[$short];
        if (!$this->reflectionProvider->hasClass($fqcn)) {
            return null;
        }
        return new ObjectType($fqcn);
    }
}
