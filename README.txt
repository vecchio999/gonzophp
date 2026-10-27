Gonzo PHP
=========

A small, explicit, filesystem-oriented PHP framework, built incrementally.
This is an early development version, not a complete application framework.

REQUIREMENTS

- PHP 8.0 or later (currently tested with PHP 8.2).
- Apache with mod_rewrite and .htaccess overrides enabled for the supplied
  Options, rewrite, and access-control directives.
- Other web servers require equivalent configuration; .htaccess is Apache-specific.

SETUP

Place the project contents in the site's document root. Include the hidden root
.htaccess file when copying or uploading the project.
For a local MAMP setup, select the project directory as the document root.

Open the site URL. The initial page displays Gonzo PHP and
Small framework. Plain PHP. The shared header loads Pico CSS from its CDN.
Only the homepage handler and default/shared views are bundled. Other route
examples in this guide illustrate conventions and are not included files.

BASE CSS: PICO CSS

The starter uses Pico CSS v2 as its base CSS framework. It is loaded directly
from the jsDelivr CDN in views/shared/header.php:

https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css

Pico CSS provides the default styling for the example pages, including the
shared main container. No local CSS installation or build step is required.
The CDN stylesheet requires network access in the visitor's browser.

Pico CSS is a presentation choice for the starter, not a dependency of the PHP
framework. To customize, replace, or host the stylesheet locally, update the
stylesheet link in views/shared/header.php and adjust the views as needed.

Pico CSS website and documentation: https://picocss.com/

LAYOUT

index.php               Public entry point
.htaccess               Request forwarding and access protection
gonzophp/gonzo.php       GonzoPHP framework class
handlers/index.php      Initial application handler
views/                  Application templates
AGENTS.md               Instructions for AI coding assistants
README.txt              This guide

Keep application handlers and views separate from the gonzophp directory so
framework updates can preserve application code.

CURRENT BEHAVIOR

index.php creates GonzoPHP with default configuration and calls run().
run() resolves a handler from the URL path and executes it.
Apache forwards URLs that do not match an existing public file or directory to
index.php. Handler lookup then follows these examples:

/                 -> handlers/index.php
/contatti         -> handlers/contatti.php or handlers/contatti/index.php
/utenti/lista     -> handlers/utenti/lista.php or handlers/utenti/lista/index.php

The file takes precedence over the directory index. Query strings do not affect
selection and remain available in $_GET. Leading and trailing slashes are
trimmed; empty segments inside the path are rejected. Each segment is decoded
once and accepts only lowercase ASCII letters, digits, underscores, and hyphens.
No .php suffix is needed in route URLs. Invalid or missing routes return HTTP
404 with Not Found. Resolved handler files must stay inside the handler root,
including when symbolic links are involved. A missing handler directory is a
configuration error and raises RuntimeException.

URLs currently assume deployment at the site root, not a subdirectory mount.
Permission callbacks are not implemented; use requireAccess() in handlers.

DYNAMIC ROUTES

A __ITEM__ directory matches a segment when no static directory matches.
On the final segment a static PHP file takes precedence over directories.
Once a static directory is chosen, the router never retries a dynamic branch
if a later segment or index.php is missing.

Example:
/utenti/davide/articoli/42/edit
-> handlers/utenti/__ITEM__/articoli/__ITEM__/edit.php

The handler receives the current framework instance as $app:

$username = $app->items()[0] ?? null; // davide
$articleId = $app->items()[1] ?? null; // 42

Indices start at zero and count only dynamic segments. Values are strings;
use $app->items()[0] ?? null to handle missing indices. Captures are cleared
on each run and remain empty
for failed requests. Dynamic values use the same character restrictions as
static segments: lowercase ASCII letters, digits, underscores, and hyphens.
items() returns the array by value; modifying the returned array does not change
the router state. With no captures it returns []. The singular item() method
has been removed.

HTTP METHODS

