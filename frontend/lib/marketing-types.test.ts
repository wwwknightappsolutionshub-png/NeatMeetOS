import { describe, expect, it } from 'vitest';
import {
  ACTIVE_TRIGGER_TYPES,
  CAMPAIGN_STATUSES,
  CAMPAIGN_TYPES,
  EXECUTION_STATUSES,
  EXECUTION_STEP_STATUSES,
  MARKETING_CHANNELS,
  MESSAGE_PURPOSES,
  MESSAGE_STATUSES,
  RUN_STATUSES,
  SUPPRESSION_REASONS,
  SUPPRESSION_SOURCES,
  TEMPLATE_PLACEHOLDERS,
  TRIGGER_TYPES,
  WORKFLOW_STATUSES,
  WORKFLOW_STEP_TYPES,
  WORKFLOW_TRIGGERS,
  channelLabel,
  humanizeToken,
  statusTone,
  triggerLabel,
  type AudienceRules,
} from '@/lib/marketing-types';

describe('marketing types', () => {
  it('exports marketing channels including push and in-app', () => {
    expect(MARKETING_CHANNELS).toEqual(['email', 'sms', 'whatsapp', 'push', 'in_app']);
    expect(channelLabel('whatsapp')).toBe('WhatsApp');
    expect(channelLabel('sms')).toBe('SMS');
    expect(channelLabel('push')).toBe('Push');
    expect(channelLabel('in_app')).toBe('In-app');
  });

  it('exports campaign type and status constants', () => {
    expect(CAMPAIGN_TYPES).toContain('broadcast');
    expect(CAMPAIGN_TYPES).toContain('automation');
    expect(CAMPAIGN_STATUSES).toContain('draft');
    expect(CAMPAIGN_STATUSES).toContain('archived');
  });

  it('exports trigger types with an active subset', () => {
    expect(TRIGGER_TYPES).toContain('booking_reminder');
    expect(TRIGGER_TYPES).toContain('win_back');
    expect(TRIGGER_TYPES).toContain('membership_renewal');
    expect(ACTIVE_TRIGGER_TYPES).toHaveLength(4);
    expect(ACTIVE_TRIGGER_TYPES).not.toContain('birthday');
    expect(triggerLabel('rebooking_nudge')).toBe('Rebooking nudge');
  });

  it('exports message and run status enums', () => {
    expect(MESSAGE_STATUSES).toContain('sent');
    expect(MESSAGE_STATUSES).toContain('skipped');
    expect(RUN_STATUSES).toContain('completed');
    expect(RUN_STATUSES).toContain('processing');
  });

  it('exposes supported template placeholders', () => {
    expect(TEMPLATE_PLACEHOLDERS).toContain('client.first_name');
    expect(TEMPLATE_PLACEHOLDERS).toContain('booking.link');
    expect(TEMPLATE_PLACEHOLDERS).toContain('review.link');
  });

  it('supports audience rule shapes with consent flags', () => {
    const rules: AudienceRules = {
      location_ids: ['loc-1'],
      client_tag_ids: ['tag-1', 'tag-2'],
      client_status: 'active',
      requires_email_consent: true,
      requires_sms_consent: false,
      has_future_booking: true,
      last_visit_after: '2026-01-01',
    };
    expect(rules.location_ids).toHaveLength(1);
    expect(rules.client_tag_ids).toHaveLength(2);
    expect(rules.requires_email_consent).toBe(true);
    expect(rules.client_status).toBe('active');
  });

  it('humanizes tokens and maps status tones', () => {
    expect(humanizeToken('win_back')).toBe('Win Back');
    expect(humanizeToken(null)).toBe('—');
    expect(statusTone('completed')).toContain('emerald');
    expect(statusTone('failed')).toContain('red');
  });

  it('exports workflow status, trigger and step constants (Module 10B)', () => {
    expect(WORKFLOW_STATUSES).toEqual(['draft', 'active', 'paused', 'archived']);
    expect(WORKFLOW_TRIGGERS).toContain('client_created');
    expect(WORKFLOW_TRIGGERS).toContain('consent_granted');
    expect(WORKFLOW_TRIGGERS).toContain('consent_withdrawn');
    expect(WORKFLOW_TRIGGERS).toContain('birthday');
    expect(WORKFLOW_TRIGGERS).toContain('manual');
    expect(WORKFLOW_STEP_TYPES).toEqual(['send_message', 'wait', 'tag_client', 'internal_note']);
    expect(triggerLabel('appointment_no_show')).toBe('Appointment no-show');
    expect(triggerLabel('consent_granted')).toBe('Consent granted');
  });

  it('exports execution status enums (Module 10B)', () => {
    expect(EXECUTION_STATUSES).toContain('queued');
    expect(EXECUTION_STATUSES).toContain('running');
    expect(EXECUTION_STATUSES).toContain('completed');
    expect(EXECUTION_STEP_STATUSES).toContain('processing');
    expect(EXECUTION_STEP_STATUSES).toContain('skipped');
  });

  it('exports suppression reason and source constants (Module 10B)', () => {
    expect(SUPPRESSION_REASONS).toContain('unsubscribe');
    expect(SUPPRESSION_REASONS).toContain('hard_bounce');
    expect(SUPPRESSION_REASONS).toContain('complaint');
    expect(SUPPRESSION_SOURCES).toEqual(['client_action', 'staff_action', 'system']);
  });

  it('extends message statuses and purposes for automation (Module 10B)', () => {
    expect(MESSAGE_STATUSES).toContain('queued');
    expect(MESSAGE_STATUSES).toContain('delivered');
    expect(MESSAGE_STATUSES).toContain('suppressed');
    expect(MESSAGE_STATUSES).toContain('unsubscribed');
    expect(MESSAGE_STATUSES).toContain('sent');
    expect(MESSAGE_PURPOSES).toContain('workflow');
  });

  it('maps tones for new automation statuses (Module 10B)', () => {
    expect(statusTone('queued')).toContain('amber');
    expect(statusTone('running')).toContain('blue');
    expect(statusTone('delivered')).toContain('emerald');
    expect(statusTone('suppressed')).toContain('orange');
    expect(statusTone('unsubscribed')).toContain('orange');
  });
});
