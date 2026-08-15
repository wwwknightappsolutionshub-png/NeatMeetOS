'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { AdminBookingShell } from '@/components/admin/booking/AdminBookingShell';
import { EmptyState, ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { BookableService } from '@/lib/booking-types';
import {
  centsToMajorInput,
  formatMoneyMinor,
  majorInputToCents,
  normalizeCurrency,
  priceFieldLabel,
} from '@/lib/currency';
import {
  archiveBookableService,
  createBookableService,
  fetchBookableServices,
  updateBookableService,
  uploadBookableServiceImage,
} from '@/services/booking.service';
import { fetchShell } from '@/services/auth.service';

const emptyForm = {
  name: '',
  category: '',
  description: '',
  image_url: '' as string | null,
  duration_minutes: '60',
  base_price: '',
  membership_price: '',
  loyalty_price: '',
  is_bookable_online: false,
  deposit_required: false,
  deposit_amount: '',
  min_lead_time_hours: '',
  cancellation_window_hours: '',
};

export default function BookingServicesPage() {
  const [services, setServices] = useState<BookableService[]>([]);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form, setForm] = useState(emptyForm);
  const [currency, setCurrency] = useState('GBP');
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [archivingId, setArchivingId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const formCardRef = useRef<HTMLDivElement | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    fetchBookableServices(false)
      .then(setServices)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
    void fetchShell()
      .then((shell) => setCurrency(normalizeCurrency(shell.tenant?.currency)))
      .catch(() => setCurrency('GBP'));
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
    if (!editingId && !form.image_url) {
      setError('A service photo is required for new services.');
      return;
    }
    const payload = {
      name: form.name,
      category: form.category || null,
      description: form.description.trim() || null,
      image_url: form.image_url || null,
      duration_minutes: Number(form.duration_minutes),
      base_price_cents: majorInputToCents(form.base_price),
      membership_price_cents: majorInputToCents(form.membership_price),
      loyalty_price_cents: majorInputToCents(form.loyalty_price),
      is_bookable_online: form.is_bookable_online,
      deposit_required: form.deposit_required,
      deposit_amount_cents: majorInputToCents(form.deposit_amount),
      min_lead_time_hours:
        form.min_lead_time_hours.trim() === '' ? null : Number(form.min_lead_time_hours),
      cancellation_window_hours:
        form.cancellation_window_hours.trim() === ''
          ? null
          : Number(form.cancellation_window_hours),
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
    setError(null);
    setEditingId(service.id);
    setForm({
      name: service.name,
      category: service.category ?? '',
      description: service.description ?? '',
      image_url: service.image_url,
      duration_minutes: String(service.duration_minutes),
      base_price: centsToMajorInput(service.base_price_cents),
      membership_price: centsToMajorInput(service.membership_price_cents),
      loyalty_price: centsToMajorInput(service.loyalty_price_cents),
      is_bookable_online: service.is_bookable_online,
      deposit_required: service.deposit_required,
      deposit_amount: centsToMajorInput(service.deposit_amount_cents),
      min_lead_time_hours: service.min_lead_time_hours?.toString() ?? '',
      cancellation_window_hours: service.cancellation_window_hours?.toString() ?? '',
    });
    requestAnimationFrame(() => {
      formCardRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  async function handleArchive(service: BookableService) {
    if (!window.confirm(`Archive “${service.name}”? It will leave the active catalogue.`)) {
      return;
    }
    setArchivingId(service.id);
    setError(null);
    try {
      await archiveBookableService(service.id);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Archive failed');
    } finally {
      setArchivingId(null);
    }
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
        <div ref={formCardRef}>
          <Card title={editingId ? 'Edit service' : 'Add service'}>
            <form onSubmit={handleSubmit} className="grid gap-3">
              <Field label="Name">
                <input
                  className={inputClass}
                  value={form.name}
                  onChange={(e) => setForm({ ...form, name: e.target.value })}
                  required
                />
              </Field>
              <Field label="Category">
                <input
                  className={inputClass}
                  value={form.category}
                  onChange={(e) => setForm({ ...form, category: e.target.value })}
                />
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
              <Field label={editingId ? 'Photo (optional)' : 'Photo (required for new services)'}>
                <input
                  type="file"
                  accept="image/*"
                  className={inputClass}
                  disabled={uploading}
                  onChange={(e) => void handleImageChange(e.target.files?.[0] ?? null)}
                />
                {uploading ? <p className="mt-1 text-xs text-zinc-500">Uploading…</p> : null}
                {form.image_url ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img
                    src={form.image_url}
                    alt="Service preview"
                    className="mt-2 h-24 w-24 rounded-lg object-cover"
                  />
                ) : (
                  <p className="mt-1 text-xs text-zinc-500">
                    {editingId
                      ? 'No photo yet — upload one if you want it on the booking page.'
                      : 'Upload a photo before saving a new service.'}
                  </p>
                )}
              </Field>
              <Field label="Duration (minutes)">
                <input
                  type="number"
                  className={inputClass}
                  value={form.duration_minutes}
                  onChange={(e) => setForm({ ...form, duration_minutes: e.target.value })}
                  required
                />
              </Field>
              <p className="text-xs text-zinc-500">
                Prices in {normalizeCurrency(currency)}. Leave Membership or Loyalty blank to hide
                that tier on the booking page. Change currency under Settings → Account.
              </p>
              <Field label={priceFieldLabel('Regular price', currency)}>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  className={inputClass}
                  value={form.base_price}
                  onChange={(e) => setForm({ ...form, base_price: e.target.value })}
                />
              </Field>
              <Field label={priceFieldLabel('Membership price', currency)}>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  className={inputClass}
                  value={form.membership_price}
                  onChange={(e) => setForm({ ...form, membership_price: e.target.value })}
                />
              </Field>
              <Field label={priceFieldLabel('Loyalty price', currency)}>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  className={inputClass}
                  value={form.loyalty_price}
                  onChange={(e) => setForm({ ...form, loyalty_price: e.target.value })}
                />
              </Field>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={form.is_bookable_online}
                  onChange={(e) => setForm({ ...form, is_bookable_online: e.target.checked })}
                />
                Bookable online
              </label>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={form.deposit_required}
                  onChange={(e) => setForm({ ...form, deposit_required: e.target.checked })}
                />
                Deposit required
              </label>
              <Field label={priceFieldLabel('Deposit amount', currency)}>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  className={inputClass}
                  value={form.deposit_amount}
                  onChange={(e) => setForm({ ...form, deposit_amount: e.target.value })}
                />
              </Field>
              <Field label="Min lead time (hours)">
                <input
                  type="number"
                  className={inputClass}
                  value={form.min_lead_time_hours}
                  onChange={(e) => setForm({ ...form, min_lead_time_hours: e.target.value })}
                />
              </Field>
              <Field label="Cancellation window (hours)">
                <input
                  type="number"
                  className={inputClass}
                  value={form.cancellation_window_hours}
                  onChange={(e) =>
                    setForm({ ...form, cancellation_window_hours: e.target.value })
                  }
                />
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
        </div>
        <Card title="Service catalogue">
          {services.length === 0 ? <EmptyState message="No services yet." /> : null}
          <ul className="divide-y divide-zinc-100">
            {services.map((service) => (
              <li key={service.id} className="flex items-start justify-between gap-2 py-3 text-sm">
                <div className="flex min-w-0 items-start gap-3">
                  {service.image_url ? (
                    // eslint-disable-next-line @next/next/no-img-element
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
                      <p className="mt-0.5 line-clamp-2 text-xs text-zinc-500">
                        {service.description}
                      </p>
                    ) : null}
                    <p className="mt-1 text-xs text-zinc-500">
                      {service.duration_minutes} min
                      {service.base_price_cents != null
                        ? ` · Regular ${formatMoneyMinor(service.base_price_cents, currency)}`
                        : ''}
                      {service.membership_price_cents != null
                        ? ` · Membership ${formatMoneyMinor(service.membership_price_cents, currency)}`
                        : ''}
                      {service.loyalty_price_cents != null
                        ? ` · Loyalty ${formatMoneyMinor(service.loyalty_price_cents, currency)}`
                        : ''}
                      {service.deposit_required
                        ? ` · deposit ${formatMoneyMinor(service.deposit_amount_cents ?? 0, currency)}`
                        : ''}
                      {!service.is_active ? ' · archived' : ''}
                    </p>
                  </div>
                </div>
                <div className="flex shrink-0 gap-2">
                  <Button type="button" variant="secondary" onClick={() => startEdit(service)}>
                    Edit
                  </Button>
                  {service.is_active ? (
                    <Button
                      type="button"
                      variant="secondary"
                      disabled={archivingId === service.id}
                      onClick={() => void handleArchive(service)}
                    >
                      {archivingId === service.id ? '…' : 'Archive'}
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
