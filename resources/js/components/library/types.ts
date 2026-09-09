export type AttachmentKind = 'image' | 'pdf' | 'spreadsheet' | 'document' | 'other'

/** Un archivo adjuntado por el usuario en alguno de sus chats. */
export interface LibraryAttachment {
  path: string
  name: string
  mime: string | null
  kind: AttachmentKind
  bytes: number | null
  url: string
  download_url: string
  chat_id: string
  chat_title: string
  created_at: string | null
}

/** Etiquetas en singular y plural de cada tipo, que las manda el servidor. */
export interface KindLabel {
  label: string
  plural: string
}

export type KindLabels = Record<string, KindLabel>

/** Adjuntos de un mismo chat, tal como los agrupa la página. */
export interface LibraryChatGroup {
  chatId: string
  chatTitle: string
  latestAt: string | null
  attachments: LibraryAttachment[]
}
