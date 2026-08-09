'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import type { BookableService, OnlineBookingCatalog, OnlineBookingSlot } from '@/lib/booking-types';
import {
  buildTelLink,
  buildVoiceBookingGreeting,
  buildWaMeLink,
  describeService,
  detectVoiceIntent,
  formatMoneyFromCents,
  getSpeechRecognitionConstructor,
  matchServicesFromSpeech,
  normalizeSpeech,
  parseRelativeDate,
  speakText,
  stopSpeaking,
  summarizeSlotsSpeech,
  type SpeechRecognitionLike,
  type VoiceIntent,
} from '@/lib/voice-booking';
import {
  createOnlineAppointment,
  fetchOnlineSlots,
} from '@/services/online-booking.service';

type ChatRole = 'assistant' | 'user';

type ChatMessage = {
  id: string;
  role: ChatRole;
  text: string;
  slots?: OnlineBookingSlot[];
  services?: BookableService[];
};

type BookDraft = {
  serviceId?: string;
  date?: string;
  slot?: OnlineBookingSlot | null;
  firstName?: string;
  lastName?: string;
  email?: string;
  phone?: string;
  whatsappOptIn?: boolean;
  step: 'idle' | 'service' | 'date' | 'slot' | 'name' | 'email' | 'phone' | 'confirm';
};

const BOOK_STEPS: BookDraft['step'][] = [
  'service',
  'date',
  'slot',
  'name',
  'email',
  'phone',
  'confirm',
];

function bookProgressLabel(step: BookDraft['step']): string {
  const labels: Record<BookDraft['step'], string> = {
    idle: 'Ready',
    service: '1 · Service',
    date: '2 · Day',
    slot: '3 · Time',
    name: '4 · Name',
    email: '5 · Email',
    phone: '6 · WhatsApp',
    confirm: '7 · Confirm',
  };
  return labels[step];
}

interface VoiceBookingConciergeProps {
  tenantSlug: string;
  catalog: OnlineBookingCatalog;
  locationId: string;
  salonName: string;
  onBooked?: () => void;
}