Unprefixed handler files handle GET only. Other methods use __METHOD__ prefixes:

utenti/index.php                       GET /utenti/
utenti/__POST__index.php               POST /utenti/
utenti/__ITEM__/__DELETE__index.php    DELETE /utenti/mario/
__POST__utenti.php                     POST /utenti

Supported method prefixes are __POST__, __PUT__, __PATCH__, __DELETE__,
__HEAD__, and __OPTIONS__. Other prefixes (including typos such as __POTS__)
are ignored and never included in Allow.
GET must use the unprefixed filename; __GET__ is not supported.
The router selects the route before selecting the HTTP method. A static file
family wins over directory indexes and dynamic branches even when only a POST
variant exists. Unsupported methods never fall back to another route or GET.
Missing routes return 404; existing routes without the requested method return
405 and an Allow header listing available methods. Captures remain empty on 405.
HEAD uses __HEAD__ when present, otherwise GET, with response output suppressed
through request shutdown (also if a handler exits). GET handlers should be safe
and read-only because HEAD may execute them. HEAD is included in Allow when GET
exists. The HEAD output buffer must not be removed by application code.
OPTIONS requires an explicit __OPTIONS__ file; there is no automatic CORS support.
No method override through query parameters, form fields, or headers is applied.
Apache may handle or reject some methods before they reach PHP.

HANDLER AUTHORIZATION

Routes are public unless their handler explicitly calls:

$app->requireAccess($condition);

Pass a boolean condition. True continues execution; false sends HTTP 403 with
Forbidden and terminates the script. Place the check before output and protected
operations. Omitting it leaves the route public. Authentication, login, and
session startup are the application's responsibility. No extra permission files
are required.

VIEWS

Handler and view directory structures are independent. In a handler, call:

$app->render('index', [
    'title' => 'Gonzo PHP',
    'message' => 'Small framework. Plain PHP.',
]);

This outputs views/index.php from the configured views directory.
For views/pages/home/index.php, request 'pages/home/index' explicitly.
There is no index fallback or __ITEM__ matching. Names are relative without
.php; each segment accepts lowercase letters, digits, underscores, and hyphens.

Inside the template, read values through $data, not automatically created
variables. Escape values explicitly for the output context, for example:

<h1><?= $app->escape($data['title']) ?></h1>


Views receive the current framework instance as $app. escape(string $value)
returns escaped HTML without printing: it uses ENT_QUOTES | ENT_SUBSTITUTE
and UTF-8. Use it for HTML text and quoted attribute values. JavaScript, CSS,
and URLs require context-specific handling; escape() does not validate URLs.

render() outputs directly and returns no content string. Missing or inaccessible
views raise RuntimeException; invalid names raise InvalidArgumentException.
Resolved files must remain inside the views directory, including symlink targets.
These are application errors rather than routing 404 responses. Layouts and
view inheritance are not implemented.

SHARED HEADER AND FOOTER

The language configuration option defaults to 'en'. Override it at startup:
$app = new GonzoPHP(['language' => 'it']);

config(string $key): mixed reads a configuration value and returns null for an
unknown key. The shared header uses $app->config('language'), escaped with
escape(), for the HTML lang attribute. This declares the document language;
it does not translate page content or change the PHP locale.


A view can compose other views using render(), without additional framework API:

<?php $app->render('shared/header', ['title' => $data['title']]); ?>
<!-- Page content goes here. -->
<?php $app->render('shared/footer'); ?>

views/shared/header.php opens the document and escapes the supplied title.
views/shared/footer.php closes the document. Each call receives its own data
array; header data does not replace the data in the calling view.
The default view demonstrates this composition. Routing remains unchanged.

RESPONSE HELPERS

renderJson(mixed $data, int $status = 200) sends application/json and terminates
execution. Encoding uses JSON_THROW_ON_ERROR before response headers are changed;
invalid UTF-8, recursion, or unsupported values raise JsonException. Status codes
must be 200-599 except 204, 205, and 304, which cannot carry JSON content.
HEAD sends the same status and content type without a body.

