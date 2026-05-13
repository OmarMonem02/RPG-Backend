<?php

namespace App\Support;

use App\Models\User;

class UserPermissions
{
    public const ROLE_PRESETS = [
        User::ROLE_ADMIN => 'Administrator',
        User::ROLE_STAFF => 'Staff',
        User::ROLE_TECHNICIAN => 'Technician',
    ];

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

    public const GROUPS = [
        'overview' => 'Overview',
        'sales' => 'Sales',
        'maintenance' => 'Maintenance',
        'inventory' => 'Inventory',
        'master-data' => 'Master Data',
        'data' => 'Data',
        'admin' => 'Admin',
        'reporting' => 'Reporting',
    ];

    public const PAGE_DEFINITIONS = [
        'dashboard' => [
            'label' => 'Dashboard',
            'group' => 'overview',
            'description' => 'View the operational home screen and quick links.',
            'actions' => ['read'],
        ],
        'sales' => [
            'label' => 'Sales',
            'group' => 'sales',
            'description' => 'Create, manage, delete, and export sales records.',
            'actions' => ['create', 'read', 'update', 'delete', 'export'],
        ],
        'maintenance' => [
            'label' => 'Maintenance',
            'group' => 'maintenance',
            'description' => 'Operate tickets, service tasks, and workshop activity.',
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'inventory' => [
            'label' => 'Inventory',
            'group' => 'inventory',
            'description' => 'Access inventory workspace navigation and summaries.',
            'actions' => ['read'],
        ],
        'brands' => [
            'label' => 'Brands',
            'group' => 'master-data',
            'description' => 'Maintain brand records used by products and bikes.',
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'products' => [
            'label' => 'Products',
            'group' => 'inventory',
            'description' => 'Manage products, pricing, stock, and catalog data.',
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'bikes' => [
            'label' => 'Bikes',
            'group' => 'inventory',
            'description' => 'Manage bikes for sale and bike inventory records.',
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'spare-parts' => [
            'label' => 'Spare Parts',
            'group' => 'inventory',
            'description' => 'Manage spare parts, compatibility links, and stock.',
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'maintenance-services' => [
            'label' => 'Maintenance Services',
            'group' => 'maintenance',
            'description' => 'Maintain service catalog items and pricing.',
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'users' => [
            'label' => 'Users & Access',
            'group' => 'admin',
            'description' => 'Create users and manage account-level permissions.',
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'import-export' => [
            'label' => 'Import / Export',
            'group' => 'data',
            'description' => 'Access templates, exports, and spreadsheet imports.',
            'actions' => ['read', 'export', 'import'],
        ],
        'payment-methods' => [
            'label' => 'Payment Methods',
            'group' => 'admin',
            'description' => 'Maintain payment methods and payment settings.',
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'product-categories' => [
            'label' => 'Product Categories',
            'group' => 'master-data',
            'description' => 'Maintain product classification data.',
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'spare-part-categories' => [
            'label' => 'Spare Part Categories',
            'group' => 'master-data',
            'description' => 'Maintain spare part classification data.',
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'bike-blueprints' => [
            'label' => 'Bike Blueprints',
            'group' => 'master-data',
            'description' => 'Maintain bike blueprint data and spare part links.',
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'sellers' => [
            'label' => 'Sellers',
            'group' => 'admin',
            'description' => 'Manage seller records and commission rates.',
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'reporting' => [
            'label' => 'Reporting & Expenses',
            'group' => 'reporting',
            'description' => 'View reports and manage the expense ledger.',
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
    ];

    public static function pages(): array
    {
        return self::PAGES;
    }

    public static function actions(): array
    {
        return self::ACTIONS;
    }

    public static function groups(): array
    {
        return array_map(
            fn (string $key, string $label): array => ['key' => $key, 'label' => $label],
            array_keys(self::GROUPS),
            self::GROUPS
        );
    }

    public static function pageDefinitions(): array
    {
        return array_map(
            fn (string $page): array => [
                'key' => $page,
                ...self::PAGE_DEFINITIONS[$page],
            ],
            self::PAGES
        );
    }

    public static function allowedActionsForPage(string $page): array
    {
        return self::PAGE_DEFINITIONS[$page]['actions'] ?? [];
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

            $allowedActions = self::allowedActionsForPage($page);

            $normalized[$page] = array_values(array_filter(
                $allowedActions,
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

    public static function roleDefaultMatrices(): array
    {
        $matrices = [];

        foreach (array_keys(self::ROLE_PRESETS) as $role) {
            $matrices[$role] = self::defaultMatrixForRole($role);
        }

        return $matrices;
    }

    public static function metadata(): array
    {
        return [
            'actions' => self::ACTIONS,
            'groups' => self::groups(),
            'pages' => self::pageDefinitions(),
            'role_presets' => array_map(
                fn (string $role, string $label): array => [
                    'key' => $role,
                    'label' => $label,
                    'permissions' => self::defaultMatrixForRole($role),
                ],
                array_keys(self::ROLE_PRESETS),
                self::ROLE_PRESETS
            ),
        ];
    }

    public static function hasPermission(User $user, string $page, string $action): bool
    {
        return in_array($action, self::effectiveMatrixForUser($user)[$page] ?? [], true);
    }

    private static function adminDefaults(array $matrix): array
    {
        $matrix['dashboard'] = ['read'];
        $matrix['sales'] = ['create', 'read', 'update', 'delete', 'export'];
        $matrix['maintenance'] = ['create', 'read', 'update', 'delete'];
        $matrix['inventory'] = ['read'];
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
        $matrix['reporting'] = ['create', 'read', 'update', 'delete'];

        return $matrix;
    }

    private static function staffDefaults(array $matrix): array
    {
        $matrix['sales'] = ['create', 'read', 'update', 'delete'];
        $matrix['maintenance'] = ['create', 'read', 'update', 'delete'];
        $matrix['inventory'] = ['read'];

        return $matrix;
    }

    private static function technicianDefaults(array $matrix): array
    {
        $matrix['maintenance'] = ['create', 'read', 'update', 'delete'];

        return $matrix;
    }
}
