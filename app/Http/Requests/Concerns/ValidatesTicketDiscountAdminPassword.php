<?php

namespace App\Http\Requests\Concerns;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Validation\Validator;

trait ValidatesTicketDiscountAdminPassword
{
    protected function validateTicketDiscountAdminPassword(Validator $validator): void
    {
        if (! $this->has('discount')) {
            return;
        }

        $ticketDiscount = (float) ($this->input('discount') ?? 0);
        if ($ticketDiscount <= 0) {
            return;
        }

        $user = $this->user();
        if (! $user) {
            $validator->errors()->add(
                'discount',
                'Authentication is required to apply an overall ticket discount.',
            );

            return;
        }

        if ($user->role === User::ROLE_ADMIN) {
            return;
        }

        if ($user->role !== User::ROLE_STAFF) {
            $validator->errors()->add(
                'discount',
                'Only administrators can apply an overall ticket discount.',
            );

            return;
        }

        $requestId = $this->input('discount_approval_request_id');
        if (! is_numeric($requestId)) {
            $validator->errors()->add(
                'discount_approval_request_id',
                'An approved discount request is required to apply an overall ticket discount.',
            );

            return;
        }

        $approvalRequest = ApprovalRequest::query()->find((int) $requestId);
        if (! $approvalRequest) {
            $validator->errors()->add(
                'discount_approval_request_id',
                'Discount approval request was not found.',
            );

            return;
        }

        if ($approvalRequest->type !== ApprovalRequest::TYPE_TICKET_DISCOUNT) {
            $validator->errors()->add(
                'discount_approval_request_id',
                'This approval request is not for a ticket discount.',
            );

            return;
        }

        if ((int) $approvalRequest->requested_by !== (int) $user->id) {
            $validator->errors()->add(
                'discount_approval_request_id',
                'This discount approval request does not belong to you.',
            );

            return;
        }

        if (! $approvalRequest->isConsumable()) {
            $validator->errors()->add(
                'discount_approval_request_id',
                'This discount approval request is not approved or has already been used.',
            );

            return;
        }

        if (round((float) $approvalRequest->approved_discount_amount, 2) !== round($ticketDiscount, 2)) {
            $validator->errors()->add(
                'discount',
                'Ticket discount must match the approved discount amount.',
            );
        }
    }
}
