import React from 'react';
import ReactDOM from 'react-dom/client';
import './index.css';
import App from './App';
import reportWebVitals from './reportWebVitals';
import { SpeedInsights } from '@vercel/speed-insights/react';



import pylotLogo from './assets/PYlot white.png';

function setFavicon(href) {
  try {
    const head = document.getElementsByTagName('head')[0];
    
    let icon = head.querySelector("link[rel*='icon']");
    if (!icon) {
      icon = document.createElement('link');
      icon.rel = 'icon';
      head.appendChild(icon);
    }
    icon.href = href;

    
    let apple = head.querySelector("link[rel='apple-touch-icon']");
    if (apple) {
      apple.href = href;
    }
  } catch (e) {
    
    
    
    console.warn('Could not set favicon:', e);
  }
}


setFavicon(pylotLogo);

const root = ReactDOM.createRoot(document.getElementById('root'));
root.render(
  <React.StrictMode>
    <App />
    <SpeedInsights />
  </React.StrictMode>
);




reportWebVitals();

