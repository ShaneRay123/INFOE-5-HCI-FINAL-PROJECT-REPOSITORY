export function tierFromPercentage(pct, tiers) {
  const n = Number.isFinite(pct) ? pct : 0;
  const list = Array.isArray(tiers) ? tiers : [];
  const normalized = list
    .map((t) => ({
      key: t?.key || t?.name,
      min: Number(t?.min),
      max: Number(t?.max)
    }))
    .filter((t) => t.key && Number.isFinite(t.min) && Number.isFinite(t.max))
    .sort((a, b) => a.min - b.min);

  for (const t of normalized) {
    if (n >= t.min && n <= t.max) return t.key;
  }
  return 'Tier 1';
}
