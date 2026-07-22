import { api } from '@/lib/api-client';
import type {
  AudiencePreviewResult,
  AudienceRules,
  AutomationExecutionReport,
  AutomationMessageReport,
  AutomationReportingSummary,
  AutomationSuppressionReport,
  AutomationWorkflowReport,
  MarketingAudience,
  MarketingAutomationSettings,
  MarketingCampaign,
  MarketingCampaignReport,
  MarketingContactSuppression,
  MarketingMessage,
  MarketingReportingSummary,
  MarketingRun,
  MarketingRunReport,
  MarketingTemplate,
  MarketingWorkflow,
  MarketingWorkflowExecution,
  MarketingWorkflowStep,
} from '@/lib/marketing-types';

const auth = { auth: true as const, tenant: true as const };

function buildQuery(params?: Record<string, string | number | boolean | undefined | null>): string {
  if (!params) return '';
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') {
      search.set(key, String(value));
    }
  }
  const q = search.toString();
  return q ? `?${q}` : '';
}

// ── Templates ────────────────────────────────────────────────────────────────

export async function fetchMarketingTemplates(params?: {
  channel?: string;
  category?: string;
  is_active?: boolean;
  search?: string;
}): Promise<MarketingTemplate[]> {
  return api<MarketingTemplate[]>(`/admin/marketing/templates${buildQuery(params)}`, auth);
}

export async function fetchMarketingTemplate(id: string): Promise<MarketingTemplate> {
  return api<MarketingTemplate>(`/admin/marketing/templates/${id}`, auth);
}

