'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/Button';
import {
  acknowledgeStaffSosAlert,
  fetchActiveStaffSosAlerts,
  shiftStaffSosAppointment,
  type StaffSosAlert,
} from '@/services/staff-sos.service';

const POLL_MS = 5_000;
const VIBRATE_PATTERN = [600, 180, 600, 180, 600, 180, 900, 400];

function stopVibrate(): void {
  try {
    navigator.vibrate?.(0);
  } catch {
    /* ignore */
  }
}

function playSosBeep(): void {
  try {
    const Ctx =
      window.AudioContext ||
      (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
    if (!Ctx) return;
    const ctx = new Ctx();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'square';
    osc.frequency.value = 880;
    gain.gain.value = 0.08;
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start();
    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.35);
    osc.stop(ctx.currentTime + 0.36);
    window.setTimeout(() => void ctx.close(), 500);
  } catch {
    /* ignore */
  }
}

export function StaffSosOverlay() {
  const [alert, setAlert] = useState<StaffSosAlert | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [muted, setMuted] = useState(false);
  const wakeLockRef = useRef<{ release: () => Promise<void> } | null>(null);

  const refresh = useCallback(async () => {
    try {
      const items = await fetchActiveStaffSosAlerts();
      const next = items[0] ?? null;
      setAlert(next);
    } catch {
      /* keep last known alert while offline/transient errors */
    }
  }, []);

  useEffect(() => {
    void refresh();
    const id = window.setInterval(() => void refresh(), POLL_MS);
    return () => window.clearInterval(id);
  }, [refresh]);

  useEffect(() => {
    if (typeof window === 'undefined' || !('serviceWorker' in navigator)) return;
    const onMessage = (event: MessageEvent) => {
      const data = event.data as { type?: string } | null;
      if (data?.type === 'staff_sos') {
        void refresh();
      }
    };
    navigator.serviceWorker.addEventListener('message', onMessage);
    return () => navigator.serviceWorker.removeEventListener('message', onMessage);
  }, [refresh]);

  useEffect(() => {
    if (!alert) {
      stopVibrate();
      void wakeLockRef.current?.release().catch(() => undefined);
      wakeLockRef.current = null;
      return;
    }

    let cancelled = false;
    const pulse = () => {
      if (cancelled) return;
      try {
        navigator.vibrate?.(VIBRATE_PATTERN);
      } catch {
        /* ignore */
      }
      if (!muted) playSosBeep();
    };
    pulse();
    const id = window.setInterval(pulse, 1_800);

    void (async () => {
      try {
        const nav = navigator as Navigator & {
          wakeLock?: { request: (type: 'screen') => Promise<{ release: () => Promise<void> }> };
        };
        if (nav.wakeLock) {
          wakeLockRef.current = await nav.wakeLock.request('screen');
        }
      } catch {
        /* wake lock optional */
      }
    })();

    return () => {
      cancelled = true;
      window.clearInterval(id);
      stopVibrate();
      void wakeLockRef.current?.release().catch(() => undefined);
      wakeLockRef.current = null;
    };
  }, [alert?.id, muted]);

  const onAcknowledge = async () => {
    if (!alert) return;
    setBusy(true);
    setError(null);
    try {
      await acknowledgeStaffSosAlert(alert.id);
      stopVibrate();
      setAlert(null);
      await refresh();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not stop the alert.');
    } finally {
      setBusy(false);
    }
  };

  const onShift = async (minutes: number) => {
    if (!alert) return;
    setBusy(true);
    setError(null);
    try {
      await shiftStaffSosAppointment(alert.id, minutes);
      stopVibrate();
      setAlert(null);
      await refresh();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not shift the appointment.');
    } finally {
      setBusy(false);
    }
  };

  if (!alert) return null;

  const shifts = (alert.shift_minutes?.length ? alert.shift_minutes : [10, 20, 30, 45]).filter(
    (n) => n === 10 || n === 20 || n === 30 || n === 45,
  );

  return (
    <div
      className="fixed inset-0 z-[80] flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm"
      role="alertdialog"
      aria-modal="true"
      aria-labelledby="staff-sos-title"
    >
      <div className="w-full max-w-lg rounded-2xl border-2 border-red-500 bg-white p-5 shadow-2xl sm:p-6">
        <div className="flex items-start justify-between gap-3">
          <p className="text-xs font-semibold uppercase tracking-[0.18em] text-red-600">
            Emergency SOS · repeating until stopped
          </p>
          <button
            type="button"
            className="text-xs font-medium text-[var(--admin-muted)] underline"
            onClick={() => setMuted((v) => !v)}
          >
            {muted ? 'Unmute beep' : 'Mute beep'}
          </button>
        </div>
        <h2 id="staff-sos-title" className="mt-2 text-xl font-semibold tracking-tight text-[var(--admin-ink)]">
          {alert.title}
        </h2>
        <p className="mt-2 text-sm text-[var(--admin-muted)]">{alert.body}</p>
        {alert.appointment ? (
          <dl className="mt-4 grid gap-1 text-sm text-[var(--admin-ink)]">
            {alert.appointment.client_name ? (
              <div>
                <dt className="inline text-[var(--admin-muted)]">Client: </dt>
                <dd className="inline font-medium">{alert.appointment.client_name}</dd>
              </div>
            ) : null}
            {alert.appointment.starts_at ? (
              <div>
                <dt className="inline text-[var(--admin-muted)]">When: </dt>
                <dd className="inline font-medium">
                  {new Date(alert.appointment.starts_at).toLocaleString()}
                </dd>
              </div>
            ) : null}
            {alert.appointment.booking_reference ? (
              <div>
                <dt className="inline text-[var(--admin-muted)]">Ref: </dt>
                <dd className="inline font-medium">{alert.appointment.booking_reference}</dd>
              </div>
            ) : null}
          </dl>
        ) : null}

        {alert.allow_shift ? (
          <div className="mt-5">
            <p className="text-sm font-medium text-[var(--admin-ink)]">Move appointment forward</p>
            <p className="mt-1 text-xs text-[var(--admin-muted)]">
              Client gets a WhatsApp (when opted in) or email/SMS reschedule message.
            </p>
            <div className="mt-2 flex flex-wrap gap-2">
              {shifts.map((minutes) => (
                <Button
                  key={minutes}
                  type="button"
                  variant="secondary"
                  disabled={busy}
                  onClick={() => void onShift(minutes)}
                >
                  +{minutes} min
                </Button>
              ))}
            </div>
          </div>
        ) : null}

        {error ? <p className="mt-3 text-sm text-red-600">{error}</p> : null}

        <div className="mt-5 flex flex-wrap gap-2">
          <Button type="button" disabled={busy} onClick={() => void onAcknowledge()}>
            Stop alert
          </Button>
        </div>
      </div>
    </div>
  );
}
