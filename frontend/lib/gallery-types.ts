export interface GalleryWork {
  id: string;
  image_url: string;
  caption: string | null;
  service_tag: string | null;
  sort_order: number;
  is_published: boolean;
  created_at: string | null;
  updated_at: string | null;
}

export interface GalleryUploadResult {
  url: string;
  path: string;
  work: GalleryWork;
}
