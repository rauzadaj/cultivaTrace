const OFFLINE_THRESHOLD_MS = 5 * 60 * 1000

export function isSensorOffline(
  lastSeen: string | undefined,
  liveRecordedAt: string | undefined,
  now = Date.now(),
): boolean {
  const timestamps = [lastSeen, liveRecordedAt]
    .filter((timestamp): timestamp is string => Boolean(timestamp))
    .map((timestamp) => Date.parse(timestamp))
    .filter(Number.isFinite)

  if (!timestamps.length) return true

  const newestTimestamp = Math.max(...timestamps)
  return now - newestTimestamp > OFFLINE_THRESHOLD_MS
}
