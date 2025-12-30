import axios from 'axios';

// Configure axios defaults
axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';

// Promise to track CSRF initialization
let csrfPromise: Promise<void> | null = null;

// Function to initialize CSRF token
const initializeCsrf = async () => {
    if (!csrfPromise) {
        csrfPromise = axios.get('/sanctum/csrf-cookie').then(() => {
            console.log('CSRF cookie initialized');
        });
    }
    return csrfPromise;
};

// Add a request interceptor to ensure CSRF token is available
axios.interceptors.request.use(
    async (config) => {
        // Don't intercept the CSRF cookie request itself
        if (config.url !== '/sanctum/csrf-cookie') {
            await initializeCsrf();
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

export default axios;
