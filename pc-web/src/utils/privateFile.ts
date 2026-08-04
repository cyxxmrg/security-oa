import { getToken } from './auth'

export async function openAuthenticatedFile(url: string, filename?: string, download = false): Promise<void> {
  const previewWindow = download ? null : window.open('', '_blank')
  try {
    const response = await fetch(url, {
      headers: { Authorization: `Bearer ${getToken() || ''}` }
    })
    if (!response.ok) throw new Error(`HTTP ${response.status}`)

    const blobUrl = URL.createObjectURL(await response.blob())
    if (download) {
      const anchor = document.createElement('a')
      anchor.href = blobUrl
      anchor.download = filename || 'download'
      anchor.click()
    } else if (previewWindow) {
      previewWindow.location.href = blobUrl
    }
    window.setTimeout(() => URL.revokeObjectURL(blobUrl), 60_000)
  } catch (error) {
    previewWindow?.close()
    throw error
  }
}
