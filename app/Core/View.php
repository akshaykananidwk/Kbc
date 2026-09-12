<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Plain-PHP template renderer with layout inheritance and section blocks.
 * Everything echoed through e() is escaped by default.
 */
final class View
{
    private static string $viewPath = '';
    /** @var array<string,mixed> */
    private static array $shared = [];
    /** @var array<string,string> */
    private array $sections = [];
    private array $sectionStack = [];
    private ?string $layout = null;
    /** @var array<string,mixed> */
    private array $layoutData = [];

    public static function setViewPath(string $path): void
    {
        self::$viewPath = rtrim($path, '/');
    }

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /** @param array<string,mixed> $data */
    public static function render(string $template, array $data = []): string
    {
        return (new self())->renderTemplate($template, $data);
    }

    /** @param array<string,mixed> $data */
    public static function response(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html(self::render($template, $data), $status);
    }

    /** @param array<string,mixed> $data */
    private function renderTemplate(string $template, array $data): string
    {
        $content = $this->capture($template, $data);

        while ($this->layout !== null) {
            $layout = $this->layout;
            $this->layout = null;

            // A template may either define a "content" section explicitly or
            // simply echo its markup; the explicit section always wins.
            if (!isset($this->sections['content']) || trim($this->sections['content']) === '') {
                $this->sections['content'] = $content;
            }

            $content = $this->capture($layout, array_merge($data, $this->layoutData));
        }

        return $content;
    }

    /** @param array<string,mixed> $data */
    private function capture(string $template, array $data): string
    {
        $file = self::$viewPath . '/' . str_replace('.', '/', $template) . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('View not found: ' . $template);
        }

        $vars = array_merge(self::$shared, $data);
        extract($vars, EXTR_SKIP);
        $view = $this;

        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $data */
    public function extend(string $layout, array $data = []): void
    {
        $this->layout = $layout;
        $this->layoutData = $data;
    }

    public function start(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function stop(): void
    {
        $name = array_pop($this->sectionStack);
        if ($name === null) {
            return;
        }
        $this->sections[$name] = (string) ob_get_clean();
    }

    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]) && trim($this->sections[$name]) !== '';
    }

    /** @param array<string,mixed> $data */
    public function include(string $template, array $data = []): string
    {
        $sub = new self();
        $sub->sections = $this->sections;
        return $sub->renderTemplate($template, $data);
    }
}
