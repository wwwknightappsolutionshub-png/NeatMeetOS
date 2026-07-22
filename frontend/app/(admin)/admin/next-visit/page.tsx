'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminModuleChrome } from '@/components/admin/AdminModuleChrome';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { Appointment } from '@/lib/booking-types';
import {
  fetchAdminNextVisitUpcoming,
  nudgeAdminNextVisit,
  type ClientThreadMessage,
} from '@/services/next-visit.service';

export default function AdminNextVisitPage() {
  const [upcoming, setUpcoming] = useState<Appointment[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [clientId, setClientId] = useState('');
  const [body, setBody] = useState('');
  const [subject, setSubject] = useState('Next visit nudge');
  const [includeWhatsapp, setIncludeWhatsapp] = useState(true);
  const [sending, setSending] = useState(false);
  const [lastNudge, setLastNudge] = useState<ClientThreadMessage | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    fetchAdminNextVisitUpcoming()
      .then(setUpcoming)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  async function handleNudge(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setLastNudge(null);
    if (!clientId.trim() || !body.trim()) {
      setError('Client ID and message body are required');
      return;
    }
    setSending(true);
    try {
      const message = await nudgeAdminNextVisit({
        client_id: clientId.trim(),
        body: body.trim(),
        subject: subject.trim() || undefined,
        include_whatsapp_deeplink: includeWhatsapp,
      });
      setLastNudge(message);
      setBody('');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Nudge failed');
    } finally {
      setSending(false);
    }
  }

  function prefillFromAppointment(appt: Appointment) {
    if (appt.client_id) setClientId(appt.client_id);
    const when = appt.starts_at
      ? new Date(appt.starts_at).toLocaleString(undefined, {
          weekday: 'short',
          month: 'short',
          day: 'numeric',
          hour: '2-digit',
          minute: '2-digit',
        })
      : 'soon';
    const name = appt.client?.resolved_display_name || 'there';
    setBody(`Hi ${name}, just a gentle nudge about your next visit on ${when}. Reply if you need to reschedule.`);
  }

  return (
    <AdminModuleChrome eyebrow="Next visit" title="Upcoming & nudges" links={[]}>
      {error ? <ErrorAlert message={error} /> : null}

      <div className="mb-4 grid gap-4 lg:grid-cols-2">
        <Card title="Send nudge">
          <form onSubmit={(e) => void handleNudge(e)} className="grid gap-3">
            <Field label="Client ID">
              <input
                className={inputClass}
                value={clientId}
                onChange={(e) => setClientId(e.target.value)}
                placeholder="UUID from client profile or upcoming list"
                required
              />
            </Field>
            <Field label="Subject">
              <input
                className={inputClass}
                value={subject}
                onChange={(e) => setSubject(e.target.value)}
              />
            </Field>
            <Field label="Message">
              <textarea
                className={inputClass}
                rows={4}
                value={body}
                onChange={(e) => setBody(e.target.value)}
                required
              />
            </Field>
            <label className="flex items-center gap-2 text-sm text-zinc-700">
              <input
                type="checkbox"
                checked={includeWhatsapp}
                onChange={(e) => setIncludeWhatsapp(e.target.checked)}
              />
              Include WhatsApp Mode A deeplink (wa.me)
            </label>
            <Button type="submit" disabled={sending}>
              {sending ? 'Sending…' : 'Send nudge'}
            </Button>
            {lastNudge ? (
              <div className="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                Nudge sent via {lastNudge.channel}.
                {lastNudge.whatsapp_deeplink ? (
                  <>
                    {' '}
                    <a
                      href={lastNudge.whatsapp_deeplink}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="font-semibold underline"
                    >
                      Open WhatsApp link
                    </a>
                  </>
                ) : null}
              </div>
            ) : null}
          </form>
        </Card>

        <Card title="How it works">
          <p className="text-sm text-zinc-600">
            Upcoming lists appointments booked as next visits after member check-in. Use nudge to
            remind a client in-app, and optionally generate a Mode A WhatsApp deeplink for staff to
            open and send.
          </p>
        </Card>
      </div>

      <Card title="Upcoming next visits">
        {loading ? <LoadingState /> : null}
        {!loading && upcoming.length === 0 ? (
          <p className="text-sm text-zinc-500">No upcoming next-visit appointments.</p>
        ) : null}
        <ul className="divide-y divide-zinc-100">
          {upcoming.map((appt) => (
            <li
              key={appt.id}
              className="flex flex-wrap items-center justify-between gap-3 py-3"
            >
              <div>
                <p className="font-medium text-zinc-900">
                  {appt.client?.resolved_display_name || 'Client'}
                </p>
                <p className="text-xs text-zinc-500">
                  {appt.starts_at
                    ? new Date(appt.starts_at).toLocaleString()
                    : 'Time TBC'}
                  {appt.location?.name ? ` · ${appt.location.name}` : ''}
                  {appt.team_member?.display_name
                    ? ` · ${appt.team_member.display_name}`
                    : ''}
                  {appt.booking_reference ? ` · ${appt.booking_reference}` : ''}
                </p>
                <p className="mt-0.5 text-[11px] text-zinc-400">Client ID: {appt.client_id}</p>
              </div>
              <Button
                type="button"
                variant="secondary"
                onClick={() => prefillFromAppointment(appt)}
              >
                Prefill nudge
              </Button>
            </li>
          ))}
        </ul>
      </Card>
    </AdminModuleChrome>
  );
}
