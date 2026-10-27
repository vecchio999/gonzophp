# Gonzo PHP: instructions for AI coding assistants

## Scope and approach

Gonzo PHP is a small, filesystem-oriented PHP framework under development.
Build incrementally. Implement only the requested behavior and keep code explicit.
Read the current source before using an API; this document is not a promise of
features that have not been implemented.

## Project layout

- `index.php`: public entry point; loads the framework and starts the application.
- `gonzophp/gonzo.php`: the `GonzoPHP` framework class.
- `handlers/`: application request handlers.
- `views/`: application templates.
- `.htaccess`: centralized routing and HTTP access protections for framework
  files, application directories, and project documentation.

For application work, prefer changes in `handlers/` and `views/`. Change framework
internals only when the requested task requires it. Preserve application files
when updating the framework.

## Conventions

- Use lowercase file and directory names.
- Exceptions: dynamic directories use exactly `__ITEM__`, method files use
  uppercase `__METHOD__` prefixes; the project documents
  are named `AGENTS.md` and `README.txt`.
- The existing checkout directory is named `GonzoPHP`; do not rename it as part
  of routine code changes.
- Use UpperCamelCase class names; the framework class is exactly `GonzoPHP`.
- Write all code comments in English.
- Keep this document and README.txt consistent with implemented behavior.

## Implemented behavior and API

The entry point currently contains:

```php
require __DIR__ . '/gonzophp/gonzo.php';

$app = new GonzoPHP();
$app->run();
```

`__construct(array $config = [])` merges overrides with internal defaults using
`array_replace`. The `root` option defaults to `dirname(__DIR__)` in gonzo.php.
The `handlers` and `views` defaults are derived from the selected root. Explicit
handler or view paths take precedence over these derived defaults.
Supply absolute filesystem paths when overriding these settings. The `views`
setting selects the template directory used by render().

```php
$app = new GonzoPHP([
    'root' => __DIR__,
    'handlers' => __DIR__ . '/custom-handlers',
    'views' => __DIR__ . '/custom-views',
]);
```

When using custom directories under the document root, update the HTTP access
protections to cover them; configuration does not change Apache rules.
`run(): void` calls the private `resolve(): ?array` method, then selects and requires the
handler for the request method. Missing or invalid routes return HTTP 404 with `Not Found`.
A missing handler directory raises RuntimeException as a configuration error.
The homepage handler renders `views/index.php` with a title and message.
The starter also includes GET and POST /csrf handlers and a csrf view as a form
example. Other routes shown below are documentation examples, not bundled handlers.

Routing rules:
- `/` resolves to `handlers/index.php`.
- `/contatti` tries `handlers/contatti.php`, then `handlers/contatti/index.php`.
- Nested paths follow the same rule; a file takes precedence over a directory index.
- Query strings do not affect handler selection; PHP still populates `$_GET`.
- Leading and trailing slashes are trimmed; empty internal segments are rejected.
- Each segment is URL-decoded once and must match `[a-z0-9_-]+`.
  Dots, encoded separators, uppercase names, and other characters return 404.
- Resolved files, including symlink targets, must remain inside the handler root.
- URLs are currently relative to the site root; subdirectory mount prefixes are
  not removed automatically.

Apache forwards requests that do not match an existing file or directory to
`index.php`. It does not select a PHP handler. Existing directories without a
configured index cannot display a directory listing.

## Dynamic routes

The router traverses the path one segment at a time. A static directory takes
precedence over __ITEM__; on the final segment a static PHP file takes precedence
over either directory. There is no backtracking to __ITEM__ after choosing a
static directory, even if that branch later fails to resolve a handler.

For /utenti/davide/articoli/42/edit, the handler can be:
`handlers/utenti/__ITEM__/articoli/__ITEM__/edit.php`.
Handlers receive the current GonzoPHP instance as `$app`:

```php
$username = $app->items()[0] ?? null; // davide
$articleId = $app->items()[1] ?? null; // 42
```

`items(): array` returns all captured values as a zero-based list of strings.
The array is returned by value; changing it does not modify the router state.
Use `$app->items()[0] ?? null` for an index that might not exist.
Values remain strings and follow traversal order. Captures are reset before
each run and are committed only after successful resolution; failed requests
leave no partial captures. Static segments do not consume an index.
The same lowercase character restrictions apply to dynamic values.
All traversed directories and final files must stay inside the handler root.

There is no permission callback API. Use requireAccess() for access checks.
The singular item() method has been removed; use items() instead.

## HTTP methods

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

## Form handler responses

HTML form handlers follow Post/Redirect/Get: GET handlers prepare and render
views; handlers that process changes (POST, or PUT/PATCH/DELETE where applicable)
finish with redirect() using status 303 to an application-controlled GET route.
Do not render views from these mutation handlers. On ordinary input-validation
errors, store feedback and appropriate non-sensitive field values temporarily in
the session, then redirect too. The GET handler reads and removes this feedback;
HEAD must not consume it. Never retain passwords or other secrets as old input.
Security checks may terminate directly with 403, and unexpected failures may
propagate normally. API endpoints may return JSON or an appropriate empty
response instead of redirecting. This is an application convention, not a router
restriction; native HTML forms support GET/POST, and requireCsrf() currently
supports POST form fields only.

## Handler authorization

