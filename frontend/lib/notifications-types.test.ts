import { describe, expect, it } from 'vitest';
import {
  NOTIFICATION_ATTEMPT_PROVIDERS,
  NOTIFICATION_CATEGORIES,
  NOTIFICATION_CHANNELS,
  NOTIFICATION_DIRECTIONS,
  NOTIFICATION_MESSAGE_STATUSES,
  NOTIFICATION_PURPOSES,
  NOTIFICATION_SOURCE_TYPES,
  canMarkDelivered,
  channelLabel,
  humanizeToken,
  isCancellable,
  purposeLabel,
  sourceTypeLabel,
  statusTone,
  type NotificationByPurposeRow,
  type NotificationFailureRow,
  type NotificationReportingSummary,
} from '@/lib/notifications-types';

describe('notifications types', () => {
  it('exports notification channels with labels', () => {
    expect(NOTIFICATION_CHANNELS).toEqual(['email', 'sms', 'whatsapp', 'push', 'in_app', 'internal_note']);
    expect(channelLabel('whatsapp')).toBe('WhatsApp');
    expect(channelLabel('sms')).toBe('SMS');
    expect(channelLabel('in_app')).toBe('In-app');
    expect(channelLabel('internal_note')).toBe('Internal note');
  });

  it('exports categories', () => {
    expect(NOTIFICATION_CATEGORIES).toEqual(['booking', 'payments', 'membership', 'crm', 'general']);
  });

  it('exports message statuses', () => {
    expect(NOTIFICATION_MESSAGE_STATUSES).toContain('queued');
    expect(NOTIFICATION_MESSAGE_STATUSES).toContain('sent');
    expect(NOTIFICATION_MESSAGE_STATUSES).toContain('delivered');
    expect(NOTIFICATION_MESSAGE_STATUSES).toContain('failed');
    expect(NOTIFICATION_MESSAGE_STATUSES).toContain('cancelled');
    expect(NOTIFICATION_MESSAGE_STATUSES).toContain('suppressed');
  });

  it('exports directions', () => {
    expect(NOTIFICATION_DIRECTIONS).toEqual(['outbound', 'inbound']);
  });

  it('exports purposes with labels', () => {
    expect(NOTIFICATION_PURPOSES).toContain('booking_reminder');
    expect(NOTIFICATION_PURPOSES).toContain('payment_link');
    expect(NOTIFICATION_PURPOSES).toContain('manual_client_message');
    expect(purposeLabel('booking_reminder')).toBe('Booking reminder');
    expect(purposeLabel('membership_expiry_notice')).toBe('Membership expiry');
  });

  it('exports source types and attempt providers', () => {
    expect(NOTIFICATION_SOURCE_TYPES).toContain('booking');
    expect(NOTIFICATION_SOURCE_TYPES).toContain('manual');
    expect(NOTIFICATION_SOURCE_TYPES).toContain('system');
    expect(NOTIFICATION_ATTEMPT_PROVIDERS).toContain('simulation');
    expect(sourceTypeLabel('memberships')).toBe('Memberships');
  });

  it('maps status tones', () => {
    expect(statusTone('sent')).toContain('emerald');
    expect(statusTone('delivered')).toContain('emerald');
    expect(statusTone('queued')).toContain('amber');
    expect(statusTone('processing')).toContain('blue');
    expect(statusTone('failed')).toContain('red');
    expect(statusTone('suppressed')).toContain('orange');
    expect(statusTone(null)).toContain('zinc');
  });

  it('humanizes tokens', () => {
    expect(humanizeToken('manual_client_message')).toBe('Manual Client Message');
    expect(humanizeToken(null)).toBe('—');
  });

  it('computes message action availability', () => {
    expect(isCancellable('queued')).toBe(true);
    expect(isCancellable('processing')).toBe(true);
    expect(isCancellable('sent')).toBe(false);
    expect(canMarkDelivered('sent')).toBe(true);
    expect(canMarkDelivered('queued')).toBe(false);
  });

  it('supports reporting response shapes', () => {
    const summary: NotificationReportingSummary = {
      period: { from: '2026-01-01T00:00:00Z', to: '2026-01-31T00:00:00Z' },
      total: 10,
      successful: 7,
      failed: 2,
      suppressed: 1,
      by_status: { sent: 7, failed: 2, suppressed: 1 },
      by_channel: { email: 8, sms: 2 },
    };
    expect(summary.total).toBe(10);
    expect(summary.by_status.sent).toBe(7);

    const failure: NotificationFailureRow = {
      message_id: 'm-1',
      channel: 'email',
      purpose: 'booking_reminder',
      status: 'failed',
      failure_reason: 'Simulated provider failure.',
    };
    expect(failure.status).toBe('failed');

    const byPurpose: NotificationByPurposeRow = {
      purpose: 'booking_reminder',
      total: 3,
      by_status: { sent: 2, failed: 1 },
    };
    expect(byPurpose.total).toBe(3);
  });
});
