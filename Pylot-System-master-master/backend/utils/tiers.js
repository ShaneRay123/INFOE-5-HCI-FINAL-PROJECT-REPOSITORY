let TierConfig;
try {
  TierConfig = require('../models/TierConfig');
} catch {
  TierConfig = null;
}

const ALLOWED_TIER_KEYS = new Set(['Tier 1', 'Tier 2']);

const DEFAULT_TIERS = [
  (() => {
    const MIN_SCORE = 0;
    const MAX_SCORE = 100;
    const split = Math.floor((MAX_SCORE + 1) / 2);
    return { key: 'Tier 1', label: 'Tier 1', min: MIN_SCORE, max: split - 1 };
  })(),
  (() => {
    const MIN_SCORE = 0;
    const MAX_SCORE = 100;
    const split = Math.floor((MAX_SCORE + 1) / 2);
    return { key: 'Tier 2', label: 'Tier 2', min: split, max: MAX_SCORE };
  })(),
];

let _cache = {
  tiers: DEFAULT_TIERS,
  byKey: new Map(DEFAULT_TIERS.map(t => [t.key, t])),
  byLabelLower: new Map(DEFAULT_TIERS.map(t => [String(t.label).toLowerCase(), t])),
  loadedAt: null,
};

function _setCache(tiers) {
  const normalized = (tiers || []).map((t) => ({
    key: String(t.key || '').trim(),
    label: String(t.label || '').trim(),
    min: Number(t.min),
    max: Number(t.max),
  })).filter(t => (
    t.key &&
    t.label &&
    Number.isFinite(t.min) &&
    Number.isFinite(t.max) &&
    ALLOWED_TIER_KEYS.has(t.key)
  ));

  _cache = {
    tiers: normalized.length ? normalized : DEFAULT_TIERS,
    byKey: new Map((normalized.length ? normalized : DEFAULT_TIERS).map(t => [t.key, t])),
    byLabelLower: new Map((normalized.length ? normalized : DEFAULT_TIERS).map(t => [String(t.label).toLowerCase(), t])),
    loadedAt: new Date(),
  };
}

async function refreshTierConfigCache() {
  if (!TierConfig) return _cache;
  const rows = await TierConfig.find({ key: { $in: Array.from(ALLOWED_TIER_KEYS) } }).lean();
  if (!rows || rows.length === 0) {
    _setCache(DEFAULT_TIERS);
    return _cache;
  }
  _setCache(rows);
  return _cache;
}

function getTierConfigCached() {
  return _cache;
}

function tierKeyFromPercentage(pct) {
  const n = Number.isFinite(pct) ? pct : 0;
  const found = (_cache.tiers || []).find((t) => n >= t.min && n <= t.max);
  return found ? found.key : 'Tier 1';
}

function tierLabelFromPercentage(pct) {
  const key = tierKeyFromPercentage(pct);
  const t = _cache.byKey.get(key);
  return t ? t.label : key;
}

function normalizeTierKeyFromStoredValue(value) {
  const raw = String(value || '').trim();
  if (!raw) return null;
  if (_cache.byKey.has(raw)) return raw;

  const byLabel = _cache.byLabelLower.get(raw.toLowerCase());
  if (byLabel) return byLabel.key;

  const m = raw.match(/^tier\s*(\d+)$/i);
  if (m) {
    const n = Number(m[1]);
    if (n === 1) return 'Tier 1';
    if (Number.isFinite(n) && n >= 2) return 'Tier 2';
  }

  return null;
}

function tierFromPercentage(pct) {
  return tierKeyFromPercentage(pct);
}

function TIERS() {
  return (_cache.tiers || []).map(t => ({ name: t.key, min: t.min, max: t.max }));
}

module.exports = {
  DEFAULT_TIERS,
  refreshTierConfigCache,
  getTierConfigCached,
  tierKeyFromPercentage,
  tierLabelFromPercentage,
  normalizeTierKeyFromStoredValue,
  tierFromPercentage,
  TIERS,
};
