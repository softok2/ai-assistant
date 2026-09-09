export interface LibraryFile {
  id: number
  name: string
  group: string
  status: string
  bytes: number | null
  synced_at: string | null
  expired_at?: string | null
  assistant_media_id?: string | null
}

export interface ReconciliationItem {
  id: string
  filename: string
  bytes: number
  created_at: number
  reason: string
}

export interface ReconciliationReport {
  store_files: number
  account_files: number
  referenced: number
  orphans: ReconciliationItem[]
  duplicates: ReconciliationItem[]
  loose: ReconciliationItem[]
}
