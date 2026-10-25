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
- Exceptions: dynamic directories use exactly `__ITEM__`; the project documents
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
`run(): void` calls the private `resolve(): ?string` method, then requires the
resolved handler. Missing or invalid routes return HTTP 404 with `Not Found`.
A missing handler directory raises RuntimeException as a configuration error.
The homepage handler currently prints `Gonzo PHP`.

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
$username = $app->item(0); // davide
$articleId = $app->item(1); // 42
```

`item(int $index): ?string` returns a captured value or null for an absent index.
Values remain strings and follow traversal order. Captures are reset before
each run and are committed only after successful resolution; failed requests
leave no partial captures. Static segments do not consume an index.
The same lowercase character restrictions apply to dynamic values.
All traversed directories and final files must stay inside the handler root.

There is no `items()` method or permission callback API yet.
Do not call invented methods such as `items()` or `allow()`.

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
or session startup. A login check alone does not establish ownership or other
resource permissions: include the application's required checks in the condition.
Omitting requireAccess() makes the route public; include it when generating
handlers intended to be restricted. Public handlers need no extra declaration.

## Views

`render(string $view, array $data = []): void` outputs a PHP template directly.
View folders are independent of handler folders. For example:

```php
$app->render('utenti/profilo', ['username' => $app->item(0)]);
```

This loads only `views/utenti/profilo.php` from the configured views directory.
For `views/utenti/profilo/index.php`, explicitly request `utenti/profilo/index`.
There is no directory index fallback, __ITEM__ matching, or automatic escaping.
Names are relative, without .php, with lowercase letters, digits, underscores,
and hyphens per segment. Absolute paths, dots, and empty segments are rejected.
Resolved files (including symlink targets) must remain within the view directory.

Templates read values from `$data`; keys are not extracted into variables:

```php
<h1><?= $app->escape($data['username']) ?></h1>
```


Views receive the current framework instance as $app. escape(string $value)
returns escaped HTML without printing: it uses ENT_QUOTES | ENT_SUBSTITUTE
and UTF-8. Use it for HTML text and quoted attribute values. JavaScript, CSS,
and URLs require context-specific handling; escape() does not validate URLs.

Do not rely on render()'s internal local variables. Missing or inaccessible views
raise RuntimeException; invalid names raise InvalidArgumentException. These are
application errors, not routing 404 responses. No layouts or view inheritance
are implemented. Apply requireAccess() in the handler before rendering.

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
