import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { getStoredToken } from './api-client';

declare global {
  interface Window {
    Pusher: typeof Pusher;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    Echo?: any;
  }
}

function apiOrigin(): string {
  const apiUrl =
    process.env.NEXT_PUBLIC_API_URL?.replace(/\/$/, '') ?? 'http://localhost:8000/api/v1';
  return apiUrl.replace(/\/api\/v1$/, '') || 'http://localhost:8000';
}

function reverbConfigured(): boolean {
  return Boolean(
    process.env.NEXT_PUBLIC_REVERB_APP_KEY &&
      process.env.NEXT_PUBLIC_REVERB_HOST,
  );
}

/**
 * Shared Echo client for Reverb private channels. Returns null when env is not configured.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function getEcho(): any | null {
  if (typeof window === 'undefined' || !reverbConfigured()) {
    return null;
  }

  if (window.Echo) {
    return window.Echo;
  }

  window.Pusher = Pusher;

  const key = process.env.NEXT_PUBLIC_REVERB_APP_KEY as string;
  const host = process.env.NEXT_PUBLIC_REVERB_HOST as string;
  const port = Number(process.env.NEXT_PUBLIC_REVERB_PORT ?? '8080');
  const scheme = process.env.NEXT_PUBLIC_REVERB_SCHEME ?? 'http';

  window.Echo = new Echo({
    broadcaster: 'reverb',
    key,
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${apiOrigin()}/broadcasting/auth`,
    auth: {
      headers: {
        Authorization: `Bearer ${getStoredToken() ?? ''}`,
        Accept: 'application/json',
      },
    },
  });

  return window.Echo;
}

export function subscribeBookingBoard(
  tenantId: string,
  onUpdate: (payload: { date: string; location_id: string | null }) => void,
): (() => void) | null {
  const echo = getEcho();
  if (!echo || !tenantId) {
    return null;
  }

  const channelName = `tenant.${tenantId}.booking-board`;
  const channel = echo.private(channelName);

  channel.listen('.BookingBoardUpdated', (payload: { date: string; location_id: string | null }) => {
    onUpdate(payload);
  });

  return () => {
    echo.leave(channelName);
  };
}
