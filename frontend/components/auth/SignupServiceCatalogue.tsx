'use client';

import { useMemo, useRef, useState, type FormEvent } from 'react';
import { createPortal } from 'react-dom';
import type { SignupServiceDraft, SignupServiceTemplate } from '@/lib/types';
import { uploadSignupServiceImage } from '@/services/signup.service';

const inputClass =
  'mt-1 w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-stone-900 outline-none transition focus:border-[#2f5a45] focus:ring-2 focus:ring-[#2f5a45]/20';

const UPGRADE_TOAST = 'Upgrade your plan to unlock more';

function formatPrice(cents: number): string {
  return new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: 'GBP',
  }).format(cents / 100);
}

function poundsToCents(value: string): number {
  const n = Number.parseFloat(value);
  if (Number.isNaN(n) || n < 0) return 0;
  return Math.round(n * 100);
}

function centsToPoundsInput(cents: number): string {
  return (cents / 100).toFixed(2);
}

export function matchesBusinessType(
  businessTypes: string[] | undefined,
  businessType: string,
): boolean {
  if (!businessType) return true;
  if (!businessTypes || businessTypes.length === 0) return true;
  return businessTypes.includes(businessType);
}

export function buildInitialServiceDrafts(
  catalogue: SignupServiceTemplate[],
  businessType?: string,
  maxSelected = 4,
  preserveCustom: SignupServiceDraft[] = [],
): SignupServiceDraft[] {
  const drafts = catalogue.map((item) => ({
    key: item.key,
    name: item.name,
    category: item.category,
    description: item.description,
    duration_minutes: item.duration_minutes,
    base_price_cents: item.base_price_cents,
    image_url: null as string | null,
    selected: false,
    business_types: item.business_types ?? [],
    is_custom: false,
  }));

  let selected = 0;
  for (const draft of drafts) {
    const template = catalogue.find((c) => c.key === draft.key);
    if (!template?.selected_by_default) continue;
    if (businessType && !matchesBusinessType(draft.business_types, businessType)) {
      continue;
    }
    if (selected >= maxSelected) break;
    draft.selected = true;
    selected += 1;
  }

  const customs: SignupServiceDraft[] = [];
  for (const d of preserveCustom.filter((x) => x.is_custom)) {
    const keepSelected = Boolean(d.selected) && selected < maxSelected;
    if (keepSelected) selected += 1;
    customs.push({
      ...d,
      business_types: businessType ? [businessType] : d.business_types,
      selected: keepSelected,
    });
  }

  return [...drafts, ...customs];
}

interface SignupServiceCatalogueProps {
  drafts: SignupServiceDraft[];
  onChange: (drafts: SignupServiceDraft[]) => void;
  businessType: string;
  maxSelectable?: number;
  onLimitReached?: () => void;
}

