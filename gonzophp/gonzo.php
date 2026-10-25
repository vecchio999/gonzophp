<?php

/**
 * Gonzo PHP framework entry class and filesystem router.
 *
 * Load this file from the application's public entry point, create a GonzoPHP
 * instance, and call run() to dispatch the current request.
 *
 * @package GonzoPHP
 */

/**
 * Configure application paths and dispatch requests to PHP handler files.
 *
 * Static URL paths map to a matching PHP file or a directory's index.php.
 * Dynamic __ITEM__ directories capture values in route order.
 * Handlers may enforce access conditions with requireAccess().
 * Views produce output through render(); authentication is application-owned.
 */
class GonzoPHP
{
    /**
     * Application configuration merged with the default filesystem paths.
     *
     * Supported keys are root, handlers, and views. The views path is used
     * by render() independently of the handler directory structure.
     *
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * Dynamic values collected in route order.
     *
     * @var list<string>
     */
    private array $items = [];

    /**
     * Initialize configuration with optional path overrides.
     *
     * The root defaults to the parent of this file's directory. Default handlers
     * and views paths are derived from the selected root; explicit paths take
     * precedence. Supply absolute filesystem paths for these options.
     * Configuration does not change Apache routing or access-control rules.
     *
     * @param array{root?: string, handlers?: string, views?: string} $config
     *     Optional application root, handler directory, and view directory.
     */
    public function __construct(array $config = [])
    {
        $root = $config['root'] ?? dirname(__DIR__);

        $this->config = array_replace([
            'root' => $root,
            'handlers' => $root . '/handlers',
            'views' => $root . '/views',
        ], $config);
    }

    /**
     * Resolve and execute the handler for the current request.
     *
     * Missing or invalid routes produce HTTP 404 and a plain Not Found message.
     * Handlers receive this instance as $app through the local include scope.
     * Dynamic values are reset before each dispatch.
     *
     * @return void
     * @throws RuntimeException If the configured handler directory is missing
     *     or does not resolve to a directory.
     */
    public function run(): void
    {
        $this->items = [];

        $handler = $this->resolve();

        if ($handler === null) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }

        $app = $this;

        require $handler;
    }

    /**
     * Return a dynamic route value by its zero-based index.
     *
     * @param int $index Dynamic parameter position.
     * @return string|null The value, or null if the index does not exist.
     */
    public function item(int $index): ?string
    {
        return $this->items[$index] ?? null;
    }

    /**
     * Require an access condition before continuing a protected handler.
     *
     * A true condition allows execution to continue. A false condition sends
     * HTTP 403 with Forbidden and terminates the script, including the caller.
     * Call before output or protected side effects. Handlers without this
     * check are public by default. Authentication remains the application's
     * responsibility; this method does not start a session or perform login.
     *
     * @param bool $condition Whether the current request is allowed.
     * @return void Returns only when access is allowed.
     */
    public function requireAccess(bool $condition): void
    {
        if (!$condition) {
            http_response_code(403);
            exit('Forbidden');
        }
    }

    /**
     * Render an explicitly named PHP view directly to the output.
     *
     * Names are relative to the configured view directory, without .php.
     * Use lowercase letters, digits, underscores, and hyphens in each segment.
     * No index.php fallback or dynamic directory matching is performed.
     * Data is available as $data; keys are not extracted into local variables.
     * The current framework instance is available as $app.
     * Templates must escape values for their output context.
     *
     * @param string $view Relative view name, for example utenti/profilo.
     * @param array<string, mixed> $data Values made available to the view.
     * @return void
     * @throws InvalidArgumentException If the view name is invalid.
     * @throws RuntimeException If the view directory or file is missing,
     *     unreadable, or the resolved file is outside the view directory.
     */
    public function render(string $view, array $data = []): void
    {
        if (!preg_match('/\A[a-z0-9_-]+(?:\/[a-z0-9_-]+)*\z/', $view)) {
            throw new InvalidArgumentException('Invalid view name.');
        }

        $viewsRoot = realpath($this->config['views']);

        if ($viewsRoot === false || !is_dir($viewsRoot)) {
            throw new RuntimeException('View directory not found.');
        }

        $prefix = rtrim($viewsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $file = realpath($prefix . $view . '.php');

        if (
            $file === false
            || !str_starts_with($file, $prefix)
            || !is_file($file)
            || !is_readable($file)
        ) {
            throw new RuntimeException('View not found or inaccessible: ' . $view);
        }

        $app = $this;

        require $file;
    }

    /**
     * Escape a string for HTML text or quoted attribute values.
     *
     * Returns the escaped value without printing it. Both quote types are
     * escaped and invalid UTF-8 sequences are replaced. This is not an
     * escaping mechanism for JavaScript, CSS, or URL validation.
     *
     * @param string $value Raw text to escape.
     * @return string The HTML-escaped UTF-8 string.
     */
    public function escape(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    /**
     * Find a handler for the path in the current request URI.
     *
     * Ignore the query string and trim leading and trailing slashes. Decode
     * each segment once and accept only lowercase ASCII letters, digits,
     * underscores, and hyphens. Empty internal segments are invalid.
     *
     * Traverse one segment at a time. On the last segment a static PHP file
     * takes precedence; otherwise prefer a static directory over __ITEM__.
     * Never backtrack to a dynamic branch after choosing a static directory.
     * A resolved directory uses index.php, including the root path.
     * Publish captured values only after finding a valid handler.
     * Resolved directories and files, including symlink targets, must remain
     * within the handler directory. URL mount prefixes are not removed.
     * If REQUEST_URI is unavailable, use the root path.
     *
     * @return string|null Absolute handler path, or null for an invalid path
     *     or when no matching file exists within the handler directory.
     * @throws RuntimeException If the configured handler directory is missing
     *     or does not resolve to a directory.
     */
    private function resolve(): ?string
    {
        $handlersRoot = realpath($this->config['handlers']);

        if ($handlersRoot === false || !is_dir($handlersRoot)) {
            throw new RuntimeException('Handler directory not found.');
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = trim(explode('?', $uri, 2)[0], '/');
        $segments = $path === '' ? [] : explode('/', $path);

        foreach ($segments as $index => $segment) {
            $segment = rawurldecode($segment);

            // Accept lowercase route names and reject path traversal.
            if (!preg_match('/\A[a-z0-9_-]+\z/', $segment)) {
                return null;
            }

            $segments[$index] = $segment;
        }

        $directory = $handlersRoot;
        $prefix = rtrim($handlersRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $items = [];
        $last = count($segments) - 1;

        foreach ($segments as $index => $segment) {
            // A static file takes precedence on the final segment.
            if ($index === $last) {
                $file = realpath($directory . '/' . $segment . '.php');

                if ($file !== false && is_file($file)) {
                    if (!str_starts_with($file, $prefix)) {
                        return null;
                    }

                    $this->items = $items;

                    return $file;
                }
            }

            // Prefer a static directory over the dynamic fallback.
            $next = realpath($directory . '/' . $segment);

            if ($next === false || !is_dir($next)) {
                $next = realpath($directory . '/__ITEM__');

                if ($next === false || !is_dir($next)) {
                    return null;
                }

                $items[] = $segment;
            }

            // Keep every resolved directory inside the handler root.
            if (!str_starts_with($next, $prefix)) {
                return null;
            }

            $directory = $next;
        }

        $file = realpath($directory . '/index.php');

        if (
            $file === false
            || !is_file($file)
            || !str_starts_with($file, $prefix)
        ) {
            return null;
        }

        $this->items = $items;

        return $file;
    }
}
