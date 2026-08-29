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
 * so Python is declared honestly as 'python' and gets auto-detected coloring
 * rather than being mislabeled as something that happens to be registered.
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
        // be serialized to.
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
            // json= rather than data=json.dumps(...): requests serializes it
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

    /* ---------------------------------------------------------------- *
     * Ruby                                                              *
     * ---------------------------------------------------------------- */

    var RUBY_METHODS = {
        GET: 'Get',
        POST: 'Post',
        PUT: 'Put',
        PATCH: 'Patch',
        DELETE: 'Delete',
        HEAD: 'Head',
        OPTIONS: 'Options'
    };

    /**
     * A double-quoted Ruby string interpolates #{...}, so that sequence has to
     * be escaped on top of the C-style escapes quoteDouble already applies.
     * Nothing else in a header or a JSON body can change the meaning.
     */
    function quoteRuby(value) {
        return quoteDouble(value).replace(/#\{/g, '\\#{');
    }

    function rubyValue(value, pad) {
        if (value === null) {
            return 'nil';
        }
        if (typeof value === 'boolean' || typeof value === 'number') {
            return String(value);
        }
        if (typeof value === 'string') {
            return quoteRuby(value);
        }
        if (Array.isArray(value)) {
            if (!value.length) {
                return '[]';
            }
            return '[\n' + value.map(function (item) {
                return pad + '  ' + rubyValue(item, pad + '  ');
            }).join(',\n') + '\n' + pad + ']';
        }
        if (typeof value === 'object') {
            var keys = Object.keys(value);
            if (!keys.length) {
                return '{}';
            }
            return '{\n' + keys.map(function (key) {
                return pad + '  ' + quoteRuby(key) + ' => '
                    + rubyValue(value[key], pad + '  ');
            }).join(',\n') + '\n' + pad + '}';
        }
        return quoteRuby(String(value));
    }

    function rubySnippet(request) {
        var req = readRequest(request);
        var body = req.body;
        var lines = [];
        var verb = RUBY_METHODS[req.method];
        var headers = req.headers;

        if (body.kind === 'form' || body.kind === 'file') {
            // set_form writes the multipart header, boundary included.
            headers = headers.filter(function (pair) {
                return pair[0].toLowerCase() !== 'content-type';
            });
        }

        // net/http is stdlib, so this runs on a stock Ruby with nothing to
        // install -- which is the point of a snippet someone pastes once.
        lines.push('require "json"');
        lines.push('require "net/http"');
        lines.push('require "uri"');
        lines.push('');
        lines.push('uri = URI(' + quoteRuby(req.url) + ')');
        lines.push('');

        if (verb) {
            lines.push('request = Net::HTTP::' + verb + '.new(uri)');
        } else {
            // Net::HTTP has no class for an unusual verb; GenericRequest takes
            // the method as a string instead.
            lines.push('request = Net::HTTPGenericRequest.new('
                + quoteRuby(req.method) + ', true, true, uri)');
        }
        headers.forEach(function (pair) {
            lines.push('request[' + quoteRuby(pair[0]) + '] = '
                + quoteRuby(pair[1]));
        });
        lines.push('');

        if (body.kind === 'json') {
            lines.push('request.body = JSON.generate(' + rubyValue(body.value, '') + ')');
            lines.push('');
        } else if (body.kind === 'raw') {
            lines.push('request.body = ' + quoteRuby(body.raw));
            lines.push('');
        } else if (body.kind === 'form' || body.kind === 'file') {
            var fields = body.kind === 'file'
                ? [['file', body.file]]
                : body.fields;
            lines.push('request.set_form([');
            fields.forEach(function (pair) {
                lines.push('  [' + quoteRuby(pair[0]) + ', '
                    + (isFileLike(pair[1])
                        ? 'File.open(' + quoteRuby(pair[1].name) + ')'
                        : quoteRuby(String(pair[1])))
                    + '],');
            });
            lines.push('], "multipart/form-data")');
            lines.push('');
        }

        // use_ssl has to be set explicitly; Net::HTTP does not infer it from
        // the scheme, and without it an https URL is spoken to in plaintext.
        lines.push('response = Net::HTTP.start('
            + 'uri.hostname, uri.port, use_ssl: uri.scheme == "https") do |http|');
        lines.push('  http.request(request)');
        lines.push('end');
        lines.push('');
        lines.push('puts response.code');
        lines.push('puts response.body');

        return lines.join('\n');
    }

    /* ---------------------------------------------------------------- *
     * PHP                                                               *
     * ---------------------------------------------------------------- */

    /**
     * Single-quoted, which in PHP escapes only the backslash and the quote and
     * interpolates nothing -- so a body containing $foo or \n stays literal.
     */
    function quotePhp(value) {
        return "'" + String(value).replace(/[\\']/g, '\\$&') + "'";
    }

    function phpValue(value, pad) {
        if (value === null) {
            return 'null';
        }
        if (typeof value === 'boolean') {
            return value ? 'true' : 'false';
        }
        if (typeof value === 'number') {
            return String(value);
        }
        if (typeof value === 'string') {
            return quotePhp(value);
        }
        if (Array.isArray(value)) {
            if (!value.length) {
                return '[]';
            }
            return '[\n' + value.map(function (item) {
                return pad + '    ' + phpValue(item, pad + '    ') + ',';
            }).join('\n') + '\n' + pad + ']';
        }
        if (typeof value === 'object') {
            var keys = Object.keys(value);
            if (!keys.length) {
                return '[]';
            }
            return '[\n' + keys.map(function (key) {
                return pad + '    ' + quotePhp(key) + ' => '
                    + phpValue(value[key], pad + '    ') + ',';
            }).join('\n') + '\n' + pad + ']';
        }
        return quotePhp(String(value));
    }

    function phpSnippet(request) {
        var req = readRequest(request);
        var body = req.body;
        var lines = ['<?php', ''];
        var headers = req.headers;

        if (body.kind === 'form' || body.kind === 'file') {
            // Handed an array, curl builds the multipart body and sets the
            // header with its own boundary. Sending the one from here, which
            // has no boundary, replaces that and the body becomes unreadable.
            headers = headers.filter(function (pair) {
                return pair[0].toLowerCase() !== 'content-type';
            });
        }

        lines.push('$ch = curl_init(' + quotePhp(req.url) + ');');
        lines.push('');
        lines.push('curl_setopt($ch, CURLOPT_CUSTOMREQUEST, '
            + quotePhp(req.method) + ');');
        // Without this curl_exec prints the body and returns a bool, which is
        // the usual first surprise for anyone new to the extension.
        lines.push('curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);');

        if (headers.length) {
            lines.push('curl_setopt($ch, CURLOPT_HTTPHEADER, [');
            headers.forEach(function (pair) {
                lines.push('    ' + quotePhp(pair[0] + ': ' + pair[1]) + ',');
            });
            lines.push(']);');
        }

        if (body.kind === 'json') {
            lines.push('');
            lines.push('$payload = ' + phpValue(body.value, '') + ';');
            lines.push('');
            lines.push('curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));');
        } else if (body.kind === 'raw') {
            lines.push('');
            lines.push('curl_setopt($ch, CURLOPT_POSTFIELDS, '
                + quotePhp(body.raw) + ');');
        } else if (body.kind === 'form' || body.kind === 'file') {
            var fields = body.kind === 'file'
                ? [['file', body.file]]
                : body.fields;
            lines.push('');
            lines.push('curl_setopt($ch, CURLOPT_POSTFIELDS, [');
            fields.forEach(function (pair) {
                lines.push('    ' + quotePhp(pair[0]) + ' => '
                    + (isFileLike(pair[1])
                        ? 'new CURLFile(' + quotePhp(pair[1].name) + ')'
                        : quotePhp(String(pair[1])))
                    + ',');
            });
            lines.push(']);');
        }

        lines.push('');
        lines.push('$response = curl_exec($ch);');
        lines.push('');
        // curl_exec returns false on a transport failure and an error page's
        // body on an HTTP one; both look like "it worked" to json_decode.
        lines.push('if ($response === false) {');
        lines.push('    throw new RuntimeException(curl_error($ch));');
        lines.push('}');
        lines.push('');
        lines.push('$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);');
        lines.push('curl_close($ch);');
        lines.push('');
        lines.push('var_dump($status, json_decode($response, true));');

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
        },
        ruby: {
            title: 'Ruby',
            syntax: 'ruby',
            fn: rubySnippet
        },
        php: {
            title: 'PHP',
            syntax: 'php',
            fn: phpSnippet
        }
    };

    /**
     * The `requestSnippets` config block.
     *
     * `languages` is doing real work here, not decoration. A `generators` map
     * passed in config is *merged* into Swagger UI's own rather than replacing
     * it, so a shorter `generators` does not mean fewer tabs -- the built-ins
     * stay in the merged map and render alongside. `languages` is the allowlist
     * the selector filters that merged map through, so it is the only thing
     * that actually decides which tabs appear.
     *
     * Every generator that exists is currently listed, built-ins included, so
     * the set can be judged on a real server rather than on argument. That is a
     * starting position and not the intended end state -- eight tabs is more
     * than a panel this size wears well, and two of them (cURL (PowerShell) and
     * PowerShell) answer the same question in different ways on purpose, so a
     * reader can see which one they actually want. Cull by deleting entries
     * from this list; nothing else has to change, and a generator dropped from
     * here stays available for a later reversal.
     *
     * Order follows the merged map, which puts Swagger UI's three first. cURL
     * leads, which also makes it the tab that opens by default.
     */
    var LANGUAGES = [
        // Swagger UI's own. curl_powershell is `curl.exe` with PowerShell
        // quoting rather than PowerShell, which is what the `powershell`
        // generator below exists to offer instead.
        'curl_bash',
        'curl_powershell',
        'curl_cmd',
        // FOG's.
        'powershell',
        'python',
        'javascript',
        'ruby',
        'php'
    ];

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
