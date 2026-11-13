<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\Audit\IndexAuditLogRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(IndexAuditLogRequest $request): Response
    {
        $filters = ['action' => $request->validated('action') ?? '', 'from' => $request->validated('from') ?? '', 'to' => $request->validated('to') ?? ''];
        $query = AuditLog::query()->with('user')->orderByDesc('created_at')->orderByDesc('id');
        if ($filters['action'] !== '') {
            $query->where('action', $filters['action']);
        }
        if ($filters['from'] !== '') {
            $query->where('created_at', '>=', CarbonImmutable::parse($filters['from'], 'America/Tegucigalpa')->startOfDay()->utc());
        }
        if ($filters['to'] !== '') {
            $query->where('created_at', '<', CarbonImmutable::parse($filters['to'], 'America/Tegucigalpa')->addDay()->startOfDay()->utc());
        }

        return Inertia::render('audit/Index', ['logs' => AuditLogResource::collection($query->paginate(50)->withQueryString()), 'filters' => $filters, 'actions' => array_map(fn (AuditAction $action): array => ['value' => $action->value, 'label' => $action->label()], AuditAction::cases())]);
    }
}
