<?php

declare(strict_types=1);

namespace Pagenode;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;

// Router Class - handles routes and dispatch
class Router
{
    /**
     * @var array<int, array{path: string, resolver: callable}>
     */
    private static array $routes = [];

    /**
     * @var callable|null
     */
    private static $fallbackResolver = null;

    private static ?Dispatcher $dispatcher = null;

    public static function AddRoute(string $path, callable $resolver): void
    {
        if ($path === '/*') {
            self::$fallbackResolver = $resolver;
            return;
        }

        self::$routes[] = [
            'path' => $path,
            'resolver' => $resolver
        ];
        self::$dispatcher = null;
    }

    public static function Dispatch(string $request): bool
    {
        $dispatcher = self::dispatcher();
        $routeInfo = $dispatcher->dispatch('GET', $request);

        if ($routeInfo[0] === Dispatcher::FOUND) {
            $resolver = $routeInfo[1];
            $params = $routeInfo[2];
            return self::Resolve($resolver, $params);
        }

        return self::ErrorNotFound();
    }

    /**
     * @param array<string, mixed> $regexpMatch
     */
    public static function Resolve(callable $resolver, array $regexpMatch, bool $recurse = true): bool
    {
        if (call_user_func_array($resolver, $regexpMatch) !== false) {
            return true;
        }

        return self::ErrorNotFound($recurse);
    }

    public static function ErrorNotFound(bool $recurse = true): bool
    {
        if ($recurse && self::$fallbackResolver) {
            self::Resolve(self::$fallbackResolver, [], false);
        } else {
            header('HTTP/1.1 404 Not Found');
            echo 'Not Found';
        }
        return false;
    }

    private static function dispatcher(): Dispatcher
    {
        if (self::$dispatcher !== null) {
            return self::$dispatcher;
        }

        self::$dispatcher = simpleDispatcher(function (RouteCollector $collector) {
            foreach (self::$routes as $route) {
                $pattern = self::normalizePattern($route['path']);
                $collector->addRoute('GET', $pattern, $route['resolver']);
            }
        });

        return self::$dispatcher;
    }

    private static function normalizePattern(string $path): string
    {
        $wildcardIndex = 0;

        return preg_replace_callback(
            '/\*/',
            static function () use (&$wildcardIndex) {
                return '{__wildcard' . $wildcardIndex++ . ':.*}';
            },
            $path
        ) ?? $path;
    }
}
