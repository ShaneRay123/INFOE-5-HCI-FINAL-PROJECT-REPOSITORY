import React, { useEffect, useState } from "react";
import { api } from "../../utils/api";

function AdminDashboard() {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const [moduleRows, setModuleRows] = useState([]);
  const [moduleLoading, setModuleLoading] = useState(true);
  const [moduleError, setModuleError] = useState("");
  const [moduleFilter, setModuleFilter] = useState({ view: 'all', threshold: 50 });

  useEffect(() => {
    let mounted = true;
    let timer;

    const fetchUsers = async () => {
      try {
        if (!mounted) return;
        setError("");
        const data = await api.get("/api/admin/users");
        const users = Array.isArray(data?.users) ? data.users : Array.isArray(data) ? data : [];
        if (!mounted) return;
        setRows(users);
      } catch (err) {
        if (!mounted) return;
        setError(err.message || "Failed to load users");
      } finally {
        if (mounted) setLoading(false);
      }
    };

    const fetchModuleProgress = async () => {
      try {
        if (!mounted) return;
        setModuleError("");
        const data = await api.get("/api/admin/module-progress");
        const items = Array.isArray(data?.items) ? data.items : Array.isArray(data) ? data : [];
        if (!mounted) return;
        setModuleRows(items);
      } catch (err) {
        if (!mounted) return;
        setModuleError(err.message || "Failed to load module progress");
      } finally {
        if (mounted) setModuleLoading(false);
      }
    };

    setLoading(true);
    setModuleLoading(true);
    fetchUsers();
    fetchModuleProgress();
    timer = setInterval(() => {
      fetchUsers();
      fetchModuleProgress();
    }, 5000);

    return () => {
      mounted = false;
      if (timer) clearInterval(timer);
    };
  }, []);

  const renderModuleSummary = (m) => {
    const scores = m.moduleScores || {};
    const keys = Object.keys(scores);
    if (!keys.length) return "—";
    const parts = keys.slice(0, 3).map((k) => {
      const s = scores[k];
      const pct = Number(s.pct ?? s.percentage ?? 0) || 0;
      const diff = s.difficulty || "";
      return `${k}: ${pct}%${diff ? ` (${diff})` : ""}`;
    });
    if (keys.length > 3) parts.push(`+${keys.length - 3} more`);
    return parts.join(" | ");
  };

  const filteredModuleRows = (() => {
    const view = moduleFilter.view;
    const threshold = Number(moduleFilter.threshold) || 0;
    const rows = moduleRows || [];
    const filtered = rows.filter((m) => {
      if (view === 'advanced') return !!m.advancedUser;
      if (view === 'low') {
        const overall = typeof m.overall === 'number' ? m.overall : null;
        return overall != null && overall < threshold;
      }
      return true;
    });

    return filtered.sort((a, b) => {
      const ao = typeof a.overall === 'number' ? a.overall : -1;
      const bo = typeof b.overall === 'number' ? b.overall : -1;
      return bo - ao;
    });
  })();

  return (
    <div>
      <div className="page-header">
        <h2>Admin Dashboard</h2>
      </div>

      {error && (
        <div style={{
          marginTop: 8,
          marginBottom: 8,
          padding: 10,
          background: "#fff5f5",
          border: "1px solid #ffdada",
          color: "#7a1f1f",
          borderRadius: 6
        }}>{error}</div>
      )}

      <div className="exams-table-container" style={{ marginBottom: 24 }}>
        {loading ? (
          <p>Loading users...</p>
        ) : (
          <table className="users-table users-table--dashboard">
            <thead>
              <tr>
                <th>Student ID</th>
                <th>Name</th>
                <th>Username</th>
                <th>Online</th>
                <th>Last Login</th>
              </tr>
            </thead>
            <tbody>
              {rows.map(u => (
                <tr key={u._id || u.username}>
                  <td>{u.studentId || 'N/A'}</td>
                  <td>{[u.firstName, u.middleName, u.lastName].filter(Boolean).join(' ') || u.fullName || 'N/A'}</td>
                  <td>{u.username}</td>
                  <td>{u.isOnline ? '🟢' : '🔴'}</td>
                  <td>{u.lastActive ? new Date(u.lastActive).toLocaleString() : (u.lastLogin ? new Date(u.lastLogin).toLocaleString() : '—')}</td>
                </tr>
              ))}
              {rows.length === 0 && (
                <tr>
                  <td colSpan={5} style={{ textAlign: 'center', padding: 20, color: '#777' }}>No users</td>
                </tr>
              )}
            </tbody>
          </table>
        )}
      </div>

      <div style={{ marginTop: 16 }}>
        <h3 style={{ marginBottom: 8 }}>Module Progress Snapshot</h3>
        {moduleError && (
          <div style={{
            marginTop: 4,
            marginBottom: 8,
            padding: 10,
            background: "#fff5f5",
            border: "1px solid #ffdada",
            color: "#7a1f1f",
            borderRadius: 6
          }}>{moduleError}</div>
        )}
        {moduleLoading ? (
          <p>Loading module progress...</p>
        ) : (
          <div className="exams-table-container">
            <div style={{ display: 'flex', gap: 8, marginBottom: 8, alignItems: 'center', flexWrap: 'wrap' }}>
              <label style={{ fontSize: 12 }}>
                View:
                <select
                  value={moduleFilter.view}
                  onChange={(e) => setModuleFilter(prev => ({ ...prev, view: e.target.value }))}
                  style={{ marginLeft: 4, padding: '4px 6px', fontSize: 12 }}
                >
                  <option value="all">All users</option>
                  <option value="advanced">Advanced only (overall >= 90%)</option>
                  <option value="low">Below threshold</option>
                </select>
              </label>
              <label style={{ fontSize: 12 }}>
                Threshold (%):
                <input
                  type="number"
                  min="0"
                  max="100"
                  value={moduleFilter.threshold}
                  onChange={(e) => setModuleFilter(prev => ({ ...prev, threshold: e.target.value }))}
                  disabled={moduleFilter.view !== 'low'}
                  style={{ marginLeft: 4, padding: '3px 6px', width: 60, fontSize: 12 }}
                />
              </label>
            </div>
            <table className="users-table users-table--dashboard">
              <thead>
                <tr>
                  <th>Username</th>
                  <th>Overall</th>
                  <th>Advanced</th>
                  <th>Assigned Modules</th>
                  <th>Module Scores</th>
                </tr>
              </thead>
              <tbody>
                {filteredModuleRows.map((m, idx) => (
                  <tr key={`${m.username || 'user'}-${idx}`}>
                    <td>{m.username}</td>
                    <td>{m.overall != null ? `${m.overall}%` : '—'}</td>
                    <td>{m.advancedUser ? '✅' : '—'}</td>
                    <td>{m.assignedCount}</td>
                    <td style={{ fontSize: '11px', color: '#555' }}>{renderModuleSummary(m)}</td>
                  </tr>
                ))}
                {filteredModuleRows.length === 0 && (
                  <tr>
                    <td colSpan={5} style={{ textAlign: 'center', padding: 20, color: '#777' }}>
                      No module progress data.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}

export default AdminDashboard;

