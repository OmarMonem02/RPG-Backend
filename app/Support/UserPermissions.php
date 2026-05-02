<?php

namespace App\Support;

use App\Models\User;

class UserPermissions
{
    public const ACTIONS = [
        'create',
        'read',
        'update',
        'delete',
        'export',
        'import',
    ];

    public const PAGES = [
        'dashboard',
        'sales',
        'maintenance',
        'inventory',
        'brands',
        'products',
        'bikes',
        'spare-parts',
        'maintenance-services',
        'users',
        'import-export',
        'payment-methods',
        'product-categories',
        'spare-part-categories',
        'bike-blueprints',
        'sellers',
        'reporting',
    ];

    public static function pages(): array
    {
        return self::PAGES;
    }

    public static function actions(): array
    {
        return self::ACTIONS;
    }

    public static function emptyMatrix(): array
    {
        return array_fill_keys(self::PAGES, []);
    }

    public static function normalizeMatrix(array $matrix): array
    {
        $normalized = self::emptyMatrix();

        foreach (self::PAGES as $page) {
            $actions = $matrix[$page] ?? [];

            if (! is_array($actions)) {
                continue;
            }

            $normalized[$page] = array_values(array_filter(
                self::ACTIONS,
                fn (string $action): bool => in_array($action, $actions, true)
            ));
        }

        return $normalized;
    }

    public static function defaultMatrixForRole(string $role): array
    {
        $matrix = self::emptyMatrix();

        return match ($role) {
            User::ROLE_ADMIN => self::adminDefaults($matrix),
            User::ROLE_STAFF => self::staffDefaults($matrix),
            User::ROLE_TECHNICIAN => self::technicianDefaults($matrix),
            default => $matrix,
        };
    }

    public static function effectiveMatrixForUser(User $user): array
    {
        return is_array($user->permissions_override)
            ? self::normalizeMatrix($user->permissions_override)
            : self::defaultMatrixForRole((string) $user->role);
    }

    public static function hasPermission(User $user, string $page, string $action): bool
    {
        return in_array($action, self::effectiveMatrixForUser($user)[$page] ?? [], true);
    }

    private static function adminDefaults(array $matrix): array
    {
        $matrix['dashboard'] = ['read'];
        $matrix['sales'] = ['create', 'read', 'update', 'delete'];
        $matrix['maintenance'] = ['create', 'read', 'update', 'delete'];
        $matrix['brands'] = ['create', 'read', 'update', 'delete'];
        $matrix['products'] = ['create', 'read', 'update', 'delete'];
        $matrix['bikes'] = ['create', 'read', 'update', 'delete'];
        $matrix['spare-parts'] = ['create', 'read', 'update', 'delete'];
        $matrix['maintenance-services'] = ['create', 'read', 'update', 'delete'];
        $matrix['users'] = ['create', 'read', 'update', 'delete'];
        $matrix['import-export'] = ['read', 'export', 'import'];
        $matrix['payment-methods'] = ['create', 'read', 'update', 'delete'];
        $matrix['product-categories'] = ['create', 'read', 'update', 'delete'];
        $matrix['spare-part-categories'] = ['create', 'read', 'update', 'delete'];
        $matrix['bike-blueprints'] = ['create', 'read', 'update', 'delete'];
        $matrix['sellers'] = ['create', 'read', 'update', 'delete'];
        $matrix['reporting'] = ['read', 'create', 'update', 'delete'];

        return $matrix;
    }

    private static function staffDefaults(array $matrix): array
    {
        $matrix['sales'] = ['create', 'read', 'update', 'delete'];
        $matrix['maintenance'] = ['create', 'read', 'update', 'delete'];

        return $matrix;
    }

    private static function technicianDefaults(array $matrix): array
    {
        $matrix['maintenance'] = ['create', 'read', 'update', 'delete'];

        return $matrix;
    }
}
