'use client';

import { useEffect, useMemo, useState } from 'react';
import { bookingQrFilename, buildBookingPageUrl } from '@/lib/booking-qr';
import { PublicQrPanel } from '@/components/shared/PublicQrPanel';

interface BookingQrPanelProps {
  tenantSlug: string;
  locationId?: string | null;
  locationName?: string | null;
  brandName?: string | null;
  variant?: 'admin' | 'portal';
}

export function BookingQrPanel({
  tenantSlug,
  locationId,
  locationName,
  brandName,
  variant = 'admin',
}: BookingQrPanelProps) {
  const [url, setUrl] = useState('');

  useEffect(() => {
    setUrl(buildBookingPageUrl(tenantSlug, { locationId: locationId ?? null }));
  }, [tenantSlug, locationId]);

  const heading = useMemo(
    () => (locationName ? `Booking QR · ${locationName}` : 'Booking QR'),
    [locationName],
  );

  if (!url) return null;

  return (
    <PublicQrPanel
      url={url}
      filename={bookingQrFilename(tenantSlug, locationName)}
      heading={heading}
      printSubtitle={locationName ? `Location: ${locationName}` : 'Online booking'}
      brandName={brandName}
      variant={variant}
    />
  );
}
