<?php

namespace App\Http\Requests\Audit;

use App\Enums\AuditAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-audit') ?? false;
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return ['action' => ['nullable', Rule::enum(AuditAction::class)], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])]];
    }
}
