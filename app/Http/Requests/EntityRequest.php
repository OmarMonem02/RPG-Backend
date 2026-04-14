<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $entity = (string) $this->route('entity');
        $id = $this->route('id');
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        if ($entity === 'settings' && !is_numeric($id) && $id) {
            $id = \App\Models\Setting::where('key', $id)->value('id') ?? $id;
        }

        // For updates, make fields optional (nullable)
        // For creates, keep them required
        return match ($entity) {
            'sellers' => [
                'name' => [$isUpdate ? 'nullable' : 'required', 'string'],
                'phone' => [$isUpdate ? 'nullable' : 'required', 'string'],
                'commission_rate' => [$isUpdate ? 'nullable' : 'required', 'numeric']
            ],
            'customers' => [
                'name' => [$isUpdate ? 'nullable' : 'required', 'string'],
                'phone' => [$isUpdate ? 'nullable' : 'required', 'string']
            ],
            'products' => [
                'name' => [$isUpdate ? 'nullable' : 'required', 'string'],
                'sku' => [$isUpdate ? 'nullable' : 'required', 'string', Rule::unique('products', 'sku')->ignore($id)],
                'products_category_id' => [$isUpdate ? 'nullable' : 'required', 'exists:product_categories,id'],
                'brand_id' => [$isUpdate ? 'nullable' : 'required', 'exists:brands,id'],
                'stock_quantity' => ['nullable', 'numeric'],
                'low_stock_alarm' => ['nullable', 'numeric'],
                'currency_pricing' => ['nullable', 'string', Rule::in(['EGP', 'USD'])],
                'cost_price' => ['nullable', 'numeric'],
                'sale_price' => ['nullable', 'numeric'],
                'max_discount_type' => ['nullable', 'string', Rule::in(['fixed', 'percentage'])],
                'max_discount_value' => ['nullable', 'numeric'],
                'universal' => ['nullable', 'boolean'],
                'notes' => ['nullable', 'string'],
            ],
            'spare_parts' => [
                'name' => [$isUpdate ? 'nullable' : 'required', 'string'],
                'sku' => [$isUpdate ? 'nullable' : 'required', 'string', Rule::unique('spare_parts', 'sku')->ignore($id)],
                'spare_parts_category_id' => [$isUpdate ? 'nullable' : 'required', 'exists:spare_part_categories,id'],
                'brand_id' => [$isUpdate ? 'nullable' : 'required', 'exists:brands,id'],
                'part_number' => ['nullable', 'string'],
                'stock_quantity' => ['nullable', 'numeric'],
                'low_stock_alarm' => ['nullable', 'numeric'],
                'currency_pricing' => ['nullable', 'string', Rule::in(['EGP', 'USD'])],
                'cost_price' => ['nullable', 'numeric'],
                'sale_price' => ['nullable', 'numeric'],
                'max_discount_type' => ['nullable', 'string', Rule::in(['fixed', 'percentage'])],
                'max_discount_value' => ['nullable', 'numeric'],
                'universal' => ['nullable', 'boolean'],
                'notes' => ['nullable', 'string'],
                'bike_blueprint_ids' => ['nullable', 'array'],
                'bike_blueprint_ids.*' => ['exists:bike_blueprints,id'],
            ],
            'product_categories', 'spare_part_categories', 'maintenance_service_sectors', 'payment_methods' => [
                'name' => [$isUpdate ? 'nullable' : 'required', 'string']
            ],
            'brands' => [
                'name' => [$isUpdate ? 'nullable' : 'required', 'string'],
                'type' => [$isUpdate ? 'nullable' : 'required', Rule::in(['spare_parts', 'products', 'bikes'])]
            ],
            'maintenance_services' => [
                'name' => [$isUpdate ? 'nullable' : 'required', 'string'],
                'currency_pricing' => [$isUpdate ? 'nullable' : 'required', Rule::in(['EGP', 'USD'])],
                'service_price' => [$isUpdate ? 'nullable' : 'required', 'numeric'],
                'max_discount_type' => [$isUpdate ? 'nullable' : 'required', Rule::in(['fixed', 'percentage'])],
                'max_discount_value' => [$isUpdate ? 'nullable' : 'required', 'numeric'],
                'maintenance_service_sector_id' => [$isUpdate ? 'nullable' : 'required', 'exists:maintenance_service_sectors,id'],
            ],
            'bike_blueprints' => [
                'brand_id' => [$isUpdate ? 'nullable' : 'required', 'exists:brands,id'],
                'model' => [$isUpdate ? 'nullable' : 'required', 'string'],
                'year' => [$isUpdate ? 'nullable' : 'required', 'integer']
            ],
            'bike_for_sale' => [
                'bike_blueprint_id' => [$isUpdate ? 'nullable' : 'required', 'exists:bike_blueprints,id'],
                'vin' => [$isUpdate ? 'nullable' : 'required', Rule::unique('bike_for_sale', 'vin')->ignore($id)]
            ],
            'customer_bikes' => [
                'customer_id' => [$isUpdate ? 'nullable' : 'required', 'exists:customers,id'],
                'bike_blueprint_id' => [$isUpdate ? 'nullable' : 'required', 'exists:bike_blueprints,id']
            ],
            'bike_blueprint_spare_parts' => [
                'bike_blueprint_id' => [$isUpdate ? 'nullable' : 'required', 'exists:bike_blueprints,id'],
                'spare_part_id' => [$isUpdate ? 'nullable' : 'required', 'exists:spare_parts,id']
            ],
            'customer_sale' => [
                'customer_id' => [$isUpdate ? 'nullable' : 'required', 'exists:customers,id'],
                'sale_id' => [$isUpdate ? 'nullable' : 'required', 'exists:sales,id']
            ],
            'sale_items' => [
                'sale_id' => [$isUpdate ? 'nullable' : 'required', 'exists:sales,id'],
                'selling_price' => [$isUpdate ? 'nullable' : 'required', 'numeric'],
                'qty' => [$isUpdate ? 'nullable' : 'required', 'integer', 'min:1']
            ],
            'deliveries' => [
                'sale_id' => [$isUpdate ? 'nullable' : 'required', 'exists:sales,id'],
                'customer_id' => [$isUpdate ? 'nullable' : 'required', 'exists:customers,id'],
                'full_address' => [$isUpdate ? 'nullable' : 'required', 'string'],
                'city' => [$isUpdate ? 'nullable' : 'required', 'string']
            ],
            'ticket_tasks' => [
                'ticket_id' => [$isUpdate ? 'nullable' : 'required', 'exists:tickets,id'],
                'name' => [$isUpdate ? 'nullable' : 'required', 'string'],
                'status' => [$isUpdate ? 'nullable' : 'required', Rule::in(['pending', 'completed'])]
            ],
            'ticket_items' => [
                'task_id' => [$isUpdate ? 'nullable' : 'required', 'exists:ticket_tasks,id'],
                'ticket_id' => [$isUpdate ? 'nullable' : 'required', 'exists:tickets,id'],
                'price_snapshot' => [$isUpdate ? 'nullable' : 'required', 'numeric'],
                'qty' => [$isUpdate ? 'nullable' : 'required', 'integer', 'min:1']
            ],
            'settings' => [
                'key' => ['sometimes', 'string', Rule::unique('settings', 'key')->ignore($id)],
                'value' => ['sometimes', 'numeric']
            ],
            default => [],
        };
    }
}
