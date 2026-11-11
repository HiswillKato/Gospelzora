<?php
declare(strict_types=1);

namespace App\core;

final class Middleware
{
    /** @var callable[] List of middleware to execute, in order. Each receives ($request, $handler) and must call $handler(). */
    private array $pipeline = [];

    public function use(callable $middleware): self
    {
        if (!is_callable($middleware)) {
            throw new \InvalidArgumentException('Middleware must be a callable.');
        }
        $this->pipeline[] = $middleware;
        return $this;
    }

    /** Run the middleware pipeline, passing the request and the original handler.
     *  Stops if any middleware calls $handler() with a response and returns,
     *  otherwise calls the original handler at the end.
     */
    public function run(Request $request, callable $handler): void
    {
        $count = \count($this->pipeline);
        $i = 0;

        $runNext = function () use (&$i, &$count, $request, $handler): void {
            if ($i < $count) {
                $middleware = $this->pipeline[$i++];
                // Each middleware receives ($request, $next) where $next is the next step
                $middleware($request, function () use (&$runNext): void {
                    $runNext();
                });
            } else {
                // No more middleware, execute the original handler
                $handler();
            }
        };

        $runNext();
    }
}