export interface AiHairstylePreview {
  id: string;
  status: string;
  composite_image_url: string | null;
  style_label: string | null;
  style_key: string | null;
  sort_order: number;
}

export interface AiHairstyleSession {
  id: string;
  public_token: string;
  status: string;
  selected_preview_ids: string[];
  error_message: string | null;
  provider?: string | null;
  submitted_at: string | null;
  expires_at: string | null;
  previews: AiHairstylePreview[];
}

export interface AiHairstyleSubmitPayload {
  first_name?: string;
  last_name?: string;
  email?: string;
  phone: string;
  notes?: string;
}
