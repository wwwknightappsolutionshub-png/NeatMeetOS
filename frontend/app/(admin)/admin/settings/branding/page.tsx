'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminSettingsShell } from '@/components/admin/AdminSettingsShell';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { BrandingSettings } from '@/lib/identity-types';
import { resolveMediaUrl } from '@/lib/media-url';
import {
  fetchBranding,
  updateBranding,
  uploadBrandingEmblem,
  uploadBrandingHeroImage,
  uploadBrandingLogo,
} from '@/services/identity.service';

const emptyBranding: BrandingSettings = {
  brand_display_name: null,
  logo_url: null,
  primary_color: '#18181b',
  secondary_color: '#fafafa',
  receipt_display_name: null,
  support_email: null,
  support_phone: null,
  hero_emblem_mode: 'none',
  hero_emblem_url: null,
  hero_image_url: null,
  store_status: 'auto',
  social_facebook_url: null,
  social_instagram_url: null,
  social_tiktok_url: null,
};

export default function BrandingSettingsPage() {
  const [form, setForm] = useState<BrandingSettings | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [uploadingHero, setUploadingHero] = useState(false);
  const [uploadingLogo, setUploadingLogo] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  const load = useCallback(() => {
    setLoading(true);
    fetchBranding()
      .then((data) =>
        setForm({
          ...emptyBranding,
          ...data,
          hero_emblem_mode: data.hero_emblem_mode ?? 'none',
          store_status: data.store_status ?? 'auto',
        }),
      )
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    if (!form) return;
    setSaving(true);
    setError(null);
    setSaved(false);
    try {
      const updated = await updateBranding(form);
      setForm({
        ...emptyBranding,
        ...updated,
        hero_emblem_mode: updated.hero_emblem_mode ?? 'none',
        store_status: updated.store_status ?? 'auto',
      });
      setSaved(true);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  }

  async function handleLogoUpload(file: File | null) {
    if (!file || !form) return;
    setUploadingLogo(true);
    setError(null);
    setSaved(false);
    try {
      const uploaded = await uploadBrandingLogo(file);
      setForm({
        ...emptyBranding,
        ...uploaded.branding,
        logo_url: uploaded.branding.logo_url ?? uploaded.url,
        hero_emblem_mode: uploaded.branding.hero_emblem_mode ?? 'none',
        store_status: uploaded.branding.store_status ?? 'auto',
      });
      setSaved(true);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Logo upload failed');
    } finally {
      setUploadingLogo(false);
    }
  }

  async function handleHeroUpload(file: File | null) {
    if (!file || !form) return;
    setUploadingHero(true);
    setError(null);
    setSaved(false);
    try {
      const uploaded = await uploadBrandingHeroImage(file);
      setForm({
        ...emptyBranding,
        ...uploaded.branding,
        hero_image_url: uploaded.branding.hero_image_url ?? uploaded.url,
        hero_emblem_mode: uploaded.branding.hero_emblem_mode ?? 'none',
        store_status: uploaded.branding.store_status ?? 'auto',
      });
      setSaved(true);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Hero upload failed');
    } finally {
      setUploadingHero(false);
    }
  }

  async function handleEmblemUpload(file: File | null) {
    if (!file || !form) return;
    setUploading(true);
    setError(null);
    setSaved(false);
    try {
      const uploaded = await uploadBrandingEmblem(file);
      setForm({
        ...emptyBranding,
        ...uploaded.branding,
        hero_emblem_mode: uploaded.branding.hero_emblem_mode ?? 'custom',
        hero_emblem_url: uploaded.branding.hero_emblem_url ?? uploaded.url,
        store_status: uploaded.branding.store_status ?? 'auto',
      });
      setSaved(true);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Upload failed');
    } finally {
      setUploading(false);
    }
  }

  return (
    <AdminSettingsShell title="Branding">
      <Card title="Business identity">
        {loading ? <LoadingState /> : null}
        {error ? <ErrorAlert message={error} /> : null}
        {form ? (
          <form onSubmit={handleSubmit} className="grid max-w-xl gap-3">
            <Field label="Brand display name">
              <input
                className={inputClass}
                value={form.brand_display_name ?? ''}
                onChange={(e) =>
                  setForm({ ...form, brand_display_name: e.target.value || null })
                }
              />
            </Field>
            <Field label="Logo">
              <input
                type="file"
                accept="image/*"
                className={inputClass}
                disabled={uploadingLogo}
                onChange={(e) => void handleLogoUpload(e.target.files?.[0] ?? null)}
              />
              <p className="mt-1 text-xs text-zinc-500">
                Upload from your device (max ~4MB). Used in the booking top bar, and optionally as
                the hero circular emblem.
              </p>
              {uploadingLogo ? (
                <p className="mt-1 text-xs text-zinc-500">Uploading logo…</p>
              ) : null}
              {form.logo_url ? (
                <div className="mt-2 flex items-center gap-3">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img
                    src={resolveMediaUrl(form.logo_url) ?? form.logo_url}
                    alt=""
                    className="h-16 w-16 rounded-lg border border-zinc-200 object-contain bg-white"
                  />
                  <button
                    type="button"
                    className="text-xs text-zinc-600 underline"
                    onClick={() => setForm({ ...form, logo_url: null })}
                  >
                    Clear logo
                  </button>
                </div>
              ) : null}
            </Field>
            <div className="grid grid-cols-2 gap-3">
              <Field label="Primary color">
                <input
                  type="color"
                  className="h-10 w-full cursor-pointer rounded border border-zinc-300"
                  value={form.primary_color}
                  onChange={(e) => setForm({ ...form, primary_color: e.target.value })}
                />
              </Field>
              <Field label="Secondary color">
                <input
                  type="color"
                  className="h-10 w-full cursor-pointer rounded border border-zinc-300"
                  value={form.secondary_color}
                  onChange={(e) => setForm({ ...form, secondary_color: e.target.value })}
                />
              </Field>
            </div>
            <Field label="Booking page store status">
              <select
                className={inputClass}
                value={form.store_status}
                onChange={(e) =>
                  setForm({
                    ...form,
                    store_status: e.target.value as BrandingSettings['store_status'],
                  })
                }
              >
                <option value="auto">Auto from opening hours</option>
                <option value="open">Force: We&apos;re open</option>
                <option value="opening_soon">Force: We&apos;re opening soon</option>
                <option value="closing">Force: We&apos;re Closing</option>
                <option value="closed">Force: Close for the day</option>
              </select>
              <p className="mt-1 text-xs text-zinc-500">
                Auto uses location opening hours (30 minutes before open/close). Override only
                when you need a manual status.
              </p>
            </Field>
            <Field label="Booking page hero background">
              <input
                type="file"
                accept="image/*"
                className={inputClass}
                disabled={uploadingHero}
                onChange={(e) => void handleHeroUpload(e.target.files?.[0] ?? null)}
              />
              <p className="mt-1 text-xs text-zinc-500">
                Full-bleed photo behind the welcome greeting on the booking page. Upload from your
                device (max ~8MB). Clears back to the default image if you remove the URL and save.
              </p>
              {uploadingHero ? (
                <p className="mt-1 text-xs text-zinc-500">Uploading hero…</p>
              ) : null}
              {form.hero_image_url ? (
                <div className="mt-2 space-y-2">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img
                    src={resolveMediaUrl(form.hero_image_url) ?? form.hero_image_url}
                    alt=""
                    className="h-28 w-full max-w-md rounded-lg border border-zinc-200 object-cover"
                  />
                  <button
                    type="button"
                    className="text-xs text-zinc-600 underline"
                    onClick={() => setForm({ ...form, hero_image_url: null })}
                  >
                    Clear custom hero (use default)
                  </button>
                </div>
              ) : null}
            </Field>
            <Field label="Hero circular emblem">
              <select
                className={inputClass}
                value={form.hero_emblem_mode}
                onChange={(e) =>
                  setForm({
                    ...form,
                    hero_emblem_mode: e.target.value as BrandingSettings['hero_emblem_mode'],
                  })
                }
              >
                <option value="none">None (optional — leave empty)</option>
                <option value="logo">Use store logo</option>
                <option value="custom">Custom image (upload)</option>
              </select>
            </Field>
            {form.hero_emblem_mode === 'custom' ? (
              <Field label="Upload hero emblem from device">
                <input
                  type="file"
                  accept="image/*"
                  className={inputClass}
                  disabled={uploading}
                  onChange={(e) => void handleEmblemUpload(e.target.files?.[0] ?? null)}
                />
                {uploading ? (
                  <p className="mt-1 text-xs text-zinc-500">Uploading…</p>
                ) : null}
                {form.hero_emblem_url ? (
                  <div className="mt-2 flex items-center gap-3">
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img
                      src={resolveMediaUrl(form.hero_emblem_url) ?? form.hero_emblem_url}
                      alt=""
                      className="h-16 w-16 rounded-full border border-zinc-200 object-cover"
                    />
                    <p className="text-xs text-zinc-500 break-all">
                      {resolveMediaUrl(form.hero_emblem_url) ?? form.hero_emblem_url}
                    </p>
                  </div>
                ) : (
                  <p className="mt-1 text-xs text-zinc-500">
                    Choose an image from your device. It is saved to storage and linked on the
                    booking hero.
                  </p>
                )}
              </Field>
            ) : null}
            <Field label="Receipt / invoice display name">
              <input
                className={inputClass}
                value={form.receipt_display_name ?? ''}
                onChange={(e) =>
                  setForm({ ...form, receipt_display_name: e.target.value || null })
                }
              />
            </Field>
            <Field label="Support email">
              <input
                type="email"
                className={inputClass}
                value={form.support_email ?? ''}
                onChange={(e) =>
                  setForm({ ...form, support_email: e.target.value || null })
                }
              />
            </Field>
            <Field label="Support phone">
              <input
                className={inputClass}
                value={form.support_phone ?? ''}
                onChange={(e) =>
                  setForm({ ...form, support_phone: e.target.value || null })
                }
              />
            </Field>
            <p className="text-xs font-semibold uppercase tracking-wide text-zinc-500">
              Social links
            </p>
            <Field label="Facebook URL">
              <input
                className={inputClass}
                type="url"
                value={form.social_facebook_url ?? ''}
                onChange={(e) =>
                  setForm({ ...form, social_facebook_url: e.target.value || null })
                }
                placeholder="https://facebook.com/…"
              />
            </Field>
            <Field label="Instagram URL">
              <input
                className={inputClass}
                type="url"
                value={form.social_instagram_url ?? ''}
                onChange={(e) =>
                  setForm({ ...form, social_instagram_url: e.target.value || null })
                }
                placeholder="https://instagram.com/…"
              />
            </Field>
            <Field label="TikTok URL">
              <input
                className={inputClass}
                type="url"
                value={form.social_tiktok_url ?? ''}
                onChange={(e) =>
                  setForm({ ...form, social_tiktok_url: e.target.value || null })
                }
                placeholder="https://tiktok.com/@…"
              />
            </Field>
            <div className="flex items-center gap-3">
              <Button type="submit" disabled={saving || uploading || uploadingHero || uploadingLogo}>
                {saving ? 'Saving…' : 'Save branding'}
              </Button>
              {saved ? <span className="text-sm text-emerald-600">Saved</span> : null}
            </div>
          </form>
        ) : null}
      </Card>
    </AdminSettingsShell>
  );
}
