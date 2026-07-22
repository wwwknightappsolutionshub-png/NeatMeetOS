import { describe, expect, it } from 'vitest';
import {
  accountStatusLabel,
  accountStatusTone,
  attemptDirectionLabel,
  attemptDriverLabel,
  attemptStatusLabel,
  attemptStatusTone,
  attemptTransportLabel,
  buildIntegrationsOverview,
  categoryLabel,
  driverLabel,
  driversForCategory,
  formatDateTime,
  humanizeToken,
  isLiveAttempt,
  isSimulationFallback,
  recipientSummary,
  sourceDomainLabel,
  sourceTypeLabel,
  testResultLabel,
  testResultTone,
  webhookProcessingStatusLabel,
  webhookProcessingStatusTone,
  type ProviderAccount,
  type ProviderDeliveryAttempt,
  type ProviderWebhookEvent,
} from '@/lib/integrations-types';

describe('integrations types & helpers', () => {
  it('humanizes tokens defensively', () => {
    expect(humanizeToken('payment_gateway')).toBe('Payment Gateway');
    expect(humanizeToken(null)).toBe('—');
  });

  it('labels categories and drivers', () => {
    expect(categoryLabel('email')).toBe('Email');
    expect(categoryLabel('payment_gateway')).toBe('Payment gateway');
    expect(driverLabel('simulation')).toBe('Simulation');
    expect(driverLabel('stripe')).toBe('Stripe');
  });

  it('maps account status labels and tones', () => {
    expect(accountStatusLabel('active')).toBe('Active');
    expect(accountStatusTone('active')).toBe('green');
    expect(accountStatusTone('archived')).toBe('red');
  });

  it('maps attempt status labels and tones', () => {
    expect(attemptStatusLabel('delivered')).toBe('Delivered');
    expect(attemptStatusTone('delivered')).toBe('green');
    expect(attemptStatusTone('failed')).toBe('red');
    expect(attemptDirectionLabel('outbound')).toBe('Outbound');
  });

  it('maps webhook processing status', () => {
    expect(webhookProcessingStatusLabel('received')).toBe('Received');
    expect(webhookProcessingStatusTone('processed')).toBe('green');
    expect(webhookProcessingStatusTone('failed')).toBe('red');
  });

  it('labels source domain and type', () => {
    expect(sourceDomainLabel('notifications')).toBe('Notifications');
    expect(sourceTypeLabel('payment_link')).toBe('Payment link');
  });

  it('formats dates defensively', () => {
    expect(formatDateTime(null)).toBe('—');
    expect(formatDateTime('2026-07-08T10:00:00Z')).toContain('2026');
  });

  it('labels test results', () => {
    expect(testResultLabel('simulation_ok')).toBe('Simulation OK');
    expect(testResultLabel(null)).toBe('Not tested');
  });

  it('detects simulation fallback and driver on attempts', () => {
    const attempt: ProviderDeliveryAttempt = {
      id: 'a1',
      category: 'email',
      source_domain: 'notifications',
      source_type: 'notification_message',
      direction: 'outbound',
      status: 'delivered',
      payload: {},
      metadata: { simulation_fallback: true, driver: 'simulation' },
    };
    expect(isSimulationFallback(attempt)).toBe(true);
    expect(attemptDriverLabel(attempt)).toBe('Simulation');
    expect(recipientSummary({ ...attempt, recipient_address: 'a@b.com' })).toBe('a@b.com');
  });

  it('builds overview summary from lists', () => {
    const accounts: ProviderAccount[] = [
      {
        id: '1',
        name: 'Email',
        category: 'email',
        driver: 'simulation',
        status: 'active',
        is_default: true,
        configuration: {},
        has_credentials: false,
        metadata: {},
      },
      {
        id: '2',
        name: 'Old',
        category: 'sms',
        driver: 'simulation',
        status: 'archived',
        is_default: false,
        configuration: {},
        has_credentials: false,
        metadata: {},
        archived_at: '2026-07-01T00:00:00Z',
      },
    ];
    const attempts: ProviderDeliveryAttempt[] = [
      {
        id: 't1',
        category: 'email',
        source_domain: 'notifications',
        source_type: 'notification_message',
        direction: 'outbound',
        status: 'delivered',
        payload: {},
        metadata: {},
      },
      {
        id: 't2',
        category: 'email',
        source_domain: 'marketing',
        source_type: 'marketing_message',
        direction: 'outbound',
        status: 'failed',
        payload: {},
        metadata: {},
      },
    ];
    const events: ProviderWebhookEvent[] = [
      {
        id: 'e1',
        driver: 'stripe',
        event_type: 'test',
        processing_status: 'received',
        payload: {},
        headers: {},
        metadata: {},
      },
    ];

    const summary = buildIntegrationsOverview(accounts, attempts, events);
    expect(summary.total_accounts).toBe(2);
    expect(summary.active_accounts).toBe(1);
    expect(summary.default_accounts).toBe(1);
    expect(summary.recent_attempts).toBe(2);
    expect(summary.failed_attempts).toBe(1);
    expect(summary.received_webhook_events).toBe(1);
    expect(summary.attempts_truncated).toBe(false);
    expect(summary.events_truncated).toBe(false);
  });

  it('labels test results for live drivers', () => {
    expect(testResultLabel('credentials_valid_stub')).toBe('Credentials valid (stub transport)');
    expect(testResultLabel('credentials_missing')).toBe('Credentials missing or incomplete');
    expect(testResultTone('credentials_missing')).toBe('red');
  });

  it('detects live vs simulation attempts', () => {
    const live: ProviderDeliveryAttempt = {
      id: 'live1',
      category: 'email',
      source_domain: 'notifications',
      source_type: 'notification_message',
      direction: 'outbound',
      status: 'delivered',
      payload: {},
      metadata: { driver: 'mailgun', simulated: false, transport: 'stub' },
    };
    expect(isLiveAttempt(live)).toBe(true);
    expect(attemptTransportLabel(live)).toBe('Live (stub)');

    const fallback: ProviderDeliveryAttempt = {
      ...live,
      id: 'fb1',
      metadata: { simulation_fallback: true, driver: 'mailgun' },
    };
    expect(isLiveAttempt(fallback)).toBe(false);
    expect(attemptTransportLabel(fallback)).toBe('Simulation fallback');
  });

  it('filters drivers by category', () => {
    expect(driversForCategory('email')).toContain('mailgun');
    expect(driversForCategory('email')).not.toContain('stripe');
    expect(driversForCategory('payment_gateway')).toContain('stripe');
  });

  it('flags overview truncation when list hits window cap', () => {
    const accounts: ProviderAccount[] = [];
    const attempts = Array.from({ length: 200 }, (_, i) => ({
      id: `t${i}`,
      category: 'email' as const,
      source_domain: 'notifications' as const,
      source_type: 'notification_message' as const,
      direction: 'outbound' as const,
      status: 'delivered' as const,
      payload: {},
      metadata: {},
    }));
    const events: ProviderWebhookEvent[] = [];

    const summary = buildIntegrationsOverview(accounts, attempts, events);
    expect(summary.attempts_truncated).toBe(true);
    expect(summary.events_truncated).toBe(false);
  });
});
