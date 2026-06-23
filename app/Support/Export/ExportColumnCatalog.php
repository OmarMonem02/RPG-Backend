<?php

namespace App\Support\Export;

use App\Support\ImportExport\ImportExportDefinitions;

class ExportColumnCatalog
{
    public function __construct(private readonly ImportExportDefinitions $definitions) {}

    /** @return array<string, mixed> */
    public function all(): array
    {
        return [
            'import_export' => $this->importExportContexts(),
            'sales' => $this->salesContext(),
            'unstored_sale_items' => $this->unstoredSaleItemsContext(),
            'unstored_ticket_items' => $this->unstoredTicketItemsContext(),
            'stocktake' => $this->stocktakeContext(),
            'history' => $this->historyContext(),
        ];
    }

    /**
     * @return list<string>
     */
    public function keys(string $context, ?string $entity = null, bool $includeExportOnly = true): array
    {
        return collect($this->columns($context, $entity, $includeExportOnly))
            ->pluck('key')
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function columns(string $context, ?string $entity = null, bool $includeExportOnly = true): array
    {
        return match ($context) {
            'import_export' => $this->importExportColumns($entity ?? '', $includeExportOnly),
            'sales' => $this->salesContext()['columns'],
            'unstored_sale_items' => $this->unstoredSaleItemsContext()['columns'],
            'unstored_ticket_items' => $this->unstoredTicketItemsContext()['columns'],
            'stocktake' => $this->stocktakeContext()['columns'],
            'history' => $this->historyContext()['columns'],
            default => abort(404, "Export context '{$context}' is not supported."),
        };
    }

    /** @return array<string, array{label: string, columns: list<array<string, mixed>>}> */
    private function importExportContexts(): array
    {
        return collect($this->definitions->all())->mapWithKeys(function (array $definition, string $slug) {
            return [
                $slug => [
                    'label' => $definition['label'],
                    'columns' => $this->importExportColumns($slug, true),
                ],
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function importExportColumns(string $entity, bool $includeExportOnly): array
    {
        $definition = $this->definitions->get($entity);
        $columns = $definition['columns'];

        if ($includeExportOnly) {
            array_unshift($columns, [
                'key' => 'id',
                'label' => 'ID',
                'required' => false,
                'type' => 'integer',
                'description' => 'Record identifier (export only).',
                'accepted_values' => [],
                'reference' => null,
                'export_only' => true,
            ]);
        }

        return $columns;
    }

    /** @return array{label: string, columns: list<array<string, mixed>>} */
    private function salesContext(): array
    {
        return [
            'label' => 'Sales',
            'columns' => [
                $this->meta('sale_id', 'Sale ID'),
                $this->meta('created_at', 'Created at'),
                $this->meta('customer_name', 'Customer name'),
                $this->meta('customer_phone', 'Customer phone'),
                $this->meta('channel', 'Channel'),
                $this->meta('delivery_status', 'Delivery status'),
                $this->meta('payment_method', 'Payment method'),
                $this->meta('seller', 'Seller'),
                $this->meta('cashier', 'Cashier'),
                $this->meta('discount', 'Discount'),
                $this->meta('shipping_fee', 'Shipping fee'),
                $this->meta('total', 'Total'),
                $this->meta('maintenance_sale', 'Maintenance sale'),
                $this->meta('line_item_count', 'Line item count'),
                $this->meta('line_items_summary', 'Line items summary'),
            ],
        ];
    }

    /** @return array{label: string, columns: list<array<string, mixed>>} */
    private function unstoredSaleItemsContext(): array
    {
        return [
            'label' => 'Unstored sale items',
            'columns' => [
                $this->meta('sale_id', 'Sale ID'),
                $this->meta('created_at', 'Sale created at'),
                $this->meta('customer_name', 'Customer name'),
                $this->meta('customer_phone', 'Customer phone'),
                $this->meta('seller', 'Seller'),
                $this->meta('payment_method', 'Payment method'),
                $this->meta('sale_total', 'Sale total'),
                $this->meta('item_name', 'Unstored item name'),
                $this->meta('description', 'Description'),
                $this->meta('item_type', 'Type'),
                $this->meta('qty', 'Qty'),
                $this->meta('cost_price', 'Cost (EGP)'),
                $this->meta('sale_price', 'Sale price (EGP)'),
            ],
        ];
    }

    /** @return array{label: string, columns: list<array<string, mixed>>} */
    private function unstoredTicketItemsContext(): array
    {
        return [
            'label' => 'Unstored ticket items',
            'columns' => [
                $this->meta('ticket_id', 'Ticket ID'),
                $this->meta('created_at', 'Ticket opened at'),
                $this->meta('status', 'Ticket status'),
                $this->meta('customer_name', 'Customer name'),
                $this->meta('customer_phone', 'Customer phone'),
                $this->meta('task_name', 'Task name'),
                $this->meta('ticket_total', 'Ticket total'),
                $this->meta('item_name', 'Unstored item name'),
                $this->meta('description', 'Description'),
                $this->meta('item_type', 'Type'),
                $this->meta('qty', 'Qty'),
                $this->meta('cost_price', 'Cost (EGP)'),
                $this->meta('sale_price', 'Sale price (EGP)'),
            ],
        ];
    }

    /** @return array{label: string, columns: list<array<string, mixed>>} */
    private function stocktakeContext(): array
    {
        return [
            'label' => 'Inventory Count Discrepancies',
            'columns' => [
                $this->meta('type', 'Type'),
                $this->meta('name', 'Name'),
                $this->meta('sku', 'SKU'),
                $this->meta('part_number', 'Part Number'),
                $this->meta('system_quantity', 'System Qty'),
                $this->meta('counted_quantity', 'Counted Qty'),
                $this->meta('variance', 'Variance'),
                $this->meta('status', 'Status'),
            ],
        ];
    }

    /** @return array{label: string, columns: list<array<string, mixed>>} */
    private function historyContext(): array
    {
        return [
            'label' => 'System History',
            'columns' => [
                $this->meta('id', 'ID'),
                $this->meta('time', 'Time'),
                $this->meta('action', 'Action'),
                $this->meta('entity', 'Entity'),
                $this->meta('record_id', 'Record ID'),
                $this->meta('user', 'User'),
                $this->meta('email', 'Email'),
                $this->meta('ip', 'IP'),
                $this->meta('summary', 'Summary'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function meta(string $key, string $label): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'required' => false,
            'type' => 'text',
            'description' => '',
            'accepted_values' => [],
            'reference' => null,
        ];
    }
}
