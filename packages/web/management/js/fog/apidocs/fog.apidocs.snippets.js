/**
 * Request snippet generators for the API Documentation page.
 *
 * Swagger UI's snippet panel is driven by a config block naming generators and
 * a matching `fn.requestSnippetGenerator_<key>` for each. What is worth knowing
 * before editing this file is what the bundle does and does not already have:
 *
 *  - It ships exactly three generators -- curl_bash, curl_cmd and
 *    curl_powershell. There is no built-in Python, JavaScript, Ruby or PHP
 *    generator, and naming one in `requestSnippets.generators` without also
 *    registering a function does not fail loudly. getSnippetGenerators()
 *    filters out any entry whose `fn` is not a function, so the tab simply
 *    never appears and the page looks like the setting did nothing.
 *  - curl_powershell is `curl.exe`, not PowerShell. It is the same curl command
 *    line with PowerShell quoting and a backtick line continuation. Someone
 *    reading it for how to call FOG from PowerShell gets an answer that ignores
 *    every cmdlet they would actually use, which is why the PowerShell
 *    generator here emits Invoke-RestMethod and curl_powershell is left out of
 *    the config.
 *
 * The generators below are deliberately generic -- plain Invoke-RestMethod,
 * `requests`, `fetch` -- rather than FogApi/fog-specific. A FOG-flavoured one
 * (Connect-FogServer + Get-FogObject, say) is a matter of adding one entry to
 * GENERATORS and one line to the config in fog.apidocs.list.js.
 *
 * On highlighting: the bundle registers only bash, http, javascript, json,
 * powershell, xml and yaml with highlight.js. An unregistered `syntax` value
 * does not throw -- react-syntax-highlighter falls back to highlightAuto() --
 * so Python is declared honestly as 'python' and gets auto-detected colouring
 * rather than being mislabelled as something that happens to be registered.
 *
 * The request object handed to a generator is an Immutable Map with `url`,
 * `method`, `headers` and `body`. Nothing here reaches for Immutable itself,
 * because the bundle does not export it; the shapes are duck-typed instead,
 * which is also what makes tests/apidocs-request-snippets.test.sh able to
 * exercise these functions under plain node.
 */
