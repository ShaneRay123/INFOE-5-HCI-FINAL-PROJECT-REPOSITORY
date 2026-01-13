import React, { useCallback, useEffect, useMemo, useState } from "react";
import { api } from "./utils/api";

function UserManagement() {
  const [users, setUsers] = useState([]);
  const [query, setQuery] = useState("");
  const [debouncedQuery, setDebouncedQuery] = useState("");
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState("");
  const [actionId, setActionId] = useState(null);

  const loadData = useCallback(async () => {
    try {
      setIsLoading(true);
      setError("");

      const all = await api.get("/admin/users").catch(() => []);
      const list = Array.isArray(all) ? all : [];

      setUsers(list);
    } catch (error) {
      setError("Failed to load users");
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  
  useEffect(() => {
    const t = setTimeout(() => setDebouncedQuery(query), 300);
    return () => clearTimeout(t);
  }, [query]);

  const filteredUsers = useMemo(() => {
    const q = debouncedQuery.trim().toLowerCase();
    if (!q) return users;

    const fields = [
      "username",
      "studentId",
      "fullName",
      "age",
      "gender",
      "address",
      "role",
      "access",
    ];

    const getFieldValue = (u, field) => {
      switch (field) {
        case "username":
          return u.username || "";
        case "studentId":
          return u.studentId || "";
        case "fullName":
          return u.fullName || "";
        case "age":
          return u.age != null ? String(u.age) : "";
        case "gender":
          return u.gender || "";
        case "address":
          return u.address || "";
        case "role":
          return u.role || "";
        case "access":
          if (u.progressSummary?.advancedUser) return "Advanced";
          return `Modules: ${u.progressSummary?.assignedModules ?? 0}`;
        default:
          return u.username || "";
      }
    };

    return users.filter(u =>
      fields.some(field =>
        getFieldValue(u, field).toLowerCase().includes(q)
      )
    );
  }, [users, debouncedQuery]);

  const approveUser = useCallback(async (id) => {
    try {
      setActionId(id);
      
      
      setUsers(prev => prev.map(u => 
        u._id === id ? { ...u, isApproved: true } : u
      ));
      
      
      await api.post(`/admin/users/${id}/approve`);
    } catch (error) {
      alert("Approve failed");
      
      await loadData();
    } finally {
      setActionId(null);
    }
  }, [loadData]);

  const deleteUser = useCallback(async (id) => {
    if (!window.confirm("Are you sure you want to delete this user? This action cannot be undone.")) return;
    try {
      setActionId(id);
      
      
      setUsers(prev => prev.filter(u => u._id !== id));
      
      
      await api.del(`/admin/users/${id}`);
    } catch (error) {
      alert("Delete failed");
      
      await loadData();
    } finally {
      setActionId(null);
    }
  }, [loadData]);


  return (
    <div>
      <div className="page-header">
        <h2>User Management</h2>
      </div>

      <div className="filters" style={{ justifyContent: "space-between" }}>
        <div style={{ display: "flex", gap: 10, alignItems: "center" }}>
          <input
            type="text"
            placeholder="Search users..."
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            style={{ width: 300, padding: 8, borderRadius: 8, border: "1px solid #ddd" }}
          />
        </div>
        <button className="admin-btn admin-btn--primary" onClick={loadData} disabled={isLoading}>
          {isLoading ? "Refreshing..." : "Refresh"}
        </button>
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
        }}>
          {error}
        </div>
      )}

      <div className="exams-table-container">
        <table className="users-table">
          <thead>
            <tr>
              <th>Username</th>
              <th>Student ID</th>
              <th>Full Name</th>
              <th>Age</th>
              <th>Gender</th>
              <th>Address</th>
              <th>Role</th>
              <th>Access</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {filteredUsers.map(u => (
              <tr key={u._id}>
                <td>{u.username}</td>
                <td>{u.studentId || 'N/A'}</td>
                <td>{u.fullName || 'N/A'}</td>
                <td>{u.age || 'N/A'}</td>
                <td>{u.gender || 'N/A'}</td>
                <td className="address-cell" title={u.address}>
                  {u.address || 'N/A'}
                </td>
                <td>{u.role}</td>
                <td>
                  {u.progressSummary?.advancedUser ? (
                    <span className="badge" style={{ background: '#4caf50', color: '#fff', padding: '2px 6px', borderRadius: 4 }}>Advanced</span>
                  ) : (
                    <span style={{ color: '#555' }}>Modules: {u.progressSummary?.assignedModules ?? 0}</span>
                  )}
                </td>
                <td>
                  <div className="user-action-buttons">
                    {!u.isApproved && (
                      <button
                        className="admin-btn admin-btn--success admin-btn--small"
                        style={{ marginRight: '4px' }}
                        onClick={() => approveUser(u._id)}
                        disabled={actionId === u._id}
                      >
                        {actionId === u._id ? "Approving..." : "Approve"}
                      </button>
                    )}
                    {String(u.role).toLowerCase() !== 'admin' && (
                      <button
                        className="admin-btn admin-btn--danger admin-btn--small"
                        onClick={() => deleteUser(u._id)}
                        disabled={actionId === u._id}
                      >
                        {actionId === u._id ? "Processing..." : "Delete"}
                      </button>
                    )}
                  </div>
                </td>
              </tr>
            ))}
            {filteredUsers.length === 0 && (
              <tr>
                <td colSpan={9} style={{ textAlign: "center", padding: 20, color: "#777" }}>
                  No users found
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}

export default UserManagement;

