import type { MemberNotice } from '@/services/member-portal.service';

const URL_PATTERN = /https?:\/\/[^\s]+/gi;

const BOOKING_PURPOSE_PREFIX = 'booking_';

export function stripUrlsFromNoticeBody(body: string, href?: string | null): string {
  let text = body.trim();
  text = text.replace(URL_PATTERN, ' ').replace(/\s+/g, ' ').trim();
  if (href) {
    text = text.replace(/\s*Manage your booking\s*$/i, '').trim();
  }
  return text;
}

export function isBookingNotice(notice: MemberNotice): boolean {
  const purpose = typeof notice.data?.purpose === 'string' ? notice.data.purpose : '';
  if (purpose.startsWith(BOOKING_PURPOSE_PREFIX)) return true;
  if (notice.type === 'notification.in_app') {
    return /appointment|booking|reminder|confirmed|cancelled|reschedule|waitlist/i.test(
      notice.title,
    );
  }
  return false;
}

export function partitionMemberNotices(notices: MemberNotice[]): {
  bookingNotices: MemberNotice[];
  salonUpdates: MemberNotice[];
} {
  const bookingNotices: MemberNotice[] = [];
  const salonUpdates: MemberNotice[] = [];
  for (const notice of notices) {
    if (isBookingNotice(notice)) {
      bookingNotices.push(notice);
    } else {
      salonUpdates.push(notice);
    }
  }
  return { bookingNotices, salonUpdates };
}

export function noticeManageHref(notice: MemberNotice): string | null {
  if (notice.href?.trim()) return notice.href.trim();
  const match = notice.body.match(URL_PATTERN);
  return match?.[0] ?? null;
}

export function noticeManageLabel(notice: MemberNotice): string {
  if (/reminder/i.test(notice.title)) return 'View appointment';
  if (/confirmed/i.test(notice.title)) return 'Manage booking';
  if (/cancel/i.test(notice.title)) return 'View details';
  return 'Open link';
}
