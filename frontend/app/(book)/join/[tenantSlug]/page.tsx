'use client';

import Link from 'next/link';
import { Suspense, useEffect, useState, type CSSProperties } from 'react';
import { useParams, useRouter, useSearchParams } from 'next/navigation';
import { MembershipJoinForm } from '@/components/member/MembershipJoinForm';
import { NeatMeetLogo } from '@/components/brand/NeatMeetLogo';
import { resolveMediaUrl } from '@/lib/media-url';
import {
  captureJoinAttribution,
  tenantCustomerPwaPath,
} from '@/lib/tenant-customer-pwa';
import { fetchCrmJoinBootstrap, type CrmJoinBootstrap } from '@/services/crm-join.service';

function CrmJoinPageInner() {
  const params = useParams<{ tenantSlug: string }>();
  const search = useSearchParams();
  const router = useRouter();
  const tenantSlug = params.tenantSlug;

  const [bootstrap, setBootstrap] = useState<CrmJoinBootstrap | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [joined, setJoined] = useState(false);

  useEffect(() => {
    captureJoinAttribution(tenantSlug, search.get('ref'), search.get('location'));
  }, [search, tenantSlug]);

  useEffect(() => {
    let cancelled = false;
    void fetchCrmJoinBootstrap(tenantSlug, search.get('location') || undefined)
      .then((data) => {
        if (!cancelled) setBootstrap(data);
      })
      .catch((e) => {
        if (!cancelled) {
          setLoadError(e instanceof Error ? e.message : 'Unable to load salon join form');
        }
      });
    return () => {
      cancelled = true;
    };
  }, [tenantSlug, search]);

  const branding = bootstrap?.tenant.branding;
  const salonName =
    (branding?.brand_display_name as string | undefined) ||
    bootstrap?.tenant.name ||
    'Salon';
  const brandPrimary = (branding?.primary_color as string | undefined) || '#3d5a45';
  const logoUrl = resolveMediaUrl((branding?.logo_url as string | undefined) || null);

  const brandStyle = {
    '--book-moss': brandPrimary,
    '--book-moss-deep': brandPrimary,
  } as CSSProperties;

  const memberHref = (() => {
    const qs = search.toString();
    return `${tenantCustomerPwaPath(tenantSlug)}${qs ? `?${qs}` : ''}`;
  })();

  return (
    <div className="min-h-screen bg-[var(--book-wash)] text-[var(--book-ink)]" style={brandStyle}>
      <header className="border-b border-[var(--book-line)] bg-white/90 backdrop-blur">
        <div className="mx-auto flex max-w-lg items-center gap-3 px-4 py-4 sm:px-6">
          {logoUrl ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={logoUrl} alt="" className="h-9 w-9 rounded-md object-cover" />
          ) : (
            <NeatMeetLogo size={36} variant="color" />
          )}
          <div className="min-w-0">
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[var(--book-muted)]">
              Join our salon family
            </p>
            <p className="truncate text-sm font-semibold">{salonName}</p>
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-lg px-4 py-8 sm:px-6">
        {loadError ? (
          <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {loadError}
          </div>
        ) : null}

        {joined ? (
          <div className="rounded-2xl border border-[var(--book-line)] bg-white p-6 shadow-[var(--book-shadow)]">
            <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-[var(--book-moss)]">
              Welcome
            </p>
            <h1 className="book-display mt-2 text-3xl font-bold text-[var(--book-ink)]">
              You&apos;re in the family
            </h1>
            <p className="mt-3 text-sm leading-relaxed text-[var(--book-muted)]">
              Your details are saved with {salonName}. Open the member app to log in with a
              one-time WhatsApp code, or book your next visit anytime.
            </p>
            <div className="mt-6 flex flex-col gap-3">
              <button
                type="button"
                className="inline-flex items-center justify-center rounded-md bg-[var(--book-moss)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--book-moss-deep)]"
                onClick={() => router.push(memberHref)}
              >
                Open member app
              </button>
              <Link
                href={`/book/${encodeURIComponent(tenantSlug)}`}
                className="inline-flex items-center justify-center rounded-md border border-[var(--book-line)] bg-white px-5 py-2.5 text-sm font-semibold text-[var(--book-ink)] hover:bg-[var(--book-wash)]"
              >
                Book an appointment
              </Link>
            </div>
          </div>
        ) : (
          <div className="rounded-2xl border border-[var(--book-line)] bg-white p-5 shadow-[var(--book-shadow)] sm:p-6">
            <MembershipJoinForm
              tenantSlug={tenantSlug}
              referralCode={search.get('ref') || undefined}
              locationFromQuery={search.get('location')}
              onJoined={() => setJoined(true)}
            />
            <p className="mt-6 border-t border-[var(--book-line)] pt-4 text-center text-xs text-[var(--book-muted)]">
              Already a member?{' '}
              <Link
                href={memberHref}
                className="font-semibold text-[var(--book-moss)] underline-offset-2 hover:underline"
              >
                Log in here
              </Link>
              {' · '}
              <Link
                href={`/book/${encodeURIComponent(tenantSlug)}`}
                className="font-semibold text-[var(--book-moss)] underline-offset-2 hover:underline"
              >
                Book online
              </Link>
            </p>
          </div>
        )}
      </main>
    </div>
  );
}

export default function CrmJoinPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-screen items-center justify-center text-sm text-zinc-500">
          Loading join form…
        </div>
      }
    >
      <CrmJoinPageInner />
    </Suspense>
  );
}
