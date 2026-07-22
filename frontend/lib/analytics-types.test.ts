import { describe, expect, it } from 'vitest';
import {
  channelLabel,
  exportFormatLabel,
  exportStatusLabel,
  exportStatusTone,
  formatDay,
  formatMoneyCents,
  formatNumber,
  formatRangeLabel,
  formatRate,
  humanizeToken,
  movementTypeLabel,
  reportSupportsLocation,
  reportSupportsProvider,
  reportTypeLabel,
  scheduleSummary,
  type AnalyticsExportJob,
  type AnalyticsOverview,
  type AnalyticsSavedReport,
  type CommunicationsAnalytics,
  type RevenueAnalytics,
} from '@/lib/analytics-types';

describe('analytics types & helpers', () => {
  it('formats cents as GBP currency', () => {
    expect(formatMoneyCents(5000)).toBe('£50.00');
    expect(formatMoneyCents(0)).toBe('£0.00');
    expect(formatMoneyCents(null)).toBe('£0.00');
    expect(formatMoneyCents(undefined)).toBe('£0.00');
  });

  it('formats rates as percentages defensively', () => {
    expect(formatRate(0.1234)).toBe('12.3%');
    expect(formatRate(0)).toBe('0.0%');
    expect(formatRate(null)).toBe('—');
    expect(formatRate(undefined)).toBe('—');
  });

  it('formats plain numbers and handles missing values', () => {
    expect(formatNumber(1234)).toBe('1,234');
    expect(formatNumber(0)).toBe('0');
    expect(formatNumber(null)).toBe('—');
  });

  it('humanizes tokens and labels', () => {
    expect(humanizeToken('service_consumption')).toBe('Service Consumption');
    expect(humanizeToken(null)).toBe('—');
    expect(movementTypeLabel('purchase_receipt')).toBe('Purchase receipt');
    expect(movementTypeLabel('service_consumption')).toBe('Service consumption');
    expect(channelLabel('whatsapp')).toBe('WhatsApp');
    expect(channelLabel('sms')).toBe('SMS');
  });

  it('formats day and range labels defensively', () => {
    expect(formatDay(null)).toBe('—');
    expect(formatDay('2026-07-08')).toContain('Jul');
    expect(formatRangeLabel(null)).toBe('—');
    expect(
      formatRangeLabel({ from: '2026-06-09T00:00:00Z', to: '2026-07-08T23:59:59Z', days: 30 }),
    ).toContain('30 days');
  });

  it('supports the overview response shape', () => {
    const overview: AnalyticsOverview = {
      range: { from: '2026-06-09', to: '2026-07-08', days: 30 },
      bookings: {
        total_appointments: 3,
        completed_appointments: 1,
        cancelled_appointments: 1,
        no_show_appointments: 1,
        checked_in_appointments: 0,
        walk_in_appointments: 0,
        average_booking_value_cents: 0,
      },
      payments: {
        total_payment_collected_cents: 5000,
        deposit_collected_cents: 1500,
        deposit_refunded_cents: 0,
        refund_total_cents: 1000,
        failed_payments_count: 0,
      },
      pos: {
        completed_checkouts_count: 1,
        gross_sales_cents: 8000,
        refund_value_cents: 500,
        average_checkout_value_cents: 8000,
      },
      clients: {
        total_clients: 1,
        new_clients_in_period: 1,
        active_clients: 1,
        marketing_email_opt_in_count: 1,
        marketing_sms_opt_in_count: 0,
      },
      memberships: {
        active_memberships: 1,
        active_packages: 0,
        wallet_liability_cents: 2500,
        loyalty_points_outstanding: 0,
      },
      inventory: {
        low_stock_items_count: 1,
        stock_adjustments_count: 1,
        stock_consumption_events_count: 1,
      },
      marketing: {
        campaigns_count: 1,
        messages_sent_count: 1,
        messages_failed_count: 1,
        workflow_executions_count: 0,
      },
      notifications: {
        messages_sent_count: 1,
        messages_failed_count: 1,
        messages_suppressed_count: 0,
      },
    };
    expect(overview.payments.total_payment_collected_cents).toBe(5000);
    expect(overview.pos.completed_checkouts_count).toBe(1);
  });

  it('supports revenue and communications response shapes', () => {
    const revenue: RevenueAnalytics = {
      range: { from: '2026-06-09', to: '2026-07-08', days: 30 },
      summary: {
        payments: {
          total_payment_collected_cents: 5000,
          deposit_collected_cents: 1500,
          deposit_refunded_cents: 0,
          refund_total_cents: 1000,
          failed_payments_count: 0,
          net_collected_cents: 4000,
        },
        pos: {
          completed_checkouts_count: 1,
          gross_sales_cents: 8000,
          refund_value_cents: 500,
          average_checkout_value_cents: 8000,
        },
      },
      daily: [{ date: '2026-07-08', collected_cents: 5000, pos_sales_cents: 8000 }],
      payment_status_breakdown: [{ status: 'succeeded', total: 1, amount_cents: 5000 }],
      payment_type_breakdown: [{ transaction_type: 'sale', total: 1, amount_cents: 5000 }],
      provider_breakdown: [{ provider: 'manual', total: 1, amount_cents: 5000 }],
    };
    expect(revenue.summary.payments.net_collected_cents).toBe(4000);
    expect(revenue.daily[0].pos_sales_cents).toBe(8000);

    const comms: CommunicationsAnalytics = {
      range: { from: '2026-06-09', to: '2026-07-08', days: 30 },
      marketing: {
        campaigns_count: 1,
        messages_sent_count: 1,
        messages_failed_count: 1,
        messages_suppressed_count: 0,
        workflow_executions_count: 0,
        workflow_execution_status_breakdown: [],
        by_channel: [{ channel: 'email', total: 2, sent: 1, failed: 1, suppressed: 0 }],
      },
      notifications: {
        messages_sent_count: 1,
        messages_failed_count: 1,
        messages_suppressed_count: 0,
        by_channel: [{ channel: 'email', total: 2, sent: 1, failed: 1, suppressed: 0 }],
      },
    };
    expect(comms.marketing.by_channel[0].channel).toBe('email');
    expect(comms.notifications.messages_sent_count).toBe(1);
  });

  it('labels report types and export formats', () => {
    expect(reportTypeLabel('bookings')).toBe('Bookings');
    expect(reportTypeLabel('communications')).toBe('Communications');
    expect(exportFormatLabel('csv')).toBe('CSV');
    expect(exportFormatLabel('json')).toBe('JSON');
  });

  it('maps export status labels and tones', () => {
    expect(exportStatusLabel('completed')).toBe('Completed');
    expect(exportStatusLabel('failed')).toBe('Failed');
    expect(exportStatusTone('completed')).toBe('green');
    expect(exportStatusTone('processing')).toBe('amber');
    expect(exportStatusTone('failed')).toBe('red');
    expect(exportStatusTone('unknown')).toBe('zinc');
  });

  it('summarises schedule config and filter capability by report type', () => {
    const scheduled: Pick<AnalyticsSavedReport, 'is_scheduled' | 'schedule_frequency' | 'schedule_day_of_week' | 'schedule_day_of_month' | 'schedule_time'> = {
      is_scheduled: true,
      schedule_frequency: 'weekly',
      schedule_day_of_week: 1,
      schedule_day_of_month: null,
      schedule_time: '09:00',
    };
    expect(scheduleSummary(scheduled)).toContain('Weekly');
    expect(scheduleSummary({ ...scheduled, is_scheduled: false })).toBeNull();

    expect(reportSupportsLocation('clients')).toBe(true);
    expect(reportSupportsLocation('communications')).toBe(false);
    expect(reportSupportsProvider('revenue')).toBe(true);
    expect(reportSupportsProvider('inventory')).toBe(false);
  });

  it('supports saved report and export job typing', () => {
    const report: AnalyticsSavedReport = {
      id: 'r1',
      name: 'Weekly bookings',
      report_type: 'bookings',
      filters: { from: '2026-07-01' },
      export_format: 'csv',
      is_scheduled: false,
      schedule_frequency: null,
      schedule_day_of_week: null,
      schedule_day_of_month: null,
      schedule_time: null,
      last_run_at: null,
      archived_at: null,
      created_at: '2026-07-08T10:00:00Z',
      updated_at: '2026-07-08T10:00:00Z',
    };

    const job: AnalyticsExportJob = {
      id: 'j1',
      report_type: 'bookings',
      export_format: 'csv',
      status: 'completed',
      filters: report.filters,
      file_name: 'analytics-bookings.csv',
      row_count: 30,
      started_at: '2026-07-08T10:01:00Z',
      completed_at: '2026-07-08T10:01:01Z',
      failed_at: null,
      failure_reason: null,
      saved_report: { id: report.id, name: report.name },
      download_url: '/api/v1/admin/analytics/exports/j1/download',
      created_at: '2026-07-08T10:01:00Z',
      updated_at: '2026-07-08T10:01:01Z',
    };

    expect(report.report_type).toBe('bookings');
    expect(job.saved_report?.name).toBe('Weekly bookings');
    expect(job.download_url).toContain('/download');
  });
});
