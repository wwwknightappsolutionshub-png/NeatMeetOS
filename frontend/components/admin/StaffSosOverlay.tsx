'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/Button';
import {
  acknowledgeStaffSosAlert,
  acceptBookingChangeRequest,
  declineBookingChangeRequest,
  fetchActiveStaffSosAlerts,
  shiftStaffSosAppointment,
  type StaffSosAlert,
} from '@/services/staff-sos.service';

const POLL_MS = 5_000;
/** Aggressive emergency vibration (long bursts) until the tenant stops the alert. */
const EMERGENCY_VIBRATE_PATTERN = [1000, 200, 1000, 200, 1000, 200, 1000, 400];
const SIREN_CYCLE_MS = 2_000;

function stopVibrate(): void {
  try {
    navigator.vibrate?.(0);
  } catch {
    /* ignore */
  }
}

/** Rising/falling siren tone for tenant SOS alerts. */
function playSosSiren(): void {
  try {
    const Ctx =
      window.AudioContext ||
      (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
    if (!Ctx) return;
    const ctx = new Ctx();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'sawtooth';
    gain.gain.value = 0.14;
    osc.connect(gain);
    gain.connect(ctx.destination);
    const now = ctx.currentTime;
    osc.frequency.setValueAtTime(650, now);
    osc.frequency.linearRampToValueAtTime(1450, now + 0.45);
    osc.frequency.linearRampToValueAtTime(650, now + 0.9);
    osc.frequency.linearRampToValueAtTime(1450, now + 1.35);
    osc.frequency.linearRampToValueAtTime(650, now + 1.8);
    gain.gain.setValueAtTime(0.14, now);
    gain.gain.setValueAtTime(0.14, now + 1.65);
    gain.gain.exponentialRampToValueAtTime(0.0001, now + 1.9);
    osc.start(now);
    osc.stop(now + 1.95);
    window.setTimeout(() => void ctx.close(), 2200);
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
  const lastSosSignatureRef = useRef<string | null>(null);

  const refresh = useCallback(async () => {
    try {
      const items = await fetchActiveStaffSosAlerts();
      const next = items[0] ?? null;
      const signature = items.map((item) => `${item.id}:${item.status}`).join('|');
      setAlert(next);
      if (signature !== lastSosSignatureRef.current) {
        lastSosSignatureRef.current = signature;
        window.dispatchEvent(new CustomEvent('neatmeet:staff-sos', { detail: { items, next } }));
      }
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
        navigator.vibrate?.(EMERGENCY_VIBRATE_PATTERN);
      } catch {
        /* ignore */
      }
      if (!muted) playSosSiren();
    };
    pulse();
    const id = window.setInterval(pulse, SIREN_CYCLE_MS);

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

  const onChangeRequest = async (action: 'accept' | 'decline') => {
    if (!alert) return;
    const changeRequestId = String(alert.payload?.change_request_id ?? '');
    if (!changeRequestId) {
      setError('Missing change request id on this alert.');
      return;
    }
    setBusy(true);
    setError(null);
    try {
      if (action === 'accept') {
        await acceptBookingChangeRequest(changeRequestId);
      } else {
        await declineBookingChangeRequest(changeRequestId);
      }
      await acknowledgeStaffSosAlert(alert.id);
      stopVibrate();
      setAlert(null);
      await refresh();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not resolve the request.');
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
            {muted ? 'Unmute siren' : 'Mute siren'}
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

        {alert.kind === 'change_request' ? (
          <div className="mt-5 flex flex-wrap gap-2">
            <Button type="button" disabled={busy} onClick={() => void onChangeRequest('accept')}>
              Confirm cancel
            </Button>
            {alert.payload?.decline_allowed ? (
              <Button
                type="button"
                variant="secondary"
                disabled={busy}
                onClick={() => void onChangeRequest('decline')}
              >
                Decline
              </Button>
            ) : (
              <p className="w-full text-xs text-[var(--admin-muted)]">
                Free window — decline is not allowed.
              </p>
            )}
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
