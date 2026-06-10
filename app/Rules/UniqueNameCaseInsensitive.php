<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UniqueNameCaseInsensitive implements ValidationRule
{
    public function __construct(
        private string $table,
        private ?int $ignoreId = null,
        private bool $softDeletes = true,
        private string $entityLabel = 'record',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $query = DB::table($this->table)
            ->whereRaw('LOWER(name) = ?', [Str::lower(trim($value))]);

        if ($this->softDeletes) {
            $query->whereNull('deleted_at');
        }

        if ($this->ignoreId !== null) {
            $query->where('id', '!=', $this->ignoreId);
        }

        if ($query->exists()) {
            $fail("A {$this->entityLabel} with this name already exists.");
        }
    }
}
