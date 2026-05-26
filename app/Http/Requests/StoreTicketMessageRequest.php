<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTicketMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:5000'],
            'image_url' => ['nullable', 'string', 'url', 'max:2048'],
            'image_public_id' => ['nullable', 'string', 'max:512'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $body = trim((string) $this->input('body', ''));
            $imageUrl = trim((string) $this->input('image_url', ''));

            if ($body === '' && $imageUrl === '') {
                $validator->errors()->add('body', 'Message text or an image is required.');
            }

            if ($imageUrl !== '' && $this->input('image_public_id') === null) {
                $validator->errors()->add('image_public_id', 'Image reference is required when sending an image.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function messagePayload(): array
    {
        $body = trim((string) $this->input('body', ''));

        return [
            'body' => $body !== '' ? $body : null,
            'image_url' => $this->input('image_url'),
            'image_public_id' => $this->input('image_public_id'),
        ];
    }
}
