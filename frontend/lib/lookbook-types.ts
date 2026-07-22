export interface LookbookItem {
  id: string;
  image_url: string;
  title: string | null;
  caption: string | null;
  category_key: string | null;
  sort_order: number;
  is_published: boolean;
  is_seeded: boolean;
  created_at: string | null;
  updated_at: string | null;
}

export interface LookbookReplaceImageResult {
  url: string;
  path: string;
  item: LookbookItem;
}
