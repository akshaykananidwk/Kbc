<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\ValidationException;
use App\Services\SettingsService;
use Throwable;

final class Application
{
    private static ?Application $instance = null;

    private string $root;
    private string $basePath = '';
    private Router $router;
    private bool $installed = false;
    private bool $testing = false;
    /** @var array<string,string> */
    private array $middlewareMap = [];

    private function __construct(string $root)
    {
        $this->root = rtrim($root, '/');
        $this->router = new Router();
    }

    public static function boot(string $root): Application
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $app = new self($root);
        self::$instance = $app;

        Env::load($app->root . '/.env');
        Config::load($app->root . '/config');
        View::setViewPath($app->root . '/resources/views');
        Lang::setPath($app->root . '/resources/lang');
        Logger::setPath($app->storagePath('logs'));

        $app->testing = (bool) Env::get('APP_TESTING', false);
        $app->basePath = $app->detectBasePath();
        $app->installed = is_file($app->root . '/storage/installed.lock');

        $app->configureErrorHandling();
        $app->registerMiddleware();
        $app->bootLocale();

        return $app;
    }

    public static function instance(): Application
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Application has not been booted.');
        }
        return self::$instance;
    }

    public static function isTesting(): bool
    {
        return self::$instance !== null && self::$instance->testing;
    }

    /**
     * Pick the interface language: the admin setting when the database is
     * reachable, otherwise whatever .env says.
     */
    private function bootLocale(): void
    {
        $locale = (string) (Env::get('APP_LOCALE', 'en') ?? 'en');

        if ($this->installed) {
            try {
                $stored = SettingsService::string('interface_language', '');
                if ($stored !== '') {
                    $locale = $stored;
                }
            } catch (\Throwable) {
                // Database not ready - the .env value stands.
            }
        }

        Lang::use(in_array($locale, Lang::available(), true) ? $locale : 'en');
    }

    public function isDebug(): bool
    {
        return (bool) Env::get('APP_DEBUG', false);
    }

    public function isInstalled(): bool
    {
        return $this->installed;
    }

    public function markInstalled(): void
    {
        $this->installed = true;
    }

    /**
     * Work out the sub-directory the app lives in so it also runs from
     * http://host/quiz/ without any configuration.
     */
    private function detectBasePath(): string
    {
        $configured = (string) (Env::get('APP_BASE_PATH', '') ?? '');
        if ($configured !== '') {
            return '/' . trim($configured, '/');
        }
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($dir === '/' || $dir === '.') {
            return '';
        }
        return $dir;
    }

    public static function basePath(): string
    {
        return self::$instance?->basePath ?? '';
    }

    public static function url(string $path = '/'): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        $base = self::basePath();
        $path = '/' . ltrim($path, '/');
        return ($base === '' ? '' : $base) . ($path === '/' ? '/' : rtrim($path, '/'));
    }

    public static function asset(string $path): string
    {
        return self::url('/public/' . ltrim($path, '/'));
    }

    public static function uploadUrl(?string $relative): string
    {
        if ($relative === null || trim($relative) === '') {
            return '';
        }
        if (str_starts_with($relative, 'http://') || str_starts_with($relative, 'https://')) {
            return $relative;
        }
        return self::url('/public/' . ltrim($relative, '/'));
    }

    public function rootPath(string $path = ''): string
    {
        return $this->root . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }

    public function storagePath(string $path = ''): string
    {
        return $this->root . '/storage' . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }

    public static function publicPath(string $path = ''): string
    {
        return self::instance()->root . '/public' . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }

    public static function uploadPath(string $path = ''): string
    {
        return self::publicPath('uploads') . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }

    public function router(): Router
    {
        return $this->router;
    }

    private function registerMiddleware(): void
    {
        $this->middlewareMap = [
            'auth'      => \App\Middleware\AuthMiddleware::class,
            'guest'     => \App\Middleware\GuestMiddleware::class,
            'csrf'      => \App\Middleware\CsrfMiddleware::class,
            'installed' => \App\Middleware\InstalledMiddleware::class,
            'admin'     => \App\Middleware\RoleMiddleware::class . ':admin',
            'operator'  => \App\Middleware\RoleMiddleware::class . ':operator',
            'noindex'   => \App\Middleware\NoIndexMiddleware::class,
            'throttle'  => \App\Middleware\ThrottleMiddleware::class,
        ];
    }

    private function configureErrorHandling(): void
    {
        $debug = $this->isDebug();
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('display_startup_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }
            // Deprecations and notices are logged, never fatal: a live show
            // must not go down because a future PHP version renamed something.
            if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED, E_NOTICE, E_USER_NOTICE], true)) {
                Logger::warning($message, ['file' => $file, 'line' => $line]);
                return true;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                Logger::error('Fatal error: ' . $error['message'], [
                    'file' => $error['file'],
                    'line' => $error['line'],
                ]);
            }
        });
    }

    public function run(): void
    {
        $request = Request::capture();

        try {
            Session::start($request->isSecure());
            $response = $this->handle($request);
        } catch (Throwable $e) {
            $response = $this->renderException($e, $request);
        }

        $response = $this->applySecurityHeaders($response);
        $response->send();
    }

    public function handle(Request $request): Response
    {
        $route = $this->router->resolve($request);
        $request->setParams($route['params']);

        $terminators = [];
        foreach ($route['middleware'] as $alias) {
            $definition = $this->middlewareMap[$alias] ?? $alias;
            [$class, $argument] = array_pad(explode(':', $definition, 2), 2, null);
            if (!class_exists($class)) {
                continue;
            }
            /** @var \App\Middleware\Middleware $middleware */
            $middleware = new $class($argument);
            $result = $middleware->handle($request);
            if ($result instanceof Response) {
                return $result;
            }
            $terminators[] = $middleware;
        }

        $response = $this->callHandler($route['handler'], $request);

        foreach ($terminators as $middleware) {
            $response = $middleware->terminate($request, $response);
        }

        return $response;
    }

    private function callHandler(mixed $handler, Request $request): Response
    {
        if (is_callable($handler)) {
            $result = $handler($request);
        } else {
            [$class, $method] = is_array($handler) ? $handler : explode('@', (string) $handler, 2);
            if (!class_exists($class)) {
                throw new HttpException(500, 'Controller not found: ' . $class);
            }
            $controller = new $class();
            if (!method_exists($controller, $method)) {
                throw new HttpException(500, 'Action not found: ' . $class . '::' . $method);
            }
            $result = $controller->{$method}($request);
        }

        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result)) {
            return Response::json($result);
        }
        return Response::html((string) $result);
    }

    private function renderException(Throwable $e, Request $request): Response
    {
        $status = 500;
        $errors = [];

        if ($e instanceof HttpException) {
            $status = $e->statusCode();
        } elseif ($e instanceof ValidationException) {
            $status = 422;
            $errors = $e->errors();
        }

        if ($status >= 500) {
            Logger::exception($e);
        }

        $message = $status >= 500 && !$this->isDebug()
            ? HttpException::defaultMessage(500)
            : $e->getMessage();

        if ($request->expectsJson()) {
            return Response::apiError($message, $status, $errors);
        }

        // Validation failures on normal forms bounce back with the messages.
        if ($e instanceof ValidationException) {
            Session::flashErrors($errors);
            Session::flashInput($request->all());
            Session::flash('error', $message);
            $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
            return Response::redirect($referer !== '' ? $referer : $request->path());
        }

        $data = [
            'status'    => $status,
            'message'   => $message,
            'exception' => $this->isDebug() ? $e : null,
        ];

        $template = in_array($status, [403, 404, 405, 419, 429], true) ? 'errors.' . $status : 'errors.500';
        try {
            return View::response($template, $data, $status);
        } catch (Throwable) {
            return Response::html('<h1>' . $status . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES) . '</p>', $status);
        }
    }

    private function applySecurityHeaders(Response $response): Response
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options'        => 'SAMEORIGIN',
            'Referrer-Policy'        => 'strict-origin-when-cross-origin',
            'X-XSS-Protection'       => '0',
            'Permissions-Policy'     => 'geolocation=(), microphone=(), camera=(), payment=()',
        ];

        // Self-hosted assets only - no external origins required.
        $csp = "default-src 'self'; "
            . "script-src 'self' 'unsafe-inline'; "
            . "style-src 'self' 'unsafe-inline'; "
            . "img-src 'self' data: blob:; "
            . "media-src 'self' data: blob:; "
            . "font-src 'self' data:; "
            . "connect-src 'self'; "
            . "frame-ancestors 'self'; "
            . "base-uri 'self'; "
            . "form-action 'self'";
        $headers['Content-Security-Policy'] = $csp;

        foreach ($headers as $name => $value) {
            if (!isset($response->headers()[$name])) {
                $response->withHeader($name, $value);
            }
        }
        return $response;
    }

    /** Convenience accessor used throughout the views. */
    public static function setting(string $key, mixed $default = null): mixed
    {
        return SettingsService::get($key, $default);
    }
}
