import React, { useState, lazy, Suspense, useEffect } from "react";
import { BrowserRouter as Router, Routes, Route, useNavigate, Navigate } from "react-router-dom";
import { GoogleOAuthProvider } from '@react-oauth/google';
import { API_BASE, api } from "./utils/api";
import { prefetchRoutes } from "./utils/routePrefetch";
import LazyImage from "./components/LazyImage";
import GoogleLoginButton from "./components/GoogleLoginButton";
import "./App.css";
import loginLogo from "./assets/PYlot white.png";

const EyeIcon = ({ slashed = false }) => (
  <svg
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    strokeWidth="2"
    strokeLinecap="round"
    strokeLinejoin="round"
    aria-hidden="true"
  >
    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7" />
    <circle cx="12" cy="12" r="3" />
    {slashed && <line x1="3" y1="3" x2="21" y2="21" />}
  </svg>
);

const AdminPage = lazy(() => import("./pages/admin/AdminPage"));
const UserPage = lazy(() => import("./pages/user/UserPages"));
const UserExam = lazy(() => import("./pages/user/UserExam"));
const UserModuleManagement = lazy(() => import("./components/user/UserModuleManagement"));
const UserCheckpointQuiz = lazy(() => import("./components/user/UserCheckpointQuiz"));

const LoadingFallback = () => (
  <div style={{ 
    display: 'flex', 
    justifyContent: 'center', 
    alignItems: 'center', 
    height: '100vh',
    fontSize: '1.2rem',
    color: '#555'
  }}>
    Loading...
  </div>
);

