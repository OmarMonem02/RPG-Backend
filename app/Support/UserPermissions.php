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
        'display',
        'update',
        'delete',
        'export',
        'import',
    ];

    public const NON_OPERATIONAL_ACTIONS = [
        'read',
        'display',
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
        'maintenance-parts',
        'maintenance-services',
        'users',
        'import-export',
        'payment-methods',
        'product-categories',
        'spare-part-categories',
        'maintenance-part-categories',
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
            'description' => 'Open the operational home screen and quick links.',
            'actions' => ['read', 'display'],
        ],
        'sales' => [
            'label' => 'Sales',
            'group' => 'sales',
            'description' => 'Create, manage, delete, and export sales records.',
            'actions' => ['create', 'read', 'display', 'update', 'delete', 'export'],
        ],
        'maintenance' => [
            'label' => 'Maintenance',
            'group' => 'maintenance',
            'description' => 'Operate tickets, service tasks, and workshop activity.',
            'actions' => ['create', 'read', 'display', 'update', 'delete'],
        ],
        'inventory' => [
            'label' => 'Inventory',
            'group' => 'inventory',
            'description' => 'Access inventory workspace navigation and summaries.',
            'actions' => ['read', 'display'],
        ],
        'brands' => [
            'label' => 'Brands',
            'group' => 'master-data',
            'description' => 'Maintain brand records used by products and bikes.',
            'actions' => ['create', 'read', 'display', 'update', 'delete'],
        ],
        'products' => [
            'label' => 'Products',
            'group' => 'inventory',
            'description' => 'Manage products, pricing, stock, and catalog data.',
            'actions' => ['create', 'read', 'display', 'update', 'delete'],
        ],
        'bikes' => [
            'label' => 'Bikes',
            'group' => 'inventory',
            'description' => 'Manage bikes for sale and bike inventory records.',
            'actions' => ['create', 'read', 'display', 'update', 'delete'],
        ],
        'spare-parts' => [
            'label' => 'Spare Parts',
            'group' => 'inventory',
            'description' => 'Manage spare parts, compatibility links, and stock.',
            'actions' => ['create', 'read', 'display', 'update', 'delete'],
        ],
        'maintenance-parts' => [
            'label' => 'Maintenance Parts',
            'group' => 'inventory',
            'description' => 'Manage maintenance parts, compatibility links, and stock.',
            'actions' => ['create', 'read', 'display', 'update', 'delete'],
        ],
        'maintenance-services' => [
            'label' => 'Maintenance Services',
            'group' => 'maintenance',
            'description' => 'Maintain service catalog items and pricing.',
            'actions' => ['create', 'read', 'display', 'update', 'delete'],
        ],
        'users' => [
            'label' => 'Users & Access',
            'group' => 'admin',
            'description' => 'Create users and manage account-level permissions.',
            'actions' => ['create', 'read', 'display', 'update', 'delete'],
        ],
        'import-export' => [
            'label' => 'Import / Export',
            'group' => 'data',
            'description' => 'Access templates, exports, and spreadsheet imports.',
            'actions' => ['read', 'display', 'export', 'import'],
        ],
        'payment-methods' => [
            'label' => 'Payment Methods',
            'group' => 'admin',
            'description' => 'Maintain payment methods and payment settings.',
            'actions' => ['create', 'read', 'display', 'update', 'delete'],
        ],
        'product-categories' => [
            'label' => 'Product Categories',
            'group' => 'master-data',
            'description' => 'Maintain product classification data.',
            'actions' => ['create', 'read', 'display', 'update', 'delete'],
        ],
        'spare-part-categories' => [
            'label' => 'Spare Part Categories',
            'group' => 'master-data',
            'description' => 'Maintain spare part classification data.',
            'actions' => ['create', 'read', 'display', 'update', 'delete'],
        ],
        'maintenance-part-categories' => [
            'label' => 'Maintenance Part Categories',
            'group' => 'master-data',
            'description' => 'Maintain maintenance part classification data.',
            'actions' => ['create', 'read', 'display', 'update', 'delete'],
        ],
        'bike-blueprints' => [
            'label' => 'Bike Blueprints',
            'group' => 'master-data',
            'description' => 'Maintain bike blueprint data and spare part links.',
            'actions' => ['create', 'read', 'display', 'update', 'delete'],
        ],
        'sellers' => [
            'label' => 'Sellers',
            'group' => 'admin',
            'description' => 'Manage seller records and commission rates.',
            'actions' => ['create', 'read', 'display', 'update', 'delete'],
        ],
        'reporting' => [
            'label' => 'Reporting & Expenses',
            'group' => 'reporting',
            'description' => 'View reports and manage the expense ledger.',
            'actions' => ['create', 'read', 'display', 'update', 'delete'],
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

    private static function grant(array &$matrix, string $page, array $actions): void
    {
        $allowed = self::allowedActionsForPage($page);
        $matrix[$page] = array_values(array_filter(
            $allowed,
            fn (string $action): bool => in_array($action, $actions, true)
        ));
    }

    private static function adminDefaults(array $matrix): array
    {
        self::grant($matrix, 'dashboard', ['read', 'display']);
        self::grant($matrix, 'sales', ['create', 'read', 'display', 'update', 'delete', 'export']);
        self::grant($matrix, 'maintenance', ['create', 'read', 'display', 'update', 'delete']);
        self::grant($matrix, 'inventory', ['read', 'display']);
        self::grant($matrix, 'brands', ['create', 'read', 'display', 'update', 'delete']);
        self::grant($matrix, 'products', ['create', 'read', 'display', 'update', 'delete']);
        self::grant($matrix, 'bikes', ['create', 'read', 'display', 'update', 'delete']);
        self::grant($matrix, 'spare-parts', ['create', 'read', 'display', 'update', 'delete']);
        self::grant($matrix, 'maintenance-parts', ['create', 'read', 'display', 'update', 'delete']);
        self::grant($matrix, 'maintenance-services', ['create', 'read', 'display', 'update', 'delete']);
        self::grant($matrix, 'users', ['create', 'read', 'display', 'update', 'delete']);
        self::grant($matrix, 'import-export', ['read', 'display', 'export', 'import']);
        self::grant($matrix, 'payment-methods', ['create', 'read', 'display', 'update', 'delete']);
        self::grant($matrix, 'product-categories', ['create', 'read', 'display', 'update', 'delete']);
        self::grant($matrix, 'spare-part-categories', ['create', 'read', 'display', 'update', 'delete']);
        self::grant($matrix, 'maintenance-part-categories', ['create', 'read', 'display', 'update', 'delete']);
        self::grant($matrix, 'bike-blueprints', ['create', 'read', 'display', 'update', 'delete']);
        self::grant($matrix, 'sellers', ['create', 'read', 'display', 'update', 'delete']);
        self::grant($matrix, 'reporting', ['create', 'read', 'display', 'update', 'delete']);

        return $matrix;
    }

    private static function staffDefaults(array $matrix): array
    {
        self::grant($matrix, 'sales', ['create', 'read', 'display', 'update', 'delete']);
        self::grant($matrix, 'maintenance', ['create', 'read', 'display', 'update', 'delete']);
        self::grant($matrix, 'inventory', ['read', 'display']);

        return $matrix;
    }

    private static function technicianDefaults(array $matrix): array
    {
        self::grant($matrix, 'maintenance', ['create', 'read', 'display', 'update', 'delete']);

        return $matrix;
    }
}
