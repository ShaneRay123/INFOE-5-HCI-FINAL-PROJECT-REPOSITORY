import { useCallback, useState } from 'react';


export const useOptimisticUpdate = (setState) => {
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState(null);

  const execute = useCallback(
    async (optimisticUpdate, apiCall, onSuccess = null, onError = null) => {
      setIsLoading(true);
      setError(null);

      
      let previousState;
      setState((prev) => {
        previousState = prev;
        return optimisticUpdate(prev);
      });

      try {
        
        const result = await apiCall();
        
        
        if (onSuccess) {
          onSuccess(result);
        }

        return result;
      } catch (err) {
        console.error('Optimistic update failed:', err);
        setError(err);

        
        setState(previousState);

        
        if (onError) {
          onError(err);
        }

        throw err;
      } finally {
        setIsLoading(false);
      }
    },
    [setState]
  );

  return { execute, isLoading, error };
};


export const optimisticArrayHelpers = {
  
  add: (item) => (prev) => [...prev, item],
  
  
  remove: (id, idField = '_id') => (prev) => 
    prev.filter(item => item[idField] !== id),
  
  
  update: (id, updates, idField = '_id') => (prev) =>
    prev.map(item => 
      item[idField] === id ? { ...item, ...updates } : item
    ),
  
  
  replace: (newArray) => () => newArray,
};


export const optimisticObjectHelpers = {
  
  updateProp: (key, value) => (prev) => ({
    ...prev,
    [key]: value
  }),
  
  
  merge: (updates) => (prev) => ({
    ...prev,
    ...updates
  }),
  
  
  deleteProp: (key) => (prev) => {
    const { [key]: _, ...rest } = prev;
    return rest;
  },
};

