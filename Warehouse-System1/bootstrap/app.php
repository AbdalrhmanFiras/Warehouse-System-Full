<?php

use Illuminate\Foundation\Application;
use App\Http\Middleware\SuperAdminPermission;
use App\Http\Middleware\AdminCheckOrSuperAdmin;
use App\Http\Middleware\AdminOrderOrSuperAdmin;
use Spatie\Permission\Middleware\RoleMiddleware;
use App\Http\Middleware\AdminManagerOrSuperAdmin;
use App\Http\Middleware\AdminSupportORSuperAdmin;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use App\Http\Middleware\EnsureEmployeeIsDeliveryCompanyEmployee;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'employee.delivery' => EnsureEmployeeIsDeliveryCompanyEmployee::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'AdminOrderOrSuperAdmin' => AdminOrderOrSuperAdmin::class,
            'AdminSupportORSuperAdmin' => AdminSupportORSuperAdmin::class,
            'AdminCheckOrSuperAdmin' => AdminCheckOrSuperAdmin::class,
            'AdminManagerOrSuperAdmin' => AdminManagerOrSuperAdmin::class,

        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
