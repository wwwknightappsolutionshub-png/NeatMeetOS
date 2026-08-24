'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useCallback, useEffect, useState } from 'react';
import { AdminCrmShell } from '@/components/admin/crm/AdminCrmShell';
import { DocumentsTab } from '@/components/admin/crm/tabs/DocumentsTab';
import { FormulasTab } from '@/components/admin/crm/tabs/FormulasTab';
import { PhotosTab } from '@/components/admin/crm/tabs/PhotosTab';
import {
  EmptyState,
  ErrorAlert,
  Field,
  inputClass,
  LoadingState,
} from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { Client, ClientNote, ClientTag, ClientTimelineEvent, ClientVisit } from '@/lib/crm-types';
import { CONSENT_TYPES, CONSENT_SOURCES, LOYALTY_DISPLAY_STATUSES, NOTE_TYPES } from '@/lib/crm-types';
import type { TeamMember } from '@/lib/identity-types';
import {
  createClientNote,
  fetchClient,
  fetchClientConsents,
  fetchClientNotes,
  fetchClientTags,
  fetchClientTimeline,
  fetchClientVisits,
  recordClientConsent,
  syncClientTags,
  updateClient,
} from '@/services/crm.service';
import { fetchTeamMembers } from '@/services/identity.service';
import {
  channelLabel,
  formatDateTime as formatNotificationDateTime,
  purposeLabel,
  statusTone as notificationStatusTone,
  type NotificationTimelineEntry,
} from '@/lib/notifications-types';
import { fetchClientNotificationTimeline } from '@/services/notifications.service';

const MONTHS = [
  { value: 1, label: 'January' },
  { value: 2, label: 'February' },
  { value: 3, label: 'March' },
  { value: 4, label: 'April' },
  { value: 5, label: 'May' },
  { value: 6, label: 'June' },
  { value: 7, label: 'July' },
  { value: 8, label: 'August' },
  { value: 9, label: 'September' },
  { value: 10, label: 'October' },
  { value: 11, label: 'November' },
  { value: 12, label: 'December' },
];

type Tab =
  | 'profile'
  | 'notes'
  | 'consent'
  | 'formulas'
  | 'photos'
  | 'documents'
  | 'visits'
  | 'communications'
  | 'timeline';

