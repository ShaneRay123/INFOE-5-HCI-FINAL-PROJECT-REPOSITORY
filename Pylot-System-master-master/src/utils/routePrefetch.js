const prefetchedRoutes = new Set();

export const prefetchRoute = (lazyComponent, routeName = '') => {
  if (!lazyComponent) {
    return;
  }

  const key = routeName || lazyComponent.toString();
  if (prefetchedRoutes.has(key)) {
    return;
  }

  try {
    if (typeof lazyComponent.preload === 'function') {
      lazyComponent.preload();
      prefetchedRoutes.add(key);
      console.log(`✅ Prefetched route: ${routeName || 'anonymous'}`);
      return;
    }
    
    if (lazyComponent._payload) {
      if (lazyComponent._payload._status === 1) {
        prefetchedRoutes.add(key);
        console.log(`✅ Route already loaded: ${routeName || 'anonymous'}`);
      } else if (typeof lazyComponent._payload._result === 'function') {
        lazyComponent._payload._result().catch(() => {});
        prefetchedRoutes.add(key);
        console.log(`✅ Prefetched route: ${routeName || 'anonymous'}`);
      }
    }
  } catch (error) {
    console.warn(`⚠️ Failed to prefetch route: ${routeName}`, error);
  }
};

export const prefetchRoutes = (routes) => {
  routes.forEach(({ component, name }) => {
    prefetchRoute(component, name);
  });
};

export const prefetchOnHover = (lazyComponent, routeName = '') => {
  let timeout;
  
  return {
    onMouseEnter: () => {
      timeout = setTimeout(() => {
        prefetchRoute(lazyComponent, routeName);
      }, 100);
    },
    onMouseLeave: () => {
      clearTimeout(timeout);
    },
    onFocus: () => {
      prefetchRoute(lazyComponent, routeName);
    }
  };
};

export const clearPrefetchCache = () => {
  prefetchedRoutes.clear();
};
