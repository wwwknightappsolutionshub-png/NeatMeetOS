<?php

namespace App\Domains\Crm\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientConsentRecord;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Services\ProgressiveModuleAccessService;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientCsvImportService
{
    public const MAX_ROWS = 2000;

    public const MAX_BYTES = 2_000_000;

    /** @var list<string> */
    public const TARGET_FIELDS = [
        'first_name',
        'last_name',
        'name',
        'email',
        'phone',
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ClientService $clients,
        private readonly ClientConsentService $consents,
        private readonly AuditLogger $auditLogger,
        private readonly ProgressiveModuleAccessService $progressiveAccess,
    ) {}

    /**
     * @return array{
     *     headers: list<string>,
     *     suggested_mapping: array<string, string|null>,
     *     sample_rows: list<array<string, string>>,
     *     row_count: int
     * }
     */
    public function preview(UploadedFile $file): array
    {
        $parsed = $this->parseCsv($file);
        $headers = $parsed['headers'];
        $rows = $parsed['rows'];

        return [
            'headers' => $headers,
            'suggested_mapping' => $this->suggestMapping($headers),
            'sample_rows' => array_slice($rows, 0, 5),
            'row_count' => count($rows),
        ];
    }

    /**
     * @param  array<string, string|null>  $mapping  target field => CSV header
     * @param  array{
     *     grant_privacy_contact?: bool,
     *     grant_marketing_email?: bool,
     *     grant_marketing_sms?: bool
     * }  $options
     * @return array{
     *     created: int,
     *     skipped_duplicates: int,
     *     skipped_invalid: int,
     *     errors: list<array{row: int, reason: string}>,
     *     created_ids: list<string>
     * }
     */
    public function import(UploadedFile $file, array $mapping, array $options = []): array
    {
        $tenantId = $this->requireTenantId();
        $this->assertMapping($mapping);

        $parsed = $this->parseCsv($file);
        $headers = $parsed['headers'];
        $rows = $parsed['rows'];

        foreach ($mapping as $target => $header) {
            if ($header === null || $header === '') {
                continue;
            }
            if (! in_array($header, $headers, true)) {
                throw ValidationException::withMessages([
                    'mapping' => ["Mapped column \"{$header}\" was not found in the CSV header."],
                ]);
            }
        }

        $lookups = $this->buildExistingLookups($tenantId);
        $seenPhones = [];
        $seenEmails = [];

        $created = 0;
        $skippedDuplicates = 0;
        $skippedInvalid = 0;
        $errors = [];
        $createdIds = [];

        $grantPrivacy = (bool) ($options['grant_privacy_contact'] ?? true);
        $grantMarketingEmail = (bool) ($options['grant_marketing_email'] ?? false);
        $grantMarketingSms = (bool) ($options['grant_marketing_sms'] ?? false);

        DB::transaction(function () use (
            $rows,
            $mapping,
            $lookups,
            &$seenPhones,
            &$seenEmails,
            &$created,
            &$skippedDuplicates,
            &$skippedInvalid,
            &$errors,
            &$createdIds,
            $grantPrivacy,
            $grantMarketingEmail,
            $grantMarketingSms,
        ) {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // header is row 1
                $payload = $this->mapRow($row, $mapping);

                if ($payload === null) {
                    $skippedInvalid++;
                    $errors[] = [
                        'row' => $rowNumber,
                        'reason' => 'Missing name and contact details (need a name plus phone or email).',
                    ];
                    continue;
                }

                $emailKey = $payload['email'] !== null ? strtolower($payload['email']) : null;
                $phoneKey = $payload['phone'] !== null ? $this->normalizePhone($payload['phone']) : null;

                if ($emailKey !== null && (isset($lookups['emails'][$emailKey]) || isset($seenEmails[$emailKey]))) {
                    $skippedDuplicates++;
                    continue;
                }

                if ($phoneKey !== null && $phoneKey !== '' && (isset($lookups['phones'][$phoneKey]) || isset($seenPhones[$phoneKey]))) {
                    $skippedDuplicates++;
                    continue;
                }

                try {
                    $client = $this->clients->create([
                        'first_name' => $payload['first_name'],
                        'last_name' => $payload['last_name'],
                        'email' => $payload['email'],
                        'phone' => $phoneKey !== null && $phoneKey !== '' ? $phoneKey : null,
                    ], ['skip_automations' => true]);

                    $this->recordImportConsents(
                        $client,
                        $grantPrivacy,
                        $grantMarketingEmail,
                        $grantMarketingSms,
                    );

                    $created++;
                    $createdIds[] = $client->id;

                    if ($emailKey !== null) {
                        $seenEmails[$emailKey] = true;
                        $lookups['emails'][$emailKey] = $client->id;
                    }
                    if ($phoneKey !== null && $phoneKey !== '') {
                        $seenPhones[$phoneKey] = true;
                        $lookups['phones'][$phoneKey] = $client->id;
                    }
                } catch (\Throwable $e) {
                    $skippedInvalid++;
                    $errors[] = [
                        'row' => $rowNumber,
                        'reason' => $e->getMessage(),
                    ];
                }
            }
        });