export default function ClientDetailPage() {
  const params = useParams();
  const clientId = params.clientId as string;

  const [client, setClient] = useState<Client | null>(null);
  const [tags, setTags] = useState<ClientTag[]>([]);
  const [teamMembers, setTeamMembers] = useState<TeamMember[]>([]);
  const [selectedTags, setSelectedTags] = useState<string[]>([]);
  const [notes, setNotes] = useState<ClientNote[]>([]);
  const [consentCurrent, setConsentCurrent] = useState<
    Record<string, { granted: boolean; recorded_at: string | null; source: string }>
  >({});
  const [consentHistory, setConsentHistory] = useState<
    Awaited<ReturnType<typeof fetchClientConsents>>['history']
  >([]);
  const [timeline, setTimeline] = useState<ClientTimelineEvent[]>([]);
  const [visits, setVisits] = useState<ClientVisit[]>([]);
  const [visitsLoaded, setVisitsLoaded] = useState(false);
  const [communications, setCommunications] = useState<NotificationTimelineEntry[]>([]);
  const [communicationsLoaded, setCommunicationsLoaded] = useState(false);
  const [tab, setTab] = useState<Tab>('profile');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [noteBody, setNoteBody] = useState('');
  const [noteType, setNoteType] = useState('general');
  const [consentForm, setConsentForm] = useState({
    consent_type: 'marketing_email',
    granted: true,
    source: 'staff_entry',
  });
  const [profileForm, setProfileForm] = useState({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    date_of_birth: '',
    special_event_month: '',
    special_event_day: '',
    special_event_label: '',
  });
  const [enrichmentForm, setEnrichmentForm] = useState({
    preferred_team_member_id: '',
    loyalty_display_status: 'none',
    appointment_notes: '',
    communication_channel: '',
  });

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([
      fetchClient(clientId),
      fetchClientTags(),
      fetchClientNotes(clientId),
      fetchClientConsents(clientId),
      fetchClientTimeline(clientId),
      fetchTeamMembers(),
    ])
      .then(([c, tagList, noteList, consents, timelineData, members]) => {
        setClient(c);
        setTags(tagList);
        setTeamMembers(members);
        setSelectedTags(c.tag_ids ?? []);
        setNotes(noteList);
        setConsentCurrent(consents.current);
        setConsentHistory(consents.history);
        setTimeline(timelineData.items);
        setProfileForm({
          first_name: c.first_name ?? '',
          last_name: c.last_name ?? '',
          email: c.email ?? '',
          phone: c.phone ?? '',
          date_of_birth: c.date_of_birth ?? '',
          special_event_month: c.special_event_month?.toString() ?? '',
          special_event_day: c.special_event_day?.toString() ?? '',
          special_event_label: c.special_event_label ?? '',
        });
        const prefs = (c.preferences ?? {}) as Record<string, string>;
        setEnrichmentForm({
          preferred_team_member_id: c.preferred_team_member_id ?? '',
          loyalty_display_status: c.loyalty_display_status ?? 'none',
          appointment_notes: prefs.appointment_notes ?? '',
          communication_channel: prefs.communication_channel ?? '',
        });
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, [clientId]);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    if (tab !== 'communications' || communicationsLoaded) return;
    fetchClientNotificationTimeline(clientId)
      .then(setCommunications)
      .catch(() => setCommunications([]))
      .finally(() => setCommunicationsLoaded(true));
  }, [tab, communicationsLoaded, clientId]);

  useEffect(() => {
    if (tab !== 'visits' || visitsLoaded) return;
    fetchClientVisits(clientId)
      .then(setVisits)
      .catch(() => setVisits([]))
      .finally(() => setVisitsLoaded(true));
  }, [tab, visitsLoaded, clientId]);

  async function saveProfile(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await updateClient(clientId, {
        first_name: profileForm.first_name || null,
        last_name: profileForm.last_name || null,
        email: profileForm.email || null,
        phone: profileForm.phone,
        date_of_birth: profileForm.date_of_birth || null,
        special_event_month: profileForm.special_event_month
          ? Number(profileForm.special_event_month)
          : null,
        special_event_day: profileForm.special_event_day
          ? Number(profileForm.special_event_day)
          : null,
        special_event_label: profileForm.special_event_label.trim() || null,
      });
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed');
    }
  }

  async function saveEnrichment(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await updateClient(clientId, {
        preferred_team_member_id: enrichmentForm.preferred_team_member_id || null,
        loyalty_display_status: enrichmentForm.loyalty_display_status,
        preferences: {
          appointment_notes: enrichmentForm.appointment_notes || undefined,
          communication_channel: enrichmentForm.communication_channel || undefined,
        },
      });
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed');
    }
  }

  function refreshTimeline() {
    fetchClientTimeline(clientId)
      .then((data) => setTimeline(data.items))
      .catch(() => {});
  }

  async function saveTags() {
    try {
      await syncClientTags(clientId, selectedTags);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Tag update failed');
    }
  }

  function toggleTag(tagId: string) {
    setSelectedTags((prev) =>
      prev.includes(tagId) ? prev.filter((id) => id !== tagId) : [...prev, tagId],
    );
  }

  async function addNote(event: React.FormEvent) {
    event.preventDefault();
    try {
      await createClientNote(clientId, { body: noteBody, note_type: noteType });
      setNoteBody('');
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Note failed');
    }
  }

  async function addConsent(event: React.FormEvent) {
    event.preventDefault();
    try {
      await recordClientConsent(clientId, consentForm);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Consent failed');
    }
  }

  if (loading && !client) {
    return (
      <AdminCrmShell title="Client">
        <LoadingState />
      </AdminCrmShell>
    );
  }

  return (
    <AdminCrmShell title={client?.resolved_display_name ?? 'Client'}>
      <p className="mb-4 text-sm">
        <Link href="/admin/clients" className="text-zinc-600 hover:underline">
          ← Back to clients
        </Link>
      </p>
      {error ? <ErrorAlert message={error} /> : null}

      <div className="mb-4 flex flex-wrap gap-2">
        {(
          [
            'profile',
            'notes',
            'consent',
            'formulas',
            'photos',
            'documents',
            'visits',
            'communications',
            'timeline',
          ] as Tab[]
        ).map((t) => (
          <Button
            key={t}
            type="button"
            variant={tab === t ? 'primary' : 'secondary'}
            onClick={() => setTab(t)}
          >
            {t.charAt(0).toUpperCase() + t.slice(1)}
          </Button>
        ))}
      </div>

      {tab === 'profile' ? (
        <div className="grid gap-4 md:grid-cols-2">
          <Card title="Profile">
            <form onSubmit={saveProfile} className="grid gap-3">
              <Field label="Phone / WhatsApp">
                <input
                  className={inputClass}
                  value={profileForm.phone}
                  onChange={(e) => setProfileForm({ ...profileForm, phone: e.target.value })}
                  required
                  inputMode="tel"
                  autoComplete="tel"
                />
              </Field>
              <Field label="First name (optional)">
                <input
                  className={inputClass}
                  value={profileForm.first_name}
                  onChange={(e) =>
                    setProfileForm({ ...profileForm, first_name: e.target.value })
                  }
                />
              </Field>
              <Field label="Last name">
                <input
                  className={inputClass}
                  value={profileForm.last_name}
                  onChange={(e) =>
                    setProfileForm({ ...profileForm, last_name: e.target.value })
                  }
                />
              </Field>
              <Field label="Email">
                <input
                  type="email"
                  className={inputClass}
                  value={profileForm.email}
                  onChange={(e) => setProfileForm({ ...profileForm, email: e.target.value })}
                />
              </Field>
              <Field label="Date of birth">
                <input
                  type="date"
                  className={inputClass}
                  value={profileForm.date_of_birth}
                  onChange={(e) =>
                    setProfileForm({ ...profileForm, date_of_birth: e.target.value })
                  }
                />
              </Field>
              <div className="grid grid-cols-2 gap-3">
                <Field label="Special event month">
                  <select
                    className={inputClass}
                    value={profileForm.special_event_month}
                    onChange={(e) =>
                      setProfileForm({ ...profileForm, special_event_month: e.target.value })
                    }
                  >
                    <option value="">—</option>
                    {MONTHS.map((m) => (
                      <option key={m.value} value={m.value}>
                        {m.label}
                      </option>
                    ))}
                  </select>
                </Field>
                <Field label="Special event day">
                  <select
                    className={inputClass}
                    value={profileForm.special_event_day}
                    onChange={(e) =>
                      setProfileForm({ ...profileForm, special_event_day: e.target.value })
                    }
                  >
                    <option value="">—</option>
                    {Array.from({ length: 31 }, (_, i) => i + 1).map((d) => (
                      <option key={d} value={d}>
                        {d}
                      </option>
                    ))}
                  </select>
                </Field>
              </div>
              <Field label="Special event label">
                <input
                  className={inputClass}
                  value={profileForm.special_event_label}
                  onChange={(e) =>
                    setProfileForm({ ...profileForm, special_event_label: e.target.value })
                  }
                  placeholder="Birthday, anniversary…"
                />
              </Field>
              <p className="text-xs text-zinc-500">
                Last visit:{' '}
                {client?.last_visited_at
                  ? new Date(client.last_visited_at).toLocaleString()
                  : 'Never'}
              </p>
              <p className="text-xs text-zinc-500">
                Membership joined:{' '}
                {client?.membership_joined_at
                  ? new Date(client.membership_joined_at).toLocaleString()
                  : '—'}
              </p>
              <p className="text-xs text-zinc-500">
                Interested next visit: {client?.interested_next_visit_date || '—'}
              </p>
              <Button type="submit">Save profile</Button>
            </form>
          </Card>
          <div className="grid gap-4">
            <Card title="CRM details">
              <form onSubmit={saveEnrichment} className="grid gap-3">
                <Field label="Preferred stylist">
                  <select
                    className={inputClass}
                    value={enrichmentForm.preferred_team_member_id}
                    onChange={(e) =>
                      setEnrichmentForm({
                        ...enrichmentForm,
                        preferred_team_member_id: e.target.value,
                      })
                    }
                  >
                    <option value="">None</option>
                    {teamMembers.map((m) => (
                      <option key={m.id} value={m.id}>
                        {m.display_name}
                      </option>
                    ))}
                  </select>
                </Field>
                <Field label="Loyalty display status">
                  <select
                    className={inputClass}
                    value={enrichmentForm.loyalty_display_status}
                    onChange={(e) =>
                      setEnrichmentForm({
                        ...enrichmentForm,
                        loyalty_display_status: e.target.value,
                      })
                    }
                  >
                    {LOYALTY_DISPLAY_STATUSES.map((s) => (
                      <option key={s} value={s}>
                        {s}
                      </option>
                    ))}
                  </select>
                  <p className="mt-1 text-xs text-zinc-500">
                    Display-only placeholder until Memberships module.
                  </p>
                </Field>
                <Field label="Appointment notes">
                  <textarea
                    className={inputClass}
                    rows={2}
                    value={enrichmentForm.appointment_notes}
                    onChange={(e) =>
                      setEnrichmentForm({
                        ...enrichmentForm,
                        appointment_notes: e.target.value,
                      })
                    }
                  />
                </Field>
                <Field label="Preferred contact channel">
                  <select
                    className={inputClass}
                    value={enrichmentForm.communication_channel}
                    onChange={(e) =>
                      setEnrichmentForm({
                        ...enrichmentForm,
                        communication_channel: e.target.value,
                      })
                    }
                  >
                    <option value="">Not set</option>
                    <option value="email">Email</option>
                    <option value="sms">SMS</option>
                    <option value="phone">Phone</option>
                  </select>
                </Field>
                <Button type="submit">Save CRM details</Button>
              </form>
            </Card>
            <Card title="Communication preferences (derived)">
              <p className="mb-2 text-xs text-zinc-500">
                Authoritative source is consent history, not profile fields.
              </p>
              <dl className="space-y-1 text-sm">
                {CONSENT_TYPES.map((type) => {
                  const pref =
                    client?.communication_preferences?.[type] ?? consentCurrent[type];
                  return (
                    <div key={type} className="flex justify-between gap-4">
                      <dt className="text-zinc-500">{type.replace(/_/g, ' ')}</dt>
                      <dd>
                        {pref
                          ? pref.granted
                            ? 'Granted'
                            : 'Withdrawn'
                          : 'Not recorded'}
                      </dd>
                    </div>
                  );
                })}
              </dl>
            </Card>
            <Card title="Tags">
            {tags.length === 0 ? <EmptyState message="No tags yet." /> : null}
            <ul className="mb-3 space-y-2">
              {tags.map((tag) => (
                <li key={tag.id} className="flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    checked={selectedTags.includes(tag.id)}
                    onChange={() => toggleTag(tag.id)}
                    id={`tag-${tag.id}`}
                  />
                  <label htmlFor={`tag-${tag.id}`}>{tag.name}</label>
                </li>
              ))}
            </ul>
            <Button type="button" onClick={saveTags}>
              Save tags
            </Button>
          </Card>
          </div>
        </div>
      ) : null}

      {tab === 'formulas' ? (
        <FormulasTab clientId={clientId} onChanged={refreshTimeline} />
      ) : null}

      {tab === 'photos' ? (
        <PhotosTab clientId={clientId} onChanged={refreshTimeline} />
      ) : null}

      {tab === 'documents' ? (
        <DocumentsTab clientId={clientId} onChanged={refreshTimeline} />
      ) : null}

      {tab === 'visits' ? (
        <Card title="Visits">
          {!visitsLoaded ? (
            <LoadingState />
          ) : visits.length === 0 ? (
            <EmptyState message="No visits recorded yet." />
          ) : (
            <ul className="divide-y divide-zinc-100">
              {visits.map((visit) => (
                <li key={visit.id} className="py-3 text-sm">
                  <p className="font-medium">
                    {visit.visited_at
                      ? new Date(visit.visited_at).toLocaleString()
                      : visit.created_at
                        ? new Date(visit.created_at).toLocaleString()
                        : '—'}
                  </p>
                  <p className="text-xs text-zinc-500">
                    {visit.location?.name ?? 'No location'}
                    {visit.source ? ` · ${visit.source}` : ''}
                  </p>
                  {visit.notes ? <p className="mt-1 text-zinc-600">{visit.notes}</p> : null}
                </li>
              ))}
            </ul>
          )}
        </Card>
      ) : null}

      {tab === 'notes' ? (
        <div className="grid gap-4 md:grid-cols-2">
          <Card title="Add note">
            <form onSubmit={addNote} className="grid gap-3">
              <Field label="Type">
                <select
                  className={inputClass}
                  value={noteType}
                  onChange={(e) => setNoteType(e.target.value)}
                >
                  {NOTE_TYPES.map((t) => (
                    <option key={t} value={t}>
                      {t.replace('_', ' ')}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label="Note">
                <textarea
                  className={inputClass}
                  rows={4}
                  value={noteBody}
                  onChange={(e) => setNoteBody(e.target.value)}
                  required
                />
              </Field>
              <Button type="submit">Add note</Button>
            </form>
          </Card>
          <Card title="Notes">
            {notes.length === 0 ? <EmptyState message="No notes yet." /> : null}
            <ul className="divide-y divide-zinc-100">
              {notes.map((note) => (
                <li key={note.id} className="py-3 text-sm">
                  <p className="text-xs text-zinc-500">
                    {note.author_name ?? 'Staff'} · {note.note_type} ·{' '}
                    {note.created_at ? new Date(note.created_at).toLocaleString() : '—'}
                  </p>
                  <p className="mt-1 whitespace-pre-wrap">{note.body}</p>
                </li>
              ))}
            </ul>
          </Card>
        </div>
      ) : null}

      {tab === 'consent' ? (
        <div className="grid gap-4 md:grid-cols-2">
          <Card title="Record consent">
            <form onSubmit={addConsent} className="grid gap-3">
              <Field label="Type">
                <select
                  className={inputClass}
                  value={consentForm.consent_type}
                  onChange={(e) =>
                    setConsentForm({ ...consentForm, consent_type: e.target.value })
                  }
                >
                  {CONSENT_TYPES.map((t) => (
                    <option key={t} value={t}>
                      {t.replace(/_/g, ' ')}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label="Granted">
                <select
                  className={inputClass}
                  value={consentForm.granted ? 'yes' : 'no'}
                  onChange={(e) =>
                    setConsentForm({ ...consentForm, granted: e.target.value === 'yes' })
                  }
                >
                  <option value="yes">Yes</option>
                  <option value="no">No</option>
                </select>
              </Field>
              <Field label="Source">
                <select
                  className={inputClass}
                  value={consentForm.source}
                  onChange={(e) => setConsentForm({ ...consentForm, source: e.target.value })}
                >
                  {CONSENT_SOURCES.map((s) => (
                    <option key={s} value={s}>
                      {s.replace(/_/g, ' ')}
                    </option>
                  ))}
                </select>
              </Field>
              <Button type="submit">Record</Button>
            </form>
          </Card>
          <Card title="Current & history">
            <dl className="mb-4 space-y-1 text-sm">
              {CONSENT_TYPES.map((type) => (
                <div key={type} className="flex justify-between gap-4">
                  <dt className="text-zinc-500">{type.replace(/_/g, ' ')}</dt>
                  <dd>
                    {consentCurrent[type]
                      ? consentCurrent[type].granted
                        ? 'Granted'
                        : 'Withdrawn'
                      : 'Not recorded'}
                  </dd>
                </div>
              ))}
            </dl>
            <ul className="divide-y divide-zinc-100 text-sm">
              {consentHistory.map((entry) => (
                <li key={entry.id} className="py-2">
                  {entry.consent_type.replace(/_/g, ' ')} —{' '}
                  {entry.granted ? 'granted' : 'withdrawn'} ({entry.source})
                  <span className="block text-xs text-zinc-500">
                    {entry.recorded_at ? new Date(entry.recorded_at).toLocaleString() : '—'}
                  </span>
                </li>
              ))}
            </ul>
          </Card>
        </div>
      ) : null}

      {tab === 'communications' ? (
        <Card title="Communications">
          <p className="mb-3 text-xs text-zinc-500">
            Operational notifications sent to this client (booking, payments, membership, manual). Marketing campaigns
            are tracked separately.
          </p>
          {!communicationsLoaded ? (
            <LoadingState />
          ) : communications.length === 0 ? (
            <EmptyState message="No operational communications yet." />
          ) : (
            <ul className="divide-y divide-zinc-100">
              {communications.map((entry) => (
                <li key={entry.id} className="py-3">
                  <div className="flex items-center gap-2">
                    <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${notificationStatusTone(entry.status)}`}>
                      {entry.status}
                    </span>
                    <span className="text-sm font-medium">{purposeLabel(entry.purpose)}</span>
                    <span className="text-xs text-zinc-500">· {channelLabel(entry.channel)}</span>
                  </div>
                  {entry.subject ? <p className="mt-1 text-sm">{entry.subject}</p> : null}
                  {entry.preview ? <p className="text-sm text-zinc-600">{entry.preview}</p> : null}
                  <p className="mt-1 text-xs text-zinc-500">
                    {entry.recipient_address ?? '—'} · {formatNotificationDateTime(entry.occurred_at ?? entry.created_at)}
                  </p>
                </li>
              ))}
            </ul>
          )}
        </Card>
      ) : null}

      {tab === 'timeline' ? (
        <Card title="Activity timeline">
          {timeline.length === 0 ? <EmptyState message="No timeline events yet." /> : null}
          <ul className="divide-y divide-zinc-100">
            {timeline.map((event) => (
              <li key={event.id} className="py-3">
                <p className="font-medium text-sm">{event.title}</p>
                {event.description ? (
                  <p className="text-sm text-zinc-600">{event.description}</p>
                ) : null}
                <p className="text-xs text-zinc-500">
                  {event.actor_name ?? 'System'} ·{' '}
                  {event.occurred_at ? new Date(event.occurred_at).toLocaleString() : '—'}
                </p>
              </li>
            ))}
          </ul>
        </Card>
      ) : null}
    </AdminCrmShell>
  );
}
