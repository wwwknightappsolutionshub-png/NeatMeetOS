'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminBookingShell } from '@/components/admin/booking/AdminBookingShell';
import { EmptyState, ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { BookableService } from '@/lib/booking-types';
import {
  archiveBookableService,
  createBookableService,
  fetchBookableServices,
  updateBookableService,
  uploadBookableServiceImage,
} from '@/services/booking.service';

const emptyForm = {
  name: '',
  category: '',
  description: '',
  image_url: '' as string | null,
  duration_minutes: '60',
  base_price_cents: '',
  membership_price_cents: '',
  loyalty_price_cents: '',
  is_bookable_online: false,
  deposit_required: false,
  deposit_amount_cents: '',
  min_lead_time_hours: '',
  cancellation_window_hours: '',
};

function centsOrNull(value: string): number | null {
  return value.trim() === '' ? null : Number(value);
}

export default function BookingServicesPage() {
  const [services, setServices] = useState<BookableService[]>([]);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form, setForm] = useState(emptyForm);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    fetchBookableServices(false)
      .then(setServices)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  async function handleImageChange(file: File | null) {
    if (!file) return;
    setUploading(true);
    setError(null);
    try {
      const uploaded = await uploadBookableServiceImage(file);
      setForm((prev) => ({ ...prev, image_url: uploaded.url }));
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Image upload failed');
    } finally {
      setUploading(false);
    }
  }

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    if (!form.image_url) {
      setError('A service photo is required.');
      return;
    }
    const payload = {
      name: form.name,
      category: form.category || null,
      description: form.description.trim() || null,
      image_url: form.image_url,
      duration_minutes: Number(form.duration_minutes),
      base_price_cents: centsOrNull(form.base_price_cents),
      membership_price_cents: centsOrNull(form.membership_price_cents),
      loyalty_price_cents: centsOrNull(form.loyalty_price_cents),
      is_bookable_online: form.is_bookable_online,
      deposit_required: form.deposit_required,
      deposit_amount_cents: centsOrNull(form.deposit_amount_cents),
      min_lead_time_hours: centsOrNull(form.min_lead_time_hours),
      cancellation_window_hours: centsOrNull(form.cancellation_window_hours),
    };
    try {
      if (editingId) {
        await updateBookableService(editingId, payload);
        setEditingId(null);
      } else {
        await createBookableService(payload);
      }
      setForm(emptyForm);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed');
    }
  }

  function startEdit(service: BookableService) {
    setEditingId(service.id);
    setForm({
      name: service.name,
      category: service.category ?? '',
      description: service.description ?? '',
      image_url: service.image_url,
      duration_minutes: String(service.duration_minutes),
      base_price_cents: service.base_price_cents?.toString() ?? '',
      membership_price_cents: service.membership_price_cents?.toString() ?? '',
      loyalty_price_cents: service.loyalty_price_cents?.toString() ?? '',
      is_bookable_online: service.is_bookable_online,
      deposit_required: service.deposit_required,
      deposit_amount_cents: service.deposit_amount_cents?.toString() ?? '',
      min_lead_time_hours: service.min_lead_time_hours?.toString() ?? '',
      cancellation_window_hours: service.cancellation_window_hours?.toString() ?? '',
    });
  }

  if (loading) {
    return (
      <AdminBookingShell title="Services">
        <LoadingState />
      </AdminBookingShell>
    );
  }

  return (
    <AdminBookingShell title="Bookable services">
      {error ? <ErrorAlert message={error} /> : null}
      <div className="grid gap-4 md:grid-cols-2">
        <Card title={editingId ? 'Edit service' : 'Add service'}>
          <form onSubmit={handleSubmit} className="grid gap-3">
            <Field label="Name">
              <input className={inputClass} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
            </Field>
            <Field label="Category">
              <input className={inputClass} value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })} />
            </Field>
            <Field label="Description">
              <textarea
                className={inputClass}
                rows={3}
                value={form.description}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
                placeholder="Shown on the online booking page"
              />
            </Field>
            <Field label="Photo (required)">
              <input
                type="file"
                accept="image/*"
                className={inputClass}
                disabled={uploading}
                onChange={(e) => void handleImageChange(e.target.files?.[0] ?? null)}
              />
              {uploading ? <p className="mt-1 text-xs text-zinc-500">Uploading…</p> : null}
              {form.image_url ? (
                <img
                  src={form.image_url}
                  alt="Service preview"
                  className="mt-2 h-24 w-24 rounded-lg object-cover"
                />
              ) : (
                <p className="mt-1 text-xs text-zinc-500">Upload a photo before saving.</p>
              )}
            </Field>
            <Field label="Duration (minutes)">
              <input type="number" className={inputClass} value={form.duration_minutes} onChange={(e) => setForm({ ...form, duration_minutes: e.target.value })} required />
            </Field>
            <p className="text-xs text-zinc-500">
              Prices in cents. Leave Membership or Loyalty blank to hide that tier on the booking page.
            </p>
            <Field label="Regular price (cents)">
              <input type="number" className={inputClass} value={form.base_price_cents} onChange={(e) => setForm({ ...form, base_price_cents: e.target.value })} />
            </Field>
            <Field label="Membership price (cents)">
              <input type="number" className={inputClass} value={form.membership_price_cents} onChange={(e) => setForm({ ...form, membership_price_cents: e.target.value })} />
            </Field>
            <Field label="Loyalty price (cents)">
              <input type="number" className={inputClass} value={form.loyalty_price_cents} onChange={(e) => setForm({ ...form, loyalty_price_cents: e.target.value })} />
            </Field>
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={form.is_bookable_online} onChange={(e) => setForm({ ...form, is_bookable_online: e.target.checked })} />
              Bookable online
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={form.deposit_required} onChange={(e) => setForm({ ...form, deposit_required: e.target.checked })} />
              Deposit required
            </label>
            <Field label="Deposit amount (cents)">
              <input type="number" className={inputClass} value={form.deposit_amount_cents} onChange={(e) => setForm({ ...form, deposit_amount_cents: e.target.value })} />
            </Field>
            <Field label="Min lead time (hours)">
              <input type="number" className={inputClass} value={form.min_lead_time_hours} onChange={(e) => setForm({ ...form, min_lead_time_hours: e.target.value })} />
            </Field>
            <Field label="Cancellation window (hours)">
              <input type="number" className={inputClass} value={form.cancellation_window_hours} onChange={(e) => setForm({ ...form, cancellation_window_hours: e.target.value })} />
            </Field>
            <div className="flex gap-2">
              <Button type="submit" disabled={uploading}>
                {editingId ? 'Update' : 'Add'}
              </Button>
              {editingId ? (
                <Button
                  type="button"
                  variant="secondary"
                  onClick={() => {
                    setEditingId(null);
                    setForm(emptyForm);
                  }}
                >
                  Cancel
                </Button>
              ) : null}
            </div>
          </form>
        </Card>
        <Card title="Service catalogue">
          {services.length === 0 ? <EmptyState message="No services yet." /> : null}
          <ul className="divide-y divide-zinc-100">
            {services.map((service) => (
              <li key={service.id} className="flex items-start justify-between gap-2 py-3 text-sm">
                <div className="flex min-w-0 items-start gap-3">
                  {service.image_url ? (
                    <img
                      src={service.image_url}
                      alt=""
                      className="h-12 w-12 shrink-0 rounded-md object-cover"
                    />
                  ) : (
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-md bg-zinc-100 text-[10px] text-zinc-400">
                      No photo
                    </div>
                  )}
                  <div className="min-w-0">
                    <p className="font-medium">{service.name}</p>
                    {service.description ? (
                      <p className="mt-0.5 line-clamp-2 text-xs text-zinc-500">{service.description}</p>
                    ) : null}
                    <p className="mt-1 text-xs text-zinc-500">
                      {service.duration_minutes} min
                      {service.base_price_cents != null ? ` · Regular £${(service.base_price_cents / 100).toFixed(2)}` : ''}
                      {service.membership_price_cents != null
                        ? ` · Membership £${(service.membership_price_cents / 100).toFixed(2)}`
                        : ''}
                      {service.loyalty_price_cents != null
                        ? ` · Loyalty £${(service.loyalty_price_cents / 100).toFixed(2)}`
                        : ''}
                      {service.deposit_required ? ` · deposit £${((service.deposit_amount_cents ?? 0) / 100).toFixed(2)}` : ''}
                      {!service.is_active ? ' · archived' : ''}
                    </p>
                  </div>
                </div>
                <div className="flex gap-2">
                  <Button type="button" variant="secondary" onClick={() => startEdit(service)}>
                    Edit
                  </Button>
                  {service.is_active ? (
                    <Button
                      type="button"
                      variant="secondary"
                      onClick={async () => {
                        await archiveBookableService(service.id);
                        load();
                      }}
                    >
                      Archive
                    </Button>
                  ) : null}
                </div>
              </li>
            ))}
          </ul>
        </Card>
      </div>
    </AdminBookingShell>
  );
}
