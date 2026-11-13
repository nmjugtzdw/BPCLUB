<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\CategoryController;
use App\Controllers\CommentController;
use App\Controllers\HealthController;
use App\Controllers\HomeController;
use App\Controllers\PublishController;
use App\Controllers\ReportController;
use App\Controllers\SearchController;
use App\Controllers\ThreadController;
use App\Controllers\TitleController;
use App\Controllers\UserController;

/**
 * Routes are declared as [method, path, handler].
 *
 * Scope note: everything here is either read-only or an authenticated write
 * with server-side authorisation. No route accepts a caller-supplied role or
 * trusts a hidden form field for identity.
 */
return [
    ['GET', '/', [HomeController::class, 'index']],
    ['GET', '/category/{slug}', [CategoryController::class, 'index']],
    ['GET', '/titles', [TitleController::class, 'index']],
    ['GET', '/title/{id}', [TitleController::class, 'show']],
    ['GET', '/thread/{id}', [ThreadController::class, 'show']],
    ['GET', '/search', [SearchController::class, 'index']],
    ['GET', '/health', [HealthController::class, 'index']],

    ['GET', '/user/{id}', [UserController::class, 'show']],

    ['GET', '/login', [AuthController::class, 'showLogin']],
    ['POST', '/login', [AuthController::class, 'login']],
    ['GET', '/register', [AuthController::class, 'showRegister']],
    ['POST', '/register', [AuthController::class, 'register']],
    ['POST', '/logout', [AuthController::class, 'logout']],

    ['GET', '/publish', [PublishController::class, 'show']],
    ['POST', '/publish', [PublishController::class, 'create']],
    ['POST', '/thread/{id}/comment', [CommentController::class, 'create']],
    ['POST', '/report', [ReportController::class, 'create']],

    ['GET', '/admin', [AdminController::class, 'index']],
    ['POST', '/admin/thread/{id}', [AdminController::class, 'threadStatus']],
    ['POST', '/admin/comment/{id}', [AdminController::class, 'commentStatus']],
    ['POST', '/admin/report/{id}', [AdminController::class, 'report']],
    ['POST', '/admin/user/{id}', [AdminController::class, 'userStatus']],
];
