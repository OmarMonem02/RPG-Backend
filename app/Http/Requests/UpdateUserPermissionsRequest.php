<?php

namespace App\Http\Requests;

use App\Support\UserPermissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array'],
            'permissions.*' => ['present', 'array'],
            'permissions.*.*' => ['required', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $permissions = $this->input('permissions');

                if (! is_array($permissions)) {
                    return;
                }

                $expectedPages = UserPermissions::pages();
                $providedPages = array_keys($permissions);
                $unknownPages = array_diff($providedPages, $expectedPages);
                $missingPages = array_diff($expectedPages, $providedPages);

                foreach ($unknownPages as $page) {
                    $validator->errors()->add("permissions.{$page}", 'This page is not supported.');
                }

                foreach ($missingPages as $page) {
                    $validator->errors()->add("permissions.{$page}", 'This page key is required.');
                }

                foreach ($permissions as $page => $actions) {
                    if (! is_array($actions)) {
                        $validator->errors()->add("permissions.{$page}", 'Permissions for each page must be an array.');

                        continue;
                    }

                    $allowedActions = UserPermissions::allowedActionsForPage((string) $page);
                    $hasOperationalAction = false;

                    foreach ($actions as $index => $action) {
                        if (! in_array($action, UserPermissions::actions(), true)) {
                            $validator->errors()->add(
                                "permissions.{$page}.{$index}",
                                'This action is not supported.'
                            );

                            continue;
                        }

                        if (! in_array($action, $allowedActions, true)) {
                            $validator->errors()->add(
                                "permissions.{$page}.{$index}",
                                'This action is not available for this page.'
                            );
                        }

                        if ($action !== 'read') {
                            $hasOperationalAction = true;
                        }
                    }

                    if ($hasOperationalAction && ! in_array('read', $actions, true)) {
                        $validator->errors()->add(
                            "permissions.{$page}",
                            'Read permission is required before granting operational actions.'
                        );
                    }
                }
            },
        ];
    }

    public function normalizedPermissions(): array
    {
        return UserPermissions::normalizeMatrix($this->input('permissions', []));
    }
}
