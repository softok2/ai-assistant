export type SourceOrigin = 'pentaho' | 'manual'

export interface SourceFile {
  id: number
  name: string
  group: string
  group_label: string
  origin: SourceOrigin
  status: string
  bytes: number | null
  synced_at: string | null
  expired_at?: string | null
  assistant_media_id?: string | null
}

export interface SourcesSchedule {
  every: string
  window: string
  next_run_at: string | null
}

export interface SourcesHealth {
  indexed: number
  total: number
  latest_sync_at: string | null
  schedule: SourcesSchedule
  environment: string
  vector_store_suffix: string | null
  expired_count: number
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
  /** Archivos del store que pertenecen a otro entorno; nunca se tocan. */
  foreign: number
  /** Archivos del store subidos antes de que se etiquetara el entorno. */
  untagged: number
  orphans: ReconciliationItem[]
  duplicates: ReconciliationItem[]
  loose: ReconciliationItem[]
}
