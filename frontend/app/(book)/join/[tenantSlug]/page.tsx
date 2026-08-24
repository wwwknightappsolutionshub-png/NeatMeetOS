'use client';

import { Suspense, useEffect } from 'react';
import { useParams, useRouter, useSearchParams } from 'next/navigation';
import {
  bookingPagePath,
  captureJoinAttribution,
  isStandaloneDisplay,
  tenantCustomerPwaPath,
} from '@/lib/tenant-customer-pwa';

function CrmJoinRedirectInner() {
  const params = useParams<{ tenantSlug: string }>();
  const search = useSearchParams();
  const router = useRouter();
  const tenantSlug = params.tenantSlug;

  useEffect(() => {
    captureJoinAttribution(tenantSlug, search.get('ref'), search.get('location'));
    const qs = search.toString();
    const suffix = qs ? `?${qs}` : '';
    if (isStandaloneDisplay()) {
      router.replace(`${tenantCustomerPwaPath(tenantSlug)}${suffix}`);
      return;
    }
    router.replace(`${bookingPagePath(tenantSlug)}${suffix}`);
  }, [router, search, tenantSlug]);

  return (
    <div className="flex min-h-screen items-center justify-center text-sm text-zinc-500">
      Opening the salon app…
    </div>
  );
}

export default function CrmJoinPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-screen items-center justify-center text-sm text-zinc-500">
          Loading…
        </div>
      }
    >
      <CrmJoinRedirectInner />
    </Suspense>
  );
}
