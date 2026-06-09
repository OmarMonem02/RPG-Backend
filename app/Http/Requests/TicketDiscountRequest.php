<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesTicketDiscountAdminPassword;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TicketDiscountRequest extends FormRequest
{
    use ValidatesTicketDiscountAdminPassword;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->hasPermission('maintenance', 'update')
            && in_array($user->role, [User::ROLE_ADMIN, User::ROLE_STAFF], true);
    }

    public function rules(): array
    {
        return [
            'discount' => ['required', 'numeric', 'min:0'],
            'discount_approval_request_id' => ['nullable', 'integer', 'exists:approval_requests,id'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateTicketDiscountAdminPassword($validator);

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var Ticket $ticket */
                $ticket = $this->route('ticket');

                if ($ticket->status === 'closed') {
                    $validator->errors()->add(
                        'discount',
                        'Cannot update discount on a closed ticket.',
                    );

                    return;
                }

                if (! in_array($ticket->status, ['in_progress', 'completed'], true)) {
                    $validator->errors()->add(
                        'discount',
                        'Overall discount can only be applied while the ticket is in progress or completed.',
                    );

                    return;
                }

                $discount = (float) $this->input('discount', 0);
                $itemsSubtotal = (float) $ticket->tasks()->sum('subtotal');

                if ($discount > $itemsSubtotal) {
                    $validator->errors()->add(
                        'discount',
                        'Overall ticket discount cannot exceed the items subtotal.',
                    );
                }
            },
        ];
    }
}
