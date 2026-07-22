'use client';

import { useCallback, useEffect, useState } from 'react';
import { ErrorAlert, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { PlatformSignupForm, SignupFormStep } from '@/lib/types';
import {
  createPlatformSignupForm,
  deletePlatformSignupForm,
  fetchPlatformSignupForms,
  updatePlatformSignupForm,
} from '@/services/platform.service';

const DEFAULT_STEPS_JSON = `[
  {
    "id": "business",
    "title": "Your business",
    "description": "Tell us about the salon.",
    "fields": [
      {
        "key": "business_name",
        "label": "Salon name",
        "type": "text",
        "required": true
      }
    ]
  }
]`;

export default function PlatformSignupFormsPage() {
  const [forms, setForms] = useState<PlatformSignupForm[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<string | null>(null);

  const [editing, setEditing] = useState<PlatformSignupForm | null>(null);
  const [creating, setCreating] = useState(false);
  const [name, setName] = useState('');
  const [slug, setSlug] = useState('');
  const [description, setDescription] = useState('');
  const [isActive, setIsActive] = useState(true);
  const [stepsJson, setStepsJson] = useState(DEFAULT_STEPS_JSON);
  const [jsonError, setJsonError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      setForms(await fetchPlatformSignupForms());
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load signup forms');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  function openCreate() {
    setCreating(true);
    setEditing(null);
    setName('');
    setSlug('');
    setDescription('');
    setIsActive(true);
    setStepsJson(DEFAULT_STEPS_JSON);
    setJsonError(null);
    setNotice(null);
  }

  function openEdit(form: PlatformSignupForm) {
    setCreating(false);
    setEditing(form);
    setName(form.name);
    setSlug(form.slug);
    setDescription(form.description ?? '');
    setIsActive(form.is_active);
    setStepsJson(JSON.stringify(form.steps, null, 2));
    setJsonError(null);
    setNotice(null);
  }

  function closeEditor() {
    setCreating(false);
    setEditing(null);
    setJsonError(null);
  }

  function parseSteps(): SignupFormStep[] | null {
    try {
      const parsed = JSON.parse(stepsJson) as unknown;
      if (!Array.isArray(parsed) || parsed.length === 0) {
        setJsonError('Steps must be a non-empty JSON array');
        return null;
      }
      setJsonError(null);
      return parsed as SignupFormStep[];
    } catch {
      setJsonError('Invalid JSON');
      return null;
    }
  }

  async function handleSave() {
    const steps = parseSteps();
    if (!steps) return;
    setSaving(true);
    setError(null);
    setNotice(null);
    try {
      if (creating) {
        await createPlatformSignupForm({
          name: name.trim(),
          slug: slug.trim() || undefined,
          description: description.trim() || null,
          steps,
          is_active: isActive,
        });
        setNotice('Signup form created');
      } else if (editing) {
        await updatePlatformSignupForm(editing.id, {
          name: name.trim(),
          slug: slug.trim(),
          description: description.trim() || null,
          steps,
          is_active: isActive,
        });
        setNotice('Signup form updated');
      }
      closeEditor();
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  }

  async function toggleActive(form: PlatformSignupForm) {
    setBusyId(form.id);
    setError(null);
    try {
      await updatePlatformSignupForm(form.id, { is_active: !form.is_active });
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Update failed');
    } finally {
      setBusyId(null);
    }
  }

  async function handleDelete(form: PlatformSignupForm) {
    if (!window.confirm(`Delete signup form “${form.name}”?`)) return;
    setBusyId(form.id);
    setError(null);
    try {
      await deletePlatformSignupForm(form.id);
      if (editing?.id === form.id) closeEditor();
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Delete failed');
    } finally {
      setBusyId(null);
    }
  }

  const showEditor = creating || editing;

  return (
    <div className="mx-auto grid max-w-6xl gap-5">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-amber-400/90">
            Platform
          </p>
          <h1 className="mt-1 text-2xl font-semibold tracking-tight text-white">
            Signup forms
          </h1>
          <p className="mt-1 text-sm text-stone-400">
            Configure the multi-step wizard shown on the public signup tab.
          </p>
        </div>
        <Button
          type="button"
          className="!bg-[var(--platform-accent)]"
          onClick={openCreate}
        >
          Create form
        </Button>
      </div>

      {error ? <ErrorAlert message={error} /> : null}
      {notice ? (
        <div className="rounded-md border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-200">
          {notice}
        </div>
      ) : null}
      {loading ? <LoadingState label="Loading forms…" /> : null}

      {!loading ? (
        <Card className="overflow-hidden border-white/10 bg-white/5 p-0">
          {forms.length === 0 ? (
            <p className="px-5 py-8 text-center text-sm text-stone-400">
              No signup forms yet.
            </p>
          ) : (
            <div className="overflow-x-auto">
              <table className="min-w-full text-left text-sm">
                <thead className="border-b border-white/10 text-[11px] uppercase tracking-[0.12em] text-stone-400">
                  <tr>
                    <th className="px-4 py-3 font-semibold">Name</th>
                    <th className="px-4 py-3 font-semibold">Slug</th>
                    <th className="px-4 py-3 font-semibold">Steps</th>
                    <th className="px-4 py-3 font-semibold">Active</th>
                    <th className="px-4 py-3 font-semibold">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {forms.map((form) => (
                    <tr key={form.id} className="border-b border-white/5 last:border-0">
                      <td className="px-4 py-3">
                        <p className="font-semibold text-white">{form.name}</p>
                        {form.description ? (
                          <p className="text-xs text-stone-400">{form.description}</p>
                        ) : null}
                      </td>
                      <td className="px-4 py-3 text-stone-300">{form.slug}</td>
                      <td className="px-4 py-3 text-stone-300">
                        {form.steps?.length ?? 0} · v{form.version}
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${
                            form.is_active
                              ? 'bg-emerald-500/15 text-emerald-300'
                              : 'bg-white/10 text-stone-400'
                          }`}
                        >
                          {form.is_active ? 'Active' : 'Inactive'}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex flex-wrap gap-2">
                          <Button
                            type="button"
                            variant="secondary"
                            className="!border-white/15 !bg-white/5 !px-2.5 !py-1.5 !text-xs !text-stone-100"
                            onClick={() => openEdit(form)}
                          >
                            Edit
                          </Button>
                          <Button
                            type="button"
                            disabled={busyId === form.id}
                            className="!bg-[var(--platform-accent)] !px-2.5 !py-1.5 !text-xs"
                            onClick={() => void toggleActive(form)}
                          >
                            {form.is_active ? 'Deactivate' : 'Activate'}
                          </Button>
                          <Button
                            type="button"
                            disabled={busyId === form.id}
                            variant="secondary"
                            className="!border-red-500/40 !bg-red-500/10 !px-2.5 !py-1.5 !text-xs !text-red-200"
                            onClick={() => void handleDelete(form)}
                          >
                            Delete
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      ) : null}

      {showEditor ? (
        <Card className="border-white/10 bg-white/5" title={creating ? 'Create signup form' : 'Edit signup form'}>
          <div className="grid gap-4">
            <label className="block text-sm">
              <span className="mb-1 block text-stone-400">Name</span>
              <input
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="w-full rounded-lg border border-white/15 bg-stone-950/40 px-3 py-2 text-sm text-white outline-none focus:border-amber-500"
                required
              />
            </label>
            <label className="block text-sm">
              <span className="mb-1 block text-stone-400">Slug</span>
              <input
                value={slug}
                onChange={(e) => setSlug(e.target.value)}
                className="w-full rounded-lg border border-white/15 bg-stone-950/40 px-3 py-2 text-sm text-white outline-none focus:border-amber-500"
                placeholder="auto if blank on create"
              />
            </label>
            <label className="block text-sm">
              <span className="mb-1 block text-stone-400">Description</span>
              <input
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                className="w-full rounded-lg border border-white/15 bg-stone-950/40 px-3 py-2 text-sm text-white outline-none focus:border-amber-500"
              />
            </label>
            <label className="flex items-center gap-2 text-sm text-stone-300">
              <input
                type="checkbox"
                checked={isActive}
                onChange={(e) => setIsActive(e.target.checked)}
                className="rounded border-white/20"
              />
              Active (only one active form drives public signup)
            </label>
            <label className="block text-sm">
              <span className="mb-1 block text-stone-400">Steps JSON</span>
              <textarea
                value={stepsJson}
                onChange={(e) => setStepsJson(e.target.value)}
                rows={18}
                spellCheck={false}
                className="w-full rounded-lg border border-white/15 bg-stone-950/60 px-3 py-2 font-mono text-xs text-stone-100 outline-none focus:border-amber-500"
              />
              {jsonError ? (
                <p className="mt-1 text-xs text-red-300">{jsonError}</p>
              ) : (
                <p className="mt-1 text-xs text-stone-500">
                  Array of steps with id, title, description, and fields[].
                </p>
              )}
            </label>
            <div className="flex gap-2">
              <Button
                type="button"
                disabled={saving || !name.trim()}
                className="!bg-[var(--platform-accent)]"
                onClick={() => void handleSave()}
              >
                {saving ? 'Saving…' : creating ? 'Create' : 'Save changes'}
              </Button>
              <Button
                type="button"
                variant="secondary"
                className="!border-white/15 !bg-white/5 !text-stone-100"
                onClick={closeEditor}
              >
                Cancel
              </Button>
            </div>
          </div>
        </Card>
      ) : null}
    </div>
  );
}
