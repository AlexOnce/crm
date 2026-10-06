import axios from 'axios';

const api = axios.create({
    baseURL: '/api',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    },
});

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('agent_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response && error.response.status === 401) {
            localStorage.removeItem('agent_token');
            localStorage.removeItem('agent_user');
            if (window.location.pathname !== '/login') {
                window.location.href = '/login';
            }
        } else if (error.response && error.response.status === 403) {
            if (window.location.pathname.startsWith('/admin')) {
                window.location.href = '/';
            }
        }
        return Promise.reject(error);
    }
);

export default api;
