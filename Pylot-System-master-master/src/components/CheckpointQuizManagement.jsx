import React, { useCallback, useEffect, useState } from "react";
import { api } from "../utils/api";
import "./CheckpointQuizManagement.css";

function CheckpointQuizManagement() {
  const [quizzes, setQuizzes] = useState([]);
  const [modules, setModules] = useState([]);
  const [error, setError] = useState("");
  const [showForm, setShowForm] = useState(false);
  const [editingQuiz, setEditingQuiz] = useState(null);
  const [formData, setFormData] = useState({
    checkpointNumber: 1,
    title: "",
    description: "",
    requiredModulesCount: 4,
    requiredModuleIds: [],
    questions: [],
    passingScore: 70,
    timeLimit: 15,
    moduleId: "" 
  });
  const [toast, setToast] = useState(null);
  const [checkpointNumberWarning, setCheckpointNumberWarning] = useState(null);
  const [isCheckingCheckpointNumber, setIsCheckingCheckpointNumber] = useState(false);
  const [debounceTimer, setDebounceTimer] = useState(null);

  const loadQuizzes = useCallback(async () => {
    try {
      setError("");
      const data = await api.get("/api/checkpoint-quizzes");
      const quizList = Array.isArray(data)
        ? data
        : Array.isArray(data?.quizzes)
          ? data.quizzes
          : [];
      setQuizzes(quizList);
    } catch (error) {
      setError("Failed to load checkpoint quizzes");
      setQuizzes([]);
      console.error("Error loading checkpoint quizzes:", error);
    }
  }, []);

  const loadModules = useCallback(async () => {
    try {
      const data = await api.get("/api/modules");
      const moduleList = Array.isArray(data)
        ? data
        : Array.isArray(data?.modules)
          ? data.modules
          : [];
      setModules(moduleList);
    } catch (error) {
      console.error("Error loading modules:", error);
      setModules([]);
    }
  }, []);

  const checkCheckpointNumberAvailability = useCallback(async (checkpointNumber) => {
    if (!checkpointNumber || checkpointNumber === editingQuiz?.checkpointNumber) {
      setCheckpointNumberWarning(null);
      return;
    }

    setIsCheckingCheckpointNumber(true);
    try {
      const response = await api.get(`/api/checkpoint-quizzes/check/${checkpointNumber}`);
      if (!response.available) {
        setCheckpointNumberWarning({
          type: 'warning',
          message: response.message,
          existingQuiz: response.existingQuiz
        });
      } else {
        setCheckpointNumberWarning(null);
      }
    } catch (error) {
      console.error("Error checking checkpoint number:", error);
      setCheckpointNumberWarning({
        type: 'error',
        message: 'Failed to check checkpoint number availability'
      });
    } finally {
      setIsCheckingCheckpointNumber(false);
    }
  }, [editingQuiz]);

  useEffect(() => {
    loadQuizzes();
    loadModules();
  }, [loadQuizzes, loadModules]);

  
  useEffect(() => {
    return () => {
      if (debounceTimer) {
        clearTimeout(debounceTimer);
      }
    };
  }, [debounceTimer]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!formData.title || !formData.requiredModuleIds.length || !formData.questions.length) {
      setToast({ type: 'error', message: 'Please fill in all required fields' });
      return;
    }

    
    if (!editingQuiz && checkpointNumberWarning?.type === 'warning') {
      setToast({ type: 'error', message: 'Cannot create quiz: Checkpoint number is already taken' });
      return;
    }

    try {
      if (editingQuiz) {
        await api.put(`/api/checkpoint-quizzes/${formData.checkpointNumber}`, formData);
        setToast({ type: 'success', message: 'Checkpoint quiz updated successfully!' });
      } else {
        await api.post("/api/checkpoint-quizzes", formData);
        setToast({ type: 'success', message: 'Checkpoint quiz created successfully!' });
      }
      
      loadQuizzes();
      resetForm();
      
      
      setTimeout(() => setToast(null), 3000);
    } catch (error) {
      console.error("Error saving checkpoint quiz:", error);
      setToast({ type: 'error', message: 'Failed to save checkpoint quiz. Please check your input and try again.' });
      
      setTimeout(() => setToast(null), 5000);
    }
  };

  const handleEdit = (quiz) => {
    setEditingQuiz(quiz);
    setFormData({
      checkpointNumber: quiz.checkpointNumber,
      title: quiz.title,
      description: quiz.description,
      requiredModulesCount: quiz.requiredModulesCount,
      requiredModuleIds: quiz.requiredModuleIds,
      questions: quiz.questions,
      passingScore: quiz.passingScore,
      timeLimit: quiz.timeLimit,
      moduleId: quiz.moduleId || `CQ${quiz.checkpointNumber}`
    });
    setShowForm(true);
  };

  const handleDelete = async (checkpointNumber) => {
    if (!window.confirm("Are you sure you want to delete this checkpoint quiz?")) return;
    try {
      await api.del(`/api/checkpoint-quizzes/${checkpointNumber}`);
      loadQuizzes();
      setToast({ type: 'success', message: 'Checkpoint quiz deleted successfully!' });
      setTimeout(() => setToast(null), 3000);
    } catch (error) {
      setToast({ type: 'error', message: 'Failed to delete checkpoint quiz' });
      setTimeout(() => setToast(null), 5000);
      console.error("Error deleting checkpoint quiz:", error);
    }
  };

  const resetForm = () => {
    setFormData({
      checkpointNumber: 1,
      title: "",
      description: "",
      requiredModulesCount: 4,
      requiredModuleIds: [],
      questions: [],
      passingScore: 70,
      timeLimit: 15,
      moduleId: ""
    });
    setEditingQuiz(null);
    setShowForm(false);
    setCheckpointNumberWarning(null);
  };

  const addQuestion = () => {
    const newQuestion = {
      id: Date.now().toString(),
      question: "",
      options: ["", "", "", ""],
      correctAnswer: "",
      explanation: ""
    };
    setFormData(prev => ({
      ...prev,
      questions: [...prev.questions, newQuestion]
    }));
  };

  const updateQuestion = (index, field, value) => {
    const updatedQuestions = [...formData.questions];
    updatedQuestions[index] = {
      ...updatedQuestions[index],
      [field]: value
    };
    setFormData(prev => ({
      ...prev,
      questions: updatedQuestions
    }));
  };

  const removeQuestion = (index) => {
    const updatedQuestions = formData.questions.filter((_, i) => i !== index);
    setFormData(prev => ({
      ...prev,
      questions: updatedQuestions
    }));
  };

  const updateOption = (questionIndex, optionIndex, value) => {
    const updatedQuestions = [...formData.questions];
    updatedQuestions[questionIndex].options[optionIndex] = value;
    setFormData(prev => ({
      ...prev,
      questions: updatedQuestions
    }));
  };

  const toggleModuleSelection = (moduleId) => {
    const currentIds = formData.requiredModuleIds;
    
    if (currentIds.includes(moduleId)) {
      setFormData(prev => ({
        ...prev,
        requiredModuleIds: currentIds.filter(id => id !== moduleId)
      }));
    } else {
      setFormData(prev => ({
        ...prev,
        requiredModuleIds: [...currentIds, moduleId]
      }));
    }
  };

  return (
    <div className="checkpoint-quiz-management">
      {toast && (
        <div className={`toast-notification ${toast.type}`}>
          {toast.message}
        </div>
      )}

      <div className="page-header">
        <h2>Checkpoint Quiz Management</h2>
        <button onClick={() => setShowForm(true)} className="admin-btn admin-btn--primary">
          Add New Checkpoint Quiz
        </button>
      </div>

      {showForm && (
        <div className="form-container">
          <h3>{editingQuiz ? 'Edit Checkpoint Quiz' : 'Add New Checkpoint Quiz'}</h3>
          <form onSubmit={handleSubmit}>
            {}
            <div className="form-section">
              <h4>Basic Information</h4>
              <div className="form-grid-2">
                <div className="form-field">
                  <label>Checkpoint Number:</label>
                  <div className="input-wrapper">
                    <input
                      type="number"
                      value={formData.checkpointNumber}
                      onChange={(e) => {
                        const checkpointNumber = parseInt(e.target.value) || 1;
                        setFormData({
                          ...formData, 
                          checkpointNumber,
                          moduleId: `CQ${checkpointNumber}`
                        });
                        
                        if (debounceTimer) {
                          clearTimeout(debounceTimer);
                        }
                        
                        const newTimer = setTimeout(() => {
                          checkCheckpointNumberAvailability(checkpointNumber);
                        }, 500);
                        
                        setDebounceTimer(newTimer);
                      }}
                      min="1"
                      style={{ 
                        border: checkpointNumberWarning?.type === 'warning' ? "2px solid #ff9800" : "1px solid #ccc"
                      }}
                    />
                    {isCheckingCheckpointNumber && (
                      <div className="input-checking-indicator">
                        Checking...
                      </div>
                    )}
                  </div>
                  {checkpointNumberWarning && (
                    <div className={`checkpoint-warning ${checkpointNumberWarning.type}`}>
                      <strong>⚠️ {checkpointNumberWarning.message}</strong>
                      {checkpointNumberWarning.existingQuiz && (
                        <div className="checkpoint-warning-details">
                          Existing quiz: "{checkpointNumberWarning.existingQuiz.title}" 
                          (Module ID: {checkpointNumberWarning.existingQuiz.moduleId})
                        </div>
                      )}
                    </div>
                  )}
                </div>
                <div className="form-field">
                  <label>Module ID:</label>
                  <input
                    type="text"
                    value={formData.moduleId}
                    onChange={(e) => setFormData({...formData, moduleId: e.target.value})}
                    placeholder="e.g., CQ1"
                  />
                </div>
              </div>
              
              <div className="form-field">
                <label>Title:</label>
                <input
                  type="text"
                  value={formData.title}
                  onChange={(e) => setFormData({...formData, title: e.target.value})}
                  placeholder="e.g., Checkpoint Quiz 1"
                />
              </div>

              <div className="form-field">
                <label>Description:</label>
                <textarea
                  value={formData.description}
                  onChange={(e) => setFormData({...formData, description: e.target.value})}
                  placeholder="Describe what this checkpoint quiz covers..."
                />
              </div>

              <div className="form-field">
                <label>Required Modules Count:</label>
                <input
                  type="number"
                  value={formData.requiredModulesCount}
                  onChange={(e) => setFormData({...formData, requiredModulesCount: parseInt(e.target.value) || 4})}
                  min="1"
                />
              </div>
            </div>

            {}
            <div className="form-section">
              <h4>Quiz Settings</h4>
              <div className="form-grid-2">
                <div className="form-field">
                  <label>Passing Score (%):</label>
                  <input
                    type="number"
                    min="0"
                    max="100"
                    value={formData.passingScore}
                    onChange={(e) => setFormData({...formData, passingScore: parseInt(e.target.value)})}
                  />
                </div>
                <div className="form-field">
                  <label>Time Limit (minutes):</label>
                  <input
                    type="number"
                    min="1"
                    max="60"
                    value={formData.timeLimit}
                    onChange={(e) => setFormData({...formData, timeLimit: parseInt(e.target.value)})}
                  />
                </div>
              </div>
            </div>

            {}
            <div className="form-section">
              <h4>Required Modules</h4>
              <p className="form-section-description">
                Select which modules users must complete before they can take this checkpoint quiz.
                <br />
                <strong>Note:</strong> After passing this quiz, all modules from Checkpoint Group {formData.checkpointNumber + 1} will be automatically unlocked.
              </p>
              <div className="modules-grid">
                {(Array.isArray(modules) ? modules : []).map(module => (
                  <label key={module._id} className="module-checkbox-label">
                    <input
                      type="checkbox"
                      checked={formData.requiredModuleIds.includes(module.moduleId)}
                      onChange={() => toggleModuleSelection(module.moduleId)}
                    />
                    {module.moduleId} - {module.title}
                  </label>
                ))}
              </div>
            </div>

            {}
            <div className="form-section">
              <div className="questions-header">
                <h4>Quiz Questions ({formData.questions.length})</h4>
                <button type="button" onClick={addQuestion} className="admin-btn admin-btn--outline">
                  Add Question
                </button>
              </div>
              
              {formData.questions.map((question, qIndex) => (
                <div key={question.id} className="question-card">
                  <div className="question-card-header">
                    <h5>Question {qIndex + 1}</h5>
                    <button type="button" onClick={() => removeQuestion(qIndex)} className="admin-btn admin-btn--danger admin-btn--small">
                      Remove
                    </button>
                  </div>
                  
                  <div className="form-field">
                    <label>Question Text:</label>
                    <textarea
                      value={question.question}
                      onChange={(e) => updateQuestion(qIndex, 'question', e.target.value)}
                      placeholder="Enter the question..."
                    />
                  </div>
                  
                  <div className="form-field">
                    <label>Options:</label>
                    <div className="question-options">
                      {question.options.map((option, oIndex) => (
                        <div key={oIndex} className="option-row">
                          <input
                            type="radio"
                            name={`correct-${qIndex}`}
                            checked={question.correctAnswer === option}
                            onChange={() => updateQuestion(qIndex, 'correctAnswer', option)}
                          />
                          <input
                            type="text"
                            value={option}
                            onChange={(e) => updateOption(qIndex, oIndex, e.target.value)}
                            placeholder={`Option ${oIndex + 1}`}
                          />
                        </div>
                      ))}
                    </div>
                  </div>
                  
                  <div className="form-field">
                    <label>Explanation (optional):</label>
                    <textarea
                      value={question.explanation}
                      onChange={(e) => updateQuestion(qIndex, 'explanation', e.target.value)}
                      placeholder="Explain why this is the correct answer..."
                      style={{ minHeight: "60px" }}
                    />
                  </div>
                </div>
              ))}
            </div>

            {}
            <div className="button-group">
              <button 
                type="submit" 
                className="admin-btn admin-btn--primary"
                disabled={!editingQuiz && checkpointNumberWarning?.type === 'warning'}
              >
                {editingQuiz ? 'Update Quiz' : 'Create Quiz'}
              </button>
              <button type="button" onClick={resetForm} className="admin-btn admin-btn--ghost">
                Cancel
              </button>
            </div>
          </form>
        </div>
      )}

      {error && (
        <div className="error-message">
          {error}
        </div>
      )}

      <div className="table-container">
        <table className="quiz-table">
          <thead>
            <tr>
              <th>Checkpoint #</th>
              <th>Module ID</th>
              <th>Title</th>
              <th>Required Modules</th>
              <th>Questions</th>
              <th>Passing Score</th>
              <th>Time Limit</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {quizzes.map(quiz => (
              <tr key={quiz._id}>
                <td>{quiz.checkpointNumber}</td>
                <td>{quiz.moduleId}</td>
                <td>{quiz.title}</td>
                <td>
                  <div className="table-modules-info">
                    <div className="table-modules-count">{quiz.requiredModulesCount} modules required</div>
                    <div className="table-modules-list">{quiz.requiredModuleIds.join(", ")}</div>
                  </div>
                </td>
                <td>{quiz.questions.length}</td>
                <td>{quiz.passingScore}%</td>
                <td>{quiz.timeLimit} min</td>
                <td>
                  <div className="table-actions">
                    <button
                      className="admin-btn admin-btn--success admin-btn--small"
                      onClick={() => handleEdit(quiz)}
                    >
                      Edit
                    </button>
                    <button
                      className="admin-btn admin-btn--danger admin-btn--small"
                      onClick={() => handleDelete(quiz.checkpointNumber)}
                    >
                      Delete
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

export default CheckpointQuizManagement;

