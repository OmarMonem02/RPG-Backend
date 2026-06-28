<?php

namespace App\Http\Requests;

use App\Models\Machine;
use App\Models\MachineDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MachineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(Machine::CATEGORIES)],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(Machine::STATUSES)],
            'notes' => ['nullable', 'string'],
            'documents' => ['nullable', 'array'],
            'documents.*.type' => ['required_with:documents', Rule::in(MachineDocument::TYPES)],
            'documents.*.url' => ['required_with:documents', 'url'],
            'documents.*.public_id' => ['required_with:documents', 'string', 'max:255'],
            'documents.*.filename' => ['required_with:documents', 'string', 'max:255'],
            'documents.*.mime_type' => ['required_with:documents', 'string', 'max:255'],
            'remove_document_ids' => ['nullable', 'array'],
            'remove_document_ids.*' => ['integer', 'exists:machine_documents,id'],
        ];
    }
}