Routes are public by default when their handler does not call requireAccess().
Use `$app->requireAccess(bool $condition): void` at the beginning of protected
handlers, before any output or protected side effects. A true condition lets
execution continue. A false condition sends HTTP 403 with `Forbidden` and exits
the script: neither the remaining handler code nor code after run() executes.
This method does not return a permission boolean and does not accept callbacks.

```php
$app->requireAccess(isset($_SESSION['user_id']));

// Protected operations start here.
```

This example assumes that the application has already started the session and
manages authentication. The framework does not implement login, users, roles,
or automatic session startup on every request. A login check alone does not establish ownership or other
resource permissions: include the application's required checks in the condition.
Omitting requireAccess() makes the route public; include it when generating
handlers intended to be restricted. Public handlers need no extra declaration.

## CSRF protection for POST forms

`csrfToken(): string` starts or reuses a PHP session and returns a reusable token
from 32 secure random bytes. The reserved session key is `gonzo_csrf_token`.
Call it in the GET handler before output and pass the token to the view.
Render an escaped hidden input named `_csrf`; keep form markup in plain PHP/HTML.

`requireCsrf(): void` must be called explicitly at the start of each POST form
handler, before output or side effects. It starts or reuses the session, accepts
only POST, and compares the submitted `_csrf` string with the stored token using
hash_equals(). Missing, array-valued, or mismatched tokens return 403 Forbidden
and exit. Validation does not generate a token. Other request bodies, JSON,
headers, and HTTP methods are not supported by this helper.

Sessions are not started by the constructor or router. Session settings and
closing remain application-owned; configure cookies before starting a session.
These helpers do not close an existing session. Startup failures raise
RuntimeException. CSRF checks do not replace authorization or input validation.
Do not put CSRF tokens in URLs, logs, or shared caches. Pages containing tokens
must not be cached across visitors. Tokens are not one-time nonces and do not
rotate per submission; the application can unset the reserved key to invalidate
one. PHP session expiry also invalidates it.

The bundled /csrf example uses handlers/csrf.php, handlers/__POST__csrf.php,
and views/csrf.php. After CSRF validation, both valid and invalid name submissions
store temporary feedback in the session and redirect to /csrf with 303. The next
GET displays escaped feedback and restores invalid input, then consumes it.
Refreshing does not repeat the POST or show the feedback again. HEAD does not
consume feedback. No application data is persisted; invalid CSRF still returns 403.

## Views

`render(string $view, array $data = []): void` outputs a PHP template directly.
View folders are independent of handler folders. For example:

```php
$app->render('index', [
    'title' => 'Gonzo PHP',
    'message' => 'Small framework. Plain PHP.',
]);
```

This loads only `views/index.php` from the configured views directory.
For a nested template such as `views/pages/home/index.php`, explicitly request
`pages/home/index`.
There is no directory index fallback, __ITEM__ matching, or automatic escaping.
Names are relative, without .php, with lowercase letters, digits, underscores,
and hyphens per segment. Absolute paths, dots, and empty segments are rejected.
Resolved files (including symlink targets) must remain within the view directory.

Templates read values from `$data`; keys are not extracted into variables:

```php
<h1><?= $app->escape($data['title']) ?></h1>
```


Views receive the current framework instance as $app. escape(string $value)
returns escaped HTML without printing: it uses ENT_QUOTES | ENT_SUBSTITUTE
and UTF-8. Use it for HTML text and quoted attribute values. JavaScript, CSS,
and URLs require context-specific handling; escape() does not validate URLs.

Do not rely on render()'s internal local variables. Missing or inaccessible views
raise RuntimeException; invalid names raise InvalidArgumentException. These are
application errors, not routing 404 responses. No layouts or view inheritance
are implemented. Apply requireAccess() in the handler before rendering.

## Shared views

The language configuration option defaults to 'en'. Override it at startup:
$app = new GonzoPHP(['language' => 'it']);

config(string $key): mixed reads a configuration value and returns null for an
unknown key. The shared header uses $app->config('language'), escaped with
escape(), for the HTML lang attribute. This declares the document language;
it does not translate page content or change the PHP locale.


Compose templates with the existing render() method. The default view uses
`shared/header` with an explicit `title` string, and `shared/footer` without data.
The header opens the HTML document and body; the footer closes them.
Each render() call has its own $data array: pass the values needed by each shared
view explicitly. Nested rendering does not overwrite the parent view's data.
There is no automatic layout selection or router change.
The starter uses Pico CSS v2 as its base styling, loaded through jsDelivr in
`views/shared/header.php`:
`https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css`.
Preserve the stylesheet link and shared container when editing starter views
unless the task calls for changing the styling. Pico CSS is replaceable and is
not required by the PHP framework.

## Response helpers

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

## Debug and native PHP errors

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

## Application safety

- Keep direct HTTP access to handlers, views, framework files, and project
  instructions blocked. PHP file inclusion must remain possible.
- Validate request data before use and check authorization before side effects.
  Use requireAccess() to enforce the application's authorization condition.
- Escape untrusted values for their output context. For HTML text and quoted
  attributes, use `$app->escape($value)`.
- Never build include paths directly from untrusted request input.
- Do not add credentials or other secrets to public files or version control.

## Verification

Use PHP syntax checks for changed PHP files and focused execution checks.
For routing or access-control changes, also verify HTTP behavior under Apache:
normal pages should work and protected paths should return 403.
Report what was actually tested and any remaining limitations.
