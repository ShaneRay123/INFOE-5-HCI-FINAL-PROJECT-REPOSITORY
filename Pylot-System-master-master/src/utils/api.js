const resolveApiBase = () => {
  const envBase = process.env.REACT_APP_API_BASE;
  if (envBase) return envBase.replace(/\/$/, "");
  if (typeof window !== 'undefined') {
    const host = window.location.hostname;
    
    if (host === 'localhost' || host === '127.0.0.1') return "http://localhost:5000";
    
    return "";
  }
  return "";
};

const API_BASE = resolveApiBase();

const cache = new Map();
const CACHE_DURATION = 5 * 60 * 1000;

function getCacheKey(path, options) {
  const token = localStorage.getItem('accessToken');
  return `${path}_${token || 'noauth'}_${JSON.stringify(options || {})}`;
}

function getCachedData(key) {
  const cached = cache.get(key);
  if (!cached) return null;
  
  const now = Date.now();
  if (now - cached.timestamp > CACHE_DURATION) {
    cache.delete(key);
    return null;
  }
  
  return cached.data;
}

function setCachedData(key, data) {
  cache.set(key, {
    data,
    timestamp: Date.now()
  });
}

function clearCache(pattern) {
  if (!pattern) {
    cache.clear();
    return;
  }
  
  for (const key of cache.keys()) {
    if (key.includes(pattern)) {
      cache.delete(key);
    }
  }
}

async function request(path, options = {}) {
  const isGetRequest = !options.method || options.method === 'GET';
  const isAdminPath = path.startsWith('/api/admin') || path.startsWith('/admin/');
  const shouldUseCache = isGetRequest && !path.startsWith('/api/exams') && !isAdminPath && !options.noCache;
  
  if (shouldUseCache) {
    const cacheKey = getCacheKey(path, options);
    const cachedData = getCachedData(cacheKey);
    if (cachedData) {
      console.log('📦 Using cached data for:', path);
      return cachedData;
    }
  }
  
  const token = localStorage.getItem('accessToken');
  
  if (token) {
    console.log('✅ Sending request with auth token to:', path);
  } else {
    console.log('⚠️ Sending request without auth token to:', path);
  }
  
  const url = `${API_BASE}${path}${isGetRequest ? (path.includes('?') ? '&' : '?') + '_ts=' + Date.now() : ''}`;
  const res = await fetch(url, {
    credentials: 'include',
    headers: {
      ...(options.body && !(options.body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
      ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
      ...(options.headers || {})
    },
    cache: isGetRequest ? 'no-store' : undefined,
    ...options,
    body: options.body && !(options.body instanceof FormData) ? JSON.stringify(options.body) : options.body
  });
  const contentType = res.headers.get('content-type') || '';
  const isJson = contentType.includes('application/json');
  const raw = isJson ? await res.json() : await res.text();
  if (!res.ok) {
    const message = isJson && raw && typeof raw === 'object'
      ? (raw.error || raw.message || raw.msg || res.statusText)
      : res.statusText;
    throw new Error(message || 'Request failed');
  }

  const data = (isJson && raw && typeof raw === 'object' && Object.prototype.hasOwnProperty.call(raw, 'data'))
    ? raw.data
    : raw;
  
  if (shouldUseCache) {
    const cacheKey = getCacheKey(path, options);
    setCachedData(cacheKey, data);
  }
  
  return data;
}

export const api = {
  get: (path, init) => request(path, { method: 'GET', ...(init || {}) }),
  post: (path, body, init) => {
    clearCache(path.split('/')[2]); 
    return request(path, { method: 'POST', body, ...(init || {}) });
  },
  put: (path, body, init) => {
    clearCache(path.split('/')[2]);
    return request(path, { method: 'PUT', body, ...(init || {}) });
  },
  del: (path, init) => {
    clearCache(path.split('/')[2]);
    return request(path, { method: 'DELETE', ...(init || {}) });
  },
  clearCache, 
};

export { API_BASE };