export function SignupServiceCatalogue({
  drafts,
  onChange,
  businessType,
  maxSelectable = 4,
  onLimitReached,
}: SignupServiceCatalogueProps) {
  const [uploadingKey, setUploadingKey] = useState<string | null>(null);
  const [uploadError, setUploadError] = useState<string | null>(null);
  const [expandedKeys, setExpandedKeys] = useState<Record<string, boolean>>({});
  const [modalOpen, setModalOpen] = useState(false);
  const [modalError, setModalError] = useState<string | null>(null);
  const [modalUploading, setModalUploading] = useState(false);
  const [modalForm, setModalForm] = useState({
    name: '',
    description: '',
    duration_minutes: 30,
    base_price_cents: 2500,
    image_url: null as string | null,
  });
  const fileRefs = useRef<Record<string, HTMLInputElement | null>>({});
  const modalFileRef = useRef<HTMLInputElement | null>(null);
  const customSeq = useRef(0);

  const visibleDrafts = useMemo(
    () =>
      drafts.filter(
        (d) =>
          d.is_custom || matchesBusinessType(d.business_types, businessType),
      ),
    [drafts, businessType],
  );

  const selectedCount = visibleDrafts.filter((d) => d.selected).length;

  function updateDraft(key: string, patch: Partial<SignupServiceDraft>) {
    onChange(drafts.map((d) => (d.key === key ? { ...d, ...patch } : d)));
  }

  function toggleExpanded(key: string) {
    setExpandedKeys((prev) => ({ ...prev, [key]: !prev[key] }));
  }

  function toggleSelected(key: string, nextSelected: boolean) {
    if (nextSelected) {
      const current = visibleDrafts.filter((d) => d.selected).length;
      if (current >= maxSelectable) {
        onLimitReached?.();
        return;
      }
    } else {
      setExpandedKeys((prev) => {
        if (!prev[key]) return prev;
        const next = { ...prev };
        delete next[key];
        return next;
      });
    }
    updateDraft(key, { selected: nextSelected });
  }

  function openCustomModal() {
    if (selectedCount >= maxSelectable) {
      onLimitReached?.();
      return;
    }
    setModalError(null);
    setModalForm({
      name: '',
      description: '',
      duration_minutes: 30,
      base_price_cents: 2500,
      image_url: null,
    });
    setModalOpen(true);
  }

  function closeCustomModal() {
    setModalOpen(false);
    setModalError(null);
    setModalUploading(false);
  }

  async function handleModalImagePick(file: File | null) {
    if (!file) return;
    setModalError(null);
    setModalUploading(true);
    try {
      const { url } = await uploadSignupServiceImage(file);
      setModalForm((prev) => ({ ...prev, image_url: url }));
    } catch (e) {
      setModalError(e instanceof Error ? e.message : 'Image upload failed');
    } finally {
      setModalUploading(false);
    }
  }

  function submitCustomService(e?: FormEvent) {
    e?.preventDefault();
    if (selectedCount >= maxSelectable) {
      onLimitReached?.();
      closeCustomModal();
      return;
    }
    const name = modalForm.name.trim();
    if (!name) {
      setModalError('Service name is required');
      return;
    }
    customSeq.current += 1;
    const key = `custom_${Date.now()}_${customSeq.current}`;
    const draft: SignupServiceDraft = {
      key,
      name,
      category: 'custom',
      description: modalForm.description.trim(),
      duration_minutes: modalForm.duration_minutes,
      base_price_cents: modalForm.base_price_cents,
      image_url: modalForm.image_url,
      selected: true,
      business_types: businessType ? [businessType] : [],
      is_custom: true,
    };
    onChange([...drafts, draft]);
    closeCustomModal();
  }

  function removeCustomService(key: string) {
    onChange(drafts.filter((d) => d.key !== key));
    setExpandedKeys((prev) => {
      const next = { ...prev };
      delete next[key];
      return next;
    });
  }

  async function handleImagePick(key: string, file: File | null) {
    if (!file) return;
    setUploadError(null);
    setUploadingKey(key);
    try {
      const { url } = await uploadSignupServiceImage(file);
      const draft = drafts.find((d) => d.key === key);
      if (!draft?.selected) {
        const current = visibleDrafts.filter((d) => d.selected).length;
        if (current >= maxSelectable) {
          onLimitReached?.();
          updateDraft(key, { image_url: url });
          return;
        }
        updateDraft(key, { image_url: url, selected: true });
      } else {
        updateDraft(key, { image_url: url });
      }
    } catch (e) {
      setUploadError(e instanceof Error ? e.message : 'Image upload failed');
    } finally {
      setUploadingKey(null);
    }
  }

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between gap-2">
        <span className="text-sm font-medium text-stone-700">Services</span>
        <span className="text-[11px] text-stone-400">
          {selectedCount}/{maxSelectable} selected · Basic plan
        </span>
      </div>
      <p className="text-[11px] text-stone-500">
        Showing services for your business type
        {businessType ? ` (${businessType})` : ''}. Toggle up to {maxSelectable}{' '}
        on Basic — or add your own custom service.
      </p>
      {uploadError ? (
        <p className="text-sm text-red-600">{uploadError}</p>
      ) : null}

      <button
        type="button"
        onClick={openCustomModal}
        className="w-full rounded-xl border border-dashed border-[#2f5a45]/50 bg-[#e8f0eb]/50 px-4 py-3 text-sm font-semibold text-[#2f5a45] transition hover:bg-[#e8f0eb]"
      >
        + Add custom service
      </button>

      {modalOpen
        ? createPortal(
            <div
              className="fixed inset-0 z-[100] flex items-center justify-center bg-stone-900/50 p-4"
              role="dialog"
              aria-modal="true"
              aria-labelledby="custom-service-title"
              onClick={(e) => {
                if (e.target === e.currentTarget) closeCustomModal();
              }}
            >
              <div className="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl border border-stone-200 bg-white p-5 shadow-xl">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <h3
                      id="custom-service-title"
                      className="text-lg font-semibold text-stone-900"
                    >
                      Add custom service
                    </h3>
                    <p className="mt-1 text-xs text-stone-500">
                      Same details as catalogue services — name, photo, price,
                      and time.
                    </p>
                  </div>
                  <button
                    type="button"
                    onClick={closeCustomModal}
                    className="rounded-md px-2 py-1 text-sm text-stone-500 hover:bg-stone-100"
                  >
                    Close
                  </button>
                </div>

                <div className="mt-4 space-y-3">
                  {modalError ? (
                    <p className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                      {modalError}
                    </p>
                  ) : null}

                  <label className="block text-xs">
                    <span className="font-medium text-stone-600">
                      Service name
                    </span>
                    <input
                      type="text"
                      value={modalForm.name}
                      onChange={(e) =>
                        setModalForm((prev) => ({
                          ...prev,
                          name: e.target.value,
                        }))
                      }
                      className={inputClass}
                      placeholder="e.g. Beard colour"
                      required
                      autoFocus
                    />
                  </label>

                  <div className="flex gap-3">
                    <div className="relative h-16 w-16 shrink-0 overflow-hidden rounded-lg border border-stone-200 bg-stone-100">
                      {modalForm.image_url ? (
                        // eslint-disable-next-line @next/next/no-img-element
                        <img
                          src={modalForm.image_url}
                          alt=""
                          className="h-full w-full object-cover"
                        />
                      ) : (
                        <div className="flex h-full items-center justify-center text-[10px] text-stone-400">
                          No photo
                        </div>
                      )}
                    </div>
                    <div className="flex flex-1 flex-col justify-center gap-1.5">
                      <input
                        ref={modalFileRef}
                        type="file"
                        accept="image/*"
                        className="hidden"
                        onChange={(e) =>
                          void handleModalImagePick(e.target.files?.[0] ?? null)
                        }
                      />
                      <button
                        type="button"
                        disabled={modalUploading}
                        onClick={() => modalFileRef.current?.click()}
                        className="rounded-lg border border-stone-300 bg-white px-3 py-1.5 text-left text-xs font-medium text-stone-700 hover:bg-stone-50 disabled:opacity-60"
                      >
                        {modalUploading
                          ? 'Uploading…'
                          : modalForm.image_url
                            ? 'Change image'
                            : 'Add image from device'}
                      </button>
                      {modalForm.image_url ? (
                        <button
                          type="button"
                          onClick={() =>
                            setModalForm((prev) => ({
                              ...prev,
                              image_url: null,
                            }))
                          }
                          className="text-left text-[11px] text-stone-500 underline"
                        >
                          Remove image
                        </button>
                      ) : null}
                    </div>
                  </div>

                  <label className="block text-xs">
                    <span className="font-medium text-stone-600">
                      Description
                    </span>
                    <textarea
                      value={modalForm.description}
                      onChange={(e) =>
                        setModalForm((prev) => ({
                          ...prev,
                          description: e.target.value,
                        }))
                      }
                      rows={2}
                      className={inputClass}
                      placeholder="What clients should expect"
                    />
                  </label>

                  <div className="grid grid-cols-2 gap-3">
                    <label className="block text-xs">
                      <span className="font-medium text-stone-600">
                        Price (£)
                      </span>
                      <input
                        type="number"
                        min={0}
                        step="0.01"
                        value={centsToPoundsInput(modalForm.base_price_cents)}
                        onChange={(e) =>
                          setModalForm((prev) => ({
                            ...prev,
                            base_price_cents: poundsToCents(e.target.value),
                          }))
                        }
                        className={inputClass}
                      />
                    </label>
                    <label className="block text-xs">
                      <span className="font-medium text-stone-600">
                        Min. completion time (minutes)
                      </span>
                      <input
                        type="number"
                        min={5}
                        max={480}
                        step={5}
                        value={modalForm.duration_minutes}
                        onChange={(e) =>
                          setModalForm((prev) => ({
                            ...prev,
                            duration_minutes: Math.max(
                              5,
                              Math.min(
                                480,
                                Number.parseInt(e.target.value || '5', 10),
                              ),
                            ),
                          }))
                        }
                        className={inputClass}
                      />
                    </label>
                  </div>

                  <div className="flex gap-2 pt-2">
                    <button
                      type="button"
                      onClick={closeCustomModal}
                      className="flex-1 rounded-lg border border-stone-300 px-3 py-2 text-sm font-medium text-stone-700 hover:bg-stone-50"
                    >
                      Cancel
                    </button>
                    <button
                      type="button"
                      onClick={() => submitCustomService()}
                      className="flex-1 rounded-lg bg-[#2f5a45] px-3 py-2 text-sm font-semibold text-white hover:bg-[#274a39]"
                    >
                      Add service
                    </button>
                  </div>
                </div>
              </div>
            </div>,
            document.body,
          )
        : null}

      {visibleDrafts.length === 0 ? (
        <p className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
          No catalogue services match this business type yet. Add a custom
          service above, or go back and choose another business type.
        </p>
      ) : (
        <div className="max-h-[28rem] space-y-2 overflow-y-auto pr-1">
          {visibleDrafts.map((draft) => {
            const open = Boolean(expandedKeys[draft.key]);
            return (
              <div
                key={draft.key}
                className={[
                  'rounded-xl border transition',
                  draft.selected
                    ? 'border-[#2f5a45] bg-[#e8f0eb]/40'
                    : 'border-stone-200 bg-white',
                ].join(' ')}
              >
                <div className="flex items-start gap-3 px-3 py-3">
                  <label className="flex min-w-0 flex-1 cursor-pointer items-start gap-3">
                    <input
                      type="checkbox"
                      checked={draft.selected}
                      onChange={(e) =>
                        toggleSelected(draft.key, e.target.checked)
                      }
                      className="mt-1 h-4 w-4 rounded border-stone-300 text-[#2f5a45] focus:ring-[#2f5a45]"
                    />
                    <div className="min-w-0 flex-1">
                      <p className="text-sm font-semibold text-stone-900">
                        {draft.name || 'Untitled service'}
                        {draft.is_custom ? (
                          <span className="ml-2 rounded bg-stone-200 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-stone-600">
                            Custom
                          </span>
                        ) : null}
                      </p>
                      <p className="mt-0.5 text-xs text-stone-500">
                        {formatPrice(draft.base_price_cents)} ·{' '}
                        {draft.duration_minutes} min
                        {draft.category ? ` · ${draft.category}` : ''}
                      </p>
                    </div>
                  </label>
                  <div className="flex shrink-0 flex-col items-end gap-1">
                    {draft.selected ? (
                      <button
                        type="button"
                        onClick={() => toggleExpanded(draft.key)}
                        className="rounded-md px-2 py-1 text-[11px] font-medium text-[#2f5a45] hover:bg-[#e8f0eb]"
                      >
                        {open ? 'Hide details' : 'Edit details'}
                      </button>
                    ) : null}
                    {draft.is_custom ? (
                      <button
                        type="button"
                        onClick={() => removeCustomService(draft.key)}
                        className="rounded-md px-2 py-1 text-[11px] font-medium text-red-600 hover:bg-red-50"
                      >
                        Remove
                      </button>
                    ) : null}
                  </div>
                </div>

                {open ? (
                  <div className="space-y-3 border-t border-[#2f5a45]/15 px-3 py-3">
                    {draft.is_custom ? (
                      <label className="block text-xs">
                        <span className="font-medium text-stone-600">
                          Service name
                        </span>
                        <input
                          type="text"
                          value={draft.name}
                          onChange={(e) =>
                            updateDraft(draft.key, { name: e.target.value })
                          }
                          className={inputClass}
                          placeholder="e.g. Beard colour"
                          required
                        />
                      </label>
                    ) : null}

                    <div className="flex gap-3">
                      <div className="relative h-16 w-16 shrink-0 overflow-hidden rounded-lg border border-stone-200 bg-stone-100">
                        {draft.image_url ? (
                          // eslint-disable-next-line @next/next/no-img-element
                          <img
                            src={draft.image_url}
                            alt=""
                            className="h-full w-full object-cover"
                          />
                        ) : (
                          <div className="flex h-full items-center justify-center text-[10px] text-stone-400">
                            No photo
                          </div>
                        )}
                      </div>
                      <div className="flex flex-1 flex-col justify-center gap-1.5">
                        <input
                          ref={(el) => {
                            fileRefs.current[draft.key] = el;
                          }}
                          type="file"
                          accept="image/*"
                          className="hidden"
                          onChange={(e) =>
                            void handleImagePick(
                              draft.key,
                              e.target.files?.[0] ?? null,
                            )
                          }
                        />
                        <button
                          type="button"
                          disabled={uploadingKey === draft.key}
                          onClick={() => fileRefs.current[draft.key]?.click()}
                          className="rounded-lg border border-stone-300 bg-white px-3 py-1.5 text-left text-xs font-medium text-stone-700 hover:bg-stone-50 disabled:opacity-60"
                        >
                          {uploadingKey === draft.key
                            ? 'Uploading…'
                            : draft.image_url
                              ? 'Change image'
                              : 'Add image from device'}
                        </button>
                        {draft.image_url ? (
                          <button
                            type="button"
                            onClick={() =>
                              updateDraft(draft.key, { image_url: null })
                            }
                            className="text-left text-[11px] text-stone-500 underline"
                          >
                            Remove image
                          </button>
                        ) : null}
                      </div>
                    </div>

                    <label className="block text-xs">
                      <span className="font-medium text-stone-600">
                        Description
                      </span>
                      <textarea
                        value={draft.description}
                        onChange={(e) =>
                          updateDraft(draft.key, {
                            description: e.target.value,
                          })
                        }
                        rows={2}
                        className={inputClass}
                        placeholder="What clients should expect"
                      />
                    </label>

                    <div className="grid grid-cols-2 gap-3">
                      <label className="block text-xs">
                        <span className="font-medium text-stone-600">
                          Price (£)
                        </span>
                        <input
                          type="number"
                          min={0}
                          step="0.01"
                          value={centsToPoundsInput(draft.base_price_cents)}
                          onChange={(e) =>
                            updateDraft(draft.key, {
                              base_price_cents: poundsToCents(e.target.value),
                            })
                          }
                          className={inputClass}
                        />
                      </label>
                      <label className="block text-xs">
                        <span className="font-medium text-stone-600">
                          Min. completion time (minutes)
                        </span>
                        <input
                          type="number"
                          min={5}
                          max={480}
                          step={5}
                          value={draft.duration_minutes}
                          onChange={(e) =>
                            updateDraft(draft.key, {
                              duration_minutes: Math.max(
                                5,
                                Math.min(
                                  480,
                                  Number.parseInt(e.target.value || '5', 10),
                                ),
                              ),
                            })
                          }
                          className={inputClass}
                        />
                      </label>
                    </div>
                  </div>
                ) : null}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}

export { UPGRADE_TOAST };