export async function createMarketingTemplate(data: Partial<MarketingTemplate>): Promise<MarketingTemplate> {
  return api<MarketingTemplate>('/admin/marketing/templates', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function updateMarketingTemplate(id: string, data: Partial<MarketingTemplate>): Promise<MarketingTemplate> {
  return api<MarketingTemplate>(`/admin/marketing/templates/${id}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function archiveMarketingTemplate(id: string): Promise<MarketingTemplate> {
  return api<MarketingTemplate>(`/admin/marketing/templates/${id}/archive`, { ...auth, method: 'PATCH' });
}

export async function installMarketingSampleTemplates(): Promise<{ created: number; skipped: number }> {
  return api<{ created: number; skipped: number }>('/admin/marketing/templates/install-samples', {
    ...auth,
    method: 'POST',
    body: JSON.stringify({}),
  });
}

export interface TemplatePreviewResult {
  subject?: string | null;
  body_text?: string;
  body_html?: string | null;
  template_snapshot?: Record<string, unknown>;
  variables_snapshot?: Record<string, unknown>;
}

export async function previewMarketingTemplate(
  id: string,
  data?: { variables?: Record<string, string>; client_id?: string },
): Promise<TemplatePreviewResult> {
  return api<TemplatePreviewResult>(`/admin/marketing/templates/${id}/preview`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data ?? {}),
  });
}

// ── Audiences ────────────────────────────────────────────────────────────────

export async function fetchMarketingAudiences(params?: {
  is_active?: boolean;
  search?: string;
}): Promise<MarketingAudience[]> {
  return api<MarketingAudience[]>(`/admin/marketing/audiences${buildQuery(params)}`, auth);
}

export async function fetchMarketingAudience(id: string): Promise<MarketingAudience> {
  return api<MarketingAudience>(`/admin/marketing/audiences/${id}`, auth);
}

export async function createMarketingAudience(data: {
  name: string;
  description?: string;
  rules: AudienceRules;
  is_active?: boolean;
}): Promise<MarketingAudience> {
  return api<MarketingAudience>('/admin/marketing/audiences', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function updateMarketingAudience(
  id: string,
  data: { name?: string; description?: string; rules?: AudienceRules; is_active?: boolean },
): Promise<MarketingAudience> {
  return api<MarketingAudience>(`/admin/marketing/audiences/${id}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function archiveMarketingAudience(id: string): Promise<MarketingAudience> {
  return api<MarketingAudience>(`/admin/marketing/audiences/${id}/archive`, { ...auth, method: 'PATCH' });
}

export async function previewMarketingAudience(data: {
  rules: AudienceRules;
  channel?: string;
  location_id?: string;
  limit?: number;
}): Promise<AudiencePreviewResult> {
  return api<AudiencePreviewResult>('/admin/marketing/audiences/preview', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

// ── Campaigns ────────────────────────────────────────────────────────────────

export async function fetchMarketingCampaigns(params?: {
  status?: string;
  campaign_type?: string;
  channel?: string;
  trigger_type?: string;
  search?: string;
}): Promise<MarketingCampaign[]> {
  return api<MarketingCampaign[]>(`/admin/marketing/campaigns${buildQuery(params)}`, auth);
}

export async function fetchMarketingCampaign(id: string): Promise<MarketingCampaign> {
  return api<MarketingCampaign>(`/admin/marketing/campaigns/${id}`, auth);
}

export async function createMarketingCampaign(data: {
  name: string;
  campaign_type: string;
  channel: string;
  trigger_type?: string | null;
  status?: string;
  template_id?: string | null;
  audience_name?: string | null;
  audience_rules?: AudienceRules;
  location_id?: string | null;
  notes?: string | null;
}): Promise<MarketingCampaign> {
  return api<MarketingCampaign>('/admin/marketing/campaigns', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function updateMarketingCampaign(id: string, data: Partial<MarketingCampaign>): Promise<MarketingCampaign> {
  return api<MarketingCampaign>(`/admin/marketing/campaigns/${id}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function updateMarketingCampaignStatus(id: string, status: string): Promise<MarketingCampaign> {
  return api<MarketingCampaign>(`/admin/marketing/campaigns/${id}/status`, {
    ...auth,
    method: 'PATCH',
    body: JSON.stringify({ status }),
  });
}

// ── Settings ─────────────────────────────────────────────────────────────────

export async function fetchMarketingSettings(): Promise<MarketingAutomationSettings> {
  return api<MarketingAutomationSettings>('/admin/marketing/settings', auth);
}

export async function updateMarketingSettings(
  data: Partial<MarketingAutomationSettings>,
): Promise<MarketingAutomationSettings> {
  return api<MarketingAutomationSettings>('/admin/marketing/settings', {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

// ── Broadcast ────────────────────────────────────────────────────────────────

export async function previewBroadcast(data: {
  marketing_campaign_id: string;
  audience_rules?: AudienceRules;
  location_id?: string;
  limit?: number;
}): Promise<AudiencePreviewResult> {
  return api<AudiencePreviewResult>('/admin/marketing/runs/broadcast-preview', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function dispatchBroadcast(data: {
  marketing_campaign_id: string;
  audience_rules?: AudienceRules;
  location_id?: string;
  scheduled_for?: string;
}): Promise<MarketingRun> {
  return api<MarketingRun>('/admin/marketing/runs/broadcast-dispatch', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

// ── Automation generation ─────────────────────────────────────────────────────

export interface GenerationFilters {
  location_id?: string;
  from?: string;
  to?: string;
  limit?: number;
}

export async function generateBookingReminders(filters?: GenerationFilters): Promise<MarketingRun> {
  return api<MarketingRun>('/admin/marketing/runs/booking-reminders/generate', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(filters ?? {}),
  });
}

export async function generateRebooking(filters?: GenerationFilters): Promise<MarketingRun> {
  return api<MarketingRun>('/admin/marketing/runs/rebooking/generate', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(filters ?? {}),
  });
}

export async function generateReviewRequests(filters?: GenerationFilters): Promise<MarketingRun> {
  return api<MarketingRun>('/admin/marketing/runs/review-requests/generate', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(filters ?? {}),
  });
}

export async function generateWinBack(filters?: GenerationFilters): Promise<MarketingRun> {
  return api<MarketingRun>('/admin/marketing/runs/win-back/generate', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(filters ?? {}),
  });
}

// ── Runs ─────────────────────────────────────────────────────────────────────

export async function fetchMarketingRuns(params?: {
  marketing_campaign_id?: string;
  status?: string;
  trigger_type?: string;
  search?: string;
}): Promise<MarketingRun[]> {
  return api<MarketingRun[]>(`/admin/marketing/runs${buildQuery(params)}`, auth);
}

export async function fetchMarketingRun(id: string): Promise<MarketingRun> {
  return api<MarketingRun>(`/admin/marketing/runs/${id}`, auth);
}

export async function fetchRunMessages(
  id: string,
  params?: { status?: string; channel?: string },
): Promise<MarketingMessage[]> {
  return api<MarketingMessage[]>(`/admin/marketing/runs/${id}/messages${buildQuery(params)}`, auth);
}

export async function dispatchMarketingRun(id: string): Promise<MarketingRun> {
  return api<MarketingRun>(`/admin/marketing/runs/${id}/dispatch`, { ...auth, method: 'POST' });
}

// ── Reporting ────────────────────────────────────────────────────────────────

export async function fetchMarketingReportingSummary(params?: {
  from?: string;
  to?: string;
  location_id?: string;
}): Promise<MarketingReportingSummary> {
  return api<MarketingReportingSummary>(`/admin/marketing/reporting/summary${buildQuery(params)}`, auth);
}

export async function fetchMarketingCampaignReporting(params?: {
  from?: string;
  to?: string;
  location_id?: string;
}): Promise<MarketingCampaignReport[]> {
  return api<MarketingCampaignReport[]>(`/admin/marketing/reporting/campaigns${buildQuery(params)}`, auth);
}

export async function fetchMarketingRunReporting(params?: {
  from?: string;
  to?: string;
  marketing_campaign_id?: string;
}): Promise<MarketingRunReport[]> {
  return api<MarketingRunReport[]>(`/admin/marketing/reporting/runs${buildQuery(params)}`, auth);
}

// ── Workflows (Module 10B) ─────────────────────────────────────────────────────

export async function fetchMarketingWorkflows(params?: {
  status?: string;
  trigger_type?: string;
  channel?: string;
  search?: string;
}): Promise<MarketingWorkflow[]> {
  return api<MarketingWorkflow[]>(`/admin/marketing/workflows${buildQuery(params)}`, auth);
}

export async function fetchMarketingWorkflow(id: string): Promise<MarketingWorkflow> {
  return api<MarketingWorkflow>(`/admin/marketing/workflows/${id}`, auth);
}

export async function createMarketingWorkflow(data: {
  name: string;
  trigger_type: string;
  channel: string;
  status?: string;
  slug?: string | null;
  description?: string | null;
  template_id?: string | null;
  audience_rules?: AudienceRules;
  delay_minutes?: number | null;
  cooldown_days?: number | null;
  allow_repeat?: boolean;
  max_executions_per_client?: number | null;
  settings_json?: Record<string, unknown>;
  steps?: MarketingWorkflowStep[];
}): Promise<MarketingWorkflow> {
  return api<MarketingWorkflow>('/admin/marketing/workflows', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function updateMarketingWorkflow(
  id: string,
  data: {
    name?: string;
    description?: string | null;
    trigger_type?: string;
    channel?: string;
    template_id?: string | null;
    audience_rules?: AudienceRules;
    delay_minutes?: number | null;
    cooldown_days?: number | null;
    allow_repeat?: boolean;
    max_executions_per_client?: number | null;
    settings_json?: Record<string, unknown>;
  },
): Promise<MarketingWorkflow> {
  return api<MarketingWorkflow>(`/admin/marketing/workflows/${id}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function updateMarketingWorkflowStatus(id: string, status: string): Promise<MarketingWorkflow> {
  return api<MarketingWorkflow>(`/admin/marketing/workflows/${id}/status`, {
    ...auth,
    method: 'PATCH',
    body: JSON.stringify({ status }),
  });
}

export async function updateMarketingWorkflowSteps(
  id: string,
  steps: MarketingWorkflowStep[],
): Promise<MarketingWorkflow> {
  return api<MarketingWorkflow>(`/admin/marketing/workflows/${id}/steps`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify({ steps }),
  });
}

export async function addMarketingWorkflowStep(
  workflowId: string,
  data: {
    step_type: string;
    position?: number;
    delay_minutes?: number | null;
    template_id?: string | null;
    channel?: string;
    payload?: Record<string, unknown>;
  },
): Promise<MarketingWorkflow> {
  return api<MarketingWorkflow>(`/admin/marketing/workflows/${workflowId}/steps`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function updateMarketingWorkflowStep(
  workflowId: string,
  stepId: string,
  data: {
    step_type?: string;
    position?: number;
    delay_minutes?: number | null;
    template_id?: string | null;
    channel?: string;
    payload?: Record<string, unknown>;
  },
): Promise<MarketingWorkflow> {
  return api<MarketingWorkflow>(`/admin/marketing/workflows/${workflowId}/steps/${stepId}`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function reorderMarketingWorkflowSteps(
  workflowId: string,
  stepIds: string[],
): Promise<MarketingWorkflow> {
  return api<MarketingWorkflow>(`/admin/marketing/workflows/${workflowId}/steps/reorder`, {
    ...auth,
    method: 'PUT',
    body: JSON.stringify({ step_ids: stepIds }),
  });
}

export async function archiveMarketingWorkflowStep(
  workflowId: string,
  stepId: string,
): Promise<MarketingWorkflow> {
  return api<MarketingWorkflow>(`/admin/marketing/workflows/${workflowId}/steps/${stepId}/archive`, {
    ...auth,
    method: 'PATCH',
  });
}

export async function fetchMarketingWorkflowExecutions(
  workflowId: string,
  params?: { status?: string },
): Promise<MarketingWorkflowExecution[]> {
  return api<MarketingWorkflowExecution[]>(
    `/admin/marketing/workflows/${workflowId}/executions${buildQuery(params)}`,
    auth,
  );
}

export async function testMarketingWorkflow(id: string, clientId: string): Promise<MarketingWorkflowExecution> {
  return api<MarketingWorkflowExecution>(`/admin/marketing/workflows/${id}/run-test`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify({ client_id: clientId }),
  });
}

// ── Executions (Module 10B) ────────────────────────────────────────────────────

export async function fetchMarketingExecutions(params?: {
  status?: string;
  workflow_id?: string;
  trigger_type?: string;
  client_id?: string;
}): Promise<MarketingWorkflowExecution[]> {
  return api<MarketingWorkflowExecution[]>(`/admin/marketing/executions${buildQuery(params)}`, auth);
}

export async function fetchMarketingExecution(id: string): Promise<MarketingWorkflowExecution> {
  return api<MarketingWorkflowExecution>(`/admin/marketing/executions/${id}`, auth);
}

export async function cancelMarketingExecution(id: string): Promise<MarketingWorkflowExecution> {
  return api<MarketingWorkflowExecution>(`/admin/marketing/executions/${id}/cancel`, { ...auth, method: 'PATCH' });
}

export interface ProcessExecutionsSummary {
  processed?: number;
  completed?: number;
  failed?: number;
  skipped?: number;
  [key: string]: unknown;
}

export async function processMarketingExecutions(limit?: number): Promise<ProcessExecutionsSummary> {
  return api<ProcessExecutionsSummary>('/admin/marketing/executions/process', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(limit ? { limit } : {}),
  });
}

export interface BirthdayAutomationResult {
  matched: number;
  executions: MarketingWorkflowExecution[];
}

export async function runBirthdayAutomations(): Promise<BirthdayAutomationResult> {
  return api<BirthdayAutomationResult>('/admin/marketing/automations/run-birthday', {
    ...auth,
    method: 'POST',
    body: JSON.stringify({}),
  });
}

// ── Messages (operational, Module 10B) ─────────────────────────────────────────

export async function fetchMarketingMessages(params?: {
  status?: string;
  channel?: string;
  client_id?: string;
  workflow_execution_id?: string;
}): Promise<MarketingMessage[]> {
  return api<MarketingMessage[]>(`/admin/marketing/messages${buildQuery(params)}`, auth);
}

export async function fetchMarketingMessage(id: string): Promise<MarketingMessage> {
  return api<MarketingMessage>(`/admin/marketing/messages/${id}`, auth);
}

export async function markMessageDelivered(id: string): Promise<MarketingMessage> {
  return api<MarketingMessage>(`/admin/marketing/messages/${id}/mark-delivered`, { ...auth, method: 'POST' });
}

export async function markMessageOpened(id: string): Promise<MarketingMessage> {
  return api<MarketingMessage>(`/admin/marketing/messages/${id}/mark-opened`, { ...auth, method: 'POST' });
}

export async function markMessageClicked(id: string): Promise<MarketingMessage> {
  return api<MarketingMessage>(`/admin/marketing/messages/${id}/mark-clicked`, { ...auth, method: 'POST' });
}

export async function markMessageFailed(
  id: string,
  data?: { failure_category?: string; error_message?: string },
): Promise<MarketingMessage> {
  return api<MarketingMessage>(`/admin/marketing/messages/${id}/mark-failed`, {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data ?? {}),
  });
}

export async function unsubscribeMarketingMessage(id: string): Promise<MarketingMessage> {
  return api<MarketingMessage>(`/admin/marketing/messages/${id}/unsubscribe`, { ...auth, method: 'POST' });
}

// ── Suppressions (Module 10B) ──────────────────────────────────────────────────

export async function fetchMarketingSuppressions(params?: {
  is_active?: boolean;
  channel?: string;
  reason?: string;
  client_id?: string;
  search?: string;
}): Promise<MarketingContactSuppression[]> {
  return api<MarketingContactSuppression[]>(`/admin/marketing/suppressions${buildQuery(params)}`, auth);
}

export async function createMarketingSuppression(data: {
  channel: string;
  contact_value: string;
  client_id?: string | null;
  reason?: string | null;
  notes?: string | null;
}): Promise<MarketingContactSuppression> {
  return api<MarketingContactSuppression>('/admin/marketing/suppressions', {
    ...auth,
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function liftMarketingSuppression(id: string): Promise<MarketingContactSuppression> {
  return api<MarketingContactSuppression>(`/admin/marketing/suppressions/${id}/lift`, { ...auth, method: 'PATCH' });
}

export async function deactivateMarketingSuppression(id: string): Promise<MarketingContactSuppression> {
  return api<MarketingContactSuppression>(`/admin/marketing/suppressions/${id}/deactivate`, { ...auth, method: 'PATCH' });
}

export async function reactivateMarketingSuppression(id: string): Promise<MarketingContactSuppression> {
  return api<MarketingContactSuppression>(`/admin/marketing/suppressions/${id}/reactivate`, { ...auth, method: 'PATCH' });
}

// ── Automation reporting (Module 10B) ──────────────────────────────────────────

export async function fetchAutomationReportingSummary(params?: {
  from?: string;
  to?: string;
}): Promise<AutomationReportingSummary> {
  return api<AutomationReportingSummary>(`/admin/marketing/reporting/automations/summary${buildQuery(params)}`, auth);
}

export async function fetchAutomationWorkflowReporting(params?: {
  from?: string;
  to?: string;
}): Promise<AutomationWorkflowReport[]> {
  return api<AutomationWorkflowReport[]>(
    `/admin/marketing/reporting/automations/workflows${buildQuery(params)}`,
    auth,
  );
}

export async function fetchAutomationExecutionReporting(params?: {
  from?: string;
  to?: string;
}): Promise<AutomationExecutionReport[]> {
  return api<AutomationExecutionReport[]>(`/admin/marketing/reporting/automations/executions${buildQuery(params)}`, auth);
}

export async function fetchAutomationMessageReporting(params?: {
  from?: string;
  to?: string;
}): Promise<AutomationMessageReport[]> {
  return api<AutomationMessageReport[]>(`/admin/marketing/reporting/automations/messages${buildQuery(params)}`, auth);
}

export async function fetchAutomationSuppressionReporting(): Promise<AutomationSuppressionReport> {
  return api<AutomationSuppressionReport>('/admin/marketing/reporting/automations/suppressions', auth);
}
