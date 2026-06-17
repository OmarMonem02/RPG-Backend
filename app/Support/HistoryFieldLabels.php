<?php

namespace App\Support;

class HistoryFieldLabels
{
    private const LABELS = [
        'status' => 'Status',
        'name' => 'Name',
        'phone' => 'Phone',
        'email' => 'Email',
        'address' => 'Address',
        'notes' => 'Notes',
        'customer_id' => 'Customer',
        'ticket_id' => 'Ticket',
        'sale_id' => 'Sale',
        'product_id' => 'Product',
        'spare_part_id' => 'Spare part',
        'brand_id' => 'Brand',
        'seller_id' => 'Seller',
        'payment_method_id' => 'Payment method',
        'total' => 'Total',
        'subtotal' => 'Subtotal',
        'discount' => 'Discount',
        'quantity' => 'Quantity',
        'price' => 'Price',
        'commission_rate' => 'Commission rate',
        'have_commission' => 'Have commission',
        'products_commission_rate' => 'Products commission rate',
        'spare_parts_commission_rate' => 'Spare parts commission rate',
        'maintenance_parts_commission_rate' => 'Maintenance parts commission rate',
        'bikes_for_sale_commission_rate' => 'Bikes for sale commission rate',
        'maintenance_services_commission_rate' => 'Maintenance services commission rate',
        'role' => 'Role',
        'permissions_override' => 'Permissions',
        'tracking_token' => 'Tracking token',
        'is_active' => 'Active',
        'description' => 'Description',
        'model' => 'Model',
        'sku' => 'SKU',
        'stock' => 'Stock',
    ];

    public static function label(string $key): string
    {
        if (isset(self::LABELS[$key])) {
            return self::LABELS[$key];
        }

        return ucwords(str_replace('_', ' ', $key));
    }
}
