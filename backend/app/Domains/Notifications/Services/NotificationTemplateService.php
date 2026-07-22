<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\Enums\NotificationCategory;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Models\NotificationTemplate;
use App\Shared\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NotificationTemplateService
{
    public function __construct(
        private readonly NotificationScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = NotificationTemplate::query()
            ->with('createdBy')
            ->orderBy('name');

        if (! empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists('is_system', $filters) && $filters['is_system'] !== null) {
            $query->where('is_system', filter_var($filters['is_system'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('subject', 'like', $search));
        }

        return $query->paginate($perPage);
    }

    public function find(string $id): NotificationTemplate
    {
        return $this->scope->findTemplate($id)->load('createdBy');
    }

    public function create(array $data): NotificationTemplate
    {
        $tenantId = $this->scope->tenantId();
        $this->validateChannel($data['channel'] ?? null);
        $this->validateCategory($data['category'] ?? null);

        $slug = $this->uniqueSlug($data['slug'] ?? $data['name'], $tenantId);

        return DB::transaction(function () use ($tenantId, $data, $slug) {
            $template = NotificationTemplate::query()->create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'slug' => $slug,
                'channel' => $data['channel'],
                'category' => $data['category'],
                'subject' => $data['subject'] ?? null,
                'body_text' => $data['body_text'] ?? null,
                'body_html' => $data['body_html'] ?? null,
                'variables_json' => $data['variables_json'] ?? null,
                'is_system' => (bool) ($data['is_system'] ?? false),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'created_by_team_member_id' => $data['created_by_team_member_id'] ?? null,
            ]);

            $this->auditLogger->log('notification_template.created', $template, null, $template->only([
                'name', 'channel', 'category', 'is_active', 'is_system',
            ]));

            return $template->fresh('createdBy');
        });
    }

    public function update(NotificationTemplate $template, array $data): NotificationTemplate
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
        if (array_key_exists('category', $data)) {
            $this->validateCategory($data['category']);
        }

        $fields = array_intersect_key($data, array_flip([
            'name', 'channel', 'category', 'subject', 'body_text', 'body_html', 'variables_json', 'is_active',
        ]));

        return DB::transaction(function () use ($template, $fields) {
            $old = $template->only(array_keys($fields));
            $template->fill($fields);
            $template->save();

            $this->auditLogger->log('notification_template.updated', $template, $old, $template->only(array_keys($fields)));

            return $template->fresh('createdBy');
        });
    }

    public function archive(NotificationTemplate $template): NotificationTemplate
    {
        $this->scope->assertTenantModel($template);

        if ($template->is_system) {
            throw ValidationException::withMessages([
                'template' => ['System templates cannot be archived.'],
            ]);
        }

        return DB::transaction(function () use ($template) {
            $old = ['is_active' => $template->is_active];
            $template->is_active = false;
            $template->save();

            $this->auditLogger->log('notification_template.archived', $template, $old, ['is_active' => false]);

            return $template->fresh('createdBy');
        });
    }

    /**
     * Resolve the first active template for a category + channel (optionally by id / purpose slug).
     */
    public function resolveFor(
        string $category,
        string $channel,
        ?string $templateId = null,
        ?string $purpose = null,
    ): ?NotificationTemplate {
        if ($templateId !== null) {
            return NotificationTemplate::query()
                ->where('id', $templateId)
                ->where('is_active', true)
                ->first();
        }

        if ($purpose !== null && $purpose !== '') {
            $slugHint = Str::slug($purpose);
            $byPurpose = NotificationTemplate::query()
                ->where('channel', $channel)
                ->where('is_active', true)
                ->where(function ($q) use ($slugHint, $purpose, $category) {
                    $q->where('slug', 'like', '%'.$slugHint.'%')
                        ->orWhere('name', 'like', '%'.$purpose.'%')
                        ->orWhere(function ($inner) use ($category, $slugHint) {
                            $inner->where('category', $category)
                                ->where('slug', 'like', '%'.$slugHint.'%');
                        });
                })
                ->orderByDesc('is_system')
                ->orderBy('name')
                ->first();

            if ($byPurpose !== null) {
                return $byPurpose;
            }
        }

        return NotificationTemplate::query()
            ->where('category', $category)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->first();
    }

    private function validateChannel(?string $channel): void
    {
        if ($channel === null || ! in_array($channel, NotificationChannel::all(), true)) {
            throw ValidationException::withMessages(['channel' => ['Invalid notification channel.']]);
        }
    }

    private function validateCategory(?string $category): void
    {
        if ($category === null || ! in_array($category, NotificationCategory::all(), true)) {
            throw ValidationException::withMessages(['category' => ['Invalid notification category.']]);
        }
    }

    private function uniqueSlug(string $name, string $tenantId): string
    {
        $base = Str::slug($name) ?: 'template';
        $slug = $base;
        $counter = 1;

        while (NotificationTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
