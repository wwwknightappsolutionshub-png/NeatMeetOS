'use client';

import { PublicQrPanel } from '@/components/shared/PublicQrPanel';
import { buildCrmJoinPageUrl, crmJoinQrFilename } from '@/lib/crm-join-qr';

interface CrmJoinQrPanelProps {
  tenantSlug: string;
  locationId?: string | null;
  locationName?: string | null;
  brandName?: string | null;
  variant?: 'admin' | 'portal';
}

export function CrmJoinQrPanel({
  tenantSlug,
  locationId,
  locationName,
  brandName,
  variant = 'admin',
}: CrmJoinQrPanelProps) {
  const url = buildCrmJoinPageUrl(tenantSlug, { locationId: locationId ?? null });
  const heading = locationName ? `Customer QR · ${locationName}` : 'Customer QR';

  return (
    <PublicQrPanel
      url={url}
      filename={crmJoinQrFilename(tenantSlug, locationName)}
      heading={heading}
      printSubtitle={
        locationName
          ? `Scan to book & install · ${locationName}`
          : 'Scan to book, install the app, then join'
      }
      brandName={brandName}
      variant={variant}
    />
  );
}
