<?php
/**
 * OpenAPI description of the FOG REST API, generated from FOG's own metadata.
 *
 * PHP version 7.4+
 *
 * @category OpenAPI
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Router;

use FOG\Auth\Authorization;
use FOG\Base\FOGBase;
use FOG\Db\SchemaReconciler;

/**
 * OpenAPI description of the FOG REST API, generated from FOG's own metadata.
 *
 * Nothing machine-readable has ever described this API. The references are
 * hand-written prose that goes stale the moment a class or route is added,
 * so this builds the description from the same structures the router and the
 * model layer already read at runtime:
 *
 * - Route::$validClasses x Route::defineRoutes() gives every path and method.
 * - ReflectionClass::getDefaultProperties() gives $databaseFields (property
 *   name to column name), $databaseFieldsRequired and $databaseTable. This is
 *   the identical call Route::create() and Route::edit() already make through
 *   FOGBase::getClass($class, '', true) to decide what they will accept.
 * - commons/schema-expected.php gives each column its SQL type, nullability,
 *   length, enum values and default.
 * - Authorization::API_ROUTE_ACTIONS and API_CLASS_ENTITIES give the
 *   permission each operation requires.
 *
 * Built per request rather than shipped as a file, and that is the point.
 * $validClasses is mutated at runtime by the API_VALID_CLASSES hook and the
 * sensitive-field lists by API_SENSITIVE_FIELDS, so a file generated at build
 * time would silently omit every class a plugin contributes. Generating from
 * live state means the description is true for the server serving it, plugins
 * included, and a client can diff what it supports against what it is talking
 * to.
 *
 * Deliberately NOT covered here, because no metadata backs them:
 *
 * - Route::getter()'s per-class response augmentations (image gains os,
 *   imagetypename, osname, storagegroupname; host, inventory, group, snapin,
 *   storagenode, storagegroup and task likewise). Those are a hand-written
 *   switch; the ~40 other classes fall through to a plain get().
 * - Per-class create/edit extras such as host's macs, primac, snapins,
 *   printers, modules and groups, which are hand-coded isset() branches.
 *
 * Both are described in prose on the affected operations rather than being
 * guessed at, so the document does not claim more precision than it has.
 *
 * Linters warn that /{class}/search/{item} and /{class}/{id}/task are
 * ambiguous templates, and as templates they are. They cannot actually
 * collide: the router constrains the id segment to digits only, so 'search' never
 * matches it and an integer never matches the 'search' literal. The warning
 * is a property of OpenAPI's path syntax having no type constraint, not of
 * the routing, and the alternative -- inventing distinct paths the server
 * does not serve -- would be worse than the warning.
 *
 * 1.6 only. The 1.5 branch has no commons/schema-expected.php, so there is no
 * type source there to generate against.
 *
 * @category OpenAPI
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class OpenAPI extends FOGBase
{
    /**
     * The OpenAPI specification version this document conforms to.
     *
     * 3.0.3 rather than 3.1.0, and the reason is that declaring 3.1 costs a
     * second of blocking main thread every time an operation is expanded in
     * the reference UI. Swagger UI resolves a 3.1 document through its
     * JSON-Schema-2020-12 resolver, which re-resolves the WHOLE document each
     * time a subtree is requested; the 3.0 path resolves the subtree alone.
     * The difference is not subtle and it scales with the document, which
     * this one does -- measured on the 1.6 lab, opening GET /availablekernels
     * (a two-line operation with no parameters):
     *
     *   openapi: 3.1.0   1798ms, of which 1760ms is one blocking task
     *   openapi: 3.0.3    156ms, of which  119ms is one blocking task
     *
     * Nothing is given up by saying 3.0.3. An audit of the generated document
     * found exactly one 3.1-only construct in it -- the type-array spelling,
     * in 70 places -- and no const, $schema, webhooks, prefixItems,
     * unevaluatedProperties, schema-level examples or numeric
     * exclusiveMinimum. Those 70 are re-spelled below as 3.0's `nullable`
     * (for the null unions) and `oneOf` (for the genuine unions), which say
     * the same thing to a client and are understood by considerably more
     * tooling than 3.1 is.
     *
     * The one real cost: `nullable` is deprecated in 3.1, so if this document
     * ever needs actual JSON Schema features, this decision has to be
     * revisited rather than extended.
     */
    const OAS_VERSION = '3.0.3';
    /**
     * Cache of the decoded schema manifest, so a 50-class walk reads and
     * parses commons/schema-expected.php once rather than once per class.
     *
     * @var array
     */
    private static $_manifest = null;
    /**
     * Reflected metadata per class, memoised. Tags, paths and schemas each
     * walk the class list, so without this every class is reflected three
     * times on a request that already reflects 50 of them.
     *
     * @var array
     */
    private static $_classVars = [];
    /**
     * Declared class-name casing per route class, memoised. Every schema
     * name, every $ref and every operationId group asks for it, so a
     * document that names 53 classes would otherwise reflect each of them
     * hundreds of times.
     *
     * @var array
     */
    private static $_classNames = [];

    /**
     * Builds the whole document.
     *
     * @return array
     */
    public static function document()
    {
        return [
            'openapi' => self::OAS_VERSION,
            'info' => self::_info(),
            'servers' => self::_servers(),
            'security' => self::_globalSecurity(),
            'x-fog-paging' => self::pagingLimits(),
            'tags' => self::_tags(),
            'paths' => self::_paths(),
            'components' => [
                'securitySchemes' => self::_securitySchemes(),
                'parameters' => self::_commonParameters(),
                'schemas' => self::_schemas()
            ]
        ];
    }

    /**
     * The server's own paging bounds, so a client can size its requests from
     * what this server does rather than from a number copied out of the
     * source at some point in the past.
     *
     * Both are constants rather than settings today, which is exactly why
     * they are worth publishing: nothing else exposes them, so a client had
     * no way to learn them except by reading the PHP, and a number learned
     * that way is wrong the moment either changes.
     *
     * Read through reflection rather than referenced directly so that a
     * change to either constant reaches this without anyone remembering to
     * update it here.
     *
     * Also served by system/info, which is the cheap way to read it -- this
     * document is several hundred kilobytes and a client that only wants two
     * integers should not have to fetch all of it.
     *
     * @return array
     */
    public static function pagingLimits()
    {
        $maxRows = null;
        $expandMax = null;
        try {
            $manager = new \ReflectionClass('FOGManagerController');
            if ($manager->hasConstant('MAX_ROWS')) {
                $maxRows = (int)$manager->getConstant('MAX_ROWS');
            }
        } catch (\Exception $e) {
            $maxRows = null;
        } catch (\Error $e) {
            $maxRows = null;
        }
        try {
            $router = new \ReflectionClass(Route::class);
            if ($router->hasConstant('EXPAND_MAX_ITEMS')) {
                $expandMax = (int)$router->getConstant('EXPAND_MAX_ITEMS');
            }
        } catch (\Exception $e) {
            $expandMax = null;
        } catch (\Error $e) {
            $expandMax = null;
        }

        return [
            'maxRows' => $maxRows,
            'expandMaxItems' => $expandMax,
            'description' => implode(' ', [
                'maxRows is the row cap applied to a list request that does',
                'not carry a start parameter, that asks for length=-1, or',
                'that sends a negative length. A request with an explicit',
                'non-negative start and a positive length is served verbatim',
                'and is not capped, so a client that always pages explicitly',
                'never meets maxRows.',
                'expandMaxItems is different: an ?expand request has its page',
                'size clamped to this value even when a larger length was',
                'asked for, so a page can come back smaller than requested.',
                'Advance by the number of rows actually returned rather than',
                'by the length you asked for, and follow nextUrl until it is',
                'null.'
            ])
        ];
    }

    /**
     * The shape of what pagingLimits() returns.
     *
     * Kept next to the producer so the two are read together. Publishing the
     * bounds on system/info only helps a client that was generated from this
     * document if the document says the key is there -- a generator builds
     * its model from this property list, so an undescribed key is a key the
     * generated client cannot see, which would defeat the point of putting
     * the bounds on the cheap endpoint in the first place.
     *
     * Both integers are nullable because pagingLimits() reports null rather
     * than a guess when reflection cannot find the constant.
     *
     * @return array
     */
    private static function _pagingSchema()
    {
        return [
            'type' => 'object',
            'description' => _('Server paging bounds. Also published as '
                . 'x-fog-paging at the root of this document.'),
            'properties' => [
                'maxRows' => [
                    'type' => 'integer',
                    'nullable' => true,
                    'description' => _('Row cap applied to a list request '
                        . 'that omits start, asks for length=-1, or sends a '
                        . 'negative length. An explicit non-negative start '
                        . 'with a positive length is served verbatim.')
                ],
                'expandMaxItems' => [
                    'type' => 'integer',
                    'nullable' => true,
                    'description' => _('Page size cap applied to an expand '
                        . 'request even when a larger length was asked for, '
                        . 'so a page can be smaller than requested. Advance '
                        . 'by the rows returned, not the length requested.')
                ],
                'description' => [
                    'type' => 'string',
                    'description' => _('The same rules in prose, for a client '
                        . 'reading this at runtime.')
                ]
            ]
        ];
    }

    /**
     * Document metadata.
     *
     * @return array
     */
    private static function _info()
    {
        return [
            'title' => 'FOG Project API',
            'version' => defined('FOG_VERSION') ? FOG_VERSION : 'unknown',
            'description' => implode("\n", [
                'Generated from this server\'s own routing and model metadata,',
                'so it describes the classes and routes THIS server exposes,',
                'including any a plugin has added.',
                '',
                'Enable under FOG Configuration > FOG Settings > API System.',
                'Preferred: issue an API token from the API tab and send it',
                'as `Authorization: Bearer <token>`, which needs nothing',
                'else. These are `fog_` prefixed, hashed at rest and shown',
                'once; a user may hold several, each revocable on its own.',
                'They are a separate credential from the per-user token that',
                '`fog-user-token` carries, which Bearer does not accept.',
                'Otherwise the `fog-api-token` (server-wide) header is',
                'required, plus one caller identity -- either the',
                '`fog-user-token` header or HTTP basic auth. Basic auth does',
                'not replace the `fog-api-token` header.',
                '',
                'Field names: every property is also addressable by its raw',
                'database column name, because the model layer flips',
                '$databaseFields into $databaseFieldsFlipped and accepts',
                'either spelling. Only the property name is documented here.'
            ]),
            'license' => [
                'name' => 'GPL-3.0',
                'url' => 'http://opensource.org/licenses/gpl-3.0'
            ]
        ];
    }

    /**
     * The base URL clients should call.
     *
     * Anchored to FOG_WEB_ROOT rather than a literal /fog, for the same
     * reason the router's unauthenticated allowlist is (GH-529): at a custom
     * webroot anything hardcoded stops matching.
     *
     * @return array
     */
    private static function _servers()
    {
        $base = rtrim((string)Route::webrootbase(), '/');
        return [
            [
                'url' => sprintf(
                    '%s://%s%s',
                    self::$httpproto,
                    self::$httphost,
                    $base
                ),
                'description' => _('This FOG server')
            ]
        ];
    }

    /**
     * Three ways in, listed strongest first.
     *
     * A Bearer token stands alone: it is a 512-bit random per-user secret.
     * The other two carry a weaker credential -- a legacy header pair, or a
     * human-chosen password -- and both keep the server-wide fog-api-token
     * gate. See Route::_testBearer() for why that asymmetry is deliberate
     * rather than an oversight.
     *
     * Two entries in the list means OR, two keys inside one entry means AND.
     *
     * @return array
     */
    private static function _globalSecurity()
    {
        return [
            ['bearerAuth' => []],
            ['fogApiToken' => [], 'fogUserToken' => []],
            ['fogApiToken' => [], 'basicAuth' => []]
        ];
    }

    /**
     * @return array
     */
    private static function _securitySchemes()
    {
        return [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'description' => implode(' ', [
                    'An API token issued from the API tab of a user with API',
                    'access enabled, sent as `Authorization: Bearer`.',
                    'Sufficient on its own -- no fog-api-token header is',
                    'needed alongside it.',
                    'Every issued token carries a `fog_` prefix and is shown',
                    'once, at creation: it is hashed at rest, so a token that',
                    'has been lost cannot be recovered and must be reissued.',
                    'A user may hold several, each individually revocable, so',
                    'one integration can be rotated without disturbing the',
                    'others.',
                    'This is NOT the per-user token that fog-user-token',
                    'carries. That token is a separate credential, is',
                    'unchanged, and is not accepted here.'
                ])
            ],
            'fogApiToken' => [
                'type' => 'apiKey',
                'in' => 'header',
                'name' => 'fog-api-token',
                'description' => implode(' ', [
                    'Server-wide API token from FOG Configuration >',
                    'FOG Settings > API System. Sent base64 encoded --',
                    'the value shown in the UI is already encoded, so send',
                    'it exactly as displayed rather than encoding it again.'
                ])
            ],
            'fogUserToken' => [
                'type' => 'apiKey',
                'in' => 'header',
                'name' => 'fog-user-token',
                'description' => implode(' ', [
                    'Per-user API token from the API tab of a user with API',
                    'access enabled. Same encoding note as fog-api-token.'
                ])
            ],
            'basicAuth' => [
                'type' => 'http',
                'scheme' => 'basic',
                'description' => _('A FOG username and password.')
            ]
        ];
    }

    /**
     * One tag per class, so a renderer groups operations by resource.
     *
     * @return array
     */
    private static function _tags()
    {
        $tags = [
            [
                'name' => 'system',
                'description' => _('Server status, export and metadata.')
            ]
        ];
        foreach (self::_documentedClasses() as $class) {
            $vars = self::_classVars($class);
            $table = isset($vars['databaseTable']) ? (string)$vars['databaseTable'] : '';
            $tags[] = [
                'name' => $class,
                'description' => '' === $table
                    ? sprintf(_('Operations on %s.'), $class)
                    : sprintf(
                        // translators: %1$s is a class name, %2$s a db table.
                        _('Operations on %1$s, stored in the %2$s table.'),
                        $class,
                        $table
                    )
            ];
        }
        return $tags;
    }

    /**
     * The class list as the router currently sees it, hook mutations and
     * all.
     *
     * @return array
     */
    private static function _classes()
    {
        $classes = (array)Route::$validClasses;
        sort($classes);
        return $classes;
    }

    /**
     * Loads and caches commons/schema-expected.php.
     *
     * Reuses SchemaReconciler::manifest() rather than re-implementing the
     * path building, so the two cannot disagree about where the file is.
     *
     * @return array
     */
    private static function _manifest()
    {
        if (null === self::$_manifest) {
            $manifest = SchemaReconciler::manifest();
            self::$_manifest = isset($manifest['tables'])
                && is_array($manifest['tables'])
                ? $manifest['tables']
                : [];
        }
        return self::$_manifest;
    }

    /**
     * Reflected model metadata for one route class, or null when the class
     * does not resolve.
     *
     * A plugin can add a name to $validClasses whose model is unavailable
     * (disabled mid-request, autoload failure); that is a reason to omit the
     * class from the document, not to fail the whole request.
     *
     * @param string $class The lowercase route class name.
     *
     * @return array|null
     */
    private static function _classVars($class)
    {
        if (array_key_exists($class, self::$_classVars)) {
            return self::$_classVars[$class];
        }
        $result = null;
        try {
            $vars = self::getClass($class, '', true);
            if (is_array($vars) && isset($vars['databaseFields'])) {
                $result = $vars;
            }
        } catch (\Exception $e) {
            $result = null;
        } catch (\Error $e) {
            // A plugin class naming a parent that is not loaded raises Error,
            // not Exception, and one broken plugin must not take the whole
            // document down with it.
            $result = null;
        }
        return self::$_classVars[$class] = $result;
    }

    /**
     * The classes that will actually appear in the document.
     *
     * Tags, paths and schemas must agree on this. They did not at first --
     * tags listed every name in $validClasses while paths and schemas
     * skipped the ones whose model would not resolve, so a plugin class that
     * failed to load left an empty tag behind with no operations under it.
     *
     * @return array
     */
    private static function _documentedClasses()
    {
        return array_values(
            array_filter(
                self::_classes(),
                function ($class) {
                    return null !== self::_classVars($class);
                }
            )
        );
    }

    /**
     * Whether /{class}/search/{item} can return anything for this class.
     *
     * An entity whose model has no `name` field has nothing to match on and
     * nothing to label a result with, so Route::_searchRows() returns null
     * for it and the route answers an empty set. Documenting search there
     * advertises an operation the server cannot honor.
     *
     * The test is deliberately the same isset() Route::_searchRows()
     * applies, against the same reflected $databaseFields, rather than a
     * list of class names written out here. $validClasses is mutated at
     * runtime by the API_VALID_CLASSES hook, so a hand-kept list would
     * silently mis-describe every class a plugin contributes -- and it
     * would be a second copy of a rule that already has one home.
     *
     * NECESSARY AND NOW SUFFICIENT. It used to be only necessary: search()
     * ran the universal search and read a bucket out of its result, and
     * unisearch() iterates $searchPages rather than $validClasses and skips
     * `task`, so 17 classes passed this test and still answered empty
     * (GH-1290). search() queries the named class directly now, so every
     * class this returns true for genuinely answers. If that ever stops
     * being true, the document starts lying again -- which is why
     * tests/class-search-searches-its-class.test.php pins the two
     * conditions to the same isset().
     *
     * @param string $class The lowercase route class name.
     *
     * @return bool
     */
    private static function _isSearchable($class)
    {
        $vars = self::_classVars($class);
        return null !== $vars && isset($vars['databaseFields']['name']);
    }

    /**
     * Turns a schema-expected column type into an OpenAPI schema.
     *
     * Input looks like 'varchar(250) NOT NULL', 'int(11) NOT NULL',
     * "enum('0','1') NOT NULL" or 'longtext DEFAULT NULL'.
     *
     * tinyint(1) maps to integer rather than boolean on purpose. Since ADR
     * 0028 tinyint(1) IS how FOG spells a boolean, so the shortcut is no
     * longer a mistyping -- but the value on the wire is 0/1, not JSON
     * true/false, because that is what mysqlnd returns for the column.
     * Documenting it as boolean would describe a payload FOG does not send.
     *
     * @param string $sqlType The column definition.
     *
     * @return array
     */
    private static function _columnSchema($sqlType)
    {
        $sqlType = (string)$sqlType;
        $nullable = false !== stripos($sqlType, 'DEFAULT NULL')
            || false === stripos($sqlType, 'NOT NULL');
        $schema = ['type' => 'string'];

        if (preg_match('/^enum\((.*?)\)/i', $sqlType, $m)) {
            $values = [];
            if (preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $m[1], $vm)) {
                $values = array_map(
                    function ($v) {
                        return stripslashes($v);
                    },
                    $vm[1]
                );
            }
            $schema = ['type' => 'string'];
            if (count($values) > 0) {
                $schema['enum'] = $values;
            }
        } elseif (preg_match('/^(tinyint|smallint|mediumint|int|bigint)/i', $sqlType)) {
            $schema = ['type' => 'integer'];
        } elseif (preg_match('/^(decimal|numeric|float|double)/i', $sqlType)) {
            $schema = ['type' => 'number'];
        } elseif (preg_match('/^(datetime|timestamp)/i', $sqlType)) {
            $schema = ['type' => 'string', 'format' => 'date-time'];
        } elseif (preg_match('/^date/i', $sqlType)) {
            $schema = ['type' => 'string', 'format' => 'date'];
        } elseif (preg_match('/^(varchar|char)\((\d+)\)/i', $sqlType, $m)) {
            $schema = ['type' => 'string', 'maxLength' => (int)$m[2]];
        }

        if (preg_match("/DEFAULT\s+(?!NULL)'?([^'\s]+)'?/i", $sqlType, $dm)) {
            $default = $dm[1];
            if (preg_match('/^\w+\s*\(/', $default)) {
                // A SQL function, not a value. DEFAULT current_timestamp()
                // means "whatever the server clock says at INSERT", which is
                // not something an OpenAPI default can express -- and copying
                // it through produces an invalid document, because `default`
                // must be an instance of the schema it sits on and
                // "current_timestamp()" is not a date-time.
                //
                // Not academic: openapi-generator REFUSES to generate from
                // this document over exactly nodefailure.failureTime and
                // snapintask.checkin, both DEFAULT current_timestamp() on a
                // datetime column. Recorded under an extension so the
                // information is not simply lost.
                $schema['x-fog-sql-default'] = $default;
            } elseif ('integer' === $schema['type']) {
                $schema['default'] = (int)$default;
            } elseif ('number' === $schema['type']) {
                $schema['default'] = (float)$default;
            } else {
                $schema['default'] = $default;
            }
        }
        if ($nullable) {
            // 3.0 spelling: a flag beside the type, rather than 3.1's null
            // joining the type. See OAS_VERSION for why this document is 3.0.
            $schema['nullable'] = true;
        }
        return $schema;
    }

    /**
     * Every entity schema, plus the shared list envelope.
     *
     * @return array
     */
    private static function _schemas()
    {
        $schemas = [
            'ListEnvelope' => self::_listEnvelopeSchema(),
            'BulkEditIds' => self::_bulkEditIdsSchema(),
            'TaskRequest' => self::_taskRequestSchema(),
            'NamesRequest' => self::_namesRequestSchema(),
            'ValueList' => self::_valueListSchema(),
            'Error' => [
                'type' => 'object',
                'properties' => [
                    'error' => ['type' => 'string']
                ]
            ]
        ];
        foreach (self::_documentedClasses() as $class) {
            $entity = self::_entitySchema($class);
            if (null === $entity) {
                continue;
            }
            $schemas[self::schemaName($class)] = $entity;
            // Every class with an entity also answers list, search and
            // active, and all three return the same page shape. Registered
            // beside the entity so the pair cannot drift apart.
            $ref = '#/components/schemas/' . self::schemaName($class);
            $pageRef = self::pageRef($ref);
            $page = substr($pageRef, strrpos($pageRef, '/') + 1);
            $schemas[$page] = self::_pageSchema($ref);
            // Likewise the join body: same field set as the entity, plus the
            // ids to apply it to. Registered here for the same reason.
            $joinRef = self::joinRef($ref);
            $join = substr($joinRef, strrpos($joinRef, '/') + 1);
            $schemas[$join] = self::_joinSchema($ref);
        }
        return $schemas;
    }

    /**
     * The schema component name for a route class.
     *
     * @param string $class The lowercase route class name.
     *
     * @return string
     */
    public static function schemaName($class)
    {
        return self::className($class);
    }

    /**
     * A route class name in the casing its own PHP class declares.
     *
     * Route class names are lowercase -- `tasklog`, `storagegroup`,
     * `usergroupmember` -- because that is what a URL carries. ucfirst()
     * on one of those gives `Tasklog`, and every generated client then
     * calls it that: a Tasklog type, a Get-Tasklog command, a
     * New-Usergroupmember. Nobody writing those by hand would.
     *
     * The correct spelling is not a judgment call and does not need a
     * hand-kept list: the model declares it. PHP class names are
     * case-insensitive to lookup but ReflectionClass::getShortName()
     * returns the name as DECLARED, so `tasklog` resolves to the class and
     * answers `TaskLog`. Same reflection pass the rest of this file already
     * relies on, same reason -- the server knows, so do not ask anyone to
     * repeat it.
     *
     * Falls back to ucfirst() for a class that will not reflect, which is
     * the same shape _classVars() already tolerates for a broken plugin.
     *
     * @param string $class The lowercase route class name.
     *
     * @return string
     */
    public static function className($class)
    {
        $class = (string)$class;
        if ('' === $class) {
            return '';
        }
        if (array_key_exists($class, self::$_classNames)) {
            return self::$_classNames[$class];
        }
        $name = ucfirst($class);
        try {
            $obj = new \ReflectionClass($class);
            $short = $obj->getShortName();
            if ('' !== $short) {
                $name = $short;
            }
        } catch (\Exception $e) {
            // Keep the ucfirst fallback.
        } catch (\Error $e) {
            // A plugin class naming a parent that is not loaded raises
            // Error rather than Exception. One broken plugin must not
            // rename every schema in the document.
        }
        return self::$_classNames[$class] = $name;
    }

    /**
     * One class's entity schema, built by joining $databaseFields against
     * the shipped column manifest.
     *
     * A property with no manifest entry still appears, typed as a plain
     * string: the field is real and settable, we simply have no type source
     * for it. Omitting it would understate the API; guessing a type would
     * misstate it.
     *
     * @param string $class The lowercase route class name.
     *
     * @return array|null
     */
    private static function _entitySchema($class)
    {
        $vars = self::_classVars($class);
        if (null === $vars) {
            return null;
        }
        $fields = (array)$vars['databaseFields'];
        if (count($fields) < 1) {
            return null;
        }
        $table = isset($vars['databaseTable']) ? (string)$vars['databaseTable'] : '';
        $manifest = self::_manifest();
        $columns = isset($manifest[$table]['columns'])
            ? (array)$manifest[$table]['columns']
            : [];
        $ignore = isset($vars['databaseFieldsToIgnore'])
            ? (array)$vars['databaseFieldsToIgnore']
            : [];
        $required = isset($vars['databaseFieldsRequired'])
            ? array_values((array)$vars['databaseFieldsRequired'])
            : [];
        $sensitive = self::_sensitiveFields($class);
        // Read through Route for the same reason the sensitive tiers are:
        // a plugin declares its own via API_SERVER_OWNED_FIELDS.
        $serverOwned = array_map(
            'strtolower',
            (array)Route::serverOwnedFields($class)
        );

        $properties = [];
        foreach ($fields as $property => $column) {
            if (in_array($property, $ignore, true)) {
                continue;
            }
            $schema = isset($columns[$column])
                ? self::_columnSchema($columns[$column])
                : ['type' => 'string'];
            $schema['x-fog-column'] = $column;
            if (!isset($columns[$column])) {
                $schema['description'] = _('No type information available for this column.');
            }
            if ('id' === $property) {
                $schema['readOnly'] = true;
            }
            if (in_array(strtolower($property), $sensitive['always'], true)) {
                $schema['x-fog-sensitive'] = 'always';
                $schema['readOnly'] = true;
                $schema['description'] = _('Never returned by the API.');
            } elseif (in_array(strtolower($property), $sensitive['list'], true)) {
                $schema['x-fog-sensitive'] = 'list';
                $schema['description'] = _(
                    'Omitted from list responses; returned only on a single GET by id.'
                );
            }
            // Server-maintained fields answer 400 to a write that would
            // change them, so documenting them as settable would document
            // a request the router refuses. readOnly rather than omitted:
            // several are still RETURNED, and a client may legitimately
            // send one back unchanged.
            if (in_array(strtolower($property), $serverOwned, true)) {
                $schema['x-fog-server-owned'] = true;
                $schema['readOnly'] = true;
                $schema['description'] = _(
                    'Maintained by the server. It may be sent back unchanged, '
                    . 'but a request that would change it is refused.'
                );
            }
            $schema = self::_applyModelConstraint($class, $property, $schema);
            $schema = self::_applyReference($vars, $property, $schema);
            $properties[$property] = $schema;
        }
        // additionalProperties is stated rather than left to the default.
        //
        // The default is already true -- an object schema that says nothing
        // permits extra properties -- so this loosens nothing and changes no
        // validator's verdict. What it changes is code generation: most
        // generators emit a catch-all bag ONLY when the keyword is explicitly
        // present, and silently drop, or refuse, unknown keys when it is
        // absent. openapi-generator's PowerShell models are the sharp case;
        // without this they throw on the first key they were not told about.
        //
        // Declaring the computed fields above fixes the 80 this document can
        // enumerate. It cannot enumerate the rest: a plugin contributes its
        // own classes and its own joined fields at runtime, and a client
        // generated from a pinned snapshot meets fields added after it was
        // generated. Both are normal here, so tolerating unknown keys is the
        // correct standing behavior for a FOG response, not a workaround.
        $out = [
            'type' => 'object',
            'x-fog-table' => $table,
            'properties' => $properties,
            'additionalProperties' => true
        ];
        $required = array_values(
            array_filter(
                $required,
                function ($r) use ($properties) {
                    return isset($properties[$r]);
                }
            )
        );
        if (count($required) > 0) {
            $out['required'] = $required;
        }
        $additional = isset($vars['additionalFields'])
            ? array_values((array)$vars['additionalFields'])
            : [];
        if (count($additional) > 0) {
            $out['description'] = sprintf(
                // translators: %s is a comma separated list of field names.
                _(
                    'Responses may carry computed fields that are not columns '
                    . 'and are not settable through the generic create/edit '
                    . 'path: %s.'
                ),
                implode(', ', $additional)
            );
            $note = self::_additionalFieldsNote($class);
            if ('' !== $note) {
                $out['description'] .= ' ' . $note;
            }
            // And say it in the schema, not only in the sentence.
            //
            // The sentence above is accurate and stays -- it carries the
            // "not settable through create/edit" nuance that no keyword
            // expresses. But a generated client cannot read English. Every
            // generator builds its deserializer from `properties`, so a
            // field named only in a description is a field the generated
            // model does not have: AutoRest copies this very sentence into
            // a doc comment and then emits a model that reads none of the
            // fields it names, and openapi-generator's PowerShell models
            // THROW on the undeclared key rather than ignoring it. Either
            // way `GET /host/1` loses macs, imagename, groups and the rest
            // -- 80 fields across 24 classes.
            //
            // readOnly is the precise keyword: OpenAPI defines it as "MAY
            // be sent in a response and MUST NOT be sent in a request",
            // which is exactly what a computed field is, and it keeps these
            // out of the request bodies that $ref this same schema.
            //
            // No `type`. An empty schema means "any type", which is honest:
            // these are computed in Route::getter() rather than derived
            // from a column, so there is no type source to read. macs is an
            // array, imagename a string, inventory an object. Asserting a
            // type here would be a guess, and a wrong guess is worse for a
            // generated client than no assertion at all.
            foreach ($additional as $field) {
                if (isset($properties[$field])) {
                    // Already a real column; the column definition wins.
                    continue;
                }
                $properties[$field] = [
                    'readOnly' => true,
                    'x-fog-computed' => true,
                    'description' => _(
                        'Computed field. Returned by the API but not a '
                        . 'column, and not settable.'
                    )
                ];
            }
            $out['properties'] = $properties;
        }
        return $out;
    }
    /**
     * Marks a column that holds another class's id as pointing at it.
     *
     * `Image.osID` is emitted as a plain integer with a column name. Nothing
     * in the document says it holds an `os` id, so a client generated from
     * it cannot offer completion for the parameter, cannot validate one, and
     * cannot follow the relationship. Every consumer that wants any of that
     * has to hand-maintain a list of which column points where -- which is a
     * copy of something the server already knows, kept somewhere it will go
     * stale.
     *
     * The model declares it. Each one carries
     * $databaseFieldClassRelationships, e.g. image.class.php:
     *
     *     'OS' => ['id', 'osID', 'os']
     *              ^^^^  ^^^^^^  ^^^^
     *              their  my      the joined property the route exposes
     *              key    column
     *
     * so the second element is the column to mark and the key is the class
     * it points at. Derived, like everything else here.
     *
     * Emitted as an object rather than a bare class name because the target
     * key is part of the fact: it is `id` everywhere today, and the map is
     * where that is stated, so reading it beats assuming it.
     *
     * A relationship whose class is not in this document is SKIPPED. Not
     * every model relation is an exposed route -- and a reference pointing
     * at a schema the reader cannot resolve is worse than no reference,
     * exactly as a dangling $ref is.
     *
     * @param array  $vars     The reflected class metadata.
     * @param string $property The property being described.
     * @param array  $schema   The schema built so far.
     *
     * @return array
     */
    private static function _applyReference($vars, $property, array $schema)
    {
        if (!isset($vars['databaseFieldClassRelationships'])) {
            return $schema;
        }
        $rels = (array)$vars['databaseFieldClassRelationships'];
        foreach ($rels as $target => $spec) {
            $spec = (array)$spec;
            if (count($spec) < 2) {
                continue;
            }
            $mine = strtolower((string)$spec[1]);
            if ($mine !== strtolower((string)$property)) {
                continue;
            }
            // The map holds both directions, and only one of them is a
            // foreign key on THIS row:
            //
            //   'OS' => ['id', 'osID', 'os']
            //       their id  <- my osID          outbound, a real FK
            //   'MACAddressAssociation' => ['hostID', 'id', 'primac']
            //       their hostID <- my id         inbound, one-to-many
            //
            // Without this the inbound half marks `id` -- the primary key,
            // readOnly in every schema here -- as referencing whichever
            // class happens to point back at it, which is both wrong and
            // useless: nothing completes a primary key from its children.
            if ('id' === strtolower((string)$property)) {
                continue;
            }
            $targetClass = strtolower((string)$target);
            if (!in_array($targetClass, self::_documentedClasses(), true)) {
                continue;
            }
            $schema['x-fog-references'] = [
                'class' => $targetClass,
                'field' => (string)$spec[0]
            ];
            break;
        }
        return $schema;
    }

    /**
     * What the generic additionalFields sentence above cannot know.
     *
     * "Responses MAY carry" is derived from the model and is true as far as
     * it goes, but for a class whose computed fields are opt-in it says
     * nothing about how to ask for them -- and a client generated from this
     * document would look for a field that is simply not in the payload.
     *
     * Deliberately a short hand-kept list, for the same reason
     * _applyModelConstraint() is one: the condition lives in Route::getter(),
     * not in anything schema-expected.php or the model can be read for.
     *
     * @param string $class The lowercase route class name.
     *
     * @return string A sentence to append, or '' for nothing to add.
     */
    private static function _additionalFieldsNote($class)
    {
        switch (strtolower((string)$class)) {
            case 'storagenode':
                return _(
                    'images and snapinfiles are opt-in: each is an outbound '
                    . 'request to the node itself, so they are returned only '
                    . 'for expand=images, expand=snapinfiles or expand=all. '
                    . 'logfiles is not returned by this route at all.'
                );
        }
        return '';
    }

    /**
     * Constraints the model enforces that the column type does not carry.
     *
     * Every other property here is derived, which is the point of this class.
     * These cannot be: they live in a model's own save() rather than in the
     * column definition, so commons/schema-expected.php has no idea they
     * exist and a document built from it alone overstates what the server
     * accepts.
     *
     * The gap is not academic. hostName is varchar(16), so the derived schema
     * says maxLength 16 -- but Host::save() calls isHostnameSafe() first,
     * which is /^[\w!@#$%^()\-\'{}\.~]{1,15}$/, and a 16 character name is
     * refused. The refusal surfaces as a 406 through _sendCaught(), so a
     * client generated from the document sends something the document said
     * was fine and gets an error that names no field.
     *
     * Deliberately a short, hand-kept list rather than an attempt to infer
     * these. Inferring them would mean reading arbitrary PHP in save(); the
     * honest thing is to name the ones we know and let the rest be described
     * by their column, which is what the rest of this class already does.
     *
     * Keyed lowercase on class then property.
     *
     * @param string $class    The lowercase route class name.
     * @param string $property The model property name.
     * @param array  $schema   The schema derived so far.
     *
     * @return array
     */
    private static function _applyModelConstraint($class, $property, array $schema)
    {
        $constraints = [
            'host' => [
                'name' => [
                    'maxLength' => 15,
                    'pattern' => '^[A-Za-z0-9_!@#$%^()\\-\'{}.~]{1,15}$',
                    'description' => 'Enforced by Host::isHostnameSafe(), which is '
                        . 'stricter than the column: at most 15 characters, and only '
                        . 'letters, digits, underscore and ! @ # $ % ^ ( ) - \' { } . ~ '
                        . 'A name that fails this is refused by Host::save() with a 406.'
                ]
            ]
        ];

        $c = strtolower($class);
        $p = strtolower($property);
        if (!isset($constraints[$c][$p])) {
            return $schema;
        }
        foreach ($constraints[$c][$p] as $key => $value) {
            if ('description' === $key) {
                // Keep whatever the sensitive/server-owned passes already said
                // rather than replacing it; both are worth knowing.
                $schema[$key] = isset($schema[$key])
                    ? $schema[$key] . ' ' . _($value)
                    : _($value);
                continue;
            }
            $schema[$key] = $value;
        }
        return $schema;
    }

    /**
     * The two tiers of sensitive field for a class, lowercased.
     *
     * Read through Route so plugin contributions via API_SENSITIVE_FIELDS
     * are included, for the same reason the class list is read live.
     *
     * @param string $class The lowercase route class name.
     *
     * @return array
     */
    private static function _sensitiveFields($class)
    {
        // Both tiers are keyed by classname, and the map is built once per
        // request behind its own cache, so calling it per class is cheap.
        $map = (array)Route::sensitiveFieldMap();
        return [
            'list' => array_map(
                'strtolower',
                (array)($map['fields'][$class] ?? [])
            ),
            'always' => array_map(
                'strtolower',
                (array)($map['always'][$class] ?? [])
            )
        ];
    }

    /**
     * The paged list envelope every list and search response is wrapped in.
     *
     * Field meanings that are not obvious from the name, and that a client
     * gets wrong at its peril:
     *
     * - recordsFiltered and recordsTotal are computed over the rows the
     *   CALLER may see. A site-restricted user's counts describe their own
     *   scope, not the server's contents. Both are real totals for them --
     *   they used to be a page-sized floor, because the boundary was applied
     *   to the rows after the database had already chosen the page.
     * - truncated is set only when the server imposed its own MAX_ROWS cap,
     *   which never happens once a caller sends explicit start/length.
     *
     * Which is why nextUrl is the only reliable "is there more" signal, and
     * why it is called out here rather than left to be discovered.
     *
     * @return array
     */
    private static function _listEnvelopeSchema()
    {
        return [
            'type' => 'object',
            'description' => implode(' ', [
                'Paged list envelope.',
                'Follow nextUrl until it is null to read every row --',
                'that is the only dependable end-of-data signal.'
            ]),
            'properties' => [
                'draw' => ['type' => 'integer'],
                'recordsTotal' => [
                    'type' => 'integer',
                    'description' => _(
                        'Total rows the caller may see, unfiltered. For a '
                        . 'site-restricted user this is the total within their '
                        . 'sites, not the server total.'
                    )
                ],
                'recordsFiltered' => [
                    'type' => 'integer',
                    'description' => _(
                        'Rows matching the filter, within what the caller may '
                        . 'see. A real total, including for site-restricted '
                        . 'users.'
                    )
                ],
                'recordsReturned' => [
                    'type' => 'integer',
                    'description' => _('Rows in this page.')
                ],
                'truncated' => [
                    'type' => 'boolean',
                    'description' => _(
                        'True only when the server capped an unbounded request at '
                        . 'MAX_ROWS. Never set when the caller sent start/length.'
                    )
                ],
                '_lang' => ['type' => 'string'],
                '_searchtypes' => [
                    'type' => 'object',
                    'description' => _(
                        'How each column may be filtered, keyed by the column '
                        . 'name: "date", "num", "string", or false where the '
                        . 'column may not be searched at all. Derived from the '
                        . 'column type on the server, so it is answerable '
                        . 'without reading any rows.'
                    ),
                    'additionalProperties' => self::_oneOfTypes(
                        ['string', 'boolean']
                    )
                ],
                'data' => ['type' => 'array', 'items' => ['type' => 'object']],
                'firstUrl' => ['type' => 'string', 'nullable' => true],
                'prevUrl' => ['type' => 'string', 'nullable' => true],
                'nextUrl' => [
                    'type' => 'string',
                    'nullable' => true,
                    'description' => _('Null when this is the last page.')
                ],
                'lastUrl' => ['type' => 'string', 'nullable' => true]
            ]
        ];
    }

    /**
     * Every path in the document.
     *
     * @return array
     */
    private static function _paths()
    {
        $paths = self::_fixedPaths();
        $tasking = array_map('strtolower', (array)Route::$validTaskingClasses);
        $active = array_map('strtolower', (array)Route::$validActiveTasks);
        foreach (self::_documentedClasses() as $class) {
            foreach (self::_classPaths($class, $tasking, $active) as $path => $item) {
                $paths[$path] = isset($paths[$path])
                    ? array_merge($paths[$path], $item)
                    : $item;
            }
        }
        ksort($paths);
        return $paths;
    }

    /**
     * The per-class operations.
     *
     * Path aliases are collapsed to one documented spelling. The router
     * accepts /{class}/list and /{class}/all for a list, /create and /new
     * for a create, /update and /edit for an update, /delete and /remove for
     * a delete; documenting all of them would treble the path count and
     * teach nobody anything, so the bare form is described and the aliases
     * are noted in prose.
     *
     * Optional trailing filter segments ({whereItems}, {getField}) are
     * likewise described rather than expanded into extra paths -- OpenAPI
     * has no optional path segment, so each one would otherwise double the
     * operations it appears on.
     *
     * @param string $class   The lowercase route class name.
     * @param array  $tasking Classes accepting task/cancel.
     * @param array  $active  Classes exposing a current/active list.
     *
     * @return array
     */
    private static function _classPaths($class, array $tasking, array $active)
    {
        $ref = '#/components/schemas/' . self::schemaName($class);
        $paths = [];
        // Route::$readOnlyClasses keeps the four write verbs off some
        // classes, so the document has to drop the same four or it
        // advertises operations that answer 404. Generated from the same
        // list the router expands, not a second copy of it.
        $writable = in_array(
            $class,
            array_map('strtolower', (array)Route::writableClasses()),
            true
        );

        $paths['/' . $class] = [
            'get' => self::_op(
                $class,
                'list',
                sprintf(_('List %s'), $class),
                _('Also reachable as /list and /all. Filter with ?filter= or '
                    . 'the equivalent trailing path segment; both take '
                    . 'field=value pairs joined with &.'),
                self::_listResponse($ref),
                self::_listParameters()
            ),
        ];
        if ($writable) {
            $paths['/' . $class]['post'] = self::_op(
                $class,
                'create',
                sprintf(_('Create a %s'), $class),
                _('Also reachable as /create and /new.'),
                self::_entityResponse($ref, _('The created object.')),
                [],
                self::_entityBody($ref)
            );
        }

        $paths['/' . $class . '/{id}'] = [
            'parameters' => [self::_idParameter()],
            'get' => self::_op(
                $class,
                'indiv',
                sprintf(_('Get one %s by id'), $class),
                _('Fields withheld from list responses are returned here.'),
                self::_entityResponse($ref)
            ),
        ];
        if ($writable) {
            $paths['/' . $class . '/{id}']['put'] = self::_op(
                $class,
                'update',
                sprintf(_('Update a %s'), $class),
                _('Also reachable as /update and /edit. Send only the fields '
                    . 'being changed.'),
                self::_entityResponse($ref),
                [],
                self::_entityBody($ref)
            );
            $paths['/' . $class . '/{id}']['delete'] = self::_op(
                $class,
                'delete',
                sprintf(_('Delete a %s'), $class),
                _('Also reachable as /delete and /remove.'),
                self::_messageResponse() + self::_conflictResponse(
                    _('Another record still refers to this one and the '
                        . 'database refused the delete. The message names '
                        . 'what is holding it; retry once that is reassigned '
                        . 'or removed.')
                )
            );
        }

        // Route::search() cannot answer for every class, so the document
        // must not offer it for every class. See _isSearchable().
        if (self::_isSearchable($class)) {
            $paths['/' . $class . '/search/{item}'] = [
                'parameters' => [
                    [
                        'name' => 'item',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                        'description' => _('Text to match against the id and name. A few '
                            . 'classes match more: host also on MAC, storagenode '
                            . 'on node hostname, setting on value.')
                    ]
                ],
                'get' => self::_op(
                    $class,
                    'search',
                    sprintf(_('Search %s'), $class),
                    _('Matches the term against the class name field. '
                        . 'Returns the same envelope as a list. Takes no '
                        . 'filter -- the match is the whole query.'),
                    self::_listResponse($ref),
                    self::_pageParameters()
                )
            ];
        }

        $paths['/' . $class . '/count'] = [
            'get' => self::_op(
                $class,
                'count',
                sprintf(_('Count %s'), $class),
                _('Accepts the same optional filter as a list. Reports the '
                    . 'true filtered total and ignores paging.'),
                [
                    '200' => [
                        'description' => _('The count.'),
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'total' => ['type' => 'integer']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                self::_filterParameters()
            )
        ];

        $paths['/' . $class . '/names'] = [
            'get' => self::_op(
                $class,
                'names',
                sprintf(_('Id and name pairs for %s'), $class),
                _('Unpaged and uncapped -- the cheap way to enumerate a large '
                    . 'table. Accepts an optional filter.'),
                self::_rawArrayResponse(),
                self::_filterParameters()
            )
        ];

        $paths['/' . $class . '/ids'] = [
            'get' => self::_op(
                $class,
                'ids',
                sprintf(_('Ids for %s'), $class),
                _('Unpaged and uncapped. Accepts an optional filter and an '
                    . 'optional trailing field name to return instead of the '
                    . 'id.'),
                self::_rawArrayResponse(),
                self::_filterParameters()
            )
        ];

        if ($writable) {
            // Route::joining() does two unrelated things depending on the
            // method, and the document described neither of them correctly:
            // it called PUT an update-or-insert keyed on the name, which is
            // what POST does, and it left POST out entirely.
            //
            // The cost of getting this one wrong is higher than for most
            // operations. A caller who believes the old summary sends an
            // object without ids, gets a 202 back, and nothing happens --
            // success on the wire and no effect on the server.
            $paths['/' . $class . '/join'] = [
                'put' => self::_op(
                    $class,
                    'join',
                    sprintf(_('Bulk edit %s'), $class),
                    _('Applies one set of field values to every object named '
                        . 'in ids. Fields left out of the body keep their '
                        . 'current value on each object, so this edits rather '
                        . 'than replaces. It never creates anything, and it '
                        . 'matches on nothing but the ids given: a body with '
                        . 'no ids matches nothing and succeeds without '
                        . 'changing anything.'),
                    self::_acceptedResponse(),
                    [],
                    self::_bulkEditBody($ref)
                )
            ];
            // POST is group only. Route::joining() answers 400 for every other
            // class, so advertising it on all of them would send callers at an
            // endpoint that refuses them.
            if ('group' === $class) {
                $paths['/' . $class . '/join']['post'] = self::_op(
                    $class,
                    'join',
                    sprintf(_('Get or create %s by name'), $class),
                    _('Takes a list of names and returns an id for each: the '
                        . 'existing object where the name is already taken, a '
                        . 'newly created one otherwise. That pattern is often '
                        . 'called an upsert -- update or insert -- and because '
                        . 'the object may not exist yet it matches on the name '
                        . 'rather than on an id. Safe to send repeatedly: the '
                        . 'same names give the same ids back and no duplicates '
                        . 'are made. Group only; every other class answers '
                        . '400.'),
                    self::_idsResponse(),
                    [],
                    self::_namesBody(),
                    'JoinByName'
                );
            }
        }

        if (in_array($class, $active, true)) {
            $paths['/' . $class . '/current'] = [
                'get' => self::_op(
                    $class,
                    'active',
                    sprintf(_('Active %s'), $class),
                    _('Also reachable as /active.'),
                    self::_listResponse($ref)
                )
            ];
        }

        if (in_array($class, $tasking, true)) {
            $paths['/' . $class . '/{id}/task'] = [
                'parameters' => [self::_idParameter()],
                'post' => self::_op(
                    $class,
                    'task',
                    sprintf(_('Queue a task against a %s'), $class),
                    _('Body accepts taskTypeID, taskName, shutdown, debug, '
                        . 'deploySnapins, passreset, sessionjoin and wol. '
                        . 'Wake-on-lan is this route with wol set, not a route '
                        . 'of its own.'),
                    self::_entityResponse(
                        '#/components/schemas/ListEnvelope',
                        _('The created task.')
                    ),
                    [],
                    [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/'
                                        . 'TaskRequest'
                                ]
                            ]
                        ]
                    ]
                )
            ];
            $paths['/' . $class . '/{id}/cancel'] = [
                'parameters' => [self::_idParameter()],
                'delete' => self::_op(
                    $class,
                    'cancel',
                    sprintf(_('Cancel a %s task'), $class),
                    // Hand-written, because the 409 is a router decision the
                    // generic emitter cannot see. Only cancel answers it, so
                    // it belongs on this operation and not in the shared
                    // _errorResponses() map every path picks up.
                    _(
                        'Only a task in a queued or in-progress state can be '
                        . 'canceled. Naming a resource whose task has already '
                        . 'finished -- Complete, Canceled or Failed -- answers '
                        . '409 rather than reporting a success it did not '
                        . 'perform.'
                    ),
                    self::_messageResponse() + self::_conflictResponse(
                        _('The resource is not in a cancellable state.')
                    )
                )
            ];
        }

        return $paths;
    }

    /**
     * Assembles one operation, resolving its permission through the same
     * Authorization call the router makes when the request actually
     * arrives, so the documented requirement cannot drift from the enforced
     * one.
     *
     * @param string $class      The lowercase route class name.
     * @param string $routeName  The router's name for this route.
     * @param string $summary    One line summary.
     * @param string $desc       Longer description, may be empty.
     * @param array  $responses  Response map.
     * @param array  $parameters Extra parameters.
     * @param array  $body       Request body, if any.
     *
     * @return array
     */
    private static function _op(
        $class,
        $routeName,
        $summary,
        $desc,
        array $responses,
        array $parameters = [],
        array $body = [],
        $action = ''
    ) {
        // operationId is `Group_Action`, which is not decoration: it is the
        // convention every OpenAPI code generator reads names out of.
        //
        // AutoRest.PowerShell's configuration says it outright -- "the
        // operationId-method is the identifier that comes after the
        // underscore" -- and its verb map turns that half into a cmdlet verb
        // while the half before it becomes the noun. openapi-generator splits
        // on the same character to pick an api class and a method name.
        //
        // This document used to emit `indivHost`: one word, no separator. So
        // the group came out empty, the verb map never matched, and the
        // generator fell back to guessing a verb out of the middle of the
        // string. It warns when it does -- "Operation indiv/usertracking is
        // inferred without finding action" -- and what came out the other end
        // was Invoke-IndivHost from AutoRest and ConvertTo-FogdivHost from
        // openapi-generator, with no Get-Host anywhere in 567 operations.
        // `Host_Get` gets Get-Host from both, with no per-operation
        // configuration at all.
        //
        // The route name is NOT what changes. Route::$routes keys on it and
        // Authorization::resolveApiPermission() looks permissions up by it,
        // so it stays exactly as the router knows it; only the id derived
        // from it here is new.
        //
        // $action overrides the mapping for a route that serves two
        // operations: PUT|POST /{class}/join is registered once, under one
        // name, and the two methods do unrelated things. Everything else is
        // decided by route name in _operationAction() and _operationGroup(),
        // so no call site carries naming.
        $action = ('' !== $action)
            ? $action
            : self::_operationAction($routeName);
        $group = self::_operationGroup($routeName, $class);
        $operationId = $group . '_' . $action;
        $op = [
            // One decision, not two. The tag is the group lowercased, so a
            // fixed route that files itself under `plugin` is tagged plugin
            // as well -- the tag used to be patched onto the returned array
            // afterward, by each route that cared, because $class had to
            // stay empty for the permission lookup.
            'tags' => [strtolower($group)],
            'operationId' => $operationId,
            'summary' => $summary,
            'responses' => $responses + self::_errorResponses()
        ];
        if ('' !== $desc) {
            $op['description'] = $desc;
        }
        if (count($parameters) > 0) {
            $op['parameters'] = $parameters;
        }
        if (count($body) > 0) {
            $op['requestBody'] = $body;
        }
        $permission = self::_permission($routeName, $class);
        if (null !== $permission) {
            $op['x-fog-permission'] = $permission;
        }
        return $op;
    }

    /**
     * The action half of an operationId, for one router route name.
     *
     * The router's vocabulary and a code generator's are not the same, and
     * this is the one place they are reconciled. `indiv` is a perfectly good
     * name for the route that returns one row, and it is a useless name to
     * derive a cmdlet from: no generator knows the word, so all of them guess.
     * `Get` is in every generator's verb table.
     *
     * Thirteen entries cover 528 of the document's 567 operations. What they
     * produce, through AutoRest's built-in verb map and with no further
     * configuration:
     *
     *   Host_Get        Get-Host        Host_Create     New-Host
     *   Host_List       Get-Host        Host_Update     Update-Host
     *   Host_Delete     Remove-Host     Host_Search     Search-Host
     *   Host_Join       Join-Host       Host_CancelTask Stop-HostTask
     *
     * Get and List deliberately differ so that a generator can merge them
     * into one cmdlet with two parameter sets, which is what Az does and
     * what `Get-Host -Id 1` versus `Get-Host` should be.
     *
     * A route name with no entry keeps its own word, PascalCased. That is
     * the right default for the fixed routes -- `whoami` and `bandwidth`
     * describe themselves -- and it means a new route is never silently
     * mapped to something it is not.
     *
     * @param string $routeName The router's name for this route.
     *
     * @return string The action half, PascalCase.
     */
    private static function _operationAction($routeName)
    {
        $map = [
            // The generic per-class routes. 528 of 567 operations.
            'indiv' => 'Get',
            'list' => 'List',
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
            'search' => 'Search',
            'join' => 'Join',
            'count' => 'Count',
            'ids' => 'ListId',
            'names' => 'ListName',
            'task' => 'CreateTask',
            'cancel' => 'CancelTask',
            'active' => 'ListActive',
            // The fixed routes. Their names describe the thing rather than
            // the doing -- `whoami`, `bandwidth`, `logfiles` -- so left
            // alone every one of them would land in the generator's
            // guess-a-verb path, which is the case this whole change exists
            // to remove. Leading each with a real verb is what makes the
            // warning count zero rather than sixteen.
            'status' => 'GetInfo',
            'openapi' => 'GetOpenapi',
            'openapiswaggeralias' => 'GetOpenapiAlias',
            'whoami' => 'GetWhoami',
            'bandwidth' => 'GetBandwidth',
            'logfiles' => 'Get',
            'pendingmacs' => 'ListPendingMac',
            'kernelupdate' => 'List',
            'initrdupdate' => 'List',
            'unisearch' => 'SearchAll',
            'userprefs' => 'ListPref',
            'settingscacheview' => 'Get',
            'settingscacheflush' => 'Flush',
            'settingscacherefresh' => 'Refresh',
            'plugininstall' => 'Install',
            'snapincreatewithfile' => 'CreateWithFile',
            'uploadsnapinfiles' => 'UploadSnapinFile'
        ];
        $key = strtolower((string)$routeName);
        return isset($map[$key])
            ? $map[$key]
            : ucfirst((string)$routeName);
    }

    /**
     * The group half of an operationId, for one router route name.
     *
     * For a per-class route it is the class, which is what the reader and
     * every generator expect: `Host_Get` becomes `Get-Host`.
     *
     * A fixed route has no class -- and must not be given one, because
     * $class is what _permission() resolves against, so inventing one there
     * would change who may call the route. It gets its noun here instead.
     * Without this they would all be `System`, and the plugin installer
     * would generate as Install-SystemPlugin rather than Install-Plugin.
     *
     * The tag follows the group, so these also file themselves under the
     * right heading in a Swagger UI. That used to be patched onto the
     * finished operation by each route that cared.
     *
     * @param string $routeName The router's name for this route.
     * @param string $class     The lowercase route class name, may be empty.
     *
     * @return string The group half, PascalCase.
     */
    private static function _operationGroup($routeName, $class)
    {
        if ('' !== (string)$class) {
            return self::className($class);
        }
        $map = [
            'pendingmacs' => 'Host',
            'logfiles' => 'Logfile',
            'kernelupdate' => 'Kernel',
            'initrdupdate' => 'Initrd',
            'settingscacheview' => 'SettingsCache',
            'settingscacheflush' => 'SettingsCache',
            'settingscacherefresh' => 'SettingsCache',
            'plugininstall' => 'Plugin',
            'snapincreatewithfile' => 'Snapin',
            'uploadsnapinfiles' => 'Storagegroup'
        ];
        $key = strtolower((string)$routeName);
        return isset($map[$key]) ? $map[$key] : 'System';
    }

    /**
     * The permission string the router will require for this operation.
     *
     * @param string $routeName The router's name for this route.
     * @param string $class     The lowercase route class name.
     *
     * @return string|null
     */
    private static function _permission($routeName, $class)
    {
        // Called directly. This used to be guarded by
        // method_exists('Authorization', ...) -- a class name in a STRING,
        // which PHP resolves as written from the global namespace, ignoring
        // the `use` above. Core stopped answering its bare names when the
        // global aliases were retired (ADR 0013 §2), so the guard was false
        // on every call: _permission() returned null for every operation and
        // the published spec said the whole REST API needs no permission.
        // Silently, plus an autoloader line in the error log per render.
        // The guard bought nothing even when it worked -- the method is
        // declared in this repository, beside this file.
        $permission = Authorization::resolveApiPermission($routeName, $class);
        return ('' === $permission || null === $permission) ? null : $permission;
    }

    /**
     * A property that genuinely accepts more than one scalar type.
     *
     * 3.1 would spell this `type: [a, b]`. 3.0 has no type array, so it is
     * oneOf -- and oneOf rather than anyOf because a JSON scalar is exactly
     * one of these, never several at once. See OAS_VERSION for why this
     * document is 3.0.
     *
     * These unions are real, not an artifact of the spelling: the task route
     * reads its body through PHP's loose comparison, so shutdown accepts
     * true and "1" alike, and documenting only one of them would understate
     * what the server takes.
     *
     * @param array $types The accepted JSON types.
     *
     * @return array
     */
    private static function _oneOfTypes(array $types)
    {
        return [
            'oneOf' => array_map(
                function ($t) {
                    return ['type' => $t];
                },
                $types
            )
        ];
    }

    /**
     * @return array
     */
    private static function _idParameter()
    {
        return [
            'name' => 'id',
            'in' => 'path',
            'required' => true,
            'schema' => ['type' => 'integer']
        ];
    }

    /**
     * @return array
     */
    private static function _listParameters()
    {
        return array_merge(
            self::_pageParameters(),
            self::_filterParameters()
        );
    }

    /**
     * Paging only, for an operation that pages but cannot be filtered.
     *
     * search() builds its own where clause from the ids it matched and
     * passes it down as an array, which Route::handleWhereItems() will not
     * let a request-supplied filter replace -- so advertising ?filter=
     * there would describe an argument the handler ignores. That is the
     * defect this whole change is fixing, so it must not be reintroduced
     * one function further along.
     *
     * @return array
     */
    private static function _pageParameters()
    {
        return [
            ['$ref' => '#/components/parameters/start'],
            ['$ref' => '#/components/parameters/length'],
            ['$ref' => '#/components/parameters/expand']
        ];
    }

    /**
     * Filter alone, for the operations that take no page.
     *
     * count/names/ids all route their filter through the same
     * Route::handleWhereItems(), so they accept exactly what list does --
     * but none of them pages, so start/length/expand would be advertising
     * arguments the handler never reads.
     *
     * @return array
     */
    private static function _filterParameters()
    {
        return [
            ['$ref' => '#/components/parameters/filter']
        ];
    }

    /**
     * @param string $ref Schema ref for the row type.
     *
     * @return array
     */
    private static function _listResponse($ref)
    {
        return [
            '200' => [
                'description' => _('A page of results.'),
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => self::pageRef($ref)]
                    ]
                ]
            ]
        ];
    }

    /**
     * The component ref for the page wrapper around a row type.
     *
     * `#/components/schemas/Host` -> `#/components/schemas/HostPage`.
     *
     * The wrapper used to be written inline at each of the three places a
     * page is returned, which is legal and unusable: an anonymous schema has
     * no name, so every code generator invents one. AutoRest called this one
     * `IPaths8Cd1AsHostGetResponses200ContentApplicationJsonSchema` and put
     * it in Get-Host's OutputType, where a user reads it.
     *
     * @param string $ref The row type's own ref.
     *
     * @return string
     */
    public static function pageRef($ref)
    {
        $name = substr((string)$ref, strrpos((string)$ref, '/') + 1);
        return '#/components/schemas/' . $name . 'Page';
    }

    /**
     * The page wrapper schema for one row type: the shared envelope, plus a
     * data array of that type.
     *
     * Named rather than inline so it is addressable -- by a generator
     * deciding what to call the model, and by x-ms-pageable, which names the
     * item property it has to walk.
     *
     * @param string $ref The row type's own ref.
     *
     * @return array
     */
    private static function _pageSchema($ref)
    {
        // Flat, not an allOf over ListEnvelope, and the reason is
        // mechanical rather than stylistic.
        //
        // x-ms-pageable names the property holding the rows and the property
        // holding the link, and a generator then looks both up on the
        // schema: AutoRest does
        //
        //   schema.properties.find(p => p.serializedName === itemName)
        //
        // An allOf composition has no properties of its own -- data and
        // nextUrl each live in a branch -- so that lookup finds nothing and
        // the generator fails outright. Composing was tried first and
        // reproduced exactly that.
        //
        // Inlining the envelope is also the better shape to read: a page IS
        // its counts, its link and its rows, and a named type saying so
        // beats one that says "see the other schema".
        $envelope = self::_listEnvelopeSchema();
        $properties = isset($envelope['properties'])
            ? $envelope['properties']
            : [];
        $properties['data'] = [
            'type' => 'array',
            'items' => ['$ref' => $ref]
        ];
        $out = [
            'type' => 'object',
            'properties' => $properties
        ];
        if (isset($envelope['description'])) {
            $out['description'] = $envelope['description'];
        }
        return $out;
    }

    /**
     * @param string $ref  Schema ref.
     * @param string $desc Optional description.
     *
     * @return array
     */
    private static function _entityResponse($ref, $desc = '')
    {
        return [
            '200' => [
                'description' => '' === $desc ? _('The object.') : $desc,
                'content' => [
                    'application/json' => ['schema' => ['$ref' => $ref]]
                ]
            ]
        ];
    }

    /**
     * @param string $ref Schema ref.
     *
     * @return array
     */
    private static function _entityBody($ref)
    {
        return [
            'required' => true,
            'content' => [
                'application/json' => ['schema' => ['$ref' => $ref]]
            ]
        ];
    }

    /**
     * The body PUT /{class}/join takes.
     *
     * The entity schema plus the ids to apply it to. allOf rather than a
     * copied property list, so the field set cannot drift from the class's
     * own schema.
     *
     * @param string $ref The entity schema reference.
     *
     * @return array
     */
    private static function _bulkEditBody($ref)
    {
        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => ['$ref' => self::joinRef($ref)]
                ]
            ]
        ];
    }

    /**
     * The component ref for the join body of a class.
     *
     * `#/components/schemas/Host` -> `#/components/schemas/HostJoin`.
     *
     * Same defect and same fix as pageRef(): the body was written inline at
     * every join route, so it had no name and every generator invented one.
     *
     * @param string $ref The entity schema reference.
     *
     * @return string
     */
    public static function joinRef($ref)
    {
        $name = substr((string)$ref, strrpos((string)$ref, '/') + 1);
        return '#/components/schemas/' . $name . 'Join';
    }

    /**
     * The join body for one class: the entity's own fields, plus the ids to
     * apply them to.
     *
     * Still an allOf, unlike the page schema, and deliberately so. A page was
     * flattened because x-ms-pageable has to find `data` in the schema's own
     * properties; nothing looks the join body up that way, and allOf is what
     * keeps this field set from drifting away from the class's own schema.
     *
     * What changed is that BOTH branches are now named refs. An allOf whose
     * second branch is written inline still leaves that branch anonymous, and
     * a generator names it -- which is how `...JoinPutRequestbodyContent
     * ApplicationJsonSchemaAllof1` came about. Pointing at BulkEditIds costs
     * one shared schema and leaves nothing unnamed.
     *
     * @param string $ref The entity schema reference.
     *
     * @return array
     */
    private static function _joinSchema($ref)
    {
        return [
            'allOf' => [
                ['$ref' => $ref],
                ['$ref' => '#/components/schemas/BulkEditIds']
            ]
        ];
    }

    /**
     * The body POST /{class}/{id}/task takes.
     *
     * Written inline at the route, which meant one anonymous copy per
     * tasking class even though every copy was identical. Shared and named,
     * because the body genuinely is the same body -- queueing a task against
     * a host and against a group take the same fields.
     *
     * @return array
     */
    private static function _taskRequestSchema()
    {
        return [
            'type' => 'object',
            'description' => _('The fields a queued task accepts. '
                . 'Wake-on-lan is this body with wol set, not a route of '
                . 'its own.'),
            'properties' => [
                'taskTypeID' => self::_oneOfTypes(['string', 'integer']),
                'taskName' => ['type' => 'string'],
                'shutdown' => self::_oneOfTypes(['string', 'boolean']),
                'debug' => self::_oneOfTypes(['string', 'boolean']),
                'deploySnapins' => self::_oneOfTypes(
                    ['string', 'integer', 'boolean']
                ),
                'passreset' => ['type' => 'string'],
                'sessionjoin' => self::_oneOfTypes(['string', 'boolean']),
                'wol' => self::_oneOfTypes(['string', 'boolean'])
            ]
        ];
    }

    /**
     * The ids half of every join body, shared rather than repeated.
     *
     * @return array
     */
    private static function _bulkEditIdsSchema()
    {
        return [
            'type' => 'object',
            'required' => ['ids'],
            'properties' => [
                'ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => _('The objects to apply these values '
                        . 'to. An empty or absent list matches nothing and '
                        . 'edits nothing.')
                ]
            ]
        ];
    }

    /**
     * The body POST /group/join takes.
     *
     * @return array
     */
    private static function _namesBody()
    {
        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => [
                        '$ref' => '#/components/schemas/NamesRequest'
                    ]
                ]
            ]
        ];
    }

    /**
     * The names half of POST /group/join, named for the same reason as the
     * rest: written inline it has no name, so a generator invents one.
     *
     * @return array
     */
    private static function _namesRequestSchema()
    {
        return [
            'type' => 'object',
            'required' => ['names'],
            'properties' => [
                'names' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => _('Names to resolve. Each is created '
                        . 'if no object already has it.')
                ]
            ]
        ];
    }

    /**
     * A write that is applied without echoing anything back.
     *
     * Route::joining()'s PUT branch sets HTTP_ACCEPTED and emits no body.
     * Saying 200 with an entity here would have a generated client wait for
     * an object that never arrives.
     *
     * @return array
     */
    private static function _acceptedResponse()
    {
        return [
            '202' => ['description' => _('Applied. No body is returned.')]
        ];
    }

    /**
     * What POST /{class}/join answers with, which is nothing.
     *
     * This used to be documented as an array of ids. It never was one:
     * Route::joining() ends at `self::sendResponse($code)` with no body
     * argument, so the route has always replied 201 and closed. A generated
     * client was being told to wait for a list that does not arrive.
     *
     * @return array
     */
    private static function _idsResponse()
    {
        return [
            '201' => [
                'description' => _('Created. No body is returned.')
            ]
        ];
    }

    /**
     * @return array
     */
    private static function _messageResponse()
    {
        return [
            '200' => [
                'description' => _('Success.'),
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => ['msg' => ['type' => 'string']]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * A 409, for a request that is well formed but cannot be applied to the
     * resource in its current state.
     *
     * Carries `{error}`, the shape every non-2xx in the router uses. It is
     * also the only one $.notifyFromAPI() colors as a failure -- a body
     * keyed on `msg` is typed as a SUCCESS -- which is why the property is
     * fixed here rather than left to the caller.
     *
     * Two routes answer it and they conflict for unrelated reasons -- cancel
     * because the task has already finished, delete because a foreign key
     * still refers to the row (ADR 0031) -- so the description IS the
     * caller's to supply. Both are retryable once the blocking condition is
     * gone, which is what makes 409 the right code for each.
     *
     * @param string $description what makes this particular request a
     *                            conflict
     *
     * @return array
     */
    private static function _conflictResponse($description)
    {
        return [
            '409' => [
                'description' => $description,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => ['error' => ['type' => 'string']]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Routes that answer with an unpaged list rather than a full page.
     *
     * @return array
     */
    private static function _rawArrayResponse()
    {
        return [
            '200' => [
                'description' => _('An unpaged list.'),
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/ValueList'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * What /ids, /names and the other unpaged routes answer with.
     *
     * The rows sit under `data`, which is where every list route already
     * puts them. These routes used to answer with a bare top-level array,
     * and a bare array cannot be described to a code generator.
     *
     * It is not a naming problem, and that was worth being certain of
     * before changing a wire format. Five response shapes were generated
     * and compiled: an inline array, a $ref to a NAMED top-level array
     * schema, an inline array whose items are a named object, an array of
     * integers, and an object wrapping the array. The first four fail
     * identically; only the wrapper compiles. AutoRest emits no model for a
     * top-level array in any form, falls back to the only named schema on
     * the operation -- Error -- and then writes `.Count`, `[0]` and
     * `foreach` against it. 108 operations, 333 compile errors, out of a
     * generation run that reported zero warnings.
     *
     * The generated code is otherwise identical either way: it walks
     * `result` for an array and `result.Data` for a wrapper, and only needs
     * a property to walk. A PowerShell caller sees no difference, because
     * the cmdlet unwraps `data` itself and writes the rows to the pipeline.
     *
     * The element type stays `object`. Route::ids() projects whatever
     * getField names, one column or several, so a row is a scalar or an
     * object depending on the request. Narrowing it here would be a guess
     * that is wrong half the time.
     *
     * @return array
     */
    private static function _valueListSchema()
    {
        return [
            'type' => 'object',
            'description' => _('An unpaged list of values.'),
            'properties' => [
                'data' => [
                    'type' => 'array',
                    'description' => _('One entry per row. The element '
                        . 'shape follows the requested fields: a scalar '
                        . 'when a single column is projected, an object '
                        . 'when several are.'),
                    'items' => ['type' => 'object']
                ]
            ]
        ];
    }

    /**
     * @return array
     */
    private static function _errorResponses()
    {
        return [
            '400' => [
                'description' => _('Bad request.'),
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/Error']
                    ]
                ]
            ],
            '401' => ['description' => _('Authentication missing or invalid.')],
            '403' => ['description' => _('Authenticated but not permitted.')],
            '404' => ['description' => _('No such object.')]
        ];
    }

    /**
     * The routes that carry no {class} segment.
     *
     * @return array
     */
    /**
     * The {key} path parameter shared by the three userpref operations.
     *
     * @return array
     */
    /**
     * The {id} path parameter shared by the saved-filter item operations.
     *
     * @return array
     */
    /**
     * An id/name list, as the share-target route returns.
     *
     * @return array
     */
    private static function _namedIdList()
    {
        return [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string']
                ]
            ]
        ];
    }
    private static function _filterIdParam()
    {
        return [
            [
                'name' => 'id',
                'in' => 'path',
                'required' => true,
                'description' => _('The saved filter.'),
                'schema' => ['type' => 'integer']
            ]
        ];
    }
    /**
     * One saved filter as the list operation returns it.
     *
     * @return array
     */
    private static function _savedFilterSchema()
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'mine' => [
                    'type' => 'boolean',
                    'description' => _('You own it, so you may rename, '
                        . 're-share or delete it.')
                ],
                'global' => [
                    'type' => 'boolean',
                    'description' => _('Offered to every user on this grid.')
                ],
                'source' => [
                    'type' => 'string',
                    'enum' => ['mine', 'user', 'group', 'role', 'global'],
                    'description' => _('Why the caller can see it, most '
                        . 'specific grant first. A filter reachable several '
                        . 'ways is still returned once and reports only the '
                        . 'most specific reason.')
                ],
                'sharedBy' => [
                    'type' => 'string',
                    'description' => _('Username of whoever created it, on '
                        . 'filters the caller does not own. Empty when the '
                        . 'creator\'s account has since been deleted.')
                ],
                'value' => [
                    'type' => 'string',
                    'description' => _('The filter state, opaque. It is '
                        . 'DataTables SearchBuilder\'s own serialization and '
                        . 'the server does not interpret it.')
                ],
                'modified' => ['type' => 'string']
            ]
        ];
    }
    /**
     * The share-target lists, as sent and as returned.
     *
     * @return array
     */
    private static function _shareTargetsSchema()
    {
        $ids = [
            'type' => 'array',
            'items' => ['type' => 'integer']
        ];

        return [
            'type' => 'object',
            'properties' => [
                'users' => $ids,
                'groups' => $ids,
                'roles' => $ids
            ]
        ];
    }
    private static function _prefKeyParam()
    {
        return [
            [
                'name' => 'key',
                'in' => 'path',
                'required' => true,
                'description' => _('The preference key. Namespaced by its '
                    . 'consumer -- a grid\'s saved state is stored under '
                    . '"dt.<table id>" -- so that two features cannot '
                    . 'collide on one name.'),
                'schema' => ['type' => 'string', 'maxLength' => 190]
            ]
        ];
    }
    private static function _fixedPaths()
    {
        $json = function ($schema, $desc) {
            return [
                '200' => [
                    'description' => $desc,
                    'content' => ['application/json' => ['schema' => $schema]]
                ]
            ];
        };
        return [
            '/system/info' => [
                'get' => self::_op(
                    '',
                    'status',
                    _('Server version and paging bounds'),
                    _('Unauthenticated. Also reachable as /system/status. The '
                        . 'cheap way to read the paging bounds -- the same '
                        . 'values appear as x-fog-paging on this document, '
                        . 'which is several hundred kilobytes larger.'),
                    $json(
                        [
                            'type' => 'object',
                            'properties' => [
                                'version' => ['type' => 'string'],
                                'paging' => self::_pagingSchema(),
                                'msg' => ['type' => 'string']
                            ]
                        ],
                        _('Version information and paging bounds.')
                    )
                )
            ],
            '/agent/v1/enroll' => [
                'post' => self::_op(
                    '',
                    'agentenroll',
                    _('FOG Agent enrollment'),
                    _('Unauthenticated, because this is how an agent obtains '
                        . 'the client certificate it will authenticate with '
                        . 'afterward. The agent posts a certificate signing '
                        . 'request and its firmware identity; the server '
                        . 'resolves the machine the way iPXE registration '
                        . 'does and answers issued, pending or denied. Pending '
                        . 'is the normal first answer for a machine nobody has '
                        . 'approved yet -- the agent polls until an admin '
                        . 'decides on /agent/enrollment/{id}/{action}, an '
                        . 'enrollment token pre-approves it, or the server '
                        . 'itself imaged the host recently '
                        . '(FOG_AGENT_ENROLL_DEPLOY_WINDOW). A pending agent '
                        . 'can do nothing else: without a certificate no other '
                        . 'agent route accepts it. Protocol 1.'),
                    [
                        '200' => [
                            'description' => _('Issued. The certificate and '
                                . 'the host it binds to.'),
                            'content' => ['application/json' => ['schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'status' => ['type' => 'string', 'enum' => ['issued']],
                                    'host_id' => ['type' => 'integer'],
                                    'certificate_pem' => [
                                        'type' => 'string',
                                        'description' => _('The leaf followed by the agent CA, PEM.')
                                    ],
                                    'not_after' => ['type' => 'string']
                                ]
                            ]]]
                        ],
                        '202' => [
                            'description' => _('Pending an admin decision. '
                                . 'Poll again after retry_after seconds.'),
                            'content' => ['application/json' => ['schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'status' => ['type' => 'string', 'enum' => ['pending']],
                                    'reason' => [
                                        'type' => 'string',
                                        'description' => _('Why it waits: unknown-host, '
                                            . 'known-host-no-agent, rebind, '
                                            . 'identity-conflict, reissue.')
                                    ],
                                    'retry_after' => ['type' => 'integer']
                                ]
                            ]]]
                        ],
                        '400' => ['description' => _('The CSR is not a usable P-256 request, or a required field is missing.')],
                        '403' => ['description' => _('Denied by an admin. The agent backs off to hourly.')],
                        '426' => ['description' => _('The agent speaks a protocol this server does not.')],
                        '503' => ['description' => _('Approved but the signer is unavailable; the agent retries.')]
                    ],
                    [],
                    [
                        'required' => true,
                        'content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'required' => ['protocol', 'csr_pem', 'identity'],
                            'properties' => [
                                'protocol' => ['type' => 'integer', 'enum' => [1]],
                                'agent_version' => ['type' => 'string'],
                                'os' => ['type' => 'string'],
                                'arch' => ['type' => 'string'],
                                'hostname' => ['type' => 'string'],
                                'identity' => [
                                    'type' => 'object',
                                    'description' => _('SMBIOS system UUID, system serial, '
                                        . 'board serial, chassis asset tag and the MAC list, '
                                        . 'as fog-agent identity prints them.')
                                ],
                                'csr_pem' => ['type' => 'string'],
                                'token' => [
                                    'type' => 'string',
                                    'description' => _('An enrollment token, if the installer was given one.')
                                ]
                            ]
                        ]]]
                    ]
                )
            ],
            '/agent/v1/poll' => [
                'post' => self::_op(
                    '',
                    'agentpoll',
                    _('FOG Agent poll'),
                    _('Authenticated by the client certificate enrollment '
                        . 'issued, verified by the web server and bound to '
                        . 'the host by its key fingerprint before the route '
                        . 'runs; no token or session applies. Records the '
                        . 'check-in and answers with the revision of the '
                        . 'host\'s desired state, plus the state itself when '
                        . 'the applied revision the agent sent is not '
                        . 'current or it asked for it. The revision is '
                        . 'opaque: compared for equality, never parsed. A '
                        . 'certificate that no longer binds to a live host '
                        . 'gets 401, which tells the agent to enroll again. '
                        . 'The request may also carry facts about the host -- '
                        . 'hardware inventory, the installed-program list -- '
                        . 'sent only when their content hash moved or the '
                        . 'answer asked; the same conditional as the state, '
                        . 'run in the other direction.'),
                    [
                        '200' => [
                            'description' => _('The host this certificate is, '
                                . 'the revision of its desired state, and the '
                                . 'state when it is not what the agent applied.'),
                            'content' => ['application/json' => ['schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'status' => ['type' => 'string', 'enum' => ['ok']],
                                    'protocol' => ['type' => 'integer'],
                                    'host' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'id' => ['type' => 'integer'],
                                            'name' => ['type' => 'string']
                                        ]
                                    ],
                                    'revision' => ['type' => 'string'],
                                    'poll_interval' => ['type' => 'integer'],
                                    'server_time' => ['type' => 'string'],
                                    'state' => [
                                        'type' => 'object',
                                        'description' => _('The desired state: revision, '
                                            . 'capabilities, and one block per capability '
                                            . 'listed. Absent when the agent is current.'),
                                        'properties' => [
                                            'revision' => ['type' => 'string'],
                                            'capabilities' => [
                                                'type' => 'array',
                                                'items' => ['type' => 'string']
                                            ]
                                        ],
                                        'additionalProperties' => true
                                    ],
                                    'want_inventory' => [
                                        'type' => 'boolean',
                                        'description' => _('The server holds no hardware '
                                            . 'inventory hash for this host and wants the '
                                            . 'block on the next poll.')
                                    ],
                                    'want_software' => [
                                        'type' => 'boolean',
                                        'description' => _('The server holds no installed-'
                                            . 'software hash for this host and wants the '
                                            . 'list on the next poll.')
                                    ],
                                    'collect_facts' => [
                                        'type' => 'boolean',
                                        'description' => _('Whether this install collects '
                                            . 'facts at all (FOG_AGENT_INVENTORY_ENABLED). '
                                            . 'Always present: an agent cannot tell an '
                                            . 'absent boolean from a false one, and absent '
                                            . 'has to mean a server that predates the '
                                            . 'field rather than one that turned collection '
                                            . 'off. False stops the agent gathering.')
                                    ]
                                ]
                            ]]]
                        ],
                        '401' => ['description' => _('No verified client certificate, or one bound to no live host.')],
                        '413' => ['description' => _('The reported software list is larger than the server accepts.')]
                    ],
                    [],
                    [
                        'description' => _('May be sent with Content-Encoding: gzip; a '
                            . 'host\'s software list is a few hundred KB of JSON and about '
                            . 'a tenth of that compressed.'),
                        'content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'properties' => [
                                'agent_version' => ['type' => 'string'],
                                'applied_revision' => ['type' => 'string'],
                                'want_state' => ['type' => 'boolean'],
                                'inventory' => [
                                    'type' => 'object',
                                    'description' => _('Hardware facts, sent only when the '
                                        . 'agent\'s own content hash for them moved or the '
                                        . 'server asked. Absent means nothing new, never '
                                        . 'nothing there.'),
                                    'additionalProperties' => ['type' => 'string']
                                ],
                                'software' => [
                                    'type' => 'array',
                                    'description' => _('The host\'s complete installed-'
                                        . 'program list, sent on the same terms as '
                                        . 'inventory. Complete by contract: anything '
                                        . 'installed and absent from it is marked removed.'),
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'name' => ['type' => 'string'],
                                            'version' => ['type' => 'string'],
                                            'publisher' => ['type' => 'string'],
                                            'source' => ['type' => 'string'],
                                            'arch' => ['type' => 'string'],
                                            'install_date' => ['type' => 'string']
                                        ]
                                    ]
                                ]
                            ]
                        ]]]
                    ]
                )
            ],
            '/agent/v1/result' => [
                'post' => self::_op(
                    '',
                    'agentresult',
                    _('FOG Agent capability result'),
                    _('What the agent did with one capability at one '
                        . 'revision, recorded on the host as agent.result; '
                        . 'or, with item, what happened to one thing under '
                        . 'the capability (a snapin task, a software entry), '
                        . 'answered with the outcome the agent acts on. One '
                        . 'route for every kind of report. Same gate as poll.'),
                    [
                        '200' => [
                            'description' => _('Recorded; outcome present for an item report.'),
                            'content' => ['application/json' => ['schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'status' => ['type' => 'string'],
                                    'outcome' => ['type' => 'string', 'enum' => ['success', 'reboot', 'retry', 'failed']]
                                ]
                            ]]]
                        ],
                        '400' => ['description' => _('Unknown capability or status, or an item for a capability with no item reports.')],
                        '401' => ['description' => _('No verified client certificate, or one bound to no live host.')],
                        '404' => ['description' => _('The item is not a live row of this host.')]
                    ],
                    [],
                    [
                        'content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'required' => ['revision', 'capability', 'status'],
                            'properties' => [
                                'revision' => ['type' => 'string'],
                                'capability' => ['type' => 'string'],
                                'status' => ['type' => 'string', 'enum' => ['applied', 'unchanged', 'pending_reboot', 'failed']],
                                'detail' => ['type' => 'string'],
                                'item' => [
                                    'type' => 'object',
                                    'required' => ['id', 'status'],
                                    'properties' => [
                                        'id' => ['type' => 'integer'],
                                        'status' => ['type' => 'string'],
                                        'exit_code' => ['type' => 'integer'],
                                        'installed_version' => ['type' => 'string'],
                                        'details' => ['type' => 'string']
                                    ]
                                ]
                            ]
                        ]]]
                    ]
                )
            ],
            '/agent/v1/payload/{capability}/{id}' => [
                'get' => self::_op(
                    '',
                    'agentpayload',
                    _('FOG Agent payload'),
                    _('The bytes behind one thing under a capability. For '
                        . 'snapin, the file for one task of the host\'s own '
                        . 'job; fetching it marks the task in progress. One '
                        . 'route for every kind of payload. Same gate as poll.'),
                    [
                        '200' => [
                            'description' => _('The payload bytes.'),
                            'content' => ['application/octet-stream' => ['schema' => ['type' => 'string', 'format' => 'binary']]]
                        ],
                        '401' => ['description' => _('No verified client certificate, or one bound to no live host.')],
                        '404' => ['description' => _('No payloads for the capability, or not a live row of this host.')],
                        '503' => ['description' => _('No storage node can serve the file.')]
                    ],
                    [
                        [
                            'name' => 'capability',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'string', 'enum' => ['snapin']]
                        ],
                        self::_idParameter()
                    ]
                )
            ],
            '/agent/v1/renew' => [
                'post' => self::_op(
                    '',
                    'agentrenew',
                    _('FOG Agent certificate renewal'),
                    _('Over the certificate being renewed: the same gate as '
                        . 'poll binds the caller to its host, and the body '
                        . 'carries a request for the same key. The answer is '
                        . 'the enroll "issued" shape. A request for any other '
                        . 'key is refused; a key change goes through enroll '
                        . 'and an admin.'),
                    [
                        '200' => [
                            'description' => _('The renewed certificate, leaf then chain.'),
                            'content' => ['application/json' => ['schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'status' => ['type' => 'string', 'enum' => ['issued']],
                                    'host_id' => ['type' => 'integer'],
                                    'certificate_pem' => ['type' => 'string'],
                                    'not_after' => ['type' => 'string']
                                ]
                            ]]]
                        ],
                        '400' => ['description' => _('Not a certificate request, or one for a key other than the one this certificate proved.')],
                        '401' => ['description' => _('No verified client certificate, or one bound to no live host.')],
                        '503' => ['description' => _('The signing helper is not available on this server.')]
                    ],
                    [],
                    [
                        'content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'required' => ['csr_pem'],
                            'properties' => [
                                'csr_pem' => ['type' => 'string']
                            ]
                        ]]]
                    ]
                )
            ],
            '/agent/enrollments' => [
                'get' => self::_op(
                    '',
                    'agentenrollments',
                    _('Pending agent enrollments'),
                    _('Every enrollment still waiting for a decision, '
                        . 'without the CSR. What the Pending Agents page reads.'),
                    $json(
                        [
                            'type' => 'object',
                            'properties' => [
                                'data' => ['type' => 'array', 'items' => ['type' => 'object']],
                                'msg' => ['type' => 'string']
                            ]
                        ],
                        _('Pending enrollment rows.')
                    )
                )
            ],
            '/agent/tokens' => [
                'get' => self::_op(
                    '',
                    'agenttokens',
                    _('Agent enrollment tokens'),
                    _('Every enrollment token with its remaining uses and '
                        . 'expiry, never the token itself. What the Agent '
                        . 'Tokens page reads.'),
                    $json(
                        [
                            'type' => 'object',
                            'properties' => [
                                'data' => ['type' => 'array', 'items' => ['type' => 'object']],
                                'msg' => ['type' => 'string']
                            ]
                        ],
                        _('Token rows.')
                    )
                )
            ],
            '/agent/token' => [
                'post' => self::_op(
                    '',
                    'agenttokenmint',
                    _('Mint an agent enrollment token'),
                    _('Returns the token exactly once; only its hash is '
                        . 'stored. An expiry is required. uses is how many '
                        . 'enrollments it approves, or -1 for unlimited '
                        . 'until it expires. Audited as agent.token.'),
                    [
                        '200' => [
                            'description' => _('The token.'),
                            'content' => ['application/json' => ['schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'id' => ['type' => 'integer'],
                                    'name' => ['type' => 'string'],
                                    'token' => ['type' => 'string'],
                                    'expires' => ['type' => 'string'],
                                    'msg' => ['type' => 'string']
                                ]
                            ]]]
                        ],
                        '400' => ['description' => _('A missing name, a bad use count, or an expiry that is not in the future.')]
                    ],
                    [],
                    [
                        'content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'required' => ['name', 'expires'],
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'uses' => ['type' => 'integer', 'default' => 1],
                                'expires' => ['type' => 'string', 'description' => _('Y-m-d H:i:s, server time.')]
                            ]
                        ]]]
                    ]
                )
            ],
            '/agent/token/{id}' => [
                'delete' => self::_op(
                    '',
                    'agenttokenrevoke',
                    _('Revoke an agent enrollment token'),
                    _('The row goes, so the token can never match again. '
                        . 'Audited as agent.token.'),
                    [
                        '200' => ['description' => _('Revoked.')],
                        '404' => ['description' => _('No such token.')]
                    ],
                    [self::_idParameter()]
                )
            ],
            '/agent/enrollment/{id}/{action}' => [
                'post' => self::_op(
                    '',
                    'agentenrollmentdecide',
                    _('Approve or deny an agent enrollment'),
                    _('approve signs the CSR, binds the certificate to the '
                        . 'host and takes the host out of pending; deny '
                        . 'records the refusal. Either way the agent learns '
                        . 'the outcome on its next poll. Audited as '
                        . 'agent.enroll.'),
                    [
                        '200' => ['description' => _('Decided.')],
                        '404' => ['description' => _('No such enrollment, or no such action.')],
                        '503' => ['description' => _('The signer is unavailable; nothing changed.')]
                    ] + self::_conflictResponse(
                        _('The enrollment is no longer pending.')
                    ),
                    [
                        self::_idParameter(),
                        [
                            'name' => 'action',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'string', 'enum' => ['approve', 'deny']]
                        ]
                    ]
                )
            ],
            '/system/openapi' => [
                'get' => self::_op(
                    '',
                    'openapi',
                    _('This document'),
                    _('Unauthenticated, and generated per request from this '
                        . 'server\'s live routing and model metadata. Also '
                        . 'served at /swagger.json, which is where most '
                        . 'tooling looks first.'),
                    $json(['type' => 'object'], _('An OpenAPI document.'))
                )
            ],
            '/system/userprefs' => [
                'get' => self::_op(
                    '',
                    'userprefs',
                    _('Your stored preferences'),
                    _('Every preference belonging to the CALLING user, as a '
                        . 'key/value map. There is no user in the path and no '
                        . 'way to name one: the id comes from the session, so '
                        . 'this cannot read anybody else\'s. Values are '
                        . 'opaque strings the server does not interpret.'),
                    $json(
                        [
                            'type' => 'object',
                            'properties' => [
                                'prefs' => [
                                    'type' => 'object',
                                    'additionalProperties' => [
                                        'type' => 'string'
                                    ]
                                ],
                                'msg' => ['type' => 'string']
                            ]
                        ],
                        _('The calling user\'s preferences.')
                    )
                )
            ],
            '/system/userpref/{key}' => [
                'get' => self::_op(
                    '',
                    'userpref',
                    _('Read one of your preferences'),
                    _('Answers an empty value when the key has never been '
                        . 'set, rather than 404 -- "no opinion" is a normal '
                        . 'answer here, not a missing resource.'),
                    $json(
                        [
                            'type' => 'object',
                            'properties' => [
                                'key' => ['type' => 'string'],
                                'value' => ['type' => 'string'],
                                'msg' => ['type' => 'string']
                            ]
                        ],
                        _('One preference.')
                    ),
                    self::_prefKeyParam(),
                    [],
                    'GetPref'
                ),
                'post' => self::_op(
                    '',
                    'userpref',
                    _('Store one of your preferences'),
                    _('The body is {"value": "..."}; a form field of the same '
                        . 'name is accepted too. An EMPTY value deletes the '
                        . 'preference rather than storing emptiness, so a '
                        . 'reset leaves no row saying "no opinion". Values '
                        . 'are capped at 64KB and refused rather than '
                        . 'truncated -- a truncated saved state cannot be '
                        . 'told from a corrupt one when it is read back.'),
                    $json(
                        [
                            'type' => 'object',
                            'properties' => [
                                'key' => ['type' => 'string'],
                                'msg' => ['type' => 'string']
                            ]
                        ],
                        _('The preference was stored.')
                    ),
                    self::_prefKeyParam(),
                    [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'value' => ['type' => 'string']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'SetPref'
                ),
                'delete' => self::_op(
                    '',
                    'userpref',
                    _('Clear one of your preferences'),
                    _('Equivalent to storing an empty value.'),
                    $json(
                        [
                            'type' => 'object',
                            'properties' => [
                                'key' => ['type' => 'string'],
                                'msg' => ['type' => 'string']
                            ]
                        ],
                        _('The preference was cleared.')
                    ),
                    self::_prefKeyParam(),
                    [],
                    'ClearPref'
                )
            ],
            '/system/savedfilters' => [
                'get' => self::_op(
                    '',
                    'savedfilters',
                    _('Saved filters for one grid'),
                    _('Every filter the CALLING user may see on that grid: '
                        . 'their own, every global one, and any shared with '
                        . 'them by name, through a user group, or through a '
                        . 'role. There is no user in the path and no way to '
                        . 'name one. mayShareGlobally reports whether the '
                        . 'caller holds savedfilter.create, so a client can '
                        . 'omit the "everyone" option rather than offer it '
                        . 'and fail on save.'),
                    $json(
                        [
                            'type' => 'object',
                            'properties' => [
                                'table' => ['type' => 'string'],
                                'filters' => [
                                    'type' => 'array',
                                    'items' => self::_savedFilterSchema()
                                ],
                                'mayShareGlobally' => ['type' => 'boolean'],
                                'msg' => ['type' => 'string']
                            ]
                        ],
                        _('The filters visible to the caller.')
                    ),
                    [
                        [
                            'name' => 'table',
                            'in' => 'query',
                            'required' => true,
                            'description' => _('The grid key.'),
                            'schema' => [
                                'type' => 'string',
                                'maxLength' => 128
                            ]
                        ]
                    ],
                    [],
                    'ListSavedFilters'
                ),
                'post' => self::_op(
                    '',
                    'savedfilters',
                    _('Save a filter'),
                    _('Saving over a name you already used replaces that '
                        . 'filter rather than adding a second one, which is '
                        . 'what a Save button is expected to do. A private '
                        . 'save never touches a global of the same name and '
                        . 'the other way round. global:true requires '
                        . 'savedfilter.create and answers 403 without it; '
                        . 'everything else needs only a session. Values are '
                        . 'capped at 64KB and refused rather than truncated.'),
                    $json(
                        [
                            'type' => 'object',
                            'properties' => [
                                'filters' => [
                                    'type' => 'array',
                                    'items' => self::_savedFilterSchema()
                                ],
                                'msg' => ['type' => 'string']
                            ]
                        ],
                        _('The filter was saved; the full list is returned.')
                    ),
                    [],
                    [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['table', 'name', 'value'],
                                    'properties' => [
                                        'table' => ['type' => 'string'],
                                        'name' => ['type' => 'string'],
                                        'value' => ['type' => 'string'],
                                        'global' => ['type' => 'boolean']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'SaveFilter'
                )
            ],
            '/system/savedfilter/{id}' => [
                'get' => self::_op(
                    '',
                    'savedfilter',
                    _('Read one saved filter'),
                    _('shares is null unless you own the filter: being '
                        . 'shared WITH one does not entitle you to see who '
                        . 'else it went to. A filter that does not exist and '
                        . 'one belonging to somebody else both answer 404, '
                        . 'deliberately, so an id cannot be used to probe for '
                        . 'other people\'s filters.'),
                    $json(
                        [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'name' => ['type' => 'string'],
                                'value' => ['type' => 'string'],
                                'global' => ['type' => 'boolean'],
                                'shares' => array_merge(
                                    self::_shareTargetsSchema(),
                                    ['nullable' => true]
                                ),
                                'msg' => ['type' => 'string']
                            ]
                        ],
                        _('One saved filter.')
                    ),
                    self::_filterIdParam(),
                    [],
                    'GetSavedFilter'
                ),
                'put' => self::_op(
                    '',
                    'savedfilter',
                    _('Rename a filter, or change who it is shared with'),
                    _('Either half may be sent, or both. ABSENT IS NOT '
                        . 'EMPTY: a body with no "shares" key leaves the '
                        . 'share list alone, while one carrying empty lists '
                        . 'means "shared with nobody" and clears it. The '
                        . 'whole list is replaced rather than added to. '
                        . 'Sharing needs no permission -- it can only add one '
                        . 'named entry to the picker of somebody you could '
                        . 'already name -- but editing a GLOBAL filter '
                        . 'requires savedfilter.edit.'),
                    $json(
                        [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'msg' => ['type' => 'string']
                            ]
                        ],
                        _('The filter was updated.')
                    ),
                    self::_filterIdParam(),
                    [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => ['type' => 'string'],
                                        'shares' => self::_shareTargetsSchema()
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'UpdateSavedFilter'
                ),
                'delete' => self::_op(
                    '',
                    'savedfilter',
                    _('Delete a saved filter'),
                    _('Yours, always. A global one requires '
                        . 'savedfilter.delete. Every share of it goes with '
                        . 'it, by foreign key.'),
                    $json(
                        [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'msg' => ['type' => 'string']
                            ]
                        ],
                        _('The filter was deleted.')
                    ),
                    self::_filterIdParam(),
                    [],
                    'DeleteSavedFilter'
                )
            ],
            '/system/savedfiltertargets' => [
                'get' => self::_op(
                    '',
                    'savedfiltertargets',
                    _('Who a filter can be shared with'),
                    _('Users, user groups and roles as id/name pairs. Each '
                        . 'section is gated on that entity\'s own view '
                        . 'permission -- user.view, usergroup.view, '
                        . 'role.view -- and comes back EMPTY rather than 403 '
                        . 'when the caller lacks it, so this route discloses '
                        . 'nothing they could not already list. A caller '
                        . 'holding none of the three can still save private '
                        . 'filters; there is simply nobody they may name.'),
                    $json(
                        [
                            'type' => 'object',
                            'properties' => [
                                'users' => self::_namedIdList(),
                                'groups' => self::_namedIdList(),
                                'roles' => self::_namedIdList(),
                                'msg' => ['type' => 'string']
                            ]
                        ],
                        _('The share targets the caller may see.')
                    ),
                    [],
                    [],
                    'ListShareTargets'
                )
            ],
            '/system/export' => [
                'get' => self::_op(
                    '',
                    'export',
                    _('Export the database'),
                    _('Streams a full SQL dump as an attachment.'),
                    [
                        '200' => [
                            'description' => _('SQL dump.'),
                            'content' => [
                                // No 'format' => 'binary'. A dump is SQL
                                // text, so a plain string is the honest
                                // declaration -- and binary is the half
                                // AutoRest cannot model: it writes
                                // File.WriteAllBytes(path, result) with
                                // result typed as the error schema.
                                // Measured across five variants: the
                                // content type is irrelevant, and
                                // application/sql, application/octet-stream
                                // and text/plain all compile once the
                                // binary format is dropped.
                                //
                                // text/plain because that is what the server
                                // actually sends (Schema::exportdb()), so
                                // anything written against the live route has
                                // been built around it. application/sql is the
                                // more correct description -- it is registered,
                                // RFC 6922 -- but changing the wire contract to
                                // match the document breaks working consumers,
                                // and correcting the document breaks none
                                // (GH-1410 item 4).
                                'text/plain' => [
                                    'schema' => [
                                        'type' => 'string'
                                    ]
                                ]
                            ]
                        ]
                    ]
                )
            ],
            '/whoami' => [
                'get' => self::_op(
                    '',
                    'whoami',
                    _('Server identity'),
                    '',
                    // Named rather than left as a bare object: the response is
                    // exactly Route::WHOAMI_KEYS, and GH-1120 renamed all five
                    // of those keys. A schema that documented nothing could not
                    // have told a consumer that the break had happened.
                    $json(
                        [
                            'type' => 'object',
                            'properties' => [
                                'NET_fog_server_ip' => [
                                    'type' => 'string',
                                    'description' => _('This server\'s own IP '
                                        . 'address. Space-separated when the '
                                        . 'interface carries more than one.')
                                ],
                                'NET_hostname' => [
                                    'type' => 'string',
                                    'description' => _('This server\'s '
                                        . 'hostname, as its certificate names '
                                        . 'it.')
                                ],
                                'FOG_os_id' => [
                                    'type' => 'string',
                                    'description' => _('Numeric OS family id '
                                        . 'the installer recorded. A second '
                                        . 'encoding of FOG_os_name whose '
                                        . 'meaning has changed between '
                                        . 'releases; prefer FOG_os_name.')
                                ],
                                'FOG_os_name' => [
                                    'type' => 'string',
                                    'description' => _('OS family name the '
                                        . 'installer recorded. The stable '
                                        . 'identifier of the two.')
                                ],
                                'FOG_install_type' => [
                                    'type' => 'string',
                                    'description' => _('N for a full server, '
                                        . 'S for a storage node.')
                                ]
                            ]
                        ],
                        _('Server facts. Every field is a string, and is empty '
                            . 'rather than absent when the installer has not '
                            . 'published it yet.')
                    )
                )
            ],
            '/pendingmacs' => [
                'get' => self::_op(
                    '',
                    'pendingmacs',
                    _('Pending MAC addresses'),
                    '',
                    // Route::pendingmacs() delegates straight to
                    // Route::listem(), so this route has always answered
                    // with a full page. The bare array documented here was
                    // simply wrong about its own server.
                    self::_listResponse(
                        '#/components/schemas/'
                        . self::schemaName('macaddressassociation')
                    )
                )
            ],
            '/availablekernels' => [
                'get' => self::_op(
                    '',
                    'kernelUpdate',
                    _('Kernels present on disk'),
                    '',
                    $json(
                        ['$ref' => '#/components/schemas/ValueList'],
                        _('Kernels.')
                    )
                )
            ],
            '/availableinitrds' => [
                'get' => self::_op(
                    '',
                    'initrdUpdate',
                    _('Init images present on disk'),
                    '',
                    $json(
                        ['$ref' => '#/components/schemas/ValueList'],
                        _('Init images.')
                    )
                )
            ],
            '/bandwidth/{dev}' => [
                'parameters' => [
                    [
                        'name' => 'dev',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                        'description' => _('Network interface name.')
                    ]
                ],
                'get' => self::_op(
                    '',
                    'bandwidth',
                    _('Interface throughput'),
                    _('Unauthenticated.'),
                    $json(['type' => 'object'], _('Throughput sample.'))
                )
            ],
            '/logfiles/{id}' => [
                'parameters' => [self::_idParameter()],
                'get' => self::_op(
                    '',
                    'logfiles',
                    _('Read a server log'),
                    '',
                    $json(['type' => 'object'], _('Log contents.'))
                )
            ],
            '/unisearch' => [
                'get' => self::_op(
                    '',
                    'unisearch',
                    _('Search every class at once'),
                    _('The term goes in q. limit caps results PER CLASS, not '
                        . 'overall, and this route is not paged -- there is no '
                        . 'nextUrl to follow. Within a class, names that start '
                        . 'with the term sort first. Both fields may also be '
                        . 'sent as POST body fields. Also reachable as /search.'),
                    $json(['type' => 'object'], _('Grouped matches.')),
                    [
                        [
                            'name' => 'q',
                            'in' => 'query',
                            'required' => true,
                            'schema' => ['type' => 'string'],
                            'description' => _('Text to match against the name '
                                . '(and the id, when the text is a whole number). '
                                . 'Matched literally: % and _ are not wildcards.')
                        ],
                        [
                            'name' => 'limit',
                            'in' => 'query',
                            'required' => false,
                            'schema' => ['type' => 'integer', 'minimum' => 0],
                            'description' => _('Maximum rows per class; 0 or '
                                . 'absent means no cap.')
                        ]
                    ]
                )
            ],
            '/unisearch/{item}' => [
                'parameters' => [
                    [
                        'name' => 'item',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                        'description' => _('The term. Because it is a path '
                            . 'segment, a term containing / ? # or % cannot '
                            . 'travel this way; use /unisearch?q= instead.')
                    ]
                ],
                'get' => self::_op(
                    '',
                    'unisearch',
                    _('Search every class at once (path form)'),
                    _('The older spelling of /unisearch?q=. An optional trailing '
                        . 'integer caps results PER CLASS, not overall, and this '
                        . 'route is not paged -- there is no nextUrl to follow. '
                        . 'Also reachable as /search.'),
                    $json(['type' => 'object'], _('Grouped matches.'))
                )
            ],
            '/settings/cache' => [
                'get' => self::_op(
                    '',
                    'settingsCacheView',
                    _('Settings cache state'),
                    '',
                    $json(['type' => 'object'], _('Cache state.'))
                )
            ],
            '/settings/cache/flush' => [
                'post' => self::_op(
                    '',
                    'settingsCacheFlush',
                    _('Flush the settings cache'),
                    '',
                    self::_messageResponse()
                )
            ],
            '/settings/cache/refresh' => [
                'post' => self::_op(
                    '',
                    'settingsCacheRefresh',
                    _('Refresh the settings cache'),
                    '',
                    self::_messageResponse()
                )
            ],
            '/snapin/createwithfile' => ['post' => self::_snapinCreateWithFileOp()],
            '/storagegroup/{id}/uploadsnapinfiles' => [
                'parameters' => [self::_idParameter()],
                'post' => self::_uploadSnapinFilesOp()
            ],
            '/plugin/{id}/install' => [
                'parameters' => [self::_idParameter()],
                'post' => self::_pluginInstallOp()
            ]
        ];
    }

    /**
     * POST /plugin/{id}/install.
     *
     * Written out here rather than falling out of the generic shapes for
     * the same reason the upload routes are: it is an action, not CRUD on
     * a row. The generic edit route cannot express it, and deliberately
     * refuses to pretend it can -- plugins.installed and plugins.schema
     * are server-owned, because both record what this operation DID.
     *
     * @return array
     */
    private static function _pluginInstallOp()
    {
        $op = self::_op(
            '',
            'pluginInstall',
            _('Install a plugin'),
            _('Activates the plugin, applies its schema migrations, and '
                . 'records the result -- the same three steps, in the same '
                . 'order, as the Install action in the web UI. Idempotent: '
                . 'migration steps are append-only and are resumed from the '
                . 'count already applied, so calling this on an installed '
                . 'plugin applies only steps it has not seen, which is what '
                . 'the UI calls Upgrade. Setting `installed` through the '
                . 'generic edit route instead is refused: that column '
                . 'records that this operation succeeded, and asserting it '
                . 'without running the migrations leaves the plugin\'s '
                . 'routes in this document while its tables do not exist.'),
            [
                '204' => ['description' => _('The plugin is installed and '
                    . 'its schema is up to date.')],
                '400' => [
                    'description' => _('The server refuses to activate this '
                        . 'plugin, or the plugin declares no schema() '
                        . 'migrations and is already installed, so '
                        . 're-running its installer would drop and recreate '
                        . 'its tables. The message says which.'),
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/Error']
                        ]
                    ]
                ],
                '404' => [
                    'description' => _('No plugin with that id.'),
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/Error']
                        ]
                    ]
                ],
                '500' => [
                    'description' => _('A migration step failed. The plugin '
                        . 'is left activated and not marked installed.'),
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/Error']
                        ]
                    ]
                ]
            ]
        );
        return $op;
    }

    /**
     * POST /snapin/createwithfile.
     *
     * The two upload routes are the only operations in the document whose
     * request is multipart rather than JSON, which is why they are written
     * out here rather than falling out of the generic create shape -- the
     * generic shape describes an application/json body built from the
     * model's columns, and these take form fields and a binary.
     *
     * Field names, requiredness and status codes come from the endpoint's
     * own published documentation on GH-823, not from reading the handler,
     * so this describes the contract that was announced to callers.
     *
     * @return array
     */
    private static function _snapinCreateWithFileOp()
    {
        // $ref only if the snapin schema is actually in the document. The
        // class list is mutable at runtime through API_VALID_CLASSES, so a
        // plugin can remove snapin, and a fixed path is emitted either way
        // -- a dangling $ref does not degrade, it stops the whole document
        // resolving in any client that reads it.
        $snapinSchema = in_array('snapin', self::_documentedClasses(), true)
            ? ['$ref' => '#/components/schemas/' . self::schemaName('snapin')]
            : ['type' => 'object'];
        $op = self::_op(
            '',
            'snapinCreateWithFile',
            _('Create a snapin and upload its file'),
            _('multipart/form-data, not JSON. The file goes to the master '
                . 'storage node of the group given, and FOGSnapinReplicator '
                . 'propagates it to the rest of the group on its normal '
                . 'cycle. A file already present under the same basename is '
                . 'overwritten, matching the UI. Names matching /ssl/i are '
                . 'refused -- that path is reserved.'),
            [
                '201' => [
                    'description' => _('Created. The body is the new snapin, '
                        . 'the same shape as GET /snapin/{id}.'),
                    'content' => [
                        'application/json' => ['schema' => $snapinSchema]
                    ]
                ],
                '500' => [
                    'description' => _('Transport to the master storage node '
                        . 'failed, or the row would not save after the file '
                        . 'had landed.'),
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/Error']
                        ]
                    ]
                ]
            ],
            [],
            [
                'required' => true,
                'content' => [
                    'multipart/form-data' => [
                        'schema' => [
                            'type' => 'object',
                            'required' => ['snapinfile', 'snapin', 'storagegroup'],
                            'properties' => [
                                'snapinfile' => [
                                    'type' => 'string',
                                    'format' => 'binary',
                                    'description' => _('The file itself.')
                                ],
                                'snapin' => [
                                    'type' => 'string',
                                    'description' => _('Name. Must be unique.')
                                ],
                                'storagegroup' => [
                                    'type' => 'integer',
                                    'description' => _('Id of the storage '
                                        . 'group whose master receives the '
                                        . 'file.')
                                ],
                                'description' => ['type' => 'string'],
                                'packtype' => [
                                    'type' => 'integer',
                                    'default' => 0,
                                    'description' => _('0 is a normal snapin, '
                                        . '1 a snapin pack the client extracts '
                                        . 'before running.')
                                ],
                                'rw' => [
                                    'type' => 'string',
                                    'description' => _('Run With interpreter.')
                                ],
                                'rwa' => [
                                    'type' => 'string',
                                    'description' => _('Run With arguments.')
                                ],
                                'args' => ['type' => 'string'],
                                'action' => [
                                    'type' => 'string',
                                    'description' => _('reboot, shutdown, or '
                                        . 'empty.')
                                ],
                                'timeout' => [
                                    'type' => 'integer',
                                    'default' => 0,
                                    'description' => _('Seconds before the '
                                        . 'client gives up. 0 is no timeout.')
                                ],
                                'isEnabled' => [
                                    'type' => 'string',
                                    'description' => _('Present means enabled.')
                                ],
                                'toReplicate' => [
                                    'type' => 'string',
                                    'description' => _('Present means include '
                                        . 'in replication.')
                                ],
                                'isHidden' => [
                                    'type' => 'string',
                                    'description' => _('Present means hidden '
                                        . 'from the UI list.')
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        );
        return $op;
    }

    /**
     * POST /storagegroup/{id}/uploadsnapinfiles.
     *
     * @return array
     */
    private static function _uploadSnapinFilesOp()
    {
        $op = self::_op(
            '',
            'uploadSnapinFiles',
            _('Upload snapin files to a storage group'),
            _('multipart/form-data, not JSON. Transport only -- no snapin row '
                . 'is created or touched, so this is how a caller pre-stages '
                . 'files to attach later. The field name must be snapinfiles[] '
                . 'even for a single file. Every file is validated before any '
                . 'transfer begins, but a failure part way through a batch '
                . 'leaves the earlier files in place.'),
            [
                '204' => ['description' => _('Every file was uploaded.')],
                '500' => [
                    'description' => _('Transport failed, or the group has no '
                        . 'reachable master node.'),
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/Error']
                        ]
                    ]
                ]
            ],
            [],
            [
                'required' => true,
                'content' => [
                    'multipart/form-data' => [
                        'schema' => [
                            'type' => 'object',
                            'required' => ['snapinfiles'],
                            'properties' => [
                                'snapinfiles' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'string',
                                        'format' => 'binary'
                                    ],
                                    'description' => _('One or more files. The '
                                        . 'field name carries the [] suffix on '
                                        . 'the wire: snapinfiles[].')
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        );
        return $op;
    }

    /**
     * Query parameters shared across list-shaped operations.
     *
     * @return array
     */
    private static function _commonParameters()
    {
        return [
            'start' => [
                'name' => 'start',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'integer', 'minimum' => 0],
                'description' => _(
                    'Row offset. Only honored when length is also sent -- the '
                    . 'server reads start from inside the length branch.'
                )
            ],
            'length' => [
                'name' => 'length',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'integer'],
                'description' => _(
                    'Page size. Send it, together with start, on every list call: '
                    . 'a request with no start is capped at MAX_ROWS and returns a '
                    . 'partial result with truncated set.'
                )
            ],
            'expand' => [
                'name' => 'expand',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'string'],
                'description' => _(
                    'Comma separated relations to inline. Forces the page size to '
                    . 'EXPAND_MAX_ITEMS, so an expanded page can come back smaller '
                    . 'than the length asked for.'
                )
            ],
            'filter' => [
                'name' => 'filter',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'string'],
                'description' => _(
                    'Server-side column filter, written as a URL encoded query '
                    . 'string: field=value, joined with & for more than one, '
                    . 'ANDed together. A comma separated value matches any of '
                    . 'its parts. Only fields the class declares are accepted -- '
                    . 'anything else answers 400 and names the offending key -- '
                    . 'and credential fields are refused outright. Also accepted '
                    . 'as a trailing path segment (/{class}/list/field=value), '
                    . 'which wins when both are sent.'
                )
            ]
        ];
    }
}
