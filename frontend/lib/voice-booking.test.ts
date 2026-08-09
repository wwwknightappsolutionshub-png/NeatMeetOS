import { describe, expect, it } from 'vitest';
import {
  buildTelLink,
  buildWaMeLink,
  describeService,
  detectVoiceIntent,
  matchServicesFromSpeech,
  parseRelativeDate,
  summarizeSlotsSpeech,
} from './voice-booking';

describe('voice-booking helpers', () => {
  it('detects core intents', () => {
    expect(detectVoiceIntent('list services')).toBe('list_services');
    expect(detectVoiceIntent('how much is a cut')).toBe('service_detail');
    expect(detectVoiceIntent('any slots tomorrow')).toBe('availability');
    expect(detectVoiceIntent('book me in')).toBe('book');
    expect(detectVoiceIntent('talk to a human')).toBe('talk_human');
    expect(detectVoiceIntent('call the salon')).toBe('call_office');
  });

  it('matches services from speech', () => {
    const services = [
      {
        id: '1',
        name: 'Signature Cut',
        duration_minutes: 45,
        base_price_cents: 4500,
        description: 'Classic trim',
      },
      {
        id: '2',
        name: 'Colour',
        duration_minutes: 90,
        base_price_cents: 9000,
      },
    ];
    expect(matchServicesFromSpeech('signature cut please', services)[0]?.id).toBe('1');
  });

  it('parses relative dates and summarizes slots', () => {
    const now = new Date('2026-08-09T12:00:00');
    expect(parseRelativeDate('tomorrow', now)).toBe('2026-08-10');
    expect(summarizeSlotsSpeech([])).toMatch(/no open slots/i);
    expect(
      summarizeSlotsSpeech([{ starts_at: '2026-08-10T10:00:00', provider_name: 'Alex' }], 1),
    ).toMatch(/Alex/);
  });

  it('describes services and builds escape links', () => {
    expect(
      describeService({
        id: '1',
        name: 'Cut',
        duration_minutes: 30,
        base_price_cents: 2500,
        deposit_required: true,
        deposit_amount_cents: 500,
      }),
    ).toMatch(/£25\.00/);
    expect(buildWaMeLink('+44 7700 900123', 'Hello')).toContain('wa.me/447700900123');
    expect(buildTelLink('+44 7700 900123')).toBe('tel:+447700900123');
  });
});
