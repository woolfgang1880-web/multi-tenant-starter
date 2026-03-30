import jsQR from 'jsqr'

async function fileToImageData(file) {
  const blobUrl = URL.createObjectURL(file)
  try {
    const img = new Image()
    img.decoding = 'async'
    img.src = blobUrl
    await new Promise((resolve, reject) => {
      img.onload = () => resolve()
      img.onerror = reject
    })

    const canvas = document.createElement('canvas')
    canvas.width = img.naturalWidth || img.width
    canvas.height = img.naturalHeight || img.height
    const ctx = canvas.getContext('2d')
    if (!ctx) return null
    ctx.drawImage(img, 0, 0)
    return ctx.getImageData(0, 0, canvas.width, canvas.height)
  } finally {
    URL.revokeObjectURL(blobUrl)
  }
}

/**
 * Intenta leer un QR desde un archivo (solo imagen por ahora).
 * - Si es PDF u otro tipo: retorna null (fallback manual).
 *
 * @returns {Promise<string|null>} URL detectada del QR (si existe)
 */
export async function decodeSatQrFromFile(file) {
  if (!file) return null
  const type = String(file.type || '')
  if (!type.startsWith('image/')) {
    return null
  }

  const imageData = await fileToImageData(file)
  if (!imageData) return null

  const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'attemptBoth' })
  const text = code?.data ? String(code.data).trim() : ''
  return text || null
}

