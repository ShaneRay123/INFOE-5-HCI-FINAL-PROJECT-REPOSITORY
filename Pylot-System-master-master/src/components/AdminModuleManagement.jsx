import React, { useCallback, useEffect, useState } from "react";
import { api, API_BASE } from "../utils/api";

function AdminModuleManagement() {
  const [modules, setModules] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState("");

  const [tierConfig, setTierConfig] = useState([]);
  const [tierConfigLoading, setTierConfigLoading] = useState(false);
  const [tierConfigError, setTierConfigError] = useState("");
  const [tierConfigSaving, setTierConfigSaving] = useState(false);
  const [tierConfigToast, setTierConfigToast] = useState(null);
  const [tierConfigValidationError, setTierConfigValidationError] = useState("");

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [isModalLoading, setIsModalLoading] = useState(false);
  const [modalError, setModalError] = useState("");
  const [modalSuccess, setModalSuccess] = useState("");
  const [editingModuleId, setEditingModuleId] = useState(null);
  const [originalFormData, setOriginalFormData] = useState(null);
  const [formData, setFormData] = useState({
    moduleId: "",
    title: "",
    description: "",
    content: "",
    scoreRange: { min: 0, max: 100 },
    isActive: true,
    tier: "Tier 1"
  });
  const [selectedFile, setSelectedFile] = useState(null);
  const [uploading, setUploading] = useState(false);
  const [toast, setToast] = useState(null);
  
  
  const [filters, setFilters] = useState({
    searchTerm: '',
    tier: ''
  });
  
  
  const [sortDir, setSortDir] = useState('asc'); 

  const findTierRow = useCallback((tierKey) => {
    const key = String(tierKey || '').trim();
    return (tierConfig || []).find(t => String(t.key).trim() === key) || null;
  }, [tierConfig]);

  const getTierLabel = useCallback((tierKey) => {
    const row = findTierRow(tierKey);
    return row ? row.label : (tierKey || '');
  }, [findTierRow]);

  const applyTierRangeToForm = useCallback((tierKey) => {
    const row = findTierRow(tierKey);
    if (!row) return;
    setFormData((prev) => ({
      ...prev,
      tier: tierKey,
      scoreRange: { min: Number(row.min), max: Number(row.max) },
    }));
  }, [findTierRow]);

  const isDirty = useCallback(() => {
    if (!originalFormData) return false;
    const a = {
      ...formData,
      scoreRange: { min: Number(formData.scoreRange?.min), max: Number(formData.scoreRange?.max) }
    };
    const b = {
      ...originalFormData,
      scoreRange: { min: Number(originalFormData.scoreRange?.min), max: Number(originalFormData.scoreRange?.max) }
    };
    return JSON.stringify(a) !== JSON.stringify(b) || !!selectedFile;
  }, [formData, originalFormData, selectedFile]);

  const loadModules = useCallback(async () => {
    try {
      setIsLoading(true);
      setError("");
      const data = await api.get("/api/modules");

      const moduleList = Array.isArray(data)
        ? data
        : Array.isArray(data?.modules)
          ? data.modules
          : [];

      setModules(moduleList);
    } catch (error) {
      setError("Failed to load modules");
      setModules([]);
      console.error("Error loading modules:", error);
    } finally {
      setIsLoading(false);
    }
  }, []);

  const loadTierConfig = useCallback(async () => {
    try {
      setTierConfigLoading(true);
      setTierConfigError("");
      const data = await api.get('/api/admin/tier-config');
      const tiers = Array.isArray(data?.tiers) ? data.tiers : [];
      setTierConfig(tiers);
      setTierConfigValidationError('');
    } catch (e) {
      console.error('Error loading tier config:', e);
      setTierConfig([]);
      setTierConfigError('Failed to load tier configuration');
    } finally {
      setTierConfigLoading(false);
    }
  }, []);

  const validateTierConfigClient = useCallback((tiers) => {
    const list = Array.isArray(tiers) ? tiers : [];
    if (list.length !== 2) return 'Tier configuration must have exactly Tier 1 and Tier 2';

    const t1 = list.find(t => String(t.key).trim() === 'Tier 1');
    const t2 = list.find(t => String(t.key).trim() === 'Tier 2');
    if (!t1 || !t2) return 'Tier 1 and Tier 2 are required';

    const min1 = Number(t1.min);
    const max1 = Number(t1.max);
    const min2 = Number(t2.min);
    const max2 = Number(t2.max);

    if (![min1, max1, min2, max2].every(Number.isFinite)) return 'Min/Max must be valid numbers';
    if (min1 < 0 || max1 > 100 || min2 < 0 || max2 > 100) return 'Ranges must be within 0–100';
    if (min1 > max1 || min2 > max2) return 'Min must be <= Max';

    if (min1 !== 0) return 'Tier 1 must always start at 0';
    if (max2 !== 100) return 'Tier 2 must always end at 100';
    if (!(max1 < min2)) return 'Tier 1 max must be less than Tier 2 min';

    const coversAll = (score) => (
      (score >= min1 && score <= max1) ||
      (score >= min2 && score <= max2)
    );
    for (let s = 0; s <= 100; s++) {
      if (!coversAll(s)) return 'Ranges must fully cover 0–100';
    }
    return '';
  }, []);

  useEffect(() => {
    loadModules();
  }, [loadModules]);

  useEffect(() => {
    loadTierConfig();
  }, [loadTierConfig]);

  useEffect(() => {
    if (!isModalOpen) return;
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.body.style.overflow = prev;
    };
  }, [isModalOpen]);

  useEffect(() => {
    const handler = (e) => {
      if (!isModalOpen) return;
      if (!isDirty()) return;
      e.preventDefault();
      e.returnValue = '';
    };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
  }, [isModalOpen, isDirty]);


  const handleSubmit = async (e) => {
    e.preventDefault();
    
    
    
    if (!formData.moduleId || !formData.title) {
      setToast({ type: 'error', message: 'Please fill in all required fields (Module ID, Title)' });
      return;
    }
    
    setModalError('');
    setModalSuccess('');
    
    try {
      setUploading(true);
      let moduleData = { ...formData };
      
      
      if (selectedFile) {
        console.log('Uploading file:', selectedFile.name);
        const uploadFormData = new FormData();
        uploadFormData.append('file', selectedFile);

        const token = localStorage.getItem('accessToken');
        const uploadResponse = await fetch(`${API_BASE}/upload`, {
          method: 'POST',
          credentials: 'include',
          headers: {
            ...(token ? { Authorization: `Bearer ${token}` } : {})
          },
          body: uploadFormData
        });
        
        if (!uploadResponse.ok) {
          const errorText = await uploadResponse.text();
          throw new Error(`Upload failed: ${uploadResponse.status} - ${errorText}`);
        }
        
        const uploadResult = await uploadResponse.json();
        moduleData.content = uploadResult.filename;
        console.log('File uploaded successfully:', uploadResult.filename);
      } else if (editingModuleId && !formData.content) {

        moduleData.content = moduleData.content || '';
      }
      
      if (editingModuleId) {
        const response = await fetch(`${API_BASE}/api/modules/${editingModuleId}`, {
          method: 'PUT',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(moduleData)
        });
        
        if (!response.ok) {
          const responseText = await response.text();
          throw new Error(`Update failed: ${response.status} - ${responseText}`);
        }
      } else {
        const response = await fetch(`${API_BASE}/api/modules`, {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(moduleData)
        });
        
        if (!response.ok) {
          const responseText = await response.text();
          throw new Error(`Create failed: ${response.status} - ${responseText}`);
        }
      }
      
      await loadModules();
      setModalSuccess('Module saved successfully!');
      setOriginalFormData({
        ...moduleData,
        scoreRange: { min: Number(moduleData.scoreRange?.min), max: Number(moduleData.scoreRange?.max) }
      });
      setTimeout(() => {
        closeModal(true);
        setToast({ type: 'success', message: 'Module saved successfully!' });
      }, 350);
    } catch (error) {
      console.error("Error saving module:", error);
      setModalError('Failed to save module. Please check your input and try again.');
    } finally {
      setUploading(false);
    }
  };

  const openCreateModal = () => {
    setModalError('');
    setModalSuccess('');
    setEditingModuleId(null);
    const defaultTier = (tierConfig && tierConfig.length ? tierConfig[0].key : 'Tier 1');
    const row = findTierRow(defaultTier);
    const next = {
      moduleId: "",
      title: "",
      description: "",
      content: "",
      scoreRange: row ? { min: Number(row.min), max: Number(row.max) } : { min: 0, max: 100 },
      isActive: true,
      tier: defaultTier,
    };
    setFormData(next);
    setOriginalFormData(next);
    setSelectedFile(null);
    setIsModalOpen(true);
  };

  const openEditModal = async (module) => {
    setModalError('');
    setModalSuccess('');
    setIsModalOpen(true);
    setIsModalLoading(true);
    setSelectedFile(null);

    try {
      const data = await api.get(`/api/modules/${module._id}`);
      const m = data?.module || data;

      const tierKey = String(m?.tier || 'Tier 1').trim();
      const tierRow = findTierRow(tierKey);
      const next = {
        moduleId: m?.moduleId || '',
        title: m?.title || '',
        description: m?.description || '',
        content: m?.content || '',
        scoreRange: tierRow
          ? { min: Number(tierRow.min), max: Number(tierRow.max) }
          : { min: Number(m?.scoreRange?.min ?? 0), max: Number(m?.scoreRange?.max ?? 100) },
        isActive: !!m?.isActive,
        tier: tierKey,
      };

      setEditingModuleId(module._id);
      setFormData(next);
      setOriginalFormData(next);
    } catch (e) {
      console.error('Failed to load module details:', e);
      setModalError('Failed to load module details.');
    } finally {
      setIsModalLoading(false);
    }
  };

  const closeModal = (force = false) => {
    if (!force && isDirty()) {
      const ok = window.confirm('You have unsaved changes. Discard them?');
      if (!ok) return;
    }
    setIsModalOpen(false);
    setIsModalLoading(false);
    setEditingModuleId(null);
    setOriginalFormData(null);
    setSelectedFile(null);
    setModalError('');
    setModalSuccess('');
  };

  const handleDelete = async (id) => {
    if (!window.confirm("Are you sure you want to delete this module?")) return;
    try {
      await api.del(`/api/modules/${id}`);
      await loadModules();
    } catch (error) {
      setToast({ type: 'error', message: 'Failed to delete module' });
      console.error("Error deleting module:", error);
    }
  };

  const getScoreRangeText = (min, max) => {
    return `${min}% - ${max}%`;
  };

  
  const filteredModules = (Array.isArray(modules) ? modules : []).filter(module => {
    const matchesSearch = !filters.searchTerm || 
      (module.title || '').toLowerCase().includes(filters.searchTerm.toLowerCase()) ||
      (module.moduleId || '').toLowerCase().includes(filters.searchTerm.toLowerCase()) ||
      (module.description || '').toLowerCase().includes(filters.searchTerm.toLowerCase());

    const matchesTier = !filters.tier || String(module.tier || '').trim() === filters.tier;

    return matchesSearch && matchesTier;
  });

  
  const displayedModules = (() => {
    const collator = new Intl.Collator(undefined, { sensitivity: 'base' });
    const arr = [...filteredModules].sort((a, b) => collator.compare((a.title || '').toLowerCase(), (b.title || '').toLowerCase()));
    return sortDir === 'asc' ? arr : arr.reverse();
  })();

  const handleFilterChange = (filterType, value) => {
    setFilters(prev => ({
      ...prev,
      [filterType]: value
    }));
  };

  const clearFilters = () => {
    setFilters({
      searchTerm: '',
      tier: ''
    });
  };

  const saveTierConfig = async () => {
    try {
      setTierConfigSaving(true);
      setTierConfigError('');
      const clientErr = validateTierConfigClient(tierConfig);
      setTierConfigValidationError(clientErr);
      if (clientErr) {
        setTierConfigToast({ type: 'error', message: clientErr });
        return;
      }
      const payload = { tiers: tierConfig.map(t => ({
        key: String(t.key || '').trim(),
        label: String(t.key || '').trim(),
        min: Number(t.min),
        max: Number(t.max),
      })) };
      const res = await api.put('/api/admin/tier-config', payload);
      const tiers = Array.isArray(res?.tiers) ? res.tiers : [];
      setTierConfig(tiers);
      setTierConfigValidationError('');
      setTierConfigToast({ type: 'success', message: 'Tier configuration saved!' });
      await loadModules();
    } catch (e) {
      console.error('Failed to save tier config:', e);
      const msg = e?.message || 'Failed to save tier configuration';
      setTierConfigError(msg);
      setTierConfigToast({ type: 'error', message: msg });
    } finally {
      setTierConfigSaving(false);
    }
  };


  return (
    <div>
      {}
      {toast && (
        <div
          role="status"
          onAnimationEnd={() => setToast(null)}
          style={{
            position: 'fixed',
            top: 16,
            left: '50%',
            transform: 'translateX(-50%)',
            zIndex: 1000,
            padding: '10px 16px',
            borderRadius: 8,
            color: toast.type === 'error' ? '#7a1f1f' : toast.type === 'success' ? '#1b5e20' : '#333',
            background: toast.type === 'error' ? '#fff5f5' : toast.type === 'success' ? '#e8f5e9' : '#f5f5f5',
            border: '1px solid',
            borderColor: toast.type === 'error' ? '#ffdada' : toast.type === 'success' ? '#c8e6c9' : '#e0e0e0',
          }}
        >
          {toast.message}
        </div>
      )}

      {}
      <div className="page-header">
        <h2>Module Management</h2>
        <div style={{ display: "flex", gap: "10px" }}>
          <button 
            className="admin-btn admin-btn--primary" 
            onClick={openCreateModal}
          >
            Add New Module
          </button>
        </div>
      </div>

      {tierConfigToast && (
        <div
          role="status"
          onAnimationEnd={() => setTierConfigToast(null)}
          style={{
            position: 'fixed',
            top: 56,
            left: '50%',
            transform: 'translateX(-50%)',
            zIndex: 1000,
            padding: '10px 16px',
            borderRadius: 8,
            color: tierConfigToast.type === 'error' ? '#7a1f1f' : tierConfigToast.type === 'success' ? '#1b5e20' : '#333',
            background: tierConfigToast.type === 'error' ? '#fff5f5' : tierConfigToast.type === 'success' ? '#e8f5e9' : '#f5f5f5',
            border: '1px solid',
            borderColor: tierConfigToast.type === 'error' ? '#ffdada' : tierConfigToast.type === 'success' ? '#c8e6c9' : '#e0e0e0',
          }}
        >
          {tierConfigToast.message}
        </div>
      )}

      <div style={{
        background: "#f9f9f9",
        padding: "16px",
        borderRadius: "8px",
        marginBottom: "20px",
        border: "1px solid #ddd"
      }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12 }}>
          <h3 style={{ margin: 0 }}>Tier Configuration</h3>
          <button
            className="admin-btn admin-btn--primary"
            onClick={saveTierConfig}
            disabled={tierConfigSaving || tierConfigLoading}
          >
            {tierConfigSaving ? 'Saving...' : 'Save Tiers'}
          </button>
        </div>

        {tierConfigLoading ? (
          <div style={{ padding: '10px 0', color: '#666' }}>Loading tiers...</div>
        ) : (
          <div style={{ overflowX: 'auto', marginTop: 12 }}>
            {!!tierConfigValidationError && (
              <div style={{
                marginBottom: 12,
                padding: '10px 12px',
                background: '#fff5f5',
                border: '1px solid #ffdada',
                color: '#7a1f1f',
                borderRadius: 6
              }}>
                {tierConfigValidationError}
              </div>
            )}
            <table className="users-table">
              <thead>
                <tr>
                  <th style={{ width: 120 }}>Tier Key</th>
                  <th>Tier Label</th>
                  <th style={{ width: 140 }}>Min</th>
                  <th style={{ width: 140 }}>Max</th>
                </tr>
              </thead>
              <tbody>
                {(tierConfig || []).map((t, idx) => (
                  <tr key={t.key || idx}>
                    <td><strong>{t.key}</strong></td>
                    <td>
                      <div style={{ width: '100%', padding: '8px', borderRadius: '4px', border: '1px solid #eee', background: '#f5f5f5', color: '#444' }}>
                        {t.key}
                      </div>
                    </td>
                    <td>
                      <input
                        type="number"
                        min="0"
                        max="100"
                        value={t.min}
                        onChange={(e) => {
                          const v = Number(e.target.value);
                          setTierConfig((prev) => {
                            const next = prev.map((x, i) => i === idx ? ({ ...x, min: v }) : x);
                            setTierConfigValidationError(validateTierConfigClient(next));
                            return next;
                          });
                        }}
                        style={{ width: '100%', padding: '8px', borderRadius: '4px', border: '1px solid #ddd' }}
                      />
                    </td>
                    <td>
                      <input
                        type="number"
                        min="0"
                        max="100"
                        value={t.max}
                        onChange={(e) => {
                          const v = Number(e.target.value);
                          setTierConfig((prev) => {
                            const next = prev.map((x, i) => i === idx ? ({ ...x, max: v }) : x);
                            setTierConfigValidationError(validateTierConfigClient(next));
                            return next;
                          });
                        }}
                        style={{ width: '100%', padding: '8px', borderRadius: '4px', border: '1px solid #ddd' }}
                      />
                    </td>
                  </tr>
                ))}
                {(!tierConfig || tierConfig.length === 0) && (
                  <tr>
                    <td colSpan={4} style={{ textAlign: 'center', padding: 16, color: '#777' }}>
                      No tier configuration found.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        )}

        {tierConfigError && (
          <div style={{
            marginTop: 12,
            padding: '10px',
            background: '#fff5f5',
            border: '1px solid #ffdada',
            color: '#7a1f1f',
            borderRadius: '6px'
          }}>
            {tierConfigError}
          </div>
        )}
      </div>

      {}
      <div style={{ 
        background: "#f5f5f5", 
        padding: "15px", 
        borderRadius: "8px", 
        marginBottom: "20px",
        border: "1px solid #ddd"
      }}>
        <h3 style={{ margin: "0 0 15px 0", fontSize: "16px", color: "#333" }}>Filter Modules</h3>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr auto auto", gap: "15px", alignItems: "end" }}>
          {}
          <div>
            <label style={{ display: "block", marginBottom: "5px", fontSize: "14px", fontWeight: "500" }}>
              Search:
            </label>
            <input
              type="text"
              placeholder="Search by title, ID, or description..."
              value={filters.searchTerm}
              onChange={(e) => handleFilterChange('searchTerm', e.target.value)}
              style={{ 
                width: "100%", 
                padding: "8px", 
                borderRadius: "4px", 
                border: "1px solid #ddd",
                fontSize: "14px"
              }}
            />
          </div>

          {}
          <div>
            <label style={{ display: "block", marginBottom: "5px", fontSize: "14px", fontWeight: "500" }}>
              Tier:
            </label>
            <select
              value={filters.tier}
              onChange={(e) => handleFilterChange('tier', e.target.value)}
              style={{ 
                width: "100%", 
                padding: "8px", 
                borderRadius: "4px", 
                border: "1px solid #ddd",
                fontSize: "14px"
              }}
            >
              <option value="">All Tiers</option>
              {(tierConfig || []).map(t => (
                <option key={t.key} value={t.key}>{t.label}</option>
              ))}
            </select>
          </div>

          {}
          <div>
            <button
              onClick={clearFilters}
              className="admin-btn admin-btn--ghost"
            >
              Clear Filters
            </button>
          </div>

          {}
          <div>
            <label htmlFor="admin-sort-dir" style={{ display: "block", marginBottom: "5px", fontSize: "14px", fontWeight: 500 }}>
              Sort (Title):
            </label>
            <select
              id="admin-sort-dir"
              value={sortDir}
              onChange={(e) => setSortDir(e.target.value)}
              aria-label="Sort modules alphabetically by title"
              style={{ 
                width: "100%", 
                padding: "8px", 
                borderRadius: "4px", 
                border: "1px solid #ddd",
                fontSize: "14px"
              }}
            >
              <option value="asc">A–Z</option>
              <option value="desc">Z–A</option>
            </select>
          </div>
        </div>
        
        {}
        {Object.values(filters).some(filter => filter !== '') && (
          <div style={{ 
            marginTop: "10px", 
            padding: "8px 12px", 
            background: "#e3f2fd", 
            border: "1px solid #bbdefb", 
            borderRadius: "4px",
            fontSize: "14px",
            color: "#1565c0"
          }}>
            Showing {filteredModules.length} of {modules.length} modules
            {filters.searchTerm && ` matching "${filters.searchTerm}"`}
            {filters.tier && ` in tier "${getTierLabel(filters.tier)}"`}
          </div>
        )}
      </div>

      {isModalOpen && (
        <div
          role="dialog"
          aria-modal="true"
          style={{
            position: 'fixed',
            inset: 0,
            background: 'rgba(0,0,0,0.55)',
            zIndex: 2000,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            padding: 16,
          }}
          onMouseDown={(e) => {
            if (e.target === e.currentTarget) closeModal();
          }}
        >
          <div
            style={{
              width: 'min(860px, 96vw)',
              maxHeight: '92vh',
              overflow: 'auto',
              background: '#fff',
              borderRadius: 12,
              boxShadow: '0 20px 60px rgba(0,0,0,0.35)',
              border: '1px solid rgba(255,255,255,0.08)',
              padding: 18,
            }}
          >
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12 }}>
              <h3 style={{ margin: 0 }}>{editingModuleId ? 'Edit Module' : 'Add New Module'}</h3>
              <button className="admin-btn admin-btn--ghost" type="button" onClick={() => closeModal()}>Close</button>
            </div>

            {isModalLoading ? (
              <div style={{ padding: '18px 0', color: '#666' }}>Loading module...</div>
            ) : (
              <form onSubmit={handleSubmit}>
                {modalError && (
                  <div style={{
                    marginTop: 12,
                    padding: '10px',
                    background: '#fff5f5',
                    border: '1px solid #ffdada',
                    color: '#7a1f1f',
                    borderRadius: '6px'
                  }}>
                    {modalError}
                  </div>
                )}
                {modalSuccess && (
                  <div style={{
                    marginTop: 12,
                    padding: '10px',
                    background: '#e8f5e9',
                    border: '1px solid #c8e6c9',
                    color: '#1b5e20',
                    borderRadius: '6px'
                  }}>
                    {modalSuccess}
                  </div>
                )}

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginTop: 14 }}>
                  <div>
                    <label>Module ID</label>
                    <input
                      type="text"
                      value={formData.moduleId}
                      onChange={(e) => setFormData({ ...formData, moduleId: e.target.value })}
                      required
                      readOnly={!!editingModuleId}
                      style={{ width: '100%', padding: '8px', borderRadius: '4px', border: '1px solid #ddd' }}
                    />
                    {editingModuleId && (
                      <div style={{ marginTop: 6, fontSize: 12, color: '#666' }}>
                        Module ID is read-only while editing.
                      </div>
                    )}
                  </div>
                  <div>
                    <label>Title</label>
                    <input
                      type="text"
                      value={formData.title}
                      onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                      required
                      style={{ width: '100%', padding: '8px', borderRadius: '4px', border: '1px solid #ddd' }}
                    />
                  </div>
                </div>

                <div style={{ marginTop: 12 }}>
                  <label>Description</label>
                  <textarea
                    value={formData.description}
                    onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                    style={{ width: '100%', padding: '8px', borderRadius: '4px', border: '1px solid #ddd', minHeight: 88 }}
                  />
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginTop: 12 }}>
                  <div>
                    <label>Tier</label>
                    <select
                      value={formData.tier}
                      onChange={(e) => {
                        const nextTier = e.target.value;
                        applyTierRangeToForm(nextTier);
                      }}
                      style={{ width: '100%', padding: '8px', borderRadius: '4px', border: '1px solid #ddd' }}
                    >
                      {(tierConfig || []).map(t => (
                        <option key={t.key} value={t.key}>{t.label}</option>
                      ))}
                      {(!tierConfig || tierConfig.length === 0) && (
                        <option value="Tier 1">Tier 1</option>
                      )}
                    </select>
                  </div>
                  <div>
                    <label>Score Range (auto from tier)</label>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                      <input
                        type="number"
                        value={formData.scoreRange.min}
                        readOnly
                        style={{ width: '100%', padding: '8px', borderRadius: '4px', border: '1px solid #ddd', background: '#fafafa' }}
                      />
                      <input
                        type="number"
                        value={formData.scoreRange.max}
                        readOnly
                        style={{ width: '100%', padding: '8px', borderRadius: '4px', border: '1px solid #ddd', background: '#fafafa' }}
                      />
                    </div>
                    <div style={{ marginTop: 6, fontSize: 12, color: '#666' }}>
                      Update tier ranges in Tier Configuration.
                    </div>
                  </div>
                </div>

                <div style={{ marginTop: 12 }}>
                  <label>Content (PDF filename or content)</label>
                  <input
                    type="text"
                    value={formData.content}
                    onChange={(e) => setFormData({ ...formData, content: e.target.value })}
                    style={{ width: '100%', padding: '8px', borderRadius: '4px', border: '1px solid #ddd' }}
                    placeholder="Enter PDF filename or leave empty to upload file"
                  />
                </div>

                <div style={{ marginTop: 12 }}>
                  <label>Upload PDF File</label>
                  {editingModuleId && formData.content && !selectedFile && (
                    <div style={{ marginTop: 8, padding: '8px', background: '#e8f5e8', border: '1px solid #c8e6c9', borderRadius: '4px', fontSize: '14px' }}>
                      Current file: <strong>{formData.content}</strong>
                      <div style={{ fontSize: 12, color: '#666', marginTop: 4 }}>
                        Select a new file to replace it.
                      </div>
                    </div>
                  )}
                  <input
                    type="file"
                    accept=".pdf"
                    onChange={(e) => {
                      const file = e.target.files[0];
                      if (file && file.type === 'application/pdf') {
                        setSelectedFile(file);
                        setFormData({ ...formData, content: file.name });
                      } else if (file) {
                        alert('Please select a PDF file');
                        setSelectedFile(null);
                      }
                    }}
                    style={{ width: '100%', padding: '8px', borderRadius: '4px', border: '1px solid #ddd', marginTop: 8 }}
                  />
                  {selectedFile && (
                    <div style={{ marginTop: 6, fontSize: 12, color: '#666' }}>
                      New file selected: {selectedFile.name} ({(selectedFile.size / 1024 / 1024).toFixed(2)} MB)
                    </div>
                  )}
                </div>

                <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginTop: 12 }}>
                  <input
                    id="module-active"
                    type="checkbox"
                    checked={!!formData.isActive}
                    onChange={(e) => setFormData({ ...formData, isActive: e.target.checked })}
                  />
                  <label htmlFor="module-active" style={{ margin: 0 }}>Active</label>
                </div>

                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 10, marginTop: 16 }}>
                  <div style={{ fontSize: 12, color: '#666' }}>
                    {isDirty() ? 'Unsaved changes' : 'No pending changes'}
                  </div>
                  <div style={{ display: 'flex', gap: 10 }}>
                    <button type="button" onClick={() => closeModal()} className="admin-btn admin-btn--ghost">
                      Cancel
                    </button>
                    <button type="submit" disabled={uploading} className="admin-btn admin-btn--primary">
                      {uploading ? 'Saving...' : 'Save'}
                    </button>
                  </div>
                </div>
              </form>
            )}
          </div>
        </div>
      )}

      {error && (
        <div style={{
          marginBottom: "20px",
          padding: "10px",
          background: "#fff5f5",
          border: "1px solid #ffdada",
          color: "#7a1f1f",
          borderRadius: "6px"
        }}>
          {error}
        </div>
      )}

      <div className="exams-table-container">
        <table className="users-table">
          <thead>
            <tr>
              <th>Module</th>
              <th>Title</th>
              <th>Score Range</th>
              <th>Tier</th>
              <th>Content</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {displayedModules.map(module => (
              <tr key={module._id}>
                <td>{module.moduleId}</td>
                <td>{module.title}</td>
                <td>{getScoreRangeText(module.scoreRange.min, module.scoreRange.max)}</td>
                <td>{getTierLabel(module.tier) || module.tier || ""}</td>
                <td>
                  {(module.content && (module.content + '').trim() !== "") ? (
                    <span style={{ color: '#28a745', fontSize: '12px' }}>
                      ✅ {module.content}
                    </span>
                  ) : (
                    <span style={{ color: '#dc3545', fontSize: '12px' }}>
                      ❌ No content
                    </span>
                  )}
                </td>
                <td>
                  <div className="user-action-buttons">
                    <button
                      className="admin-btn admin-btn--success admin-btn--small"
                      onClick={() => openEditModal(module)}
                    >
                      Edit
                    </button>
                    <button
                      className="admin-btn admin-btn--danger admin-btn--small"
                      onClick={() => handleDelete(module._id)}
                    >
                      Delete
                    </button>
                  </div>
                </td>
              </tr>
            ))}
            {filteredModules.length === 0 && !isLoading && (
              <tr>
                <td colSpan={6} style={{ textAlign: "center", padding: 20, color: "#777" }}>
                  No modules found. Create your first module above.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {isLoading && (
        <div style={{ textAlign: "center", padding: "40px", color: "#666" }}>
          Loading modules...
        </div>
      )}
    </div>
  );
}

export default AdminModuleManagement;

