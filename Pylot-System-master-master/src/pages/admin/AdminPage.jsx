import React from "react";
import { Routes, Route, useNavigate } from "react-router-dom";
import SettingsPage from "../../SettingsPage";
import UserManagement from "../../UserManagement";
import Results from "../../Results";
import Certificates from "../../Certificates";
import AdminModuleManagement from "../../components/admin/AdminModuleManagement";
import CheckpointQuizManagement from "../../components/admin/CheckpointQuizManagement";
import ExamManagement from "./ExamManagement";
import AdminDashboard from "./AdminDashboard";
import "./AdminPage.css";
import Logo from "../../assets/PYlot white.png";

function AdminPage() {
  const navigate = useNavigate();

  return (
    <div className="admin-container">
      {}
      <aside className="sidebar">
        <img src={Logo} alt="PYlot Logo" className="logo" />
        <nav>
          <ul>
            <li onClick={() => navigate("/admin/dashboard")}>Dashboard</li>
            <li onClick={() => navigate("/admin/exam-management")}>Exam Management</li>
            <li onClick={() => navigate("/admin/module-management")}>Module Management</li>
            <li onClick={() => navigate("/admin/checkpoint-quizzes")}>Checkpoint Quizzes</li>
            <li onClick={() => navigate("/admin/results")}>Results</li>
            <li onClick={() => navigate("/admin/users")}>Users</li>
            <li onClick={() => navigate("/admin/certificates")}>Certificates</li>
            <li onClick={() => navigate("/admin/settings")}>Settings</li>
          </ul>
        </nav>
      </aside>

      {}
      <main className="content">
        <Routes>
          <Route path="/" element={<AdminDashboard />} />
          <Route path="dashboard" element={<AdminDashboard />} />
          <Route path="exam-management" element={<ExamManagement />} />
          <Route path="results" element={<Results />} />
          <Route path="users" element={<UserManagement />} />
          <Route path="module-management" element={<AdminModuleManagement />} />
          <Route path="checkpoint-quizzes" element={<CheckpointQuizManagement />} />
          <Route path="certificates" element={<Certificates />} />
          <Route path="settings" element={<SettingsPage />} />
        </Routes>
      </main>
    </div>
  );
}

export default AdminPage;