redirect(string $url, int $status = 302) sends Location and terminates execution.
Allowed statuses are 301, 302, 303, 307, 308. Use 303 after POST to request a GET
on the destination. Pass an application-controlled destination; external URLs
are allowed, so do not use unvalidated user input. Empty destinations, whitespace,
and control characters are rejected. Encode spaces in URLs as %20.

Both helpers must be called before output, including buffered output. They raise
RuntimeException if headers have already been sent; they do not discard earlier
buffered content. Invalid arguments raise InvalidArgumentException.
Unlike render(), which continues for template composition, these methods exit:
code after the call does not execute. No json() or renderHtml() aliases exist.

Examples:
$app->renderJson(['username' => $app->items()[0] ?? null]);
$app->renderJson(['error' => 'User not found'], 404);
$app->redirect('/utenti', 303);

Debug and native PHP errors

$app = new GonzoPHP(['debug' => true]);

Debug defaults to false. The constructor sets PHP display_errors to 1 only
when debug is exactly true, otherwise to 0. This setting applies from construction
onward for the current execution; it is not restored after run().
PHP error_reporting, log_errors and error_log remain configured by the host.
Debug does not enable reporting levels that PHP has disabled.

Gonzo does not catch exceptions centrally, format diagnostic pages, clean
response headers, or buffer partial pages for recovery. Exceptions propagate to
the caller; uncaught errors are handled natively by PHP. There is no custom 500
response or guarantee that previously emitted output can be replaced.
The existing HEAD-only buffer remains to suppress response bodies.

Configure display_errors=Off and log_errors=On in production PHP settings;
errors before the constructor and startup/parse errors cannot be controlled by
this runtime switch. Keep diagnostics enabled only in local development and
avoid including secrets in exception messages.

CONFIGURATION

The default setup needs no configuration arguments:

$app = new GonzoPHP();
$app->run();

The constructor accepts an optional array of overrides:

$app = new GonzoPHP([
    'root' => __DIR__,
    'handlers' => __DIR__ . '/custom-handlers',
    'views' => __DIR__ . '/custom-views',
]);

The root option defaults to the parent directory of the framework directory.
The handlers and views defaults are derived from the selected root, so passing
only 'root' relocates both. Explicit handlers or views paths take precedence.
Root is a filesystem path, not a URL or an Apache document-root setting.
Omitted options keep their defaults. Use absolute filesystem paths for custom
directories. The views setting selects the template directory for render().
If custom directories are inside the document root, update .htaccess to protect
them too: changing configuration does not update Apache access rules.

CONVENTIONS

Use lowercase filenames and directory names, except for uppercase __METHOD__ prefixes, special dynamic
__ITEM__ directories and the explicitly named AGENTS.md and README.txt files.
Use UpperCamelCase class names (GonzoPHP) and English code comments.
The existing local project folder keeps its name, GonzoPHP.

ACCESS PROTECTION

The root .htaccess centralizes access protection: it blocks direct requests
to gonzophp, handlers, views, AGENTS.md, and README.txt. No internal .htaccess
files are required in these directories.
Directory listings are disabled. These protections do not prevent PHP from
including application files internally.

After deployment, verify:
- / displays Gonzo PHP.
- A path without a matching handler, such as /utenti/davide, returns HTTP 404.
- /handlers/index.php, /views/, and /gonzophp/gonzo.php return HTTP 403.
- /AGENTS.md and /README.txt return HTTP 403.
- An existing directory without an index returns HTTP 403.

These files must be served through the intended Apache configuration for the
protections to apply. Serving the project with a different server requires
recreating and verifying the same protections.

WORKING WITH AI

Ask your coding assistant to read AGENTS.md before making changes. Not all
assistants load that file automatically. Keep both documents updated as real
features are added; do not treat planned APIs as already available.
