import { describe, expect, it } from 'vitest';
import {
  bookingPagePath,
  tenantCustomerManifestPath,
  tenantCustomerPwaPath,
} from '@/lib/tenant-customer-pwa';

describe('tenant customer PWA paths', () => {
  it('points Book home and the membership PWA at the tenant slug', () => {
    expect(bookingPagePath('chris-cut')).toBe('/book/chris-cut');
    expect(tenantCustomerPwaPath('chris-cut')).toBe('/member/chris-cut');
    expect(tenantCustomerManifestPath('chris-cut')).toBe(
      '/member/chris-cut/manifest.webmanifest',
    );
  });
});
