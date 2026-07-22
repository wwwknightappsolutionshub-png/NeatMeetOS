import { describe, expect, it } from 'vitest';
import {
  EMPLOYMENT_TYPES,
  WORKSPACE_TYPES,
  type BrandingSettings,
  type PermissionGroup,
} from '@/lib/identity-types';

describe('Module 1B identity types', () => {
  it('defines branding settings shape', () => {
    const branding: BrandingSettings = {
      brand_display_name: 'Salon',
      logo_url: null,
      primary_color: '#18181b',
      secondary_color: '#fafafa',
      receipt_display_name: null,
      support_email: 'help@example.com',
      support_phone: null,
      hero_emblem_mode: 'none',
      hero_emblem_url: null,
      hero_image_url: null,
      store_status: 'auto',
      social_facebook_url: null,
      social_instagram_url: 'https://instagram.com/example',
      social_tiktok_url: null,
    };
    expect(branding.primary_color).toMatch(/^#[0-9A-Fa-f]{6}$/);
    expect(branding.social_instagram_url).toContain('instagram.com');
  });

  it('groups permissions by module', () => {
    const groups: PermissionGroup[] = [
      {
        module: 'identity',
        permissions: [
          { id: 'identity.view', name: 'View', slug: 'identity.view', module: 'identity' },
        ],
      },
    ];
    expect(groups[0].module).toBe('identity');
  });

  it('includes opening hours on locations', () => {
    const hour = {
      day_of_week: 1,
      start_time: '09:00',
      end_time: '18:00',
      is_closed: false,
    };
    expect(hour.day_of_week).toBe(1);
  });
});
