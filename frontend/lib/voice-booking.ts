/** Voice booking concierge helpers — browser Web Speech + catalog/slots intents. */

export type SpeechRecognitionLike = {
  continuous: boolean;
  interimResults: boolean;
  lang: string;
  start: () => void;
  stop: () => void;
  abort?: () => void;
  onresult: ((event: SpeechRecognitionResultEventLike) => void) | null;
  onerror: ((event: { error?: string }) => void) | null;
  onend: (() => void) | null;
};

export type SpeechRecognitionResultEventLike = {
  resultIndex: number;
  results: {
    length: number;
    [index: number]: {
      isFinal: boolean;
      0: { transcript: string };
      length: number;
    };
  };
};

type SpeechWindow = Window & {
  SpeechRecognition?: new () => SpeechRecognitionLike;
  webkitSpeechRecognition?: new () => SpeechRecognitionLike;
};

export function normalizeSpeech(value: string): string {
  return value
    .toLowerCase()
    .replace(/[^a-z0-9\s]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

export function getSpeechRecognitionConstructor(): (new () => SpeechRecognitionLike) | null {
  if (typeof window === 'undefined') return null;
  const w = window as SpeechWindow;
  return w.SpeechRecognition ?? w.webkitSpeechRecognition ?? null;
}

export function speakText(text: string): Promise<void> {
  return new Promise((resolve) => {
    if (typeof window === 'undefined' || !('speechSynthesis' in window)) {
      resolve();
      return;
    }
    let settled = false;
    const finish = () => {
      if (settled) return;
      settled = true;
      resolve();
    };
    try {
      window.speechSynthesis.cancel();
      const utterance = new SpeechSynthesisUtterance(text);
      utterance.rate = 1;
      utterance.pitch = 1;
      utterance.onend = () => finish();
      utterance.onerror = () => finish();
      window.speechSynthesis.speak(utterance);
      window.setTimeout(finish, Math.min(18_000, Math.max(2_000, text.length * 70)));
    } catch {
      finish();
    }
  });
}

export function stopSpeaking(): void {
  if (typeof window === 'undefined' || !('speechSynthesis' in window)) return;
  try {
    window.speechSynthesis.cancel();
  } catch {
    /* ignore */
  }
}

export function timeOfDayGreeting(date: Date = new Date()): string {
  const hour = date.getHours();
  if (hour < 12) return 'Good morning';
  if (hour < 17) return 'Good afternoon';
  return 'Good evening';
}

export function buildVoiceBookingGreeting(salonName?: string | null): string {
  const name = salonName?.trim() || 'our salon';
  return `${timeOfDayGreeting()}! Welcome to ${name}. I can list services, prices, availability, or help you book. You can also ask to talk to a human or call the salon. What would you like?`;
}

export type VoiceServiceLike = {
  id: string;
  name: string;
  description?: string | null;
  category?: string | null;
  duration_minutes: number;
  base_price_cents: number | null;
  membership_price_cents?: number | null;
  deposit_required?: boolean;
  deposit_amount_cents?: number | null;
  cancellation_window_hours?: number | null;
  min_lead_time_hours?: number | null;
};

export function matchServicesFromSpeech<T extends VoiceServiceLike>(
  query: string,
  services: T[],
  limit = 5,
): T[] {
  const q = normalizeSpeech(query);
  if (!q || services.length === 0) return [];

  const scored = services
    .map((service) => {
      const name = normalizeSpeech(service.name);
      const desc = normalizeSpeech(service.description ?? '');
      const cat = normalizeSpeech(service.category ?? '');
      let score = 0;
      if (name === q) score += 100;
      if (name.includes(q) || q.includes(name)) score += 60;
      for (const word of q.split(' ').filter((w) => w.length > 2)) {
        if (name.includes(word)) score += 18;
        if (desc.includes(word)) score += 8;
        if (cat.includes(word)) score += 10;
      }
      return { service, score };
    })
    .filter((row) => row.score > 0)
    .sort((a, b) => b.score - a.score);

  return scored.slice(0, limit).map((row) => row.service);
}

export type VoiceIntent =
  | 'list_services'
  | 'service_detail'
  | 'availability'
  | 'book'
  | 'providers'
  | 'hours'
  | 'talk_human'
  | 'call_office'
  | 'help'
  | 'affirm'
  | 'negative'
  | 'unknown';

const AFFIRM = /^(yes|yeah|yep|yup|ok|okay|sure|confirm|correct|book it|that one|please)\b/;
const NEGATIVE = /^(no|nope|nah|cancel|never mind|nevermind|stop|wrong)\b/;

export function detectVoiceIntent(text: string): VoiceIntent {
  const q = normalizeSpeech(text);
  if (!q) return 'unknown';
  if (AFFIRM.test(q)) return 'affirm';
  if (NEGATIVE.test(q)) return 'negative';
  if (/\b(talk to (a )?human|speak to (someone|staff|receptionist)|whatsapp|message (the )?salon|human)\b/.test(q)) {
    return 'talk_human';
  }
  if (/\b(call (the )?(salon|office|shop|studio)|phone (the )?salon|dial)\b/.test(q)) {
    return 'call_office';
  }
  if (/\b(hours|open|opening|address|where are you|location)\b/.test(q)) return 'hours';
  if (/\b(stylist|provider|who (can|do)|staff|barber|therapist)\b/.test(q)) return 'providers';
  if (/\b(available|availability|free|slots?|when can|tomorrow|today|friday|monday|tuesday|wednesday|thursday|saturday|sunday)\b/.test(q)) {
    return 'availability';
  }
  if (/\b(book|appointment|reserve|schedule me)\b/.test(q)) return 'book';
  if (/\b(list|what services|menu|what do you offer|all services|services)\b/.test(q)) {
    return 'list_services';
  }
  if (/\b(price|cost|how much|how long|duration|deposit|cancel|cancellation)\b/.test(q)) {
    return 'service_detail';
  }
  if (/\b(help|what can you)\b/.test(q)) return 'help';
  return 'unknown';
}

export function formatMoneyFromCents(cents: number | null | undefined): string {
  if (cents == null) return 'price on request';
  return `£${(cents / 100).toFixed(2)}`;
}

export function describeService(service: VoiceServiceLike): string {
  const parts = [
    `${service.name}`,
    `${service.duration_minutes} minutes`,
    formatMoneyFromCents(service.base_price_cents),
  ];
  if (service.description?.trim()) parts.push(service.description.trim());
  if (service.deposit_required) {
    parts.push(
      service.deposit_amount_cents
        ? `deposit ${formatMoneyFromCents(service.deposit_amount_cents)}`
        : 'deposit required',
    );
  }
  if (service.cancellation_window_hours != null) {
    parts.push(`cancel up to ${service.cancellation_window_hours} hours before`);
  }
  return parts.join('. ') + '.';
}

export function summarizeSlotsSpeech(
  slots: Array<{ starts_at: string; provider_name?: string | null }>,
  limit = 6,
): string {
  if (slots.length === 0) return 'There are no open slots for that day.';
  const slice = slots.slice(0, limit);
  const lines = slice.map((s) => {
    const t = new Date(s.starts_at);
    const time = t.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    return s.provider_name ? `${time} with ${s.provider_name}` : time;
  });
  const more = slots.length > limit ? ` Plus ${slots.length - limit} more on screen.` : '';
  return `I found ${slots.length} opening${slots.length === 1 ? '' : 's'}: ${lines.join('; ')}.${more}`;
}

export function parseRelativeDate(text: string, now = new Date()): string | null {
  const q = normalizeSpeech(text);
  const iso = (d: Date) => d.toISOString().slice(0, 10);
  if (/\btoday\b/.test(q)) return iso(now);
  if (/\btomorrow\b/.test(q)) {
    const d = new Date(now);
    d.setDate(d.getDate() + 1);
    return iso(d);
  }
  const days: Record<string, number> = {
    sunday: 0,
    monday: 1,
    tuesday: 2,
    wednesday: 3,
    thursday: 4,
    friday: 5,
    saturday: 6,
  };
  for (const [name, dow] of Object.entries(days)) {
    if (q.includes(name)) {
      const d = new Date(now);
      const delta = (dow - d.getDay() + 7) % 7 || 7;
      d.setDate(d.getDate() + delta);
      return iso(d);
    }
  }
  return null;
}

export function buildWaMeLink(whatsapp: string | null | undefined, text: string): string | null {
  const digits = String(whatsapp ?? '').replace(/\D+/g, '');
  if (digits.length < 8) return null;
  return `https://wa.me/${digits}?text=${encodeURIComponent(text)}`;
}

export function buildTelLink(phone: string | null | undefined): string | null {
  const trimmed = String(phone ?? '').trim();
  if (trimmed.length < 6) return null;
  const href = trimmed.replace(/[^\d+]/g, '');
  return href ? `tel:${href}` : null;
}
