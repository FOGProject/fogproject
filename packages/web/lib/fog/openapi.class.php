<?php
/**
 * OpenAPI description of the FOG REST API, generated from FOG's own metadata.
 *
 * PHP version 5
 *
 * @category OpenAPI
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
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
     */
    const OAS_VERSION = '3.1.0';
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
            $manager = new ReflectionClass('FOGManagerController');
            if ($manager->hasConstant('MAX_ROWS')) {
                $maxRows = (int)$manager->getConstant('MAX_ROWS');
            }
        } catch (Exception $e) {
            $maxRows = null;
        } catch (Error $e) {
            $maxRows = null;
        }
        try {
            $router = new ReflectionClass('Route');
            if ($router->hasConstant('EXPAND_MAX_ITEMS')) {
                $expandMax = (int)$router->getConstant('EXPAND_MAX_ITEMS');
            }
        } catch (Exception $e) {
            $expandMax = null;
        } catch (Error $e) {
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
                'Authenticate with the `fog-api-token` (server-wide) and',
                '`fog-user-token` (per user) headers, or with HTTP basic auth.',
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
     * Both token headers are required together; basic auth is the
     * alternative. Two entries in the list means OR, two keys inside one
     * entry means AND.
     *
     * @return array
     */
    private static function _globalSecurity()
    {
        return [
            ['fogApiToken' => [], 'fogUserToken' => []],
            ['basicAuth' => []]
        ];
    }

    /**
     * @return array
     */
    private static function _securitySchemes()
    {
        return [
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
        } catch (Exception $e) {
            $result = null;
        } catch (Error $e) {
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
     * Turns a schema-expected column type into an OpenAPI schema.
     *
     * Input looks like 'varchar(250) NOT NULL', 'int(11) NOT NULL',
     * "enum('0','1') NOT NULL" or 'longtext DEFAULT NULL'.
     *
     * tinyint(1) maps to integer rather than boolean on purpose: FOG spells
     * its booleans enum('0','1') and uses tinyint for genuine small integers,
     * so the usual MySQL-to-bool shortcut would mistype real data.
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
            if ('integer' === $schema['type']) {
                $schema['default'] = (int)$default;
            } elseif ('number' === $schema['type']) {
                $schema['default'] = (float)$default;
            } else {
                $schema['default'] = $default;
            }
        }
        if ($nullable) {
            // 3.1 spelling: null joins the type rather than a nullable flag.
            $schema['type'] = [$schema['type'], 'null'];
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
            $properties[$property] = $schema;
        }
        $out = [
            'type' => 'object',
            'x-fog-table' => $table,
            'properties' => $properties
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
        }
        return $out;
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
     * - recordsFiltered is the true filtered total, but the site plugin
     *   rewrites it to the post-scoping count for site-restricted users, so
     *   it is a floor rather than a total for those callers.
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
                    'description' => _('Total rows in the table, unfiltered.')
                ],
                'recordsFiltered' => [
                    'type' => 'integer',
                    'description' => _(
                        'Rows matching the filter. Rewritten to the post-scoping '
                        . 'count for site-restricted users, so treat it as a floor '
                        . 'rather than a total.'
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
                'firstUrl' => ['type' => ['string', 'null']],
                'prevUrl' => ['type' => ['string', 'null']],
                'nextUrl' => [
                    'type' => ['string', 'null'],
                    'description' => _('Null when this is the last page.')
                ],
                'lastUrl' => ['type' => ['string', 'null']]
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

        $paths['/' . $class] = [
            'get' => self::_op(
                $class,
                'list',
                sprintf(_('List %s'), $class),
                _('Also reachable as /list and /all. A trailing filter segment '
                    . 'accepts field:value pairs.'),
                self::_listResponse($ref),
                self::_listParameters()
            ),
            'post' => self::_op(
                $class,
                'create',
                sprintf(_('Create a %s'), $class),
                _('Also reachable as /create and /new.'),
                self::_entityResponse($ref, _('The created object.')),
                [],
                self::_entityBody($ref)
            )
        ];

        $paths['/' . $class . '/{id}'] = [
            'parameters' => [self::_idParameter()],
            'get' => self::_op(
                $class,
                'indiv',
                sprintf(_('Get one %s by id'), $class),
                _('Fields withheld from list responses are returned here.'),
                self::_entityResponse($ref)
            ),
            'put' => self::_op(
                $class,
                'update',
                sprintf(_('Update a %s'), $class),
                _('Also reachable as /update and /edit. Send only the fields '
                    . 'being changed.'),
                self::_entityResponse($ref),
                [],
                self::_entityBody($ref)
            ),
            'delete' => self::_op(
                $class,
                'delete',
                sprintf(_('Delete a %s'), $class),
                _('Also reachable as /delete and /remove.'),
                self::_messageResponse()
            )
        ];

        $paths['/' . $class . '/search/{item}'] = [
            'parameters' => [
                [
                    'name' => 'item',
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'string'],
                    'description' => _('Text to match across the class fields.')
                ]
            ],
            'get' => self::_op(
                $class,
                'search',
                sprintf(_('Search %s'), $class),
                _('Returns the same envelope as a list.'),
                self::_listResponse($ref),
                self::_listParameters()
            )
        ];

        $paths['/' . $class . '/count'] = [
            'get' => self::_op(
                $class,
                'count',
                sprintf(_('Count %s'), $class),
                _('Accepts the same optional trailing filter segment as a list. '
                    . 'Reports the true filtered total and ignores paging.'),
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
                ]
            )
        ];

        $paths['/' . $class . '/names'] = [
            'get' => self::_op(
                $class,
                'names',
                sprintf(_('Id and name pairs for %s'), $class),
                _('Unpaged and uncapped -- the cheap way to enumerate a large '
                    . 'table. Accepts an optional trailing filter segment.'),
                self::_rawArrayResponse()
            )
        ];

        $paths['/' . $class . '/ids'] = [
            'get' => self::_op(
                $class,
                'ids',
                sprintf(_('Ids for %s'), $class),
                _('Unpaged and uncapped. Accepts an optional trailing filter '
                    . 'segment and an optional field name to return instead of '
                    . 'the id.'),
                self::_rawArrayResponse()
            )
        ];

        $paths['/' . $class . '/join'] = [
            'put' => self::_op(
                $class,
                'join',
                sprintf(_('Create or update a %s by natural key'), $class),
                _('Upserts against the association keys rather than an id.'),
                self::_entityResponse($ref),
                [],
                self::_entityBody($ref)
            )
        ];

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
                                        'taskTypeID' => ['type' => ['string', 'integer']],
                                        'taskName' => ['type' => 'string'],
                                        'shutdown' => ['type' => ['string', 'boolean']],
                                        'debug' => ['type' => ['string', 'boolean']],
                                        'deploySnapins' => ['type' => ['string', 'integer', 'boolean']],
                                        'passreset' => ['type' => 'string'],
                                        'sessionjoin' => ['type' => ['string', 'boolean']],
                                        'wol' => ['type' => ['string', 'boolean']]
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
                    '',
                    self::_messageResponse()
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
        array $body = []
    ) {
        $op = [
            'tags' => ['' === $class ? 'system' : $class],
            'operationId' => '' === $class
                ? $routeName
                : $routeName . ucfirst($class),
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
        return [
            ['$ref' => '#/components/parameters/start'],
            ['$ref' => '#/components/parameters/length'],
            ['$ref' => '#/components/parameters/expand']
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
                    _('Server version'),
                    _('Unauthenticated. Also reachable as /system/status.'),
                    $json(
                        [
                            'type' => 'object',
                            'properties' => [
                                'version' => ['type' => 'string'],
                                'msg' => ['type' => 'string']
                            ]
                        ],
                        _('Version information.')
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
                    $json(['type' => 'object'], _('Server facts.'))
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
            ]
        ];
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
            ]
        ];
    }
}
