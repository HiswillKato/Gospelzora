<?php
declare(strict_types=1);

namespace App\Http\Controllers;

/**
 * Transitional controller for the existing homepage.
 *
 * The page remains responsible for its current data queries and template while
 * the view/controller split is introduced incrementally. This controller gives
 * the Router a stable entry point without changing the live rewrite rules.
 */
final class HomeController
{
    public function index(): void
    {
        require BASE_PATH . '/pages/index.php';
    }
}
