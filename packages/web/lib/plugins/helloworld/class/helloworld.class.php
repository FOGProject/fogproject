<?php
/**
 * Hello World example plugin (single-entity model).
 *
 * A model extends FOGController and describes one row/entity. The ORM is
 * driven entirely by $databaseTable and $databaseFields; access values with
 * get('name') / set('name', ...) / save() / load() / destroy(), and
 * instantiate with an id (new HelloWorld(42)) to auto-load.
 *
 * NOTE: the autoloader uses PHP's default spl_autoload, which lowercases the
 * class name to find the file. So class HelloWorld must live in the file
 * helloworld.class.php.
 *
 * PHP version 5
 *
 * @category HelloWorld
 * @package  FOGProject
 * @author   FOG Project <info@fogproject.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Hello World example plugin (model).
 *
 * @category HelloWorld
 * @package  FOGProject
 * @author   FOG Project <info@fogproject.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HelloWorld extends FOGController
{
    /**
     * The database table this model maps to.
     *
     * @var string
     */
    protected $databaseTable = 'helloWorld';
    /**
     * friendly name => real column name.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'hwID',
        'name' => 'hwName',
        'description' => 'hwDesc',
    ];
    /**
     * Fields that must be set before save() will succeed.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name',
    ];
}
