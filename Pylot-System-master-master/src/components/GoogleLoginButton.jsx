import React from 'react';
import { GoogleLogin } from '@react-oauth/google';
import { API_BASE } from '../utils/api';

const GoogleLoginButton = ({ onSuccess, onError, className, mode = 'icon' }) => {
  const handleSuccess = async (credentialResponse) => {
    try {
      const response = await fetch(`${API_BASE}/api/auth/google`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          token: credentialResponse.credential,
        }),
        credentials: 'include',
      });

      const data = await response.json();

      if (response.ok) {
        onSuccess(data);
      } else {
        onError(data.message || 'Failed to authenticate with Google');
      }
    } catch (error) {
      console.error('Google login error:', error);
      onError('An error occurred during Google login');
    }
  };

  return (
    <div className={className}>
      <GoogleLogin
        onSuccess={handleSuccess}
        onError={() => onError('Google login failed')}
        useOneTap
        auto_select
        type={mode === 'icon' ? 'icon' : 'standard'}
        theme="outline"
        shape="circle"
        size="large"
      />
    </div>
  );
};

export default GoogleLoginButton;
