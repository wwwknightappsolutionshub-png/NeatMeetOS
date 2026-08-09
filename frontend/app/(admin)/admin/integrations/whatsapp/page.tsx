'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { LoadingState } from '@/components/admin/ui';

/** WhatsApp scan lives under Settings so it is not plan-gated with Integrations. */
export default function TenantWhatsAppIntegrationsRedirectPage() {
  const router = useRouter();

  useEffect(() => {
    router.replace('/admin/settings/whatsapp');
  }, [router]);

  return <LoadingState label="Opening Salon WhatsApp…" />;
}
