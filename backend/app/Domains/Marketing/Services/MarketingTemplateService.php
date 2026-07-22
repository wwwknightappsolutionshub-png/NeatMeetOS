<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Models\MarketingTemplate;
use App\Shared\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketingTemplateService
{
    public function __construct(
        private readonly MarketingScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
        private readonly TemplateRendererService $renderer,
    ) {}

    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = MarketingTemplate::query()->orderBy('name');

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('subject', 'like', $search));
        }

        return $query->paginate($perPage);
    }

    public function find(string $id): MarketingTemplate
    {
        return $this->scope->findTemplate($id);
    }

    public function create(array $data): MarketingTemplate
    {
        $tenantId = $this->scope->tenantId();
        $this->validateChannel($data['channel'] ?? null);

        return DB::transaction(function () use ($tenantId, $data) {
            $template = MarketingTemplate::query()->create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'category' => $data['category'],
                'channel' => $data['channel'],
                'subject' => $data['subject'] ?? null,
                'body_text' => $data['body_text'],
                'body_html' => $data['body_html'] ?? null,
                'variables_json' => $data['variables_json'] ?? $this->renderer->supportedPlaceholders(),
                'is_system' => (bool) ($data['is_system'] ?? false),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            $this->auditLogger->log('marketing_template.created', $template, null, $template->only([
                'name', 'category', 'channel', 'is_active',
            ]));

            return $template->fresh();
        });
    }

    public function update(MarketingTemplate $template, array $data): MarketingTemplate
    {
        $this->scope->assertTenantModel($template);

        if ($template->is_system) {
            throw ValidationException::withMessages([
                'template' => ['System templates cannot be modified.'],
            ]);
        }

        if (array_key_exists('channel', $data)) {
            $this->validateChannel($data['channel']);
        }

        $fields = array_intersect_key($data, array_flip([
            'name', 'category', 'channel', 'subject', 'body_text', 'body_html', 'variables_json', 'is_active',
        ]));

        return DB::transaction(function () use ($template, $fields) {
            $old = $template->only(array_keys($fields));
            $template->fill($fields);
            $template->save();

            $this->auditLogger->log('marketing_template.updated', $template, $old, $template->only(array_keys($fields)));

            return $template->fresh();
        });
    }

    public function archive(MarketingTemplate $template): MarketingTemplate
    {
        $this->scope->assertTenantModel($template);

        return DB::transaction(function () use ($template) {
            $old = ['is_active' => $template->is_active];
            $template->is_active = false;
            $template->save();

            $this->auditLogger->log('marketing_template.archived', $template, $old, ['is_active' => false]);

            return $template->fresh();
        });
    }

    /**
     * Render a preview without persisting anything.
     *
     * @param  array<string, mixed>|null  $payload
     * @return array{subject: string|null, body_text: string, body_html: string|null, template_snapshot: array<string, mixed>, variables_snapshot: array<string, mixed>}
     */
    public function preview(MarketingTemplate $template, ?array $payload = null): array
    {
        $this->scope->assertTenantModel($template);

        return $this->renderer->renderTemplate($template, $payload ?? $this->renderer->samplePayload());
    }

    /**
     * Resolve the first active template for a trigger category + channel.
     */
    public function resolveForTrigger(string $category, string $channel, ?string $templateId = null): ?MarketingTemplate
    {
        if ($templateId !== null) {
            return MarketingTemplate::query()
                ->where('id', $templateId)
                ->where('is_active', true)
                ->first();
        }

        return MarketingTemplate::query()
            ->where('category', $category)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->first();
    }

    private function validateChannel(?string $channel): void
    {
        if ($channel === null || ! in_array($channel, MarketingChannel::all(), true)) {
            throw ValidationException::withMessages([
                'channel' => ['Invalid marketing channel.'],
            ]);
        }
    }
}
