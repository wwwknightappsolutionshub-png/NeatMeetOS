<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Enums\AnalyticsReportType;

/**
 * Flattens the structured analytics payloads produced by the 12A services into
 * a compact, stable set of CSV rows (one logical row set per report type).
 *
 * Design notes:
 *  - CSV rows favour the most operationally useful "primary" row set for each
 *    report type (daily series where one exists, otherwise a single summary
 *    row / breakdown rows). We deliberately do not denormalise every nested
 *    series into one giant sheet.
 *  - JSON export keeps the full structured payload (handled by the caller); the
 *    transformer only owns the CSV flattening + header contract.
 */
class AnalyticsExportTransformer
{
    /**
     * Build CSV header + rows for the given report type + payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array{headers: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    public function toCsv(string $reportType, array $payload): array
    {
        return match ($reportType) {
            AnalyticsReportType::OVERVIEW => $this->overviewCsv($payload),
            AnalyticsReportType::BOOKINGS => $this->bookingsCsv($payload),
            AnalyticsReportType::REVENUE => $this->revenueCsv($payload),
            AnalyticsReportType::CLIENTS => $this->clientsCsv($payload),
            AnalyticsReportType::INVENTORY => $this->inventoryCsv($payload),
            AnalyticsReportType::COMMUNICATIONS => $this->communicationsCsv($payload),
            default => ['headers' => [], 'rows' => []],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{headers: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    private function overviewCsv(array $payload): array
    {
        $bookings = $payload['bookings'] ?? [];
        $payments = $payload['payments'] ?? [];
        $pos = $payload['pos'] ?? [];
        $clients = $payload['clients'] ?? [];
        $memberships = $payload['memberships'] ?? [];
        $inventory = $payload['inventory'] ?? [];
        $marketing = $payload['marketing'] ?? [];
        $notifications = $payload['notifications'] ?? [];

        $columns = [
            'total_appointments' => $bookings['total_appointments'] ?? 0,
            'completed_appointments' => $bookings['completed_appointments'] ?? 0,
            'cancelled_appointments' => $bookings['cancelled_appointments'] ?? 0,
            'no_show_appointments' => $bookings['no_show_appointments'] ?? 0,
            'total_payment_collected_cents' => $payments['total_payment_collected_cents'] ?? 0,
            'deposit_collected_cents' => $payments['deposit_collected_cents'] ?? 0,
            'refund_total_cents' => $payments['refund_total_cents'] ?? 0,
            'pos_completed_checkouts_count' => $pos['completed_checkouts_count'] ?? 0,
            'pos_gross_sales_cents' => $pos['gross_sales_cents'] ?? 0,
            'total_clients' => $clients['total_clients'] ?? 0,
            'new_clients_in_period' => $clients['new_clients_in_period'] ?? 0,
            'active_clients' => $clients['active_clients'] ?? 0,
            'active_memberships' => $memberships['active_memberships'] ?? 0,
            'active_packages' => $memberships['active_packages'] ?? 0,
            'low_stock_items_count' => $inventory['low_stock_items_count'] ?? 0,
            'marketing_messages_sent_count' => $marketing['messages_sent_count'] ?? 0,
            'marketing_messages_failed_count' => $marketing['messages_failed_count'] ?? 0,
            'notification_messages_sent_count' => $notifications['messages_sent_count'] ?? 0,
            'notification_messages_failed_count' => $notifications['messages_failed_count'] ?? 0,
        ];

        return [
            'headers' => array_keys($columns),
            'rows' => [array_values($columns)],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{headers: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    private function bookingsCsv(array $payload): array
    {
        $headers = ['date', 'total', 'completed'];
        $rows = [];
        foreach ($payload['daily'] ?? [] as $day) {
            $rows[] = [
                $day['date'] ?? '',
                (int) ($day['total'] ?? 0),
                (int) ($day['completed'] ?? 0),
            ];
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{headers: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    private function revenueCsv(array $payload): array
    {
        $headers = ['date', 'collected_cents', 'pos_sales_cents'];
        $rows = [];
        foreach ($payload['daily'] ?? [] as $day) {
            $rows[] = [
                $day['date'] ?? '',
                (int) ($day['collected_cents'] ?? 0),
                (int) ($day['pos_sales_cents'] ?? 0),
            ];
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{headers: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    private function clientsCsv(array $payload): array
    {
        $headers = ['date', 'new_clients'];
        $rows = [];
        foreach ($payload['growth'] ?? [] as $day) {
            $rows[] = [
                $day['date'] ?? '',
                (int) ($day['new_clients'] ?? 0),
            ];
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{headers: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    private function inventoryCsv(array $payload): array
    {
        $headers = ['item_id', 'item_name', 'item_type', 'on_hand_quantity', 'reorder_point'];
        $rows = [];
        foreach ($payload['low_stock'] ?? [] as $item) {
            $rows[] = [
                $item['item_id'] ?? '',
                $item['item_name'] ?? '',
                $item['item_type'] ?? '',
                $item['on_hand_quantity'] ?? 0,
                $item['reorder_point'] ?? 0,
            ];
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{headers: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    private function communicationsCsv(array $payload): array
    {
        $headers = ['domain', 'channel', 'total', 'sent', 'failed', 'suppressed'];
        $rows = [];

        foreach (['marketing', 'notifications'] as $domain) {
            foreach ($payload[$domain]['by_channel'] ?? [] as $channel) {
                $rows[] = [
                    $domain,
                    $channel['channel'] ?? '',
                    (int) ($channel['total'] ?? 0),
                    (int) ($channel['sent'] ?? 0),
                    (int) ($channel['failed'] ?? 0),
                    (int) ($channel['suppressed'] ?? 0),
                ];
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }
}