(function (root, factory) {
    'use strict';
    if (typeof module === 'object' && module && module.exports) {
        module.exports = factory();
    } else {
        root.FogRequestSnippets = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    // Swagger UI suffixes duplicated form keys with this marker so that
    // repeated fields survive a Map; the wire name is everything before it.
    var ARRAY_MARKER = '_**[]';

    var JSON_CONTENT = /^application\/(?:[\w.+-]+\+)?json\b/i;
    var MULTIPART_CONTENT = /^multipart\/form-data\b/i;

    /**
     * Immutable Map, native Map or plain object to an array of [key, value].
     *
     * Immutable and native Map both call back as (value, key); a plain object
     * has no forEach at all, which is how the two are told apart.
     */
    function toPairs(map) {
        var pairs = [];
        if (!map) {
            return pairs;
        }
        if (typeof map.forEach === 'function' && typeof map.get === 'function') {
            map.forEach(function (value, key) {
                pairs.push([key, value]);
            });
            return pairs;
        }
        if (typeof map !== 'object') {
            return pairs;
        }
        Object.keys(map).forEach(function (key) {
            pairs.push([key, map[key]]);
        });
        return pairs;
    }

    function extractKey(key) {
        var name = String(key);
        return name.indexOf(ARRAY_MARKER) < 0
            ? name
            : name.split(ARRAY_MARKER)[0].trim();
    }

    function isMapLike(value) {
        return !!value
            && typeof value === 'object'
            && typeof value.get === 'function'
            && typeof value.forEach === 'function';
    }

    /**
     * A file the user attached in try-it. Swagger UI uses the browser's File in
     * a browser and its own stand-in elsewhere, so this tests for the shape
     * both share rather than for either constructor.
     */
    function isFileLike(value) {
        if (typeof File !== 'undefined' && value instanceof File) {
            return true;
        }
        return !!value
            && typeof value === 'object'
            && !isMapLike(value)
            && typeof value.name === 'string'
            && (typeof value.size === 'number' || typeof value.type === 'string');
    }

    function headerValue(headers, name) {
        var wanted = name.toLowerCase();
        var found = '';
        headers.forEach(function (pair) {
            if (String(pair[0]).toLowerCase() === wanted) {
                found = String(pair[1]);
            }
        });
        return found;
    }

    /**
     * Sort the request into the handful of shapes the generators care about:
     * no body, a JSON document, a form (multipart or urlencoded), a single
     * uploaded file, or an opaque string.
     */
    function describeBody(request, headers) {
        var body = request.get('body');
        var contentType = headerValue(headers, 'content-type');

        if (body === undefined || body === null || body === '') {
            return { kind: 'none' };
        }
        if (isFileLike(body)) {
            return { kind: 'file', file: body };
        }
        if (isMapLike(body)) {
            return {
                kind: 'form',
                multipart: MULTIPART_CONTENT.test(contentType),
                fields: toPairs(body).map(function (pair) {
                    return [extractKey(pair[0]), pair[1]];
                })
            };
        }
        if (typeof body === 'string') {
            try {
                return { kind: 'json', value: JSON.parse(body), raw: body };
            } catch (ignore) {
                return { kind: 'raw', raw: body };
            }
        }
        // Anything else is already a structure; treat it as the JSON it will
        // be serialised to.
        return { kind: 'json', value: body, raw: JSON.stringify(body, null, 2) };
    }

    function readRequest(request) {
        var headers = toPairs(request.get('headers')).map(function (pair) {
            return [String(pair[0]), String(pair[1])];
        });
        return {
            url: String(request.get('url') || ''),
            method: String(request.get('method') || 'GET').toUpperCase(),
            headers: headers,
            contentType: headerValue(headers, 'content-type'),
            body: describeBody(request, headers)
        };
    }

    /**
     * Escape into a double-quoted literal for the C-derived syntaxes (Python,
     * JavaScript, PHP all agree on these escapes).
     *
     * Non-ASCII is passed through as itself rather than as \uXXXX. JSON's own
     * escaping would emit surrogate pairs for astral characters, and Python
     * reads "😀" as two lone surrogates rather than as the character
     * it came from -- so a hostname with an emoji in it would round-trip
     * through the snippet as something else.
     */
    function quoteDouble(value) {
        var out = String(value).replace(/[\\"]/g, '\\$&');
        return '"' + out.replace(/[\x00-\x1f]/g, function (ch) {
            switch (ch) {
            case '\n':
                return '\\n';
            case '\r':
                return '\\r';
            case '\t':
                return '\\t';
            default:
                return '\\u' + ('000' + ch.charCodeAt(0).toString(16)).slice(-4);
            }
        }) + '"';
    }

    /* ---------------------------------------------------------------- *
     * PowerShell                                                        *
     * ---------------------------------------------------------------- */

    function quotePowershell(value) {
        return "'" + String(value).replace(/'/g, "''") + "'";
    }

    /**
     * A here-string when the text has newlines in it, a single-quoted string
     * when it does not.
     *
     * @' must be the last thing on its line and '@ the first thing on its own,
     * so a body containing a line that begins with '@ would terminate the
     * here-string early. That falls back to the quoted form, which is uglier
     * but always correct.
     */
    function powershellLiteral(text) {
        var value = String(text);
        if (value.indexOf('\n') < 0) {
            return quotePowershell(value);
        }
        if (/^'@/m.test(value)) {
            return quotePowershell(value);
        }
        return "@'\n" + value + "\n'@";
    }

    function powershellHashtable(pairs, pad) {
        if (!pairs.length) {
            return '@{}';
        }
        var lines = pairs.map(function (pair) {
            return pad + '    ' + quotePowershell(pair[0])
                + ' = ' + quotePowershell(pair[1]);
        });
        return '@{\n' + lines.join('\n') + '\n' + pad + '}';
    }

    function powershellSnippet(request) {
        var req = readRequest(request);
        var lines = [];
        var args = ['-Uri ' + quotePowershell(req.url)];
        var body = req.body;

        // Content-Type is pulled out of the header table on purpose. Windows
        // PowerShell 5.1 refuses to set restricted headers through -Headers
        // ("The 'Content-Type' header must be modified using the appropriate
        // property or method"), and -ContentType is the parameter that exists
        // for it in every version.
        var headers = req.headers.filter(function (pair) {
            return pair[0].toLowerCase() !== 'content-type';
        });

        if (headers.length) {
            lines.push('$headers = ' + powershellHashtable(headers, ''));
            lines.push('');
        }

        if (body.kind === 'json' || body.kind === 'raw') {
            lines.push('$body = ' + powershellLiteral(body.raw));
            lines.push('');
        } else if (body.kind === 'form') {
            // -Form is PowerShell 6.1+. Say so rather than handing 5.1 users a
            // command that fails on a parameter they cannot get.
            lines.push('# -Form requires PowerShell 6.1 or newer.');
            lines.push('$form = ' + powershellHashtable(
                body.fields.filter(function (pair) {
                    return !isFileLike(pair[1]);
                }).map(function (pair) {
                    return [pair[0], String(pair[1])];
                }),
                ''
            ));
            // File fields are added after the literal, not inside it: -Form
            // uploads a FileInfo as a file and a string as a text field, so the
            // value has to be the Get-Item result rather than the name.
            body.fields.forEach(function (pair) {
                if (isFileLike(pair[1])) {
                    lines.push('$form[' + quotePowershell(pair[0])
                        + '] = Get-Item ' + quotePowershell(pair[1].name));
                }
            });
            lines.push('');
        } else if (body.kind === 'file') {
            lines.push('$form = @{ file = Get-Item '
                + quotePowershell(body.file.name) + ' }');
            lines.push('');
        }

        args.push('-Method ' + req.method);
        if (headers.length) {
            args.push('-Headers $headers');
        }
        if (req.contentType && (body.kind === 'json' || body.kind === 'raw')) {
            args.push('-ContentType ' + quotePowershell(req.contentType));
        }
        if (body.kind === 'json' || body.kind === 'raw') {
            args.push('-Body $body');
        } else if (body.kind === 'form' || body.kind === 'file') {
            args.push('-Form $form');
        }

        // Backtick continuation, one argument per line: these commands run long
        // once headers and a body are in play, and the panel does not wrap.
        lines.push('$response = Invoke-RestMethod `\n    '
            + args.join(' `\n    '));
        lines.push('');
        // FOG's responses nest (a host carries its images, snapins, macs),
        // and ConvertTo-Json truncates at depth 2 unless told otherwise.
        lines.push('$response | ConvertTo-Json -Depth 10');

        return lines.join('\n');
    }

    /* ---------------------------------------------------------------- *
     * Python                                                            *
     * ---------------------------------------------------------------- */

    var PYTHON_METHODS = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options'];

    function pythonValue(value, pad) {
        if (value === null) {
            return 'None';
        }
        if (value === true) {
            return 'True';
        }
        if (value === false) {
            return 'False';
        }
        if (typeof value === 'number') {
            return String(value);
        }
        if (typeof value === 'string') {
            return quoteDouble(value);
        }
        if (Array.isArray(value)) {
            if (!value.length) {
                return '[]';
            }
            return '[\n' + value.map(function (item) {
                return pad + '    ' + pythonValue(item, pad + '    ');
            }).join(',\n') + '\n' + pad + ']';
        }
        if (typeof value === 'object') {
            var keys = Object.keys(value);
            if (!keys.length) {
                return '{}';
            }
            return '{\n' + keys.map(function (key) {
                return pad + '    ' + quoteDouble(key) + ': '
                    + pythonValue(value[key], pad + '    ');
            }).join(',\n') + '\n' + pad + '}';
        }
        return quoteDouble(String(value));
    }

    function pythonDict(pairs, pad) {
        if (!pairs.length) {
            return '{}';
        }
        return '{\n' + pairs.map(function (pair) {
            return pad + '    ' + quoteDouble(pair[0]) + ': '
                + quoteDouble(pair[1]);
        }).join(',\n') + '\n' + pad + '}';
    }

    function pythonSnippet(request) {
        var req = readRequest(request);
        var body = req.body;
        var lines = ['import requests', ''];
        var args = ['url'];
        var headers = req.headers;
        var call;

        if (body.kind === 'form' || body.kind === 'file') {
            // requests writes its own multipart/form-data header, boundary and
            // all. Passing the one Swagger UI sent -- which has no boundary on
            // it -- overrides that, and the server then cannot parse a body
            // that was encoded correctly.
            headers = headers.filter(function (pair) {
                return pair[0].toLowerCase() !== 'content-type';
            });
        }

        lines.push('url = ' + quoteDouble(req.url));
        lines.push('');

        if (headers.length) {
            lines.push('headers = ' + pythonDict(headers, ''));
            lines.push('');
            args.push('headers=headers');
        }

        if (body.kind === 'json') {
            lines.push('payload = ' + pythonValue(body.value, ''));
            lines.push('');
            // json= rather than data=json.dumps(...): requests serialises it
            // and the shape stays readable and editable in the snippet.
            args.push('json=payload');
        } else if (body.kind === 'raw') {
            lines.push('payload = ' + quoteDouble(body.raw));
            lines.push('');
            args.push('data=payload');
        } else if (body.kind === 'form') {
            var files = body.fields.filter(function (pair) {
                return isFileLike(pair[1]);
            });
            var plain = body.fields.filter(function (pair) {
                return !isFileLike(pair[1]);
            });
            if (plain.length) {
                lines.push('payload = ' + pythonDict(plain.map(function (pair) {
                    return [pair[0], String(pair[1])];
                }), ''));
                lines.push('');
                args.push('data=payload');
            }
            if (files.length) {
                lines.push('files = {');
                files.forEach(function (pair) {
                    lines.push('    ' + quoteDouble(pair[0]) + ': open('
                        + quoteDouble(pair[1].name) + ', "rb"),');
                });
                lines.push('}');
                lines.push('');
                args.push('files=files');
            }
        } else if (body.kind === 'file') {
            lines.push('files = {"file": open('
                + quoteDouble(body.file.name) + ', "rb")}');
            lines.push('');
            args.push('files=files');
        }

        if (PYTHON_METHODS.indexOf(req.method.toLowerCase()) >= 0) {
            call = 'requests.' + req.method.toLowerCase() + '(' + args.join(', ') + ')';
        } else {
            call = 'requests.request(' + quoteDouble(req.method) + ', '
                + args.join(', ') + ')';
        }

        lines.push('response = ' + call);
        lines.push('response.raise_for_status()');
        lines.push('print(response.json())');

        return lines.join('\n');
    }

    /* ---------------------------------------------------------------- *
     * JavaScript                                                        *
     * ---------------------------------------------------------------- */

    function jsValue(value, pad) {
        if (value === null) {
            return 'null';
        }
        if (typeof value === 'boolean' || typeof value === 'number') {
            return String(value);
        }
        if (typeof value === 'string') {
            return quoteDouble(value);
        }
        if (Array.isArray(value)) {
            if (!value.length) {
                return '[]';
            }
            return '[\n' + value.map(function (item) {
                return pad + '  ' + jsValue(item, pad + '  ');
            }).join(',\n') + '\n' + pad + ']';
        }
        if (typeof value === 'object') {
            var keys = Object.keys(value);
            if (!keys.length) {
                return '{}';
            }
            return '{\n' + keys.map(function (key) {
                return pad + '  ' + quoteDouble(key) + ': '
                    + jsValue(value[key], pad + '  ');
            }).join(',\n') + '\n' + pad + '}';
        }
        return quoteDouble(String(value));
    }

    function jsObject(pairs, pad) {
        if (!pairs.length) {
            return '{}';
        }
        return '{\n' + pairs.map(function (pair) {
            return pad + '  ' + quoteDouble(pair[0]) + ': ' + quoteDouble(pair[1]);
        }).join(',\n') + '\n' + pad + '}';
    }

    function javascriptSnippet(request) {
        var req = readRequest(request);
        var body = req.body;
        var lines = [];
        var options = ['method: ' + quoteDouble(req.method)];
        var headers = req.headers;

        lines.push('const url = ' + quoteDouble(req.url) + ';');
        lines.push('');

        if (body.kind === 'form' || body.kind === 'file') {
            // The browser sets multipart/form-data with its own boundary; a
            // Content-Type copied from here would carry the wrong one and the
            // request would arrive unparseable.
            headers = headers.filter(function (pair) {
                return pair[0].toLowerCase() !== 'content-type';
            });
            lines.push('const form = new FormData();');
            if (body.kind === 'file') {
                lines.push('// Supply the file yourself; a snippet cannot carry one.');
                lines.push('form.append("file", fileInput.files[0]);');
            } else {
                body.fields.forEach(function (pair) {
                    if (isFileLike(pair[1])) {
                        lines.push('form.append(' + quoteDouble(pair[0])
                            + ', fileInput.files[0]); // ' + pair[1].name);
                    } else {
                        lines.push('form.append(' + quoteDouble(pair[0]) + ', '
                            + quoteDouble(String(pair[1])) + ');');
                    }
                });
            }
            lines.push('');
        }

        if (headers.length) {
            options.push('headers: ' + jsObject(headers, '  '));
        }
        if (body.kind === 'json') {
            options.push('body: JSON.stringify(' + jsValue(body.value, '  ') + ')');
        } else if (body.kind === 'raw') {
            options.push('body: ' + quoteDouble(body.raw));
        } else if (body.kind === 'form' || body.kind === 'file') {
            options.push('body: form');
        }

        lines.push('const response = await fetch(url, {');
        // Each option is already generated at a two-space pad, so only its
        // first line needs prefixing -- indenting the whole block would push
        // nested members and their closing brace out of line with it.
        lines.push(options.map(function (option) {
            return '  ' + option;
        }).join(',\n'));
        lines.push('});');
        lines.push('');
        lines.push('if (!response.ok) {');
        lines.push('  throw new Error(`FOG returned ${response.status}`);');
        lines.push('}');
        lines.push('console.log(await response.json());');

        return lines.join('\n');
    }

    /* ---------------------------------------------------------------- */

    var GENERATORS = {
        powershell: {
            title: 'PowerShell',
            syntax: 'powershell',
            fn: powershellSnippet
        },
        python: {
            title: 'Python',
            syntax: 'python',
            fn: pythonSnippet
        },
        javascript: {
            title: 'JavaScript',
            syntax: 'javascript',
            fn: javascriptSnippet
        }
    };

    /**
     * The `requestSnippets` config block.
     *
     * `languages` is doing real work here, not decoration. A `generators` map
     * passed in config is *merged* into Swagger UI's own rather than replacing
     * it, so listing only the four wanted below still leaves curl_powershell
     * and curl_cmd in the map and renders six tabs -- including the `curl.exe`
     * one this exists to displace. `languages` is the allowlist the selector
     * filters the merged map through, so it is what actually decides the tabs.
     *
     * Tab order follows the merged map, which puts the built-ins first: cURL,
     * then PowerShell, Python, JavaScript. cURL leads deliberately -- it is the
     * form every reader recognises and the one the FOG docs quote -- and being
     * first also makes it the tab that opens by default.
     *
     * Adding the Windows cmd tab back is one entry in LANGUAGES. curl_powershell
     * is the one to leave out; see the note at the top of this file.
     */
    var LANGUAGES = ['curl_bash', 'powershell', 'python', 'javascript'];

    function config() {
        var generators = {
            curl_bash: { title: 'cURL', syntax: 'bash' }
        };
        Object.keys(GENERATORS).forEach(function (key) {
            generators[key] = {
                title: GENERATORS[key].title,
                syntax: GENERATORS[key].syntax
            };
        });
        return {
            generators: generators,
            defaultExpanded: true,
            languages: LANGUAGES.slice()
        };
    }

    /**
     * The Swagger UI plugin carrying the functions the config above names.
     */
    function plugin() {
        var fn = {};
        Object.keys(GENERATORS).forEach(function (key) {
            fn['requestSnippetGenerator_' + key] = GENERATORS[key].fn;
        });
        return { fn: fn };
    }

    return {
        config: config,
        plugin: plugin,
        // Exposed so tests/apidocs-request-snippets.test.sh can call the
        // generators directly rather than standing up a browser.
        generators: GENERATORS
    };
}));
