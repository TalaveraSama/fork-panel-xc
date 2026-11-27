export function formatNumber(num, decimals = 0) {
  if (num === null || num === undefined) return '0'
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(num)
}

export function formatBytes(bytes, decimals = 2) {
  if (bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(decimals))} ${sizes[i]}`
}

export function formatBitrate(kbps) {
  const value = Number(kbps) || 0

  if (value >= 1000) {
    return `${(value / 1000).toFixed(2)} Mbps`
  }

  return `${Math.round(value)} kbps`
}

export function formatDuration(timestamp) {
  if (!timestamp) return ''
  const date = new Date(timestamp * 1000)
  const now = new Date()
  const diff = now - date

  if (diff < 60000) return 'Just now'
  if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`
  if (diff < 86400000) return `${Math.floor(diff / 3600000)}h ago`
  if (diff < 604800000) return `${Math.floor(diff / 86400000)}d ago`

  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined,
  })
}

export function formatDateTime(timestamp) {
  if (!timestamp) return ''
  return new Date(timestamp * 1000).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

/**
 * Date-only expiry (the API stores the end of the selected day in UTC).
 * Formatting in UTC avoids an off-by-one day for negative time zones.
 */
export function formatExpiryDate(timestamp) {
  if (!timestamp) return 'Never'
  return new Date(timestamp * 1000).toISOString().slice(0, 10)
}

export function formatRelativeTime(timestamp) {
  if (!timestamp) return ''
  const date = new Date(timestamp * 1000)
  const now = new Date()
  const diff = now - date

  const seconds = Math.floor(diff / 1000)
  const minutes = Math.floor(seconds / 60)
  const hours = Math.floor(minutes / 60)
  const days = Math.floor(hours / 24)

  if (seconds < 60) return `${seconds}s ago`
  if (minutes < 60) return `${minutes}m ago`
  if (hours < 24) return `${hours}h ago`
  if (days < 7) return `${days}d ago`

  return formatDateTime(timestamp)
}

export function truncate(str, length = 50) {
  if (!str || str.length <= length) return str
  return str.slice(0, length) + '...'
}

export function capitalize(str) {
  if (!str) return ''
  return str.charAt(0).toUpperCase() + str.slice(1)
}

export function slugify(str) {
  return str
    .toLowerCase()
    .trim()
    .replace(/[^\w\s-]/g, '')
    .replace(/[\s_-]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

export function getStatusColor(status) {
  const colors = {
    online: 'success',
    offline: 'danger',
    active: 'success',
    inactive: 'danger',
    pending: 'warning',
    disabled: 'danger',
    enabled: 'success',
    error: 'danger',
    warning: 'warning',
    info: 'info',
  }
  return colors[status?.toLowerCase()] || 'info'
}