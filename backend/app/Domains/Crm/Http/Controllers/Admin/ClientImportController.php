<?php

namespace App\Domains\Crm\Http\Controllers\Admin;

use App\Domains\Crm\Services\ClientCsvImportService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientImportController extends Controller
{
    public function __construct(
        private readonly ClientCsvImportService $imports,
    ) {}

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $preview = $this->imports->preview($data['file']);

        return ApiResponse::success($preview, 'CSV preview ready');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'mapping' => ['required'],
            'grant_privacy_contact' => ['sometimes', 'boolean'],
            'grant_marketing_email' => ['sometimes', 'boolean'],
            'grant_marketing_sms' => ['sometimes', 'boolean'],
        ]);

        $mapping = $data['mapping'];
        if (is_string($mapping)) {
            $decoded = json_decode($mapping, true);
            if (! is_array($decoded)) {
                return ApiResponse::error('Invalid mapping JSON.', 422);
            }
            $mapping = $decoded;
        }

        if (! is_array($mapping)) {
            return ApiResponse::error('Mapping must be an object of target fields to CSV headers.', 422);
        }

        /** @var array<string, string|null> $mapping */
        $result = $this->imports->import($data['file'], $mapping, [
            'grant_privacy_contact' => (bool) ($data['grant_privacy_contact'] ?? true),
            'grant_marketing_email' => (bool) ($data['grant_marketing_email'] ?? false),
            'grant_marketing_sms' => (bool) ($data['grant_marketing_sms'] ?? false),
        ]);

        return ApiResponse::success($result, 'Client import completed');
    }
}