function App() {
  useEffect(() => {
    const role = localStorage.getItem('role');
    
    const timeout = setTimeout(() => {
      if (role === 'admin') {
        prefetchRoutes([
          { component: AdminPage, name: 'AdminPage' }
        ]);
      } else if (role === 'user') {
        prefetchRoutes([
          { component: UserPage, name: 'UserPage' },
          { component: UserExam, name: 'UserExam' },
          { component: UserModuleManagement, name: 'UserModuleManagement' }
        ]);
      }
    }, 1000);
    
    return () => clearTimeout(timeout);
  }, []);

  useEffect(() => {
    try {
      const sendHeartbeat = () => {
        const tk = localStorage.getItem('accessToken');
        if (!tk) return; // avoid hitting endpoint without token
        api.post('/api/auth/heartbeat', {}, { noCache: true }).catch(() => {});
      };

      sendHeartbeat();
      const intervalId = setInterval(() => {
        const tk = localStorage.getItem('accessToken');
        if (!tk) {
          clearInterval(intervalId);
          return;
        }
        sendHeartbeat();
      }, 5000);

      const goOffline = () => {
        try {
          const tk = localStorage.getItem('accessToken');
          if (!tk) return;
          fetch(`${API_BASE}/api/auth/offline`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${tk}` },
            credentials: 'include',
            keepalive: true
          }).catch(() => {});
        } catch {}
      };

      window.addEventListener('pagehide', goOffline);
      window.addEventListener('beforeunload', goOffline);

      return () => {
        clearInterval(intervalId);
        window.removeEventListener('pagehide', goOffline);
        window.removeEventListener('beforeunload', goOffline);
      };
    } catch {}
  }, []);
  
  return (
    <GoogleOAuthProvider clientId="291303377694-d0gaqmc7cntiovt57931h6oihcro9sqi.apps.googleusercontent.com">
      <Router>
        <Suspense fallback={<LoadingFallback />}>
          <Routes>
            <Route path="/" element={<LoginPage />} />
            <Route path="/signup" element={<SignupPage />} />
            <Route
              path="/admin/*"
              element={
                <RequireAdmin>
                  <AdminPage />
                </RequireAdmin>
              }
            />
            <Route path="/user" element={<RequireUser><UserPage /></RequireUser>} />
            <Route path="/user/exam" element={<RequireUser><UserExam /></RequireUser>} />
            <Route path="/user/pre-assessment" element={<Navigate to="/user/exam" replace />} />
            <Route path="/user/modules" element={<RequireUser><UserModuleManagement /></RequireUser>} />
            <Route path="/user/checkpoint-quiz/:checkpointNumber" element={<RequireUser><UserCheckpointQuiz /></RequireUser>} />
            <Route path="/user/post-assessment" element={<Navigate to="/user/exam" replace />} />
            <Route path="/user/ModuleManagement" element={<Navigate to="/user/modules" replace />} />
            <Route path="/reset-password" element={<ResetPassword />} />
          </Routes>
        </Suspense>
      </Router>
    </GoogleOAuthProvider>
  );
}

export default App;

function ResetPassword() {
  const [username, setUsername] = React.useState('');
  const [email, setEmail] = React.useState('');
  const [newPassword, setNewPassword] = React.useState('');
  const [confirmPassword, setConfirmPassword] = React.useState('');
  const [showNewPassword, setShowNewPassword] = React.useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = React.useState(false);
  const [error, setError] = React.useState('');
  const [success, setSuccess] = React.useState('');
  const [loading, setLoading] = React.useState(false);
  const navigate = useNavigate();

  const isValid = () => {
    const u = username.trim();
    const e = email.trim();
    const p = newPassword.trim();
    const c = confirmPassword.trim();
    if (!u || !e || !p || !c) { setError('All fields are required'); return false; }
    const allowedEmailRegex = /^[a-zA-Z0-9._%+-]+@(gmail\.com|yahoo\.com|school\.edu)$/i;
    if (!allowedEmailRegex.test(e)) { setError('Please enter a valid email (gmail.com, yahoo.com, or school.edu).'); return false; }
    if (p.length < 8) { setError('Password must be at least 8 characters'); return false; }
    if (p !== c) { setError('Passwords do not match'); return false; }
    return true;
  };

  const onSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    if (!isValid()) return;
    setLoading(true);
    try {
      const res = await fetch(`${API_BASE}/api/auth/reset-password`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username: username.trim(), email: email.trim(), newPassword: newPassword.trim() })
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        setError(data?.message || 'Password reset failed');
        setLoading(false);
        return;
      }
      setSuccess('Password reset successful');
      setTimeout(() => navigate('/'), 1200);
    } catch (err) {
      setError('Network error. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="container">
      <div className="BoxLogin">
        <h2 style={{ textAlign: 'center', marginBottom: 16 }}>Reset Password</h2>
        <form onSubmit={onSubmit} className="form">
          <input
            type="text"
            placeholder="Username"
            value={username}
            onChange={(e) => setUsername(e.target.value)}
          />
          <input
            type="email"
            placeholder="Gmail address"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
          />
          <div className="input-with-icon">
            <input
              type={showNewPassword ? "text" : "password"}
              placeholder="New Password (min 8 chars)"
              value={newPassword}
              onChange={(e) => setNewPassword(e.target.value)}
            />
            <button
              type="button"
              className="password-toggle"
              aria-label={showNewPassword ? "Hide password" : "Show password"}
              onClick={() => setShowNewPassword((prev) => !prev)}
            >
              <EyeIcon slashed={showNewPassword} />
            </button>
          </div>
          <div className="input-with-icon">
            <input
              type={showConfirmPassword ? "text" : "password"}
              placeholder="Confirm New Password"
              value={confirmPassword}
              onChange={(e) => setConfirmPassword(e.target.value)}
            />
            <button
              type="button"
              className="password-toggle"
              aria-label={showConfirmPassword ? "Hide password" : "Show password"}
              onClick={() => setShowConfirmPassword((prev) => !prev)}
            >
              <EyeIcon slashed={showConfirmPassword} />
            </button>
          </div>

          <button type="submit" disabled={loading}>
            {loading ? 'Resetting...' : 'Reset Password'}
          </button>

          {error && <p className="error">{error}</p>}
          {success && <p className="success">{success}</p>}
        </form>

        <p className="toggle-text" style={{ marginTop: 8 }}>
          <button className="link-button" type="button" onClick={() => navigate('/')}>Back to Login</button>
        </p>
      </div>
    </div>
  );
}

function LoginPage() {
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState("");
  const [usernameError, setUsernameError] = useState("");
  const [passwordError, setPasswordError] = useState("");
  const navigate = useNavigate();

  const handleGoogleLoginSuccess = async (data) => {
    // Store user data in localStorage
    if (data.user) {
      localStorage.setItem('role', data.user.role || 'user');
      localStorage.setItem('username', data.user.username || '');
      localStorage.setItem('isApproved', 'true'); // Google users are auto-approved
    }
    if (data.accessToken) localStorage.setItem('accessToken', data.accessToken);
    if (data.refreshToken) localStorage.setItem('refreshToken', data.refreshToken);

    const finalRole = (data.user?.role || localStorage.getItem('role') || '').toLowerCase();
    if (finalRole === 'admin') {
      navigate('/admin');
    } else {
      navigate('/user');
    }
  };

  const handleGoogleLoginError = (error) => {
    setError(error || 'Failed to sign in with Google');
  };

  const isFormValid = () => {
    return username.trim() !== "" && password.trim() !== "" && !usernameError && !passwordError;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError("");

    const trimmedUsername = username.trim();
    const trimmedPassword = password.trim();

    if (!trimmedUsername || !trimmedPassword) {
      setError("Please fill in all fields");
      return;
    }

    const endpoint = `${API_BASE}/api/auth/login`;
    const requestBody = { username: trimmedUsername, password: trimmedPassword };

    const response = await fetch(endpoint, {
      method: "POST",
      credentials: "include",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(requestBody),
    });

    let data;
    try {
      data = await response.json();
    } catch (e) {
      setError(`Server error: ${response.status} ${response.statusText}`);
      return;
    }

    const handleSuccessfulLogin = async (payload) => {
      const envelope = payload && payload.data ? payload.data : payload;
      const userObj = envelope?.user;
      const accessToken = envelope?.accessToken;
      const refreshToken = envelope?.refreshToken;

      if (userObj?.role) {
        localStorage.setItem("role", userObj.role);
      } else if (payload.role) {
        localStorage.setItem("role", payload.role);
      }
      if (userObj?.username) {
        localStorage.setItem("username", userObj.username);
      } else if (username) {
        localStorage.setItem("username", username);
      }
      if (accessToken) localStorage.setItem("accessToken", accessToken);
      if (refreshToken) localStorage.setItem("refreshToken", refreshToken);

      try {
        const token = localStorage.getItem('accessToken');
        if (token) {
          const meRes = await fetch(`${API_BASE}/api/auth/me`, {
            method: 'GET',
            credentials: 'include',
            headers: { 'Authorization': `Bearer ${token}` }
          });
          const meData = await meRes.json().catch(() => ({}));
          const roleFromMe = meData?.data?.user?.role || meData?.user?.role;
          const approvedFromMe = meData?.data?.user?.isApproved ?? meData?.user?.isApproved;
          if (roleFromMe) localStorage.setItem('role', roleFromMe);
          if (typeof approvedFromMe !== 'undefined') {
            localStorage.setItem('isApproved', String(!!approvedFromMe));
          }
        }
      } catch {}
    };

    if (response.ok) {
      try {
        await handleSuccessfulLogin(data);
        console.log('Login response data:', data);
      } catch (error) {
        console.error("Error storing auth data:", error);
      }

      const finalRole = (localStorage.getItem('role') || '').toLowerCase();
      const approved = (localStorage.getItem('isApproved') || '').toLowerCase() === 'true';
      if (finalRole === "admin") {
        navigate("/admin");
      } else {
        if (approved) navigate("/user");
        else setError('Account pending admin approval');
      }
    } else {
      // On login failure, show a clearer message.
      if (response.status === 429) {
        setError(data?.message || 'Too many attempts. Please wait a bit before trying again.');
      } else {
        setError(data?.message || 'Login failed');
      }
    }
  };

  return (
    <div className="container">
      <div className="BoxLogin">
        <LazyImage src={loginLogo} alt="PYlot Logo" className="login-logo" />
        <form onSubmit={handleSubmit} className="form">
          <div>
            <input
              type="text"
              placeholder="Username"
              value={username}
              onChange={(e) => {
                const value = e.target.value;
                setUsername(value);
                if (value.trim() === "") {
                  setUsernameError("This field cannot be blank");
                } else {
                  setUsernameError("");
                }
              }}
            />
            {usernameError && <p className="inline-error">{usernameError}</p>}
          </div>

          <div>
            <div className="input-with-icon">
              <input
                type={showPassword ? "text" : "password"}
                placeholder="Password"
                value={password}
                onChange={(e) => {
                  const value = e.target.value;
                  setPassword(value);
                  if (value.trim() === "") {
                    setPasswordError("This field cannot be blank");
                  } else {
                    setPasswordError("");
                  }
                }}
              />
              <button
                type="button"
                className="password-toggle"
                aria-label={showPassword ? "Hide password" : "Show password"}
                onClick={() => setShowPassword((prev) => !prev)}
              >
                <EyeIcon slashed={showPassword} />
              </button>
            </div>
            {passwordError && <p className="inline-error">{passwordError}</p>}
          </div>

          <div style={{ display: 'flex', justifyContent: 'center', marginTop: 8 }}>
            <GoogleLoginButton
              onSuccess={handleGoogleLoginSuccess}
              onError={handleGoogleLoginError}
              mode="icon"
            />
          </div>

          <button type="submit" disabled={!isFormValid()}>Log In</button>
          {error && <p className="error">{error}</p>}
        </form>

        <p className="toggle-text" style={{ marginTop: 8 }}>
          <button
            onClick={() => navigate('/reset-password')}
            className="link-button"
            type="button"
          >
            Forgot Password?
          </button>
        </p>

        <p className="toggle-text" style={{ marginTop: 4 }}>
          <span style={{ color: 'rgba(255,255,255,0.7)' }}>Don&apos;t have an account?</span>{' '}
          <button
            onClick={() => navigate('/signup')}
            className="link-button"
            type="button"
          >
            Sign up
          </button>
        </p>
      </div>
    </div>
  );
}

function SignupPage() {
  const [username, setUsername] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [firstName, setFirstName] = useState("");
  const [middleName, setMiddleName] = useState("");
  const [lastName, setLastName] = useState("");
  const [age, setAge] = useState("");
  const [address, setAddress] = useState("");
  const [gender, setGender] = useState("Male");
  const [showPassword, setShowPassword] = useState(false);

  const [usernameError, setUsernameError] = useState("");
  const [emailError, setEmailError] = useState("");
  const [passwordError, setPasswordError] = useState("");
  const [firstNameError, setFirstNameError] = useState("");
  const [lastNameError, setLastNameError] = useState("");
  const [ageError, setAgeError] = useState("");
  const [addressError, setAddressError] = useState("");

  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const allowedEmailRegex = /^[a-zA-Z0-9._%+-]+@(gmail\.com|yahoo\.com|school\.edu)$/i;

  const validateUsername = (value) => {
    const v = value.trim();
    if (!v) return "Username is required.";
    if (v.length < 3 || v.length > 30) return "Username must be between 3 and 30 characters.";
    if (!/^[a-zA-Z0-9_]+$/.test(v)) return "Username can only contain letters, numbers, and underscores.";
    return "";
  };

  const validateEmail = (value) => {
    const v = value.trim();
    if (!v) return "Email is required.";
    if (!allowedEmailRegex.test(v)) return "Please enter a valid email (gmail.com, yahoo.com, or school.edu).";
    return "";
  };

  const validatePassword = (value) => {
    const v = value.trim();
    if (!v) return "Password is required.";
    if (v.length < 8) return "Password must be at least 8 characters long.";
    return "";
  };

  const nameRegex = /^[A-Za-z\s]+$/;

  const validateFirstName = (value) => {
    const v = value.trim();
    if (!v) return "First name is required.";
    if (!nameRegex.test(v)) return "First name can only contain letters and spaces.";
    return "";
  };

  const validateLastName = (value) => {
    const v = value.trim();
    if (!v) return "Last name is required.";
    if (!nameRegex.test(v)) return "Last name can only contain letters and spaces.";
    return "";
  };

  const validateAge = (value) => {
    const v = value.trim();
    if (!v) return "Age is required.";
    const num = Number(v);
    if (!Number.isInteger(num) || num < 1 || num > 65) return "Age must be a number between 1 and 65.";
    return "";
  };

  const validateAddress = (value) => {
    const v = value.trim();
    if (!v) return "Address is required.";
    if (v.length < 5 || v.length > 200) return "Address must be between 5 and 200 characters.";
    return "";
  };

  const isValid = () => {
    const uErr = validateUsername(username);
    const eErr = validateEmail(email);
    const pErr = validatePassword(password);
    const fErr = validateFirstName(firstName);
    const lErr = validateLastName(lastName);
    const aErr = validateAge(age);
    const addrErr = validateAddress(address);

    setUsernameError(uErr);
    setEmailError(eErr);
    setPasswordError(pErr);
    setFirstNameError(fErr);
    setLastNameError(lErr);
    setAgeError(aErr);
    setAddressError(addrErr);

    if (uErr || eErr || pErr || fErr || lErr || aErr || addrErr || !gender) {
      return false;
    }

    setError("");
    return true;
  };

  const onSubmit = async (e) => {
    e.preventDefault();
    setError("");
    setSuccess("");

    if (!isValid()) return;

    setLoading(true);
    try {
      const res = await fetch(`${API_BASE}/api/auth/signup`, {
        method: "POST",
        credentials: "include",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          username: username.trim(),
          email: email.trim(),
          password: password.trim(),
          firstName: firstName.trim(),
          middleName: middleName.trim(),
          lastName: lastName.trim(),
          age: Number(age),
          address: address.trim(),
          gender,
        }),
      });

      let data;
      try {
        data = await res.json();
      } catch {
        data = {};
      }

      if (!res.ok) {
        setError(data?.message || "Signup failed");
        return;
      }

      setSuccess(data?.message || "Account created successfully.");
      setTimeout(() => navigate('/'), 500);
    } catch (err) {
      setError(err?.message || "Network error");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="container">
      <div className="BoxLogin">
        <LazyImage src={loginLogo} alt="PYlot Logo" className="login-logo" />
        <h2 style={{ textAlign: 'center', marginBottom: 16 }}>Sign Up</h2>

        <form onSubmit={onSubmit} className="form">
          <div>
            <input
              type="text"
              placeholder="Username"
              value={username}
              onChange={(e) => {
                const value = e.target.value;
                setUsername(value);
                setUsernameError(validateUsername(value));
              }}
            />
            {usernameError && <p className="inline-error">{usernameError}</p>}
          </div>

          <div>
            <input
              type="email"
              placeholder="Email"
              value={email}
              onChange={(e) => {
                const value = e.target.value;
                setEmail(value);
                setEmailError(validateEmail(value));
              }}
            />
            {emailError && <p className="inline-error">{emailError}</p>}
          </div>

          <div>
            <div className="input-with-icon">
              <input
                type={showPassword ? "text" : "password"}
                placeholder="Password"
                value={password}
                onChange={(e) => {
                  const value = e.target.value;
                  setPassword(value);
                  setPasswordError(validatePassword(value));
                }}
              />
              <button
                type="button"
                className="password-toggle"
                aria-label={showPassword ? "Hide password" : "Show password"}
                onClick={() => setShowPassword((prev) => !prev)}
              >
                <EyeIcon slashed={showPassword} />
              </button>
            </div>
            {passwordError && <p className="inline-error">{passwordError}</p>}
          </div>

          <div>
            <input
              type="text"
              placeholder="First Name"
              value={firstName}
              onChange={(e) => {
                const value = e.target.value;
                setFirstName(value);
                setFirstNameError(validateFirstName(value));
              }}
            />
            {firstNameError && <p className="inline-error">{firstNameError}</p>}
          </div>

          <div>
            <input
              type="text"
              placeholder="Middle Name (optional)"
              value={middleName}
              onChange={(e) => setMiddleName(e.target.value)}
            />
          </div>

          <div>
            <input
              type="text"
              placeholder="Last Name"
              value={lastName}
              onChange={(e) => {
                const value = e.target.value;
                setLastName(value);
                setLastNameError(validateLastName(value));
              }}
            />
            {lastNameError && <p className="inline-error">{lastNameError}</p>}
          </div>

          <div>
            <input
              type="text"
              placeholder="Age"
              value={age}
              onChange={(e) => {
                const value = e.target.value;
                setAge(value);
                setAgeError(validateAge(value));
              }}
            />
            {ageError && <p className="inline-error">{ageError}</p>}
          </div>

          <div>
            <input
              type="text"
              placeholder="Address"
              value={address}
              onChange={(e) => {
                const value = e.target.value;
                setAddress(value);
                setAddressError(validateAddress(value));
              }}
            />
            {addressError && <p className="inline-error">{addressError}</p>}
          </div>

          <div>
            <select value={gender} onChange={(e) => setGender(e.target.value)}>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
          </div>

          <button type="submit" disabled={loading}>
            {loading ? 'Creating...' : 'Create Account'}
          </button>

          {error && <p className="error">{error}</p>}
          {success && <p className="success">{success}</p>}
        </form>

        <p className="toggle-text" style={{ marginTop: 8 }}>
          <button className="link-button" type="button" onClick={() => navigate('/')}>Back to Login</button>
        </p>
      </div>
    </div>
  );
}

function RequireAdmin({ children }) {
  const isAdmin = (() => {
    try {
      const role = (localStorage.getItem("role") || "").toLowerCase();
      const accessToken = localStorage.getItem("accessToken");

      if (role !== "admin" || !accessToken) {
        return false;
      }

      try {
        const payload = JSON.parse(atob(accessToken.split('.')[1]));
        const currentTime = Date.now() / 1000;

        if (payload.exp && payload.exp < currentTime) {
          localStorage.removeItem("accessToken");
          localStorage.removeItem("refreshToken");
          localStorage.removeItem("role");
          localStorage.removeItem("username");
          return false;
        }
      } catch (tokenError) {
        localStorage.removeItem("accessToken");
        localStorage.removeItem("refreshToken");
        return false;
      }

      return true;
    } catch {
      return false;
    }
  })();

  if (!isAdmin) {
    return <Navigate to="/" replace />;
  }
  return children;
}

function RequireUser({ children }) {
  const isValidUser = (() => {
    try {
      const role = (localStorage.getItem("role") || "").toLowerCase();
      const accessToken = localStorage.getItem("accessToken");
      
      const isApproved = (localStorage.getItem("isApproved") || "").toLowerCase() === 'true';
      if (role !== "user" || !isApproved || !accessToken) {
        return false;
      }
      
      try {
        const payload = JSON.parse(atob(accessToken.split('.')[1]));
        const currentTime = Date.now() / 1000;
        
        if (payload.exp && payload.exp < currentTime) {
          localStorage.removeItem("accessToken");
          localStorage.removeItem("refreshToken");
          localStorage.removeItem("role");
          localStorage.removeItem("username");
          localStorage.removeItem("isApproved");
          return false;
        }
      } catch (tokenError) {
        localStorage.removeItem("accessToken");
        localStorage.removeItem("refreshToken");
        return false;
      }
      
      return true;
    } catch {
      return false;
    }
  })();
  
  if (!isValidUser) {
    return <Navigate to="/" replace />;
  }
  return children;
}
