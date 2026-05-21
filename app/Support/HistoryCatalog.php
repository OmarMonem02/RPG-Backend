<?php

namespace App\Support;

use App\Models\BikeBlueprint;
use App\Models\BikeForSale;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\CustomerBike;
use App\Models\CustomerSale;
use App\Models\Delivery;
use App\Models\MaintenanceService;
use App\Models\MaintenanceServiceSector;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\SparePart;
use App\Models\SparePartCategory;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\TicketTask;
use App\Models\User;

class HistoryCatalog
{
    /** @var array<string, array{class: class-string, label: string, path?: string}> */
    private const ENTITIES = [
        'ticket' => [
            'class' => Ticket::class,
            'label' => 'Ticket',
            'path' => '/tickets/{id}',
        ],
        'ticket_item' => [
            'class' => TicketItem::class,
            'label' => 'Ticket Item',
        ],
        'ticket_task' => [
            'class' => TicketTask::class,
            'label' => 'Ticket Task',
        ],
        'sale' => [
            'class' => Sale::class,
            'label' => 'Sale',
            'path' => '/inventory/sales/{id}',
        ],
        'sale_item' => [
            'class' => SaleItem::class,
            'label' => 'Sale Item',
        ],
        'customer' => [
            'class' => Customer::class,
            'label' => 'Customer',
            'path' => '/customers/{id}',
        ],
        'customer_bike' => [
            'class' => CustomerBike::class,
            'label' => 'Customer Bike',
        ],
        'customer_sale' => [
            'class' => CustomerSale::class,
            'label' => 'Customer Sale',
        ],
        'delivery' => [
            'class' => Delivery::class,
            'label' => 'Delivery',
        ],
        'product' => [
            'class' => Product::class,
            'label' => 'Product',
            'path' => '/inventory/products',
        ],
        'spare_part' => [
            'class' => SparePart::class,
            'label' => 'Spare Part',
            'path' => '/inventory/spare-parts/edit/{id}',
        ],
        'product_category' => [
            'class' => ProductCategory::class,
            'label' => 'Product Category',
            'path' => '/data/product-categories',
        ],
        'spare_part_category' => [
            'class' => SparePartCategory::class,
            'label' => 'Spare Part Category',
            'path' => '/data/spare-part-categories',
        ],
        'brand' => [
            'class' => Brand::class,
            'label' => 'Brand',
            'path' => '/inventory/brands',
        ],
        'bike_for_sale' => [
            'class' => BikeForSale::class,
            'label' => 'Bike For Sale',
            'path' => '/inventory/bikes',
        ],
        'bike_blueprint' => [
            'class' => BikeBlueprint::class,
            'label' => 'Bike Blueprint',
            'path' => '/data/bike-blueprints/{id}/spare-parts',
        ],
        'maintenance_service' => [
            'class' => MaintenanceService::class,
            'label' => 'Maintenance Service',
            'path' => '/inventory/maintenance-services',
        ],
        'maintenance_service_sector' => [
            'class' => MaintenanceServiceSector::class,
            'label' => 'Service Sector',
            'path' => '/inventory/maintenance-services',
        ],
        'payment_method' => [
            'class' => PaymentMethod::class,
            'label' => 'Payment Method',
            'path' => '/data/payment-methods',
        ],
        'seller' => [
            'class' => Seller::class,
            'label' => 'Seller',
            'path' => '/sellers',
        ],
        'user' => [
            'class' => User::class,
            'label' => 'User',
            'path' => '/users',
        ],
        'setting' => [
            'class' => Setting::class,
            'label' => 'Setting',
        ],
    ];

    public static function entityTypes(): array
    {
        return array_keys(self::ENTITIES);
    }

    public static function filterOptions(): array
    {
        return array_map(
            fn (string $key, array $meta) => [
                'key' => $key,
                'label' => $meta['label'],
            ],
            array_keys(self::ENTITIES),
            self::ENTITIES,
        );
    }

    public static function resolveFromModelType(?string $modelType): ?array
    {
        if (! is_string($modelType) || $modelType === '') {
            return null;
        }

        foreach (self::ENTITIES as $key => $meta) {
            if ($meta['class'] === $modelType) {
                return [
                    'entity_type' => $key,
                    'entity_label' => $meta['label'],
                    'path_template' => $meta['path'] ?? null,
                ];
            }
        }

        $basename = class_basename($modelType);
        $fallbackKey = self::guessKeyFromBasename($basename);

        return [
            'entity_type' => $fallbackKey,
            'entity_label' => self::labelFromBasename($basename),
            'path_template' => null,
        ];
    }

    public static function modelClassForEntityType(string $entityType): ?string
    {
        return self::ENTITIES[$entityType]['class'] ?? null;
    }

    public static function entityPath(?string $entityType, int $modelId): ?string
    {
        if (! $entityType || $modelId <= 0) {
            return null;
        }

        $template = self::ENTITIES[$entityType]['path'] ?? null;
        if (! is_string($template) || $template === '') {
            return null;
        }

        if (! str_contains($template, '{id}')) {
            return $template;
        }

        return str_replace('{id}', (string) $modelId, $template);
    }

    private static function guessKeyFromBasename(string $basename): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $basename) ?? $basename);
    }

    private static function labelFromBasename(string $basename): string
    {
        return trim(preg_replace('/\s+/', ' ', ucwords(str_replace('_', ' ', self::guessKeyFromBasename($basename)))) ?? $basename);
    }
}
