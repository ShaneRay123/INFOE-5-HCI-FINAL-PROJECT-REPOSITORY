import React from "react";
import { useNavigate } from "react-router-dom";

function SettingsPage() {
  const navigate = useNavigate();

  const handleLogout = () => {
    try {
      
      localStorage.clear();
      sessionStorage.clear();
    } catch {}
    navigate("/");
  };

  return (
    <div>
      <div className="page-header">
        <h2>Settings</h2>
      </div>
      <button className="logout-btn" onClick={handleLogout}>Log out</button>
    </div>
  );
}

export default SettingsPage;