function newId(): string {
  return typeof crypto !== 'undefined' && 'randomUUID' in crypto
    ? crypto.randomUUID()
    : `vb-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

function hoursSpeech(catalog: OnlineBookingCatalog, locationId: string): string {
  const loc =
    catalog.locations.find((l) => l.id === locationId) ?? catalog.locations[0] ?? null;
  if (!loc) return 'I do not have location details yet.';
  const addressParts = loc.address
    ? [loc.address.line1, loc.address.city, loc.address.postcode].filter(Boolean)
    : [];
  const address = addressParts.length ? ` We are at ${addressParts.join(', ')}.` : '';
  const hours = loc.opening_hours;
  if (!hours?.length) {
    return `Ask the salon for opening hours.${address}`;
  }
  const openDays = hours
    .filter((h) => !h.is_closed && h.start_time && h.end_time)
    .slice(0, 4)
    .map((h) => {
      const names = ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
      return `${names[h.day_of_week] ?? h.day_of_week} ${h.start_time}-${h.end_time}`;
    });
  return `Typical hours include ${openDays.join('; ')}.${address}`;
}

export function VoiceBookingConcierge({
  tenantSlug,
  catalog,
  locationId,
  salonName,
  onBooked,
}: VoiceBookingConciergeProps) {
  const [open, setOpen] = useState(false);
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [draft, setDraft] = useState('');
  const [listening, setListening] = useState(false);
  const [speechSupported, setSpeechSupported] = useState(false);
  const [statusNote, setStatusNote] = useState<string | null>(null);
  const [bookDraft, setBookDraft] = useState<BookDraft>({ step: 'idle' });
  const [busy, setBusy] = useState(false);

  const recognitionRef = useRef<SpeechRecognitionLike | null>(null);
  const listRef = useRef<HTMLDivElement | null>(null);
  const openRef = useRef(false);
  const sessionRef = useRef(0);
  const listenAfterSpeakRef = useRef(false);
  const startListeningRef = useRef<(() => void) | null>(null);
  const bookDraftRef = useRef(bookDraft);
  const processRef = useRef<(raw: string) => void>(() => {});

  bookDraftRef.current = bookDraft;
  openRef.current = open;

  const ownerWhatsapp =
    catalog.tenant.owner_whatsapp ||
    catalog.tenant.branding?.support_phone ||
    catalog.locations.find((l) => l.id === locationId)?.contact_phone ||
    catalog.locations[0]?.contact_phone ||
    null;
  const officePhone =
    catalog.locations.find((l) => l.id === locationId)?.contact_phone ||
    catalog.tenant.branding?.support_phone ||
    null;

  useEffect(() => {
    setSpeechSupported(Boolean(getSpeechRecognitionConstructor()));
  }, []);

  useEffect(() => {
    if (!open) return;
    listRef.current?.scrollTo({ top: listRef.current.scrollHeight, behavior: 'smooth' });
  }, [messages, open, listening]);

  const stopListening = useCallback(() => {
    try {
      recognitionRef.current?.abort?.();
      recognitionRef.current?.stop();
    } catch {
      /* ignore */
    }
    recognitionRef.current = null;
    setListening(false);
  }, []);

  useEffect(() => {
    return () => {
      sessionRef.current += 1;
      listenAfterSpeakRef.current = false;
      stopSpeaking();
      try {
        recognitionRef.current?.abort?.();
        recognitionRef.current?.stop();
      } catch {
        /* ignore */
      }
      recognitionRef.current = null;
    };
  }, []);

  const appendAssistant = useCallback((text: string, extra?: Partial<ChatMessage>) => {
    setMessages((prev) => [...prev, { id: newId(), role: 'assistant', text, ...extra }]);
  }, []);

  const appendUser = useCallback((text: string) => {
    setMessages((prev) => [...prev, { id: newId(), role: 'user', text }]);
  }, []);

  const speakAndMaybeListen = useCallback(
    async (text: string, thenListen: boolean) => {
      const session = sessionRef.current;
      listenAfterSpeakRef.current = thenListen;
      stopListening();
      await speakText(text);
      if (session !== sessionRef.current || !openRef.current) return;
      if (listenAfterSpeakRef.current && getSpeechRecognitionConstructor()) {
        listenAfterSpeakRef.current = false;
        startListeningRef.current?.();
      }
    },
    [stopListening],
  );

  const reply = useCallback(
    async (text: string, thenListen = true, extra?: Partial<ChatMessage>) => {
      appendAssistant(text, extra);
      await speakAndMaybeListen(text, thenListen);
    },
    [appendAssistant, speakAndMaybeListen],
  );

  const loadSlots = useCallback(
    async (serviceId: string, date: string) => {
      return fetchOnlineSlots(tenantSlug, {
        booking_service_id: serviceId,
        location_id: locationId,
        date,
      });
    },
    [tenantSlug, locationId],
  );

  const startBookFlow = useCallback(
    async (service?: BookableService | null) => {
      if (!service) {
        setBookDraft({ step: 'service' });
        await reply('Which service would you like to book?');
        return;
      }
      setBookDraft({ step: 'date', serviceId: service.id });
      await reply(
        `Booking ${service.name}. Which day — today, tomorrow, or a weekday like Friday?`,
      );
    },
    [reply],
  );

  const completeBooking = useCallback(async () => {
    const d = bookDraftRef.current;
    const service = catalog.services.find((s) => s.id === d.serviceId);
    if (!service || !d.slot || !d.firstName || !d.lastName || !d.email) {
      await reply('I still need a few details before I can confirm.');
      return;
    }
    setBusy(true);
    try {
      await createOnlineAppointment(tenantSlug, {
        booking_service_id: service.id,
        location_id: locationId,
        team_member_id: d.slot.team_member_id,
        workspace_id: d.slot.workspace_id,
        starts_at: d.slot.starts_at,
        first_name: d.firstName,
        last_name: d.lastName,
        email: d.email,
        phone: d.phone,
        whatsapp_opt_in: Boolean(d.whatsappOptIn && d.phone),
      });
      setBookDraft({ step: 'idle' });
      await reply(
        `You're booked for ${service.name} at ${new Date(d.slot.starts_at).toLocaleString()}. You will get a confirmation shortly.`,
        false,
      );
      onBooked?.();
    } catch (e) {
      await reply(e instanceof Error ? e.message : 'Booking failed. Please try again or talk to a human.');
    } finally {
      setBusy(false);
    }
  }, [catalog.services, locationId, onBooked, reply, tenantSlug]);

  const handleIntent = useCallback(
    async (text: string, intent: VoiceIntent) => {
      const services = catalog.services;
      const matched = matchServicesFromSpeech(text, services);

      if (intent === 'talk_human') {
        const link = buildWaMeLink(
          ownerWhatsapp,
          `Hi ${salonName}, I need help booking on the online page.`,
        );
        if (link) {
          await reply('Opening WhatsApp to message the salon.');
          window.open(link, '_blank', 'noopener,noreferrer');
        } else {
          await reply('I do not have a WhatsApp number for this salon yet. Try calling the office.');
        }
        return;
      }

      if (intent === 'call_office') {
        const tel = buildTelLink(officePhone);
        if (tel) {
          await reply('Opening your phone dialer for the salon.');
          window.location.href = tel;
        } else {
          await reply('I do not have a phone number on file for this location.');
        }
        return;
      }

      if (intent === 'help' || intent === 'unknown') {
        await reply(
          'I can list services, prices, providers, hours, check availability, or book. You can also say talk to a human or call the salon.',
        );
        return;
      }

      if (intent === 'list_services') {
        const names = services.slice(0, 12).map((s) => s.name);
        await reply(
          names.length
            ? `We offer: ${names.join(', ')}${services.length > names.length ? ', and more on screen.' : '.'}`
            : 'No online services are listed yet.',
          true,
          { services: services.slice(0, 8) },
        );
        return;
      }

      if (intent === 'service_detail') {
        const service = matched[0];
        if (!service) {
          await reply('Which service should I describe?');
          return;
        }
        await reply(describeService(service), true, { services: [service] });
        return;
      }

      if (intent === 'providers') {
        const names = catalog.providers.map((p) => p.display_name).filter(Boolean);
        await reply(
          names.length
            ? `Bookable providers include ${names.slice(0, 10).join(', ')}.`
            : 'Provider list is empty right now.',
        );
        return;
      }

      if (intent === 'hours') {
        await reply(hoursSpeech(catalog, locationId));
        return;
      }

      if (intent === 'availability' || intent === 'book') {
        const service = matched[0] ?? services.find((s) => s.id === bookDraftRef.current.serviceId);
        const date = parseRelativeDate(text) ?? bookDraftRef.current.date;
        if (!service) {
          await startBookFlow(null);
          return;
        }
        if (!date) {
          setBookDraft((prev) => ({ ...prev, step: 'date', serviceId: service.id }));
          await reply(`For ${service.name}, which day — today, tomorrow, or a weekday?`);
          return;
        }
        setBusy(true);
        try {
          const slots = await loadSlots(service.id, date);
          setBookDraft({
            step: slots.length ? 'slot' : 'date',
            serviceId: service.id,
            date,
            slot: null,
          });
          await reply(summarizeSlotsSpeech(slots), true, { slots });
        } catch (e) {
          await reply(e instanceof Error ? e.message : 'Could not load availability.');
        } finally {
          setBusy(false);
        }
        return;
      }

      if (intent === 'affirm' && bookDraftRef.current.step === 'confirm') {
        await completeBooking();
        return;
      }

      if (intent === 'negative') {
        setBookDraft({ step: 'idle' });
        await reply('Okay, cancelled. What else can I help with?');
        return;
      }

      if (matched[0]) {
        await reply(describeService(matched[0]), true, { services: matched.slice(0, 3) });
        return;
      }

      await reply(
        'I can list services, prices, providers, hours, check availability, or book. You can also say talk to a human or call the salon.',
      );
    },
    [
      catalog,
      completeBooking,
      loadSlots,
      locationId,
      officePhone,
      ownerWhatsapp,
      reply,
      salonName,
      startBookFlow,
    ],
  );

  const continueBookDraft = useCallback(
    async (text: string) => {
      const d = bookDraftRef.current;
      const q = normalizeSpeech(text);

      if (d.step === 'service') {
        const matched = matchServicesFromSpeech(text, catalog.services);
        if (!matched[0]) {
          await reply('I did not catch that service. Try the exact name, or say list services.');
          return;
        }
        await startBookFlow(matched[0]);
        return;
      }

      if (d.step === 'date') {
        const date = parseRelativeDate(text);
        if (!date || !d.serviceId) {
          await reply('Please say today, tomorrow, or a weekday like Friday.');
          return;
        }
        setBusy(true);
        try {
          const slots = await loadSlots(d.serviceId, date);
          setBookDraft({ ...d, date, step: slots.length ? 'slot' : 'date', slot: null });
          await reply(summarizeSlotsSpeech(slots), true, { slots });
        } catch (e) {
          await reply(e instanceof Error ? e.message : 'Could not load availability.');
        } finally {
          setBusy(false);
        }
        return;
      }

      if (d.step === 'slot') {
        await reply('Tap a time chip on screen, or say talk to a human for help.');
        return;
      }

      if (d.step === 'name') {
        const parts = text.trim().split(/\s+/).filter(Boolean);
        if (parts.length < 2) {
          await reply('Please say your first and last name.');
          return;
        }
        setBookDraft({
          ...d,
          firstName: parts[0],
          lastName: parts.slice(1).join(' '),
          step: 'email',
        });
        await reply('Thanks. What email should we use for the confirmation?');
        return;
      }

      if (d.step === 'email') {
        const email = text.trim().replace(/\s+/g, '');
        if (!email.includes('@')) {
          await reply('That does not look like an email. Please say it again.');
          return;
        }
        setBookDraft({ ...d, email, step: 'phone' });
        await reply(
          'For WhatsApp booking updates, say your mobile number — or say skip for email only. You can also say WhatsApp yes first.',
        );
        return;
      }

      if (d.step === 'phone') {
        if (/\b(skip|no|email only)\b/.test(q)) {
          const next = { ...d, phone: undefined, whatsappOptIn: false, step: 'confirm' as const };
          setBookDraft(next);
          const service = catalog.services.find((s) => s.id === d.serviceId);
          const when = d.slot ? new Date(d.slot.starts_at).toLocaleString() : 'the selected time';
          await reply(
            `Confirm booking ${service?.name ?? 'service'} at ${when} for ${d.firstName} ${d.lastName}? Say yes to book.`,
          );
          return;
        }
        if (/\b(whatsapp|opt in|yes updates|message me)\b/.test(q) && !/\d/.test(q)) {
          setBookDraft({ ...d, whatsappOptIn: true });
          await reply('Great — WhatsApp updates enabled. Now say your mobile number, or skip.');
          return;
        }
        const digits = text.replace(/[^\d+]/g, '');
        if (digits.replace(/\D/g, '').length < 8) {
          await reply(
            'Say your mobile number for WhatsApp updates, say "WhatsApp yes" first to opt in, or say skip.',
          );
          return;
        }
        const next = {
          ...d,
          phone: digits,
          whatsappOptIn: d.whatsappOptIn ?? true,
          step: 'confirm' as const,
        };
        setBookDraft(next);
        const service = catalog.services.find((s) => s.id === d.serviceId);
        const when = d.slot ? new Date(d.slot.starts_at).toLocaleString() : 'the selected time';
        await reply(
          `Confirm ${service?.name ?? 'service'} at ${when} for ${d.firstName} ${d.lastName}, WhatsApp updates ${next.whatsappOptIn ? 'on' : 'off'}. Say yes to book.`,
        );
        return;
      }

      if (d.step === 'confirm') {
        const intent = detectVoiceIntent(text);
        if (intent === 'affirm') {
          await completeBooking();
          return;
        }
        if (intent === 'negative') {
          setBookDraft({ step: 'idle' });
          await reply('Okay, cancelled. What else can I help with?');
          return;
        }
        await reply('Say yes to confirm the booking, or no to cancel.');
        return;
      }

      await handleIntent(text, detectVoiceIntent(text));
    },
    [catalog.services, completeBooking, handleIntent, loadSlots, reply, startBookFlow],
  );

  const processUserText = useCallback(
    (raw: string) => {
      const text = raw.trim();
      if (!text) return;
      appendUser(text);
      const d = bookDraftRef.current;
      if (d.step !== 'idle') {
        void continueBookDraft(text);
        return;
      }
      void handleIntent(text, detectVoiceIntent(text));
    },
    [appendUser, continueBookDraft, handleIntent],
  );

  processRef.current = processUserText;

  const startListening = useCallback(() => {
    const Ctor = getSpeechRecognitionConstructor();
    if (!Ctor) {
      setStatusNote('Voice input is not supported on this browser. You can type instead.');
      return;
    }
    stopListening();
    const recognition = new Ctor();
    recognition.continuous = false;
    recognition.interimResults = false;
    recognition.lang = 'en-GB';
    recognition.onresult = (event) => {
      let transcript = '';
      for (let i = event.resultIndex; i < event.results.length; i += 1) {
        if (event.results[i]?.isFinal) {
          transcript += event.results[i][0]?.transcript ?? '';
        }
      }
      const cleaned = transcript.trim();
      if (cleaned) processRef.current(cleaned);
    };
    recognition.onerror = () => {
      setListening(false);
      setStatusNote('Could not hear that — try again or type.');
    };
    recognition.onend = () => {
      setListening(false);
      recognitionRef.current = null;
    };
    recognitionRef.current = recognition;
    try {
      recognition.start();
      setListening(true);
      setStatusNote(null);
    } catch {
      setStatusNote('Microphone busy — try again.');
    }
  }, [stopListening]);

  startListeningRef.current = startListening;

  const openPanel = async () => {
    setOpen(true);
    if (messages.length === 0) {
      const greeting = buildVoiceBookingGreeting(salonName);
      appendAssistant(greeting);
      await speakAndMaybeListen(greeting, true);
    }
  };

  const closePanel = () => {
    sessionRef.current += 1;
    listenAfterSpeakRef.current = false;
    stopSpeaking();
    stopListening();
    setOpen(false);
  };

  const onPickSlot = async (slot: OnlineBookingSlot) => {
    setBookDraft((prev) => ({
      ...prev,
      slot,
      step: prev.firstName ? (prev.email ? 'confirm' : 'email') : 'name',
    }));
    if (!bookDraftRef.current.firstName) {
      await reply(`Got ${new Date(slot.starts_at).toLocaleString()}. What is your full name?`);
      setBookDraft((prev) => ({ ...prev, slot, step: 'name' }));
      return;
    }
    await reply('Say yes to confirm, or update your details.');
  };

  const onPickService = async (service: BookableService) => {
    await startBookFlow(service);
  };

  return (
    <>
      <button
        type="button"
        onClick={() => void openPanel()}
        className="fixed bottom-5 right-5 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-[var(--book-moss,#1f4d3a)] text-white shadow-lg shadow-black/20 transition hover:brightness-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--book-moss,#1f4d3a)]"
        aria-label="Open voice booking assistant"
      >
        <MicIcon />
      </button>

      {open ? (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-3 sm:items-center">
          <div
            className="flex max-h-[min(88vh,640px)] w-full max-w-md flex-col overflow-hidden rounded-2xl border border-[var(--book-line,#e5e2dc)] bg-white shadow-2xl"
            role="dialog"
            aria-label="Voice booking concierge"
          >
            <div className="flex items-center justify-between border-b border-[var(--book-line,#e5e2dc)] px-4 py-3">
              <div>
                <p className="text-sm font-semibold tracking-tight text-[var(--book-ink,#1a1a1a)]">
                  Voice concierge
                </p>
                <p className="text-xs text-[var(--book-muted,#6b6b6b)]">
                  {bookDraft.step !== 'idle'
                    ? bookProgressLabel(bookDraft.step)
                    : speechSupported
                      ? 'Speak or type'
                      : 'Type your request'}
                </p>
              </div>
              <button
                type="button"
                className="rounded-lg px-2 py-1 text-sm text-[var(--book-muted,#6b6b6b)] hover:bg-[var(--book-wash,#f6f4f0)]"
                onClick={closePanel}
              >
                Close
              </button>
            </div>

            {bookDraft.step !== 'idle' ? (
              <div className="flex gap-1 border-b border-[var(--book-line,#e5e2dc)] px-4 py-2">
                {BOOK_STEPS.map((s) => {
                  const idx = BOOK_STEPS.indexOf(s);
                  const current = BOOK_STEPS.indexOf(bookDraft.step);
                  return (
                    <span
                      key={s}
                      className={`h-1 flex-1 rounded-full ${
                        idx <= current ? 'bg-[var(--book-moss,#1f4d3a)]' : 'bg-[var(--book-line,#e5e2dc)]'
                      }`}
                    />
                  );
                })}
              </div>
            ) : null}

            <div ref={listRef} className="flex-1 space-y-3 overflow-y-auto px-4 py-3">
              {messages.map((m) => (
                <div
                  key={m.id}
                  className={`max-w-[92%] rounded-xl px-3 py-2 text-sm ${
                    m.role === 'user'
                      ? 'ml-auto bg-[var(--book-moss,#1f4d3a)] text-white'
                      : 'bg-[var(--book-wash,#f6f4f0)] text-[var(--book-ink,#1a1a1a)]'
                  }`}
                >
                  <p>{m.text}</p>
                  {m.services?.length ? (
                    <div className="mt-2 flex flex-wrap gap-1.5">
                      {m.services.map((s) => (
                        <button
                          key={s.id}
                          type="button"
                          disabled={busy}
                          onClick={() => void onPickService(s)}
                          className="rounded-full border border-[var(--book-line,#e5e2dc)] bg-white px-2.5 py-1 text-xs font-medium text-[var(--book-ink,#1a1a1a)]"
                        >
                          {s.name} · {formatMoneyFromCents(s.base_price_cents)}
                        </button>
                      ))}
                    </div>
                  ) : null}
                  {m.slots?.length ? (
                    <div className="mt-2 flex flex-wrap gap-1.5">
                      {m.slots.slice(0, 12).map((slot) => (
                        <button
                          key={`${slot.starts_at}-${slot.team_member_id}`}
                          type="button"
                          disabled={busy}
                          onClick={() => void onPickSlot(slot)}
                          className="rounded-full border border-[var(--book-line,#e5e2dc)] bg-white px-2.5 py-1 text-xs font-medium text-[var(--book-ink,#1a1a1a)]"
                        >
                          {new Date(slot.starts_at).toLocaleTimeString([], {
                            hour: 'numeric',
                            minute: '2-digit',
                          })}
                          {slot.provider_name ? ` · ${slot.provider_name}` : ''}
                        </button>
                      ))}
                    </div>
                  ) : null}
                </div>
              ))}
              {listening ? (
                <p className="text-xs font-medium text-[var(--book-moss,#1f4d3a)]">Listening…</p>
              ) : null}
              {statusNote ? <p className="text-xs text-amber-800">{statusNote}</p> : null}
            </div>

            <div className="border-t border-[var(--book-line,#e5e2dc)] p-3">
              <div className="mb-2 flex flex-wrap gap-2">
                <EscapeChip
                  label="Talk to human"
                  onClick={() => processUserText('talk to a human')}
                />
                <EscapeChip label="Call salon" onClick={() => processUserText('call the salon')} />
              </div>
              <form
                className="flex gap-2"
                onSubmit={(e) => {
                  e.preventDefault();
                  const value = draft.trim();
                  if (!value || busy) return;
                  setDraft('');
                  processUserText(value);
                }}
              >
                <button
                  type="button"
                  disabled={!speechSupported || busy}
                  onClick={() => (listening ? stopListening() : startListening())}
                  className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-[var(--book-line,#e5e2dc)] text-[var(--book-ink,#1a1a1a)] disabled:opacity-40"
                  aria-label={listening ? 'Stop listening' : 'Start listening'}
                >
                  {listening ? <MicOffIcon /> : <MicIcon small />}
                </button>
                <input
                  value={draft}
                  onChange={(e) => setDraft(e.target.value)}
                  placeholder="Type a message…"
                  className="min-w-0 flex-1 rounded-xl border border-[var(--book-line,#e5e2dc)] px-3 py-2 text-sm outline-none focus:border-[var(--book-moss,#1f4d3a)]"
                />
                <button
                  type="submit"
                  disabled={busy || !draft.trim()}
                  className="rounded-xl bg-[var(--book-moss,#1f4d3a)] px-3 py-2 text-sm font-semibold text-white disabled:opacity-40"
                >
                  Send
                </button>
              </form>
            </div>
          </div>
        </div>
      ) : null}
    </>
  );
}

function EscapeChip({ label, onClick }: { label: string; onClick: () => void }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="rounded-full border border-[var(--book-line,#e5e2dc)] bg-[var(--book-wash,#f6f4f0)] px-3 py-1 text-xs font-medium text-[var(--book-ink,#1a1a1a)]"
    >
      {label}
    </button>
  );
}

function MicIcon({ small }: { small?: boolean }) {
  const size = small ? 18 : 22;
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" aria-hidden>
      <path
        d="M12 14a3 3 0 0 0 3-3V7a3 3 0 1 0-6 0v4a3 3 0 0 0 3 3Z"
        stroke="currentColor"
        strokeWidth="1.75"
      />
      <path
        d="M19 11a7 7 0 0 1-14 0M12 18v3"
        stroke="currentColor"
        strokeWidth="1.75"
        strokeLinecap="round"
      />
    </svg>
  );
}

function MicOffIcon() {
  return (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden>
      <path
        d="M4 4l16 16M15 9.5V7a3 3 0 0 0-5.8-1M9 9v2a3 3 0 0 0 4.6 2.5"
        stroke="currentColor"
        strokeWidth="1.75"
        strokeLinecap="round"
      />
      <path
        d="M19 11a7 7 0 0 1-9.5 6.5M12 18v3"
        stroke="currentColor"
        strokeWidth="1.75"
        strokeLinecap="round"
      />
    </svg>
  );
}
