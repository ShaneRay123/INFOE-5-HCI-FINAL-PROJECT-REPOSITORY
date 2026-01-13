
import { api } from './api';


const prefetchedRoutes = new Set();


export const prefetchRouteData = async (routeName, username = null) => {
  
  const cacheKey = `${routeName}_${username || 'default'}`;
  if (prefetchedRoutes.has(cacheKey)) {
    return;
  }

  try {
    switch (routeName) {
      case 'user-modules':
        if (username) {
          
          await api.get(`/api/user-progress/${username}`);
          
          await api.get('/api/modules');
        }
        break;

      case 'pre-assessment':
        
        await api.get('/api/exams/active');
        break;

      case 'post-assessment':
        
        await api.get('/api/exams/active');
        break;

      case 'admin-exams':
        
        await api.get('/api/exams');
        break;

      case 'admin-modules':
        
        await api.get('/api/modules');
        break;

      case 'admin-users':
        
        await api.get('/admin/users');
        break;

      default:
        break;
    }

    prefetchedRoutes.add(cacheKey);
    console.log('?? Prefetched data for:', routeName);
  } catch (error) {
    
    console.warn('?? Prefetch failed for:', routeName, error);
  }
};


export const prefetchOnHover = (routeName, username = null) => {
  return () => {
    prefetchRouteData(routeName, username);
  };
};


export const clearPrefetchCache = () => {
  prefetchedRoutes.clear();
};