        $this->auditLogger->log('client.import_completed', null, null, [
            'created' => $created,
            'skipped_duplicates' => $skippedDuplicates,
            'skipped_invalid' => $skippedInvalid,
            'row_count' => count($rows),
        ]);

        try {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant && $created > 0) {
                $this->progressiveAccess->maybeNudgeAfterClientCreated($tenant);
            }
        } catch (\Throwable) {
            // Non-blocking.
        }

        return [
            'created' => $created,
            'skipped_duplicates' => $skippedDuplicates,
            'skipped_invalid' => $skippedInvalid,
            'errors' => array_slice($errors, 0, 50),
            'created_ids' => $createdIds,
        ];
    }

    /**
     * @return array{headers: list<string>, rows: list<array<string, string>>}
     */
    private function parseCsv(UploadedFile $file): array
    {
        if ($file->getSize() !== null && $file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'file' => ['CSV must be 2 MB or smaller.'],
            ]);
        }

        $path = $file->getRealPath();
        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => ['Could not read uploaded CSV.'],
            ]);
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => ['Could not open uploaded CSV.'],
            ]);
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                throw ValidationException::withMessages([
                    'file' => ['CSV is empty.'],
                ]);
            }

            // Strip UTF-8 BOM.
            if (str_starts_with($firstLine, "\xEF\xBB\xBF")) {
                $firstLine = substr($firstLine, 3);
            }

            $delimiter = $this->detectDelimiter($firstLine);
            rewind($handle);
            // Re-skip BOM after rewind.
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $headerRow = fgetcsv($handle, 0, $delimiter);
            if ($headerRow === false || $headerRow === [null] || $headerRow === []) {
                throw ValidationException::withMessages([
                    'file' => ['CSV header row is missing.'],
                ]);
            }

            $headers = [];
            foreach ($headerRow as $i => $raw) {
                $label = trim((string) $raw);
                $headers[] = $label !== '' ? $label : 'column_'.($i + 1);
            }

            if (count(array_unique($headers)) !== count($headers)) {
                throw ValidationException::withMessages([
                    'file' => ['CSV headers must be unique.'],
                ]);
            }

            $rows = [];
            while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                if ($this->rowIsEmpty($data)) {
                    continue;
                }

                $assoc = [];
                foreach ($headers as $i => $header) {
                    $assoc[$header] = trim((string) ($data[$i] ?? ''));
                }
                $rows[] = $assoc;

                if (count($rows) > self::MAX_ROWS) {
                    throw ValidationException::withMessages([
                        'file' => ['CSV may contain at most '.self::MAX_ROWS.' data rows.'],
                    ]);
                }
            }

            if ($rows === []) {
                throw ValidationException::withMessages([
                    'file' => ['CSV has a header but no data rows.'],
                ]);
            }

            return ['headers' => $headers, 'rows' => $rows];
        } finally {
            fclose($handle);
        }
    }

    private function detectDelimiter(string $line): string
    {
        $comma = substr_count($line, ',');
        $semi = substr_count($line, ';');
        $tab = substr_count($line, "\t");

        if ($tab >= $comma && $tab >= $semi && $tab > 0) {
            return "\t";
        }
        if ($semi > $comma) {
            return ';';
        }

        return ',';
    }

    /**
     * @param  list<string|null>  $data
     */
    private function rowIsEmpty(array $data): bool
    {
        foreach ($data as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, string|null>
     */
    private function suggestMapping(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $header) {
            $normalized[$this->normalizeHeader($header)] = $header;
        }

        $aliases = [
            'first_name' => ['first_name', 'firstname', 'first', 'given_name', 'givenname'],
            'last_name' => ['last_name', 'lastname', 'last', 'surname', 'family_name', 'familyname'],
            'name' => ['name', 'full_name', 'fullname', 'display_name', 'displayname', 'contact_name', 'contact'],
            'email' => ['email', 'e_mail', 'email_address', 'mail'],
            'phone' => ['phone', 'mobile', 'telephone', 'tel', 'whatsapp', 'whatsapp_number', 'cell', 'cellphone', 'phone_number', 'mobile_phone'],
        ];

        $mapping = [];
        $used = [];
        foreach (self::TARGET_FIELDS as $field) {
            $mapping[$field] = null;
            foreach ($aliases[$field] as $alias) {
                if (isset($normalized[$alias]) && ! isset($used[$normalized[$alias]])) {
                    $mapping[$field] = $normalized[$alias];
                    $used[$normalized[$alias]] = true;
                    break;
                }
            }
        }

        return $mapping;
    }

    private function normalizeHeader(string $header): string
    {
        $value = strtolower(trim($header));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;

        return trim($value, '_');
    }

    /**
     * @param  array<string, string|null>  $mapping
     */
    private function assertMapping(array $mapping): void
    {
        foreach (array_keys($mapping) as $field) {
            if (! in_array($field, self::TARGET_FIELDS, true)) {
                throw ValidationException::withMessages([
                    'mapping' => ["Unknown mapping field \"{$field}\"."],
                ]);
            }
        }

        $hasName = filled($mapping['first_name'] ?? null) || filled($mapping['name'] ?? null);
        $hasContact = filled($mapping['email'] ?? null) || filled($mapping['phone'] ?? null);

        if (! $hasName) {
            throw ValidationException::withMessages([
                'mapping' => ['Map a first name or full name column.'],
            ]);
        }

        if (! $hasContact) {
            throw ValidationException::withMessages([
                'mapping' => ['Map a phone or email column.'],
            ]);
        }
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, string|null>  $mapping
     * @return array{first_name: string, last_name: string|null, email: string|null, phone: string|null}|null
     */
    private function mapRow(array $row, array $mapping): ?array
    {
        $first = $this->cell($row, $mapping['first_name'] ?? null);
        $last = $this->cell($row, $mapping['last_name'] ?? null);
        $full = $this->cell($row, $mapping['name'] ?? null);
        $emailRaw = $this->cell($row, $mapping['email'] ?? null);
        $phoneRaw = $this->cell($row, $mapping['phone'] ?? null);

        if (($first === null || $first === '') && $full !== null && $full !== '') {
            $parts = preg_split('/\s+/', $full, 2) ?: [];
            $first = $parts[0] ?? '';
            if (($last === null || $last === '') && isset($parts[1])) {
                $last = $parts[1];
            }
        }

        $first = trim((string) $first);
        $last = $last !== null && trim($last) !== '' ? trim($last) : null;

        $email = null;
        if ($emailRaw !== null && $emailRaw !== '') {
            $email = strtolower(trim($emailRaw));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return null;
            }
        }

        $phone = null;
        if ($phoneRaw !== null && $phoneRaw !== '') {
            $phone = $this->normalizePhone($phoneRaw);
            if ($phone === '' || strlen(preg_replace('/\D/', '', $phone) ?? '') < 7) {
                return null;
            }
        }

        if ($first === '') {
            return null;
        }

        if ($email === null && ($phone === null || $phone === '')) {
            return null;
        }

        return [
            'first_name' => mb_substr($first, 0, 255),
            'last_name' => $last !== null ? mb_substr($last, 0, 255) : null,
            'email' => $email,
            'phone' => $phone,
        ];
    }

    /**
     * @param  array<string, string>  $row
     */
    private function cell(array $row, ?string $header): ?string
    {
        if ($header === null || $header === '') {
            return null;
        }

        return $row[$header] ?? null;
    }

    /**
     * @return array{emails: array<string, string>, phones: array<string, string>}
     */
    private function buildExistingLookups(string $tenantId): array
    {
        $emails = [];
        $phones = [];

        $clients = Client::query()
            ->where('tenant_id', $tenantId)
            ->get(['id', 'email', 'phone']);

        foreach ($clients as $client) {
            if (filled($client->email)) {
                $emails[strtolower((string) $client->email)] = $client->id;
            }
            if (filled($client->phone)) {
                $normalized = $this->normalizePhone((string) $client->phone);
                if ($normalized !== '') {
                    $phones[$normalized] = $client->id;
                }
            }
        }

        return ['emails' => $emails, 'phones' => $phones];
    }

    private function recordImportConsents(
        Client $client,
        bool $grantPrivacy,
        bool $grantMarketingEmail,
        bool $grantMarketingSms,
    ): void {
        $pairs = [
            [ClientConsentRecord::TYPE_PRIVACY_CONTACT, $grantPrivacy],
            [ClientConsentRecord::TYPE_MARKETING_EMAIL, $grantMarketingEmail],
            [ClientConsentRecord::TYPE_MARKETING_SMS, $grantMarketingSms],
        ];

        foreach ($pairs as [$type, $granted]) {
            if (! $granted) {
                continue;
            }

            $this->consents->record($client, [
                'consent_type' => $type,
                'granted' => true,
                'source' => ClientConsentRecord::SOURCE_IMPORT,
                'metadata' => ['via' => 'csv_import'],
            ]);
        }
    }

    private function normalizePhone(string $raw): string
    {
        $trimmed = trim($raw);
        $digits = preg_replace('/[^\d+]/', '', $trimmed) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = '+'.substr($digits, 2);
        }

        return $digits;
    }

    private function requireTenantId(): string
    {
        $tenantId = $this->tenantContext->id();
        if ($tenantId === null) {
            throw ValidationException::withMessages([
                'tenant' => ['Tenant context is required.'],
            ]);
        }

        return $tenantId;
    }
}
