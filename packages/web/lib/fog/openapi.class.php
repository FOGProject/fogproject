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

namespace FOG;

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
 * collide: AltoRouter constrains the id segment to [i:id], so 'search' never
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
            $router = new \ReflectionClass('Route');
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
     * advertises an operation the server cannot honour.
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
        return ucfirst((string)$class);
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
        $serverOwned = method_exists('Route', 'serverOwnedFields')
            ? array_map('strtolower', (array)Route::serverOwnedFields($class))
            : [];

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
        // correct standing behaviour for a FOG response, not a workaround.
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
            // generator builds its deserialiser from `properties`, so a
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
        $empty = ['list' => [], 'always' => []];
        if (!method_exists('Route', 'sensitiveFieldMap')) {
            return $empty;
        }
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
                self::_messageResponse()
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
                                    'type' => 'object',
                                    'properties' => [
                                        'taskTypeID' => self::_oneOfTypes(['string', 'integer']),
                                        'taskName' => ['type' => 'string'],
                                        'shutdown' => self::_oneOfTypes(['string', 'boolean']),
                                        'debug' => self::_oneOfTypes(['string', 'boolean']),
                                        'deploySnapins' => self::_oneOfTypes(['string', 'integer', 'boolean']),
                                        'passreset' => ['type' => 'string'],
                                        'sessionjoin' => self::_oneOfTypes(['string', 'boolean']),
                                        'wol' => self::_oneOfTypes(['string', 'boolean'])
                                    ]
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
                        . 'cancelled. Naming a resource whose task has already '
                        . 'finished -- Complete, Cancelled or Failed -- answers '
                        . '409 rather than reporting a success it did not '
                        . 'perform.'
                    ),
                    self::_messageResponse() + self::_conflictResponse()
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
            // afterwards, by each route that cared, because $class had to
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
            return ucfirst((string)$class);
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
        if (!method_exists('Authorization', 'resolveApiPermission')) {
            return null;
        }
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
                        'schema' => [
                            'allOf' => [
                                ['$ref' => '#/components/schemas/ListEnvelope'],
                                [
                                    'type' => 'object',
                                    'properties' => [
                                        'data' => [
                                            'type' => 'array',
                                            'items' => ['$ref' => $ref]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
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
                    'schema' => [
                        'allOf' => [
                            ['$ref' => $ref],
                            [
                                'type' => 'object',
                                'required' => ['ids'],
                                'properties' => [
                                    'ids' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'integer'],
                                        'description' => _('The objects to '
                                            . 'apply these values to. An '
                                            . 'empty or absent list matches '
                                            . 'nothing and edits nothing.')
                                    ]
                                ]
                            ]
                        ]
                    ]
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
                        'type' => 'object',
                        'required' => ['names'],
                        'properties' => [
                            'names' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                                'description' => _('Names to resolve. Each is '
                                    . 'created if no object already has it.')
                            ]
                        ]
                    ]
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
     * An array of ids.
     *
     * @return array
     */
    private static function _idsResponse()
    {
        return [
            '201' => [
                'description' => _('One id per name, in the order given.'),
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer']
                        ]
                    ]
                ]
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
     * The 409 the cancel route answers when the named resource is not in a
     * cancellable state.
     *
     * Carries the same {msg} object the 200 does, rather than the Error
     * schema: the reason is written for a person, and the UI reads it with
     * the same $.notifyFromAPI() call either way.
     *
     * @return array
     */
    private static function _conflictResponse()
    {
        return [
            '409' => [
                'description' => _('The resource is not in a cancellable state.'),
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
     * Routes that answer with a bare array rather than the list envelope.
     *
     * @return array
     */
    private static function _rawArrayResponse()
    {
        return [
            '200' => [
                'description' => _('An unpaged array.'),
                'content' => [
                    'application/json' => [
                        'schema' => ['type' => 'array', 'items' => ['type' => 'object']]
                    ]
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
                                'application/sql' => [
                                    'schema' => [
                                        'type' => 'string',
                                        'format' => 'binary'
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
                    $json(
                        ['type' => 'array', 'items' => ['type' => 'object']],
                        _('Pending MACs.')
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
                        ['type' => 'array', 'items' => ['type' => 'object']],
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
                        ['type' => 'array', 'items' => ['type' => 'object']],
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
            '/unisearch/{item}' => [
                'parameters' => [
                    [
                        'name' => 'item',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string']
                    ]
                ],
                'get' => self::_op(
                    '',
                    'unisearch',
                    _('Search every class at once'),
                    _('An optional trailing integer caps results PER CLASS, not '
                        . 'overall, and this route is not paged -- there is no '
                        . 'nextUrl to follow. Also reachable as /search.'),
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
                    'Row offset. Only honoured when length is also sent -- the '
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

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\OpenAPI', 'OpenAPI');
