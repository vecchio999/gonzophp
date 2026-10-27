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
     * Supported keys are root, handlers, views, debug, and language. The views path is used
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
     * Debug toggles PHP display_errors from construction onward; reporting
     * levels and logging remain controlled by the host's PHP configuration.
     *
     * @param array{root?: string, handlers?: string, views?: string, debug?: bool, language?: string} $config
     *     Optional paths, debug flag (false), and document language (en).
     */
    public function __construct(array $config = [])
    {
        $root = $config['root'] ?? dirname(__DIR__);

        $this->config = array_replace([
            'debug' => false,
            'language' => 'en',
            'root' => $root,
            'handlers' => $root . '/handlers',
            'views' => $root . '/views',
        ], $config);

        ini_set('display_errors', $this->config['debug'] === true ? '1' : '0');
    }

    /**
     * Resolve and execute the handler for the current request.
     *
     * Missing routes return 404; unsupported methods return 405 with Allow.
     * Handlers receive this instance as $app. HEAD suppresses output through
     * request shutdown, including exit() in a handler. Exceptions propagate
     * to the caller and are handled by PHP unless the application catches them.
     *
     * @return void
     * @throws Throwable If routing or handler execution fails.
     */
    public function run(): void
    {
        $this->items = [];
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'HEAD') {
            // Discard output through shutdown, including exit() in a handler.
            ob_start(static function (string $output): string {
                return '';
            }, 1, 0);
        }

        $route = $this->resolve();

        if ($route === null) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }

        $handlers = $route['handlers'];

        if (isset($handlers['GET']) && !isset($handlers['HEAD'])) {
            $handlers['HEAD'] = $handlers['GET'];
        }

        if (!isset($handlers[$method])) {
            $allowed = array_keys($handlers);
            sort($allowed);
            header('Allow: ' . implode(', ', $allowed));
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        $this->items = $route['items'];
        $handler = $handlers[$method];
        $app = $this;

        require $handler;
    }

    /**
     * Read a configuration value without exposing the configuration property.
     *
     * @param string $key Configuration key.
     * @return mixed Configured value, or null if the key does not exist.
     */
    public function config(string $key): mixed
    {
        return $this->config[$key] ?? null;
    }

    /**
     * Return dynamic route values in traversal order, with zero-based indices.
     *
     * The array is returned by value; modifying it does not change router state.
     * Use items()[0] ?? null when an index might not exist.
     *
     * @return list<string> Captured values, or an empty array when none exist.
     */
    public function items(): array
    {
        return $this->items;
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
     * Send a complete JSON response and terminate execution.
     *
     * Call before any output. Encode before changing response headers so a
     * serialization failure does not send a partial JSON response.
     *
     * @param mixed $data JSON-serializable data.
     * @param int $status Final HTTP status supporting a response body.
     * @return void Does not return on success.
     * @throws InvalidArgumentException If the status cannot carry JSON.
     * @throws JsonException If data cannot be encoded as JSON.
     * @throws RuntimeException If response headers were already sent.
     */
    public function renderJson(mixed $data, int $status = 200): void
    {
        if ($status < 200 || $status > 599 || in_array($status, [204, 205, 304], true)) {
            throw new InvalidArgumentException('Invalid status for a JSON response.');
        }

        $json = json_encode($data, JSON_THROW_ON_ERROR);

        if (headers_sent()) {
            throw new RuntimeException('Response headers have already been sent.');
        }

        header('Content-Type: application/json');
        http_response_code($status);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
            echo $json;
        }

        exit;
    }

    /**
     * Send a redirect and terminate execution without a response body.
     *
     * Call before output. Pass an application-controlled URI reference;
     * this method does not restrict destinations to the current site.
     * Use 303 after a POST when the next request should use GET.
     *
     * @param string $url Redirect destination without whitespace or controls.
     * @param int $status One of 301, 302, 303, 307, or 308.
     * @return void Does not return on success.
     * @throws InvalidArgumentException If the status or destination is invalid.
     * @throws RuntimeException If response headers were already sent.
     */
    public function redirect(string $url, int $status = 302): void
    {
        if (!in_array($status, [301, 302, 303, 307, 308], true)) {
            throw new InvalidArgumentException('Invalid redirect status.');
        }

        if ($url === '' || preg_match('/[\x00-\x20\x7f]/', $url)) {
            throw new InvalidArgumentException('Invalid redirect destination.');
        }

        if (headers_sent()) {
            throw new RuntimeException('Response headers have already been sent.');
        }

        header('Location: ' . $url, true, $status);
        exit;
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
     * Route selection is independent of the requested HTTP method.
     * Captures are published by run() only when that method is supported.
     * Resolved directories and files, including symlink targets, must remain
     * within the handler directory. URL mount prefixes are not removed.
     * If REQUEST_URI is unavailable, use the root path.
     *
     * @return array{handlers: array<string, string>, items: list<string>}|null
     *     Method handlers and captures, or null if the route does not exist.
     * @throws RuntimeException If the configured handler directory is missing
     *     or does not resolve to a directory.
     */
    private function resolve(): ?array
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
            // Any static method variant reserves the route before directories.
            if ($index === $last) {
                $handlers = $this->methodHandlers($directory, $segment, $prefix);

                if ($handlers !== []) {
                    return ['handlers' => $handlers, 'items' => $items];
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

        $handlers = $this->methodHandlers($directory, 'index', $prefix);

        return $handlers === []
            ? null
            : ['handlers' => $handlers, 'items' => $items];
    }

    /**
     * Collect method-specific files for a logical route name.
     *
     * The unprefixed file handles GET. Other methods use __METHOD__name.php;
     * supported prefixes are POST, PUT, PATCH, DELETE, HEAD, and OPTIONS.
     * __GET__ is not an alias; unrecognized method prefixes are ignored.
     * Only readable files resolving inside the handler root are eligible.
     *
     * @param string $directory Directory to inspect.
     * @param string $name Logical filename without its extension.
     * @param string $prefix Canonical handler root with a trailing separator.
     * @return array<string, string> HTTP methods mapped to absolute file paths.
     * @throws RuntimeException If the directory cannot be read.
     */
    private function methodHandlers(string $directory, string $name, string $prefix): array
    {
        $entries = scandir($directory);

        if ($entries === false) {
            throw new RuntimeException('Handler directory cannot be read.');
        }

        $handlers = [];
        $pattern = '/\A__(POST|PUT|PATCH|DELETE|HEAD|OPTIONS)__' . preg_quote($name, '/') . '\.php\z/';

        foreach ($entries as $entry) {
            if ($entry === $name . '.php') {
                $method = 'GET';
            } elseif (preg_match($pattern, $entry, $matches)) {
                $method = $matches[1];
            } else {
                continue;
            }

            $file = realpath($directory . '/' . $entry);

            if (
                $file !== false
                && str_starts_with($file, $prefix)
                && is_file($file)
                && is_readable($file)
            ) {
                $handlers[$method] = $file;
            }
        }

        return $handlers;
    }
}
