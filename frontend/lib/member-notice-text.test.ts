import { describe, expect, it } from 'vitest';
import {
  isBookingNotice,
  partitionMemberNotices,
  stripUrlsFromNoticeBody,
} from '@/lib/member-notice-text';
import type { MemberNotice } from '@/services/member-portal.service';

function notice(partial: Partial<MemberNotice>): MemberNotice {
  return {
    id: '1',
    type: 'notification.in_app',
    title: 'Test',
    body: '',
    ...partial,
  };
}

describe('member-notice-text', () => {
  it('strips manage link url from booking notice body', () => {
    const body =
      'Reminder: your appointment is at Tue, Aug 25, 2026 6:30 PM. Reference NM-EWYYNLIF. Manage your booking https://neatmeet.prohost.cloud/book/spring/manage?token=abc';
    const cleaned = stripUrlsFromNoticeBody(body, 'https://neatmeet.prohost.cloud/book/spring/manage?token=abc');
    expect(cleaned).toBe(
      'Reminder: your appointment is at Tue, Aug 25, 2026 6:30 PM. Reference NM-EWYYNLIF.',
    );
    expect(cleaned).not.toContain('https://');
  });

  it('partitions booking notices from salon updates', () => {
    const notices = [
      notice({
        id: 'a',
        title: 'Booking confirmed (NM-1)',
        data: { purpose: 'booking_confirmation' },
      }),
      notice({
        id: 'b',
        type: 'marketing.in_app',
        title: 'Welcome to Spring unisex saloon',
        data: { purpose: 'crm_join_welcome' },
      }),
    ];
    const { bookingNotices, salonUpdates } = partitionMemberNotices(notices);
    expect(bookingNotices).toHaveLength(1);
    expect(salonUpdates).toHaveLength(1);
    expect(isBookingNotice(notices[0]!)).toBe(true);
    expect(isBookingNotice(notices[1]!)).toBe(false);
  });
});
