/** Calendar helpers for public booking confirmation. */

export function buildGoogleCalendarUrl(opts: {
  title: string;
  startsAt: string;
  endsAt: string;
  details?: string;
  location?: string;
}): string {
  const start = toGoogleDate(opts.startsAt);
  const end = toGoogleDate(opts.endsAt);
  const params = new URLSearchParams({
    action: 'TEMPLATE',
    text: opts.title,
    dates: `${start}/${end}`,
  });
  if (opts.details) params.set('details', opts.details);
  if (opts.location) params.set('location', opts.location);
  return `https://calendar.google.com/calendar/render?${params.toString()}`;
}

export function downloadIcsFile(opts: {
  title: string;
  startsAt: string;
  endsAt: string;
  description?: string;
  location?: string;
  filename?: string;
}): void {
  const ics = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//NeatMeet OS//Booking//EN',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'BEGIN:VEVENT',
    `UID:${crypto.randomUUID()}@neatmeet`,
    `DTSTAMP:${toIcsDate(new Date().toISOString())}`,
    `DTSTART:${toIcsDate(opts.startsAt)}`,
    `DTEND:${toIcsDate(opts.endsAt)}`,
    `SUMMARY:${escapeIcs(opts.title)}`,
    opts.description ? `DESCRIPTION:${escapeIcs(opts.description)}` : null,
    opts.location ? `LOCATION:${escapeIcs(opts.location)}` : null,
    'END:VEVENT',
    'END:VCALENDAR',
  ]
    .filter(Boolean)
    .join('\r\n');

  const blob = new Blob([ics], { type: 'text/calendar;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = opts.filename ?? 'appointment.ics';
  a.click();
  URL.revokeObjectURL(url);
}

function toGoogleDate(iso: string): string {
  return new Date(iso).toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
}

function toIcsDate(iso: string): string {
  return toGoogleDate(iso);
}

function escapeIcs(value: string): string {
  return value.replace(/\\/g, '\\\\').replace(/;/g, '\\;').replace(/,/g, '\\,').replace(/\n/g, '\\n');
}
