import React, { useState, useEffect, useCallback } from "react";
import { api } from "../../utils/api";

function ExamManagement() {
  const [exams, setExams] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState("");
  const [showForm, setShowForm] = useState(false);
  const [editingExam, setEditingExam] = useState(null);
  const [toast, setToast] = useState(null);
  const [tierConfig, setTierConfig] = useState([]);
  const [formData, setFormData] = useState({
    title: "",
    timeLimit: 30, 
    passingScore: 70,
    questions: [],
    isActive: true
  });
  const [editingQuestion, setEditingQuestion] = useState(null);
  const [editingQuestionData, setEditingQuestionData] = useState(null);

  const loadExams = useCallback(async () => {
    try {
      setIsLoading(true);
      setError("");
      const data = await api.get("/api/exams");
      setExams(data);
    } catch (error) {
      setError("Failed to load exams");
      console.error("Error loading exams:", error);
    } finally {
      setIsLoading(false);
    }
  }, []);

  const loadTierConfig = useCallback(async () => {
    try {
      const data = await api.get('/api/admin/tier-config');
      const tiers = Array.isArray(data?.tiers) ? data.tiers : [];
      setTierConfig(tiers);
    } catch (e) {
      console.error('Error loading tier config:', e);
      setTierConfig([]);
    }
  }, []);

  useEffect(() => {
    loadExams();
  }, [loadExams]);

  useEffect(() => {
    if (!showForm) return;
    loadTierConfig();
  }, [showForm, loadTierConfig]);

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (!formData.title) {
      setToast({ type: 'error', message: 'Title is required' });
      return;
    }

    let payload = { ...formData };
    payload.questions = (payload.questions || []).map((q) => ({
      id: String(q.id),
      question: String(q.question || '').trim(),
      options: Array.isArray(q.options) ? q.options.map(o => String(o || '').trim()) : ["", "", "", ""],
      correctAnswer: String(q.correctAnswer || '').trim(),
      explanation: q.explanation ? String(q.explanation) : "",
      moduleTitle: "",
      moduleType: String(q.moduleType || q.moduleTitle || '').trim(),
      tier: String(q.tier || '').trim(),
    }));

    const invalid = payload.questions.some(q => !q.question || !Array.isArray(q.options) || q.options.length !== 4 || !q.correctAnswer || !q.options.includes(q.correctAnswer));
    if (invalid) {
      setToast({ type: 'error', message: 'Please complete all questions (text, exactly 4 options, and a correct answer that matches an option) before saving.' });
      return;
    }

    try {
      if (editingExam) {
        await api.put(`/api/exams/${editingExam._id}`, payload);
      } else {
        await api.post("/api/exams", payload);
      }
      await loadExams();
      resetForm();
      setToast({ type: 'success', message: 'Exam saved successfully!' });
    } catch (error) {
      setToast({ type: 'error', message: 'Failed to save exam' });
      console.error("Error saving exam:", error);
    }
  };

  const handleEdit = (exam) => {
    setEditingExam(exam);

    setFormData({
      title: exam.title,
      timeLimit: exam.timeLimit,
      passingScore: exam.passingScore || 70,
      questions: exam.questions,
      isActive: exam.isActive
    });
    setEditingQuestion(null);
    setEditingQuestionData(null);
    setShowForm(true);
  };

  const resetForm = () => {
    setFormData({
      title: "",
      timeLimit: 30,
      passingScore: 70,
      questions: [],
      isActive: true
    });
    setEditingQuestion(null);
    setEditingQuestionData(null);
    setEditingExam(null);
    setShowForm(false);
  };

  const generateQuestionId = () => {
    try {
      if (window?.crypto?.randomUUID) return window.crypto.randomUUID();
    } catch (_) {}
    return `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
  };

  const addQuestionToModule = () => {
    const newQuestion = {
      id: generateQuestionId(),
      question: "",
      options: ["", "", "", ""],
      correctAnswer: "",
      explanation: "",
      moduleTitle: "",
      moduleType: "",
      tier: "",
    };

    setFormData(prev => ({ ...prev, questions: [...prev.questions, newQuestion] }));
  };

  const editQuestion = (question, questionIndex) => {
    setEditingQuestion(questionIndex);
    setEditingQuestionData({ ...question });
  };

  const updateEditingQuestion = (field, value) => {
    setEditingQuestionData(prev => ({
      ...prev,
      [field]: value
    }));
  };

  const updateEditingQuestionOption = (optionIndex, value) => {
    setEditingQuestionData(prev => ({
      ...prev,
      options: prev.options.map((option, index) => 
        index === optionIndex ? value : option
      )
    }));
  };

  const saveEditedQuestion = () => {
    if (!editingQuestionData.question.trim()) {
      setToast({ type: 'error', message: 'Question text is required' });
      return;
    }
    
    if (!editingQuestionData.correctAnswer.trim()) {
      setToast({ type: 'error', message: 'Please select a correct answer' });
      return;
    }

    setFormData(prev => ({
      ...prev,
      questions: prev.questions.map((q, index) => 
        index === editingQuestion ? editingQuestionData : q
      )
    }));
    
    setEditingQuestion(null);
    setEditingQuestionData(null);
  };

  const cancelEditQuestion = () => {
    setEditingQuestion(null);
    setEditingQuestionData(null);
  };

  const removeQuestion = (questionIndex) => {
    setFormData(prev => ({
      ...prev,
      questions: prev.questions.filter((_, index) => index !== questionIndex)
    }));
  };

  return (
    <div>
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

      <div className="page-header">
        <h2>Exam Management</h2>
        <button 
          onClick={() => setShowForm(!showForm)}
          className="admin-btn admin-btn--primary"
          style={{ padding: "10px 20px", fontSize: "14px" }}
        >
          {showForm ? "Cancel" : "Create New Exam"}
        </button>
      </div>

      {showForm && (
        <div style={{ 
          background: "#f9f9f9", 
          padding: "20px", 
          borderRadius: "8px", 
          marginBottom: "20px",
          border: "1px solid #ddd"
        }}>
          <h3>{editingExam ? "Edit Exam" : "Create New Exam"}</h3>
          <form onSubmit={handleSubmit}>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "15px", marginBottom: "15px" }}>
              <div>
                <label>Title:</label>
                <input
                  type="text"
                  value={formData.title}
                  onChange={(e) => setFormData({...formData, title: e.target.value})}
                  required
                  style={{ width: "100%", padding: "8px", borderRadius: "4px", border: "1px solid #ddd" }}
                />
              </div>
              <div>
                <label>Exam:</label>
                <div style={{ paddingTop: 6, color: '#666', fontSize: 12 }}>
                  Unified Exam (no pre/post selection)
                </div>
              </div>
            </div>

            <div style={{ marginBottom: "15px" }}>
              <label>Time Limit (minutes):</label>
              <input
                type="number"
                value={formData.timeLimit}
                onChange={(e) => setFormData({...formData, timeLimit: parseInt(e.target.value)})}
                min="1"
                max="180"
                required
                style={{ width: "100%", padding: "8px", borderRadius: "4px", border: "1px solid #ddd" }}
              />
            </div>

            <div style={{ marginBottom: "15px" }}>
              <label>Passing Score (%):</label>
              <input
                type="number"
                value={formData.passingScore}
                onChange={(e) => setFormData({...formData, passingScore: parseInt(e.target.value)})}
                min="0"
                max="100"
                required
                style={{ width: "100%", padding: "8px", borderRadius: "4px", border: "1px solid #ddd" }}
              />
              <small style={{ color: "#666", fontSize: "12px" }}>
                Minimum score (0-100%) required to pass this assessment
              </small>
            </div>

            <div style={{ marginBottom: "15px" }}>
              <label>
                <input
                  type="checkbox"
                  checked={formData.isActive}
                  onChange={(e) => setFormData({...formData, isActive: e.target.checked})}
                />
                Active
              </label>
            </div>

            {}
            <div style={{ marginBottom: "20px" }}>
              <h4>Questions</h4>

              <div style={{ marginBottom: 10, padding: '10px', backgroundColor: '#e3f2fd', borderRadius: '4px', border: '1px solid #2196F3' }}>
                <strong style={{ color: '#1976d2' }}>Unified Exam Editor:</strong>
                <p style={{ margin: '5px 0 0 0', color: '#666', fontSize: '12px' }}>
                  Each question can be tagged with Module Type and Tier.
                </p>
              </div>

              <button type="button" onClick={addQuestionToModule} style={{
                padding: "8px 16px",
                backgroundColor: "#2196F3",
                color: "white",
                border: "none",
                borderRadius: "4px",
                cursor: "pointer",
                marginBottom: "15px"
              }}>
                Add Question
              </button>
            </div>

            {}
            <div style={{ marginBottom: "15px" }}>
              <h4>Current Exam Questions ({formData.questions.length})</h4>
              
              {formData.questions.length === 0 ? (
                <p style={{ color: "#666", fontStyle: "italic" }}>
                  No questions added yet. Click 'Add Question' above to start adding questions.
                </p>
              ) : (
                <div style={{ 
                  border: "1px solid #ddd", 
                  padding: "15px", 
                  marginBottom: "15px", 
                  borderRadius: "4px",
                  backgroundColor: "#f9f9f9"
                }}>
                  <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "10px" }}>
                    <strong>All Questions: {formData.questions.length} question(s)</strong>
                    <button type="button" onClick={() => setFormData(prev => ({ ...prev, questions: [] }))} style={{ 
                      padding: "6px 12px", 
                      backgroundColor: "#f44336", 
                      color: "white", 
                      border: "none", 
                      borderRadius: "4px", 
                      cursor: "pointer",
                      fontSize: "12px"
                    }}>
                      Remove All
                    </button>
                  </div>

                  {formData.questions.map((question, qIndex) => (
                    <div key={question.id} style={{ 
                      border: "1px solid #e0e0e0", 
                      padding: "10px", 
                      marginBottom: "8px", 
                      borderRadius: "4px",
                      backgroundColor: "white"
                    }}>
                      {editingQuestion === qIndex ? (
                        <div>
                          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "10px" }}>
                            <h6 style={{ margin: 0, color: "#2196F3" }}>Editing Question {qIndex + 1}</h6>
                            <div>
                              <button type="button" onClick={saveEditedQuestion} style={{ 
                                padding: "4px 8px", 
                                backgroundColor: "#4CAF50", 
                                color: "white", 
                                border: "none", 
                                borderRadius: "4px", 
                                cursor: "pointer",
                                marginRight: "5px",
                                fontSize: "12px"
                              }}>
                                Save
                              </button>
                              <button type="button" onClick={cancelEditQuestion} style={{ 
                                padding: "4px 8px", 
                                backgroundColor: "#666", 
                                color: "white", 
                                border: "none", 
                                borderRadius: "4px", 
                                cursor: "pointer",
                                fontSize: "12px"
                              }}>
                                Cancel
                              </button>
                            </div>
                          </div>

                          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10, marginBottom: 10 }}>
                            <div>
                              <label>Module Type</label>
                              <input
                                type="text"
                                value={editingQuestionData.moduleType || ''}
                                onChange={(e) => updateEditingQuestion('moduleType', e.target.value)}
                                style={{ width: '100%', padding: '6px', borderRadius: '4px', border: '1px solid #ddd' }}
                                placeholder="e.g., Strings"
                              />
                            </div>
                            <div>
                              <label>Tier</label>
                              <select
                                value={editingQuestionData.tier || ''}
                                onChange={(e) => updateEditingQuestion('tier', e.target.value)}
                                style={{ width: '100%', padding: '6px', borderRadius: '4px', border: '1px solid #ddd' }}
                              >
                                <option value="">(Any)</option>
                                {tierConfig.map(t => (
                                  <option key={t.key} value={t.key}>{t.label}</option>
                                ))}
                              </select>
                            </div>
                          </div>
                          
                          <div style={{ marginBottom: "10px" }}>
                            <label>Question Text:</label>
                            <textarea
                              value={editingQuestionData.question}
                              onChange={(e) => updateEditingQuestion('question', e.target.value)}
                              style={{ width: "100%", padding: "8px", borderRadius: "4px", border: "1px solid #ddd", minHeight: "60px" }}
                              placeholder="Enter the question..."
                            />
                          </div>
                          
                          <div style={{ marginBottom: "10px" }}>
                            <label>Options:</label>
                            {editingQuestionData.options.map((option, oIndex) => (
                              <div key={oIndex} style={{ display: "flex", alignItems: "center", marginBottom: "5px" }}>
                                <input
                                  type="radio"
                                  name={`edit-correct-${qIndex}`}
                                  checked={editingQuestionData.correctAnswer === option}
                                  onChange={() => updateEditingQuestion('correctAnswer', option)}
                                  style={{ marginRight: "8px" }}
                                />
                                <input
                                  type="text"
                                  value={option}
                                  onChange={(e) => updateEditingQuestionOption(oIndex, e.target.value)}
                                  style={{ flex: 1, padding: "6px", borderRadius: "4px", border: "1px solid #ddd" }}
                                  placeholder={`Option ${oIndex + 1}`}
                                />
                              </div>
                            ))}
                          </div>
                          
                          <div>
                            <label>Explanation (optional):</label>
                            <textarea
                              value={editingQuestionData.explanation}
                              onChange={(e) => updateEditingQuestion('explanation', e.target.value)}
                              style={{ width: "100%", padding: "8px", borderRadius: "4px", border: "1px solid #ddd", minHeight: "40px" }}
                              placeholder="Explain why this is the correct answer..."
                            />
                          </div>
                        </div>
                      ) : (
                        <div>
                          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "8px" }}>
                            <strong>Question {qIndex + 1}:</strong>
                            <div>
                              <button type="button" onClick={() => editQuestion(question, qIndex)} style={{ 
                                padding: "4px 8px", 
                                backgroundColor: "#2196F3", 
                                color: "white", 
                                border: "none", 
                                borderRadius: "4px", 
                                cursor: "pointer",
                                marginRight: "5px",
                                fontSize: "12px"
                              }}>
                                Edit
                              </button>
                              <button type="button" onClick={() => removeQuestion(qIndex)} style={{ 
                                padding: "4px 8px", 
                                backgroundColor: "#f44336", 
                                color: "white", 
                                border: "none", 
                                borderRadius: "4px", 
                                cursor: "pointer",
                                fontSize: "12px"
                              }}>
                                Remove
                              </button>
                            </div>
                          </div>
                          <div style={{ fontSize: 12, color: '#666', marginBottom: 8 }}>
                            <strong>Tags:</strong> {question.moduleType || '(no module type)'}
                            {question.tier ? ` · ${question.tier}` : ''}
                          </div>
                          <div style={{ marginBottom: "8px" }}>
                            <strong>Q:</strong> {question.question}
                          </div>
                          <div style={{ marginBottom: "8px" }}>
                            <strong>Options:</strong>
                            <ul style={{ margin: "5px 0 0 20px", fontSize: "12px" }}>
                              {question.options.map((option, oIndex) => (
                                <li key={oIndex} style={{ 
                                  color: question.correctAnswer === option ? "#4CAF50" : "#666",
                                  fontWeight: question.correctAnswer === option ? "bold" : "normal"
                                }}>
                                  {option} {question.correctAnswer === option ? "✓" : ""}
                                </li>
                              ))}
                            </ul>
                          </div>
                          {question.explanation && (
                            <div style={{ fontSize: "12px", color: "#666", fontStyle: "italic" }}>
                              <strong>Explanation:</strong> {question.explanation}
                            </div>
                          )}
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>

            <div style={{ display: "flex", gap: "10px" }}>
              <button type="submit" className="admin-btn admin-btn--primary">
                {editingExam ? "Update" : "Create"}
              </button>
              <button type="button" onClick={resetForm} className="admin-btn admin-btn--ghost">
                Cancel
              </button>
            </div>
          </form>
        </div>
      )}

      {error && (
        <div style={{
          marginBottom: "20px",
          padding: "10px",
          background: "#fff5f5",
          border: "1px solid #ffdada",
          color: "#7a1f1f",
          borderRadius: "4px"
        }}>
          {error}
        </div>
      )}


      {isLoading ? (
        <div className="loading">Loading exams...</div>
      ) : (
        <div>
          <div className="table-container">
            <table className="exam-table">
              <thead>
                <tr>
                  <th>Title</th>
                  <th>Time Limit</th>
                  <th>Questions</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {exams.map((exam) => (
                  <tr key={exam._id}>
                    <td>{exam.title}</td>
                    <td>{exam.timeLimit} min</td>
                    <td>{exam.questions.length}</td>
                    <td style={{ verticalAlign: "middle" }}>
                      <span
                        className={`status-badge ${
                          exam.isActive ? "active" : "inactive"
                        }`}
                      >
                        {exam.isActive ? "Active" : "Inactive"}
                      </span>
                    </td>
                    <td style={{ verticalAlign: "middle" }}>
                      <div className="exam-action-buttons" style={{ display: "flex", gap: "8px", alignItems: "center", justifyContent: "center" }}>
                        <button 
                          onClick={() => handleEdit(exam)}
                          className="admin-btn admin-btn--success admin-btn--small"
                        >
                          Edit
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}

export default ExamManagement;

