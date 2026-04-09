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

        return match ($entity) {
            'sellers' => ['name' => ['required', 'string'], 'phone' => ['required', 'string'], 'commission_rate' => ['required', 'numeric']],
            'customers' => ['name' => ['required', 'string'], 'phone' => ['required', 'string']],
            'products' => [
                'name' => ['required', 'string'],
                'sku' => ['required', 'string', Rule::unique('products', 'sku')->ignore($id)],
                'products_category_id' => ['required', 'exists:product_categories,id'],
                'brand_id' => ['required', 'exists:brands,id'],
            ],
            'spare_parts' => [
                'name' => ['required', 'string'],
                'sku' => ['required', 'string', Rule::unique('spare_parts', 'sku')->ignore($id)],
                'spare_parts_category_id' => ['required', 'exists:spare_part_categories,id'],
                'brand_id' => ['required', 'exists:brands,id'],
            ],
            'product_categories', 'spare_part_categories', 'maintenance_service_sectors', 'payment_methods' => ['name' => ['required', 'string']],
            'brands' => ['name' => ['required', 'string'], 'type' => ['required', Rule::in(['spare_parts', 'products', 'bikes'])]],
            'maintenance_services' => [
                'name' => ['required', 'string'],
                'currency_pricing' => ['required', Rule::in(['EGP', 'USD'])],
                'service_price' => ['required', 'numeric'],
                'max_discount_type' => ['required', Rule::in(['fixed', 'percentage'])],
                'max_discount_value' => ['required', 'numeric'],
                'maintenance_service_sector_id' => ['required', 'exists:maintenance_service_sectors,id'],
            ],
            'bike_blueprints' => ['brand_id' => ['required', 'exists:brands,id'], 'model' => ['required', 'string'], 'year' => ['required', 'integer']],
            'bike_for_sale' => ['bike_blueprint_id' => ['required', 'exists:bike_blueprints,id'], 'vin' => ['required', Rule::unique('bike_for_sale', 'vin')->ignore($id)]],
            'customer_bikes' => ['customer_id' => ['required', 'exists:customers,id'], 'bike_blueprint_id' => ['required', 'exists:bike_blueprints,id']],
            'bike_blueprint_spare_parts' => ['bike_blueprint_id' => ['required', 'exists:bike_blueprints,id'], 'spare_part_id' => ['required', 'exists:spare_parts,id']],
            'customer_sale' => ['customer_id' => ['required', 'exists:customers,id'], 'sale_id' => ['required', 'exists:sales,id']],
            'sale_items' => ['sale_id' => ['required', 'exists:sales,id'], 'selling_price' => ['required', 'numeric'], 'qty' => ['required', 'integer', 'min:1']],
            'deliveries' => ['sale_id' => ['required', 'exists:sales,id'], 'customer_id' => ['required', 'exists:customers,id'], 'full_address' => ['required', 'string'], 'city' => ['required', 'string']],
            'ticket_tasks' => ['ticket_id' => ['required', 'exists:tickets,id'], 'name' => ['required', 'string'], 'status' => ['required', Rule::in(['pending', 'completed'])]],
            'ticket_items' => ['task_id' => ['required', 'exists:ticket_tasks,id'], 'ticket_id' => ['required', 'exists:tickets,id'], 'price_snapshot' => ['required', 'numeric'], 'qty' => ['required', 'integer', 'min:1']],
            default => [],
        };
    }
}
