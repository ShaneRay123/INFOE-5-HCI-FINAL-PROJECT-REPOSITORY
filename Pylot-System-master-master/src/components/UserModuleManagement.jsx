import React, { useCallback, useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { api, API_BASE } from "../utils/api";
import PDFViewer from "./PDFViewer";
import "./UserModuleManagement.css";
import backIcon from "../assets/back.png";
import logo from "../assets/PYlot white.png";

function UserModuleManagement() {
  const navigate = useNavigate();
  const [modules, setModules] = useState([]);
  const [checkpointQuizzes, setCheckpointQuizzes] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState("");
  const [userProgress, setUserProgress] = useState(null);
  const [viewingPdf, setViewingPdf] = useState(null);
  const [readyForPost, setReadyForPost] = useState(false);
  const [toast, setToast] = useState(null);
  const [searchQuery, setSearchQuery] = useState("");

  const username = localStorage.getItem("username");

  const refreshAccessibleModules = useCallback(async () => {
    try {
      const progressResponse = await api.get(`/api/user-progress/${username}`);
      if (!progressResponse.hasCompletedPreAssessment) return;
      await api.post(`/api/user-progress/${username}/refresh-modules`, {});
    } catch (error) {
      console.error('Error refreshing accessible modules:', error);
    }
  }, [username]);

  const loadModules = useCallback(async () => {
    try {
      setIsLoading(true);
      setError("");

      const progressResponse = await api.get(`/api/user-progress/${username}`);
      setUserProgress(progressResponse);

      if (!progressResponse.hasCompletedPreAssessment) {
        setError("Please complete the assessment first to access modules.");
        return;
      }

      
      try {
        const checkpointQuizzesResponse = await api.get('/api/checkpoint-quizzes');
        
        if (Array.isArray(checkpointQuizzesResponse)) {
          setCheckpointQuizzes(checkpointQuizzesResponse);
        } else {
          setCheckpointQuizzes([]);
        }
      } catch (error) {
        console.error('Error loading checkpoint quizzes:', error);
        setCheckpointQuizzes([]);
      }

      const modulesResponse = await api.get(
        `/api/modules/user/${username}`
      );
      
      
      let availableFiles = [];
      try {
        const filesResponse = await fetch(`${API_BASE}/api/files`, {
          credentials: 'include'
        });
        if (filesResponse.ok) {
          availableFiles = await filesResponse.json();
          
        }
      } catch (error) {
        console.error('Error fetching files:', error);
      }

      
      const combinedModules = modulesResponse.map((module) => {
        
        let content = module.content || module.filename || module.fileName || "";
        
        
        if (!content || content.trim() === "") {
          
          let matchingFile = availableFiles.find(file => {
            const filename = file.filename.toLowerCase();
            const moduleTitle = module.title.toLowerCase();
            const moduleId = module.moduleId.toLowerCase();
            
            
            if (filename.includes(moduleTitle)) return true;
            
            
            if (moduleId.length === 1 && filename.includes(`python ${moduleTitle}`)) return true;
            
            return false;
          });
          
          
          
          if (matchingFile) {
            content = matchingFile.filename;
          }
        }
        
        const hasContent = !!(content && content.trim() !== "" && content !== "undefined" && content !== "null");
        
        
        
        
        
        
        
        
        
        
        
        return {
          ...module,
          fileName: content,
          hasContent: hasContent
        };
      });

      setModules(combinedModules);

      const assignedIds = combinedModules.map((m) => m._id);
      const completedIds = progressResponse.completedModules || [];
      
      setReadyForPost(
        assignedIds.length > 0 && assignedIds.every((id) => completedIds.includes(id))
      );
    } catch {
      setError("Failed to load modules");
    } finally {
      setIsLoading(false);
    }
  }, [username]);

  useEffect(() => {
    loadModules();
  }, [loadModules]);

  const handleDownload = (module) => {
    
    
    
    
    
    
    
    
    
    if (!module.hasContent) {
      setToast({ type: "info", message: "No content available for this module." });
      return;
    }
    window.open(`${API_BASE}/api/file/${encodeURIComponent(module.fileName)}?download=true`, "_blank");
  };

  const handleView = async (module) => {
    if (!module.hasContent) {
      setToast({ type: "info", message: "No content available for this module." });
      return;
    }
    setViewingPdf(module);
    
    
    const isAlreadyCompleted = userProgress?.completedModules?.includes(module._id);
    if (!isAlreadyCompleted) {
      setUserProgress(prev => ({
        ...prev,
        completedModules: [...(prev?.completedModules || []), module._id]
      }));
    }
    
    try {
      
      await api.post(`/api/user-progress/${username}/module-complete`, { moduleId: module._id });
      
      
      const allModuleIds = modules.map(m => m._id);
      const updatedCompleted = isAlreadyCompleted 
        ? userProgress.completedModules 
        : [...(userProgress?.completedModules || []), module._id];
      
      const allCompleted = allModuleIds.every(id => updatedCompleted.includes(id));
      
      if (allCompleted && modules.length > 0) {
        setReadyForPost(true);
        setToast({
          type: "success",
          message: "All assigned modules completed. You can now take the Post-Assessment."
        });
      }
    } catch (error) {
      console.error('Error marking module as completed:', error);
      
      if (!isAlreadyCompleted) {
        setUserProgress(prev => ({
          ...prev,
          completedModules: prev?.completedModules?.filter(id => id !== module._id) || []
        }));
      }
    }
  };

  const closePdfViewer = () => setViewingPdf(null);
  const getPdfUrl = (module) => `${API_BASE}/api/file/${encodeURIComponent(module.fileName)}`;

  const getStatForModuleType = useCallback((title) => {
    if (!userProgress) return null;

    const normalizeKey = (v) => String(v || '').trim().toLowerCase();
    const moduleScores = userProgress.moduleScores || {};
    const legacyScores = userProgress.moduleTypeScores || {};

    let label = Object.keys(moduleScores).find((k) => normalizeKey(k) === normalizeKey(title));
    let entry = label ? moduleScores[label] : null;

    if (!entry) {

      label = Object.keys(legacyScores).find((k) => normalizeKey(k) === normalizeKey(title));
      const stats = label ? legacyScores[label] : null;
      if (!stats) return null;
      const pctLegacy = Number(stats.percentage ?? 0);
      const bracketLegacy = pctLegacy >= 90 ? 'Advanced' : pctLegacy >= 70 ? 'Intermediate' : pctLegacy >= 50 ? 'Mid-intermediate' : 'Beginner';
      return {
        correct: stats.correct,
        total: stats.total,
        pct: pctLegacy,
        percentage: pctLegacy,
        difficulty: bracketLegacy,
        bracket: bracketLegacy,
      };
    }

    const pct = Number(entry.pct ?? entry.percentage ?? 0) || 0;
    const difficulty = entry.difficulty || (pct >= 90 ? 'Advanced' : pct >= 70 ? 'Intermediate' : pct >= 50 ? 'Mid-intermediate' : 'Beginner');

    return {
      correct: entry.correct,
      total: entry.total,
      pct,
      percentage: pct,
      difficulty,
      bracket: difficulty,
    };
  }, [userProgress]);

  

  const checkQuizEligibility = (quiz) => {
    if (!userProgress) return { canTake: false, reason: "Loading...", completed: false };

    const completedQuiz = userProgress.completedCheckpointQuizzes?.find(
      q => q.checkpointNumber === quiz.checkpointNumber
    );
    const alreadyCompleted = !!(completedQuiz && completedQuiz.passed);

    if (!userProgress.hasCompletedPreAssessment) {
      return { canTake: false, reason: "Complete Pre-Assessment first", completed: !!completedQuiz };
    }

    if (!modules || modules.length === 0) {
      return { canTake: false, reason: "Loading modules...", completed: !!completedQuiz };
    }

    const requiredIds = Array.isArray(quiz.requiredModuleIds) ? quiz.requiredModuleIds : [];
    const requiredModules = requiredIds
      .map(id => modules.find(m => m.moduleId === id))
      .filter(Boolean);
    const completedModuleIds = userProgress.completedModules || [];

    const allRequiredCompleted = requiredModules.length === 0
      ? true
      : requiredModules.every(m => completedModuleIds.includes(m._id));

    if (!allRequiredCompleted) {
      return {
        canTake: false,
        reason: "Complete all required modules first",
        completed: !!completedQuiz,
      };
    }

    if (alreadyCompleted) {
      return { canTake: false, reason: "Already completed", completed: true };
    }

    return { canTake: true, reason: "Available", completed: false };
  };

  const startQuiz = (quiz) => {
    navigate(`/user/checkpoint-quiz/${quiz.checkpointNumber}`, { 
      state: { quiz, userProgress } 
    });
  };


  if (error) {
    return (
      <div className="page-container">
        <header className="page-header">
          <img src={backIcon} alt="Back" className="back-img" onClick={() => navigate("/user")} />
          <h2>Course Modules</h2>
          <img src={logo} alt="PYlot Logo" className="logo-img" />
        </header>
        <div style={{ textAlign: "center", padding: "40px", color: "#666" }}>
          <h3>{error}</h3>
          <button className="apply-btn" onClick={() => navigate("/user/pre-assessment")} style={{ marginTop: "20px" }}>
            Take Pre-Assessment
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="page-container user-modules-page">
      {toast && (
        <div
          role="status"
          onAnimationEnd={() => setToast(null)}
          style={{
            position: "fixed",
            top: 16,
            left: "50%",
            transform: "translateX(-50%)",
            zIndex: 1000,
            padding: "10px 16px",
            borderRadius: 8,
            color: toast.type === "success" ? "#1b5e20" : "#333",
            background: toast.type === "success" ? "#e8f5e9" : "#f5f5f5",
            border: "1px solid",
            borderColor: toast.type === "success" ? "#c8e6c9" : "#e0e0e0",
          }}
        >
          {toast.message}
        </div>
      )}

      <header className="page-header">
        <img src={backIcon} alt="Back" className="back-img" onClick={() => navigate("/user")} />
        <h2>Course Modules</h2>
        <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
          <button 
            onClick={async () => {
              await refreshAccessibleModules();
              await loadModules();
              setToast({ type: 'success', message: 'Modules refreshed!' });
            }}
            style={{
              padding: '6px 12px',
              backgroundColor: '#2196F3',
              color: 'white',
              border: 'none',
              borderRadius: '4px',
              cursor: 'pointer',
              fontSize: '14px'
            }}
            title="Refresh available modules"
          >
            Refresh
          </button>
          <img src={logo} alt="PYlot Logo" className="logo-img" />
        </div>
      </header>

      {userProgress && (
        <div style={{ background: "#e8f5e8", padding: "15px", borderRadius: "8px", marginBottom: "10px", textAlign: "center" }}>
          <h3 style={{ margin: "0 0 10px 0", color: "#2d7a2d" }}>Personalized Learning Path</h3>
          <p style={{ margin: "0 0 10px 0", color: "#666" }}>
            Based on your assessment score of <strong>{userProgress.preAssessmentScore}%</strong>, you have access to modules designed for your skill level.
          </p>
          {modules.length > 0 && <p style={{ margin: "0", color: "#2d7a2d", fontSize: "14px" }}>Progress: {userProgress.completedModules?.filter(id => modules.find(m => m._id === id)).length || 0} of {modules.length} modules completed</p>}
          {readyForPost && (
            <div style={{ background: "#fff8e1", border: "1px solid #ffe0b2", color: "#8d6e63", padding: "12px 16px", borderRadius: 8, marginTop: 10, display: "flex", alignItems: "center", justifyContent: "space-between" }}>
              <span>All modules completed. You may proceed to the assessment.</span>
              <button className="apply-btn" onClick={() => navigate("/user/exam")}>Take Assessment</button>
            </div>
          )}
        </div>
      )}



      <div className="search-bar" style={{ display: 'flex', gap: 12, alignItems: 'center', flexWrap: 'wrap' }}>
        <input 
          type="text" 
          placeholder="Search modules..." 
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          aria-label="Search modules"
        />
      </div>

      <div className="table-container">
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Your Score</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {(() => {
              
              const allItems = [];
              const processedModules = new Set();
              const processedQuizzes = new Set();
              let currentIndex = 0;
              
              
              const sortedCheckpointQuizzes = [...checkpointQuizzes].sort((a, b) => 
                (a.checkpointNumber || 0) - (b.checkpointNumber || 0)
              );
              
              
              const sortModules = (arr) => arr;

              
              sortedCheckpointQuizzes.forEach(quiz => {
                if (quiz.requiredModuleIds && quiz.requiredModuleIds.length > 0) {
                  
                  const requiredModules = quiz.requiredModuleIds
                    .map(requiredModuleId => modules.find(m => m.moduleId === requiredModuleId && !processedModules.has(m._id)))
                    .filter(Boolean);

                  const sortedRequired = sortModules(requiredModules);

                  sortedRequired.forEach(requiredModule => {
                    allItems.push({
                      ...requiredModule,
                      type: 'module',
                      originalIndex: currentIndex++,
                      isRequiredForQuiz: true
                    });
                    processedModules.add(requiredModule._id);
                  });

                  
                  allItems.push({
                    ...quiz,
                    type: 'checkpoint_quiz',
                    originalIndex: currentIndex++
                  });
                  processedQuizzes.add(quiz._id);
                }
              });
              
              
              const remainingModules = modules.filter(module => !processedModules.has(module._id));
              const sortedRemaining = sortModules(remainingModules);
              sortedRemaining.forEach(module => {
                allItems.push({
                  ...module,
                  type: 'module',
                  originalIndex: currentIndex++,
                  isRequiredForQuiz: false
                });
              });
              
              
              const remainingQuizzes = checkpointQuizzes.filter(quiz => !processedQuizzes.has(quiz._id))
                .sort((a, b) => (a.checkpointNumber || 0) - (b.checkpointNumber || 0));
              remainingQuizzes.forEach(quiz => {
                allItems.push({
                  ...quiz,
                  type: 'checkpoint_quiz',
                  originalIndex: currentIndex++
                });
              });
              
              
              const filteredItems = allItems.filter((item) => {
                if (!searchQuery) return true;
                const query = searchQuery.toLowerCase();
                return (
                  item.moduleId?.toLowerCase().includes(query) ||
                  item.title?.toLowerCase().includes(query) ||
                  item.description?.toLowerCase().includes(query)
                );
              });
              
              return filteredItems.map((item) => {
                if (item.type === 'checkpoint_quiz') {
                  
                  const eligibility = checkQuizEligibility(item);
                  const completedQuiz = userProgress?.completedCheckpointQuizzes?.find(
                    q => q.checkpointNumber === item.checkpointNumber
                  );
                  
                  return (
                    <tr key={`quiz-${item._id}`} style={{ backgroundColor: '#f8f9ff', borderLeft: '4px solid #2196F3' }}>
                      <td data-label="Title">
                        <div>
                          <strong style={{ color: '#2196F3' }}>🎯 {item.title}</strong>
                          <p style={{ margin: "5px 0 0 0", fontSize: "12px", color: "#666" }}>
                            {item.description}
                          </p>
                          <p style={{ margin: "5px 0 0 0", fontSize: "11px", color: "#888" }}>
                            Required modules: {item.requiredModuleIds?.join(', ')}
                          </p>
                        </div>
                      </td>
                      <td data-label="Your Score">
                        {completedQuiz ? (
                          <span style={{ fontSize: '12px', color: '#333' }}>
                            {completedQuiz.score}%
                          </span>
                        ) : (
                          <span style={{ color: '#999' }}>—</span>
                        )}
                      </td>
                      <td data-label="Status">
                        {(() => {
                          if (completedQuiz && completedQuiz.passed === true) {
                            return (
                              <span className="status-completed">
                                <span className="status-icon">✓</span>
                                <span className="status-text">Passed</span>
                              </span>
                            );
                          } else if (completedQuiz && completedQuiz.passed === false) {
                            return (
                              <span className="status-pending">
                                <span className="status-icon">✗</span>
                                <span className="status-text">Failed</span>
                              </span>
                            );
                          } else {
                            return (
                              <span className="status-pending">
                                <span className="status-icon">○</span>
                                <span className="status-text">Not Taken</span>
                              </span>
                            );
                          }
                        })()}
                      </td>
                      <td data-label="Actions">
                        <div style={{ display: 'flex', gap: '8px', flexDirection: 'column' }}>
                          {eligibility.canTake && !completedQuiz?.passed ? (
                            <button 
                              className="download-btn" 
                              onClick={() => startQuiz(item)}
                              style={{ background: '#2196F3', color: 'white' }}
                            >
                              Take Quiz
                            </button>
                          ) : completedQuiz?.passed ? (
                            <button 
                              className="download-btn" 
                              onClick={() => startQuiz(item)}
                              style={{ background: '#28a745', color: 'white' }}
                            >
                              Retake Quiz
                            </button>
                          ) : (
                            <button 
                              className="download-btn" 
                              disabled
                              style={{ background: '#6c757d', color: 'white' }}
                              title={eligibility.reason}
                            >
                              🔒 Locked
                            </button>
                          )}
                          <small style={{ color: '#666', fontSize: '11px' }}>
                            {item.questions?.length || 0} questions • {item.timeLimit}min • {item.passingScore}% to pass
                          </small>
                        </div>
                      </td>
                    </tr>
                  );
                } else {
                  
                  const module = item;
                  const isCompleted = userProgress?.completedModules?.includes(module._id);
                  
                  
                  const moduleQuizzes = Array.isArray(checkpointQuizzes)
                    ? checkpointQuizzes.filter(quiz =>
                        Array.isArray(quiz.requiredModuleIds) && quiz.requiredModuleIds.includes(module.moduleId)
                      )
                    : [];
                  
                  const requiredCheckpointLabels = moduleQuizzes.length > 0
                    ? moduleQuizzes
                        .map(q => q.checkpointNumber)
                        .filter(n => typeof n === 'number')
                        .sort((a, b) => a - b)
                    : [];




                  const gatingCheckpoint = (() => {
                    if (requiredCheckpointLabels.length === 0) return null;
                    const firstCheckpoint = requiredCheckpointLabels[0];
                    if (firstCheckpoint <= 1) return null;
                    return firstCheckpoint - 1;
                  })();
                  
                  const isModuleLocked = (() => {
                    if (gatingCheckpoint == null) return false;
                    const gatePassed = userProgress?.completedCheckpointQuizzes?.some(
                      q => q.checkpointNumber === gatingCheckpoint && q.passed
                    );
                    return !gatePassed;
                  })();
                  
                  return (
                    <tr key={module._id} className={`${isCompleted ? 'completed' : ''} ${isModuleLocked ? 'locked' : ''}`} 
                        style={{
                          ...(isModuleLocked ? { backgroundColor: '#f8f8f8', opacity: 0.7 } : {}),
                          ...(item.isRequiredForQuiz ? { backgroundColor: '#fff3cd', borderLeft: '4px solid #ffc107' } : {})
                        }}>
                      <td data-label="Title">
                        <div>
                          <strong style={isModuleLocked ? { color: '#6c757d' } : {}}>
                            {item.isRequiredForQuiz && '📚 '}{module.title}
                          </strong>
                          {module.description && (
                            <p style={{ margin: "5px 0 0 0", fontSize: "12px", color: isModuleLocked ? "#999" : "#666" }}>
                              {module.description}
                            </p>
                          )}
                          {requiredCheckpointLabels.length > 0 && (
                            <p style={{ margin: "5px 0 0 0", fontSize: "11px", color: "#0d6efd", fontWeight: "bold" }}>
                              📘 Module Set {requiredCheckpointLabels[0]}
                            </p>
                          )}
                          {requiredCheckpointLabels.length > 0 && (
                            <p style={{ margin: "2px 0 0 0", fontSize: "11px", color: "#856404", fontWeight: "bold" }}>
                              ⭐ Required for Checkpoint{requiredCheckpointLabels.length > 1 ? 's' : ''} {requiredCheckpointLabels.join(', ')}
                            </p>
                          )}
                          {isModuleLocked && gatingCheckpoint != null && (
                            <p style={{ margin: "5px 0 0 0", fontSize: "11px", color: "#dc3545", fontWeight: "bold" }}>
                              🔒 Unlocks after passing Checkpoint {gatingCheckpoint}
                            </p>
                          )}
                        </div>
                      </td>
                      <td data-label="Your Score">
                        {(() => {
                          const stat = getStatForModuleType(module.title);
                          if (!stat) return <span style={{ color: '#999' }}>—</span>;
                          return (
                            <span style={{ fontSize: '12px', color: isModuleLocked ? '#999' : '#333' }}>
                              Correct: {stat.correct}/{stat.total} ({stat.percentage}%) — <strong>{stat.bracket}</strong>
                            </span>
                          );
                        })()}
                      </td>
                      <td data-label="Status">
                        {isModuleLocked ? (
                          <span className="status-pending">
                            <span className="status-icon">🔒</span>
                            <span className="status-text">Locked</span>
                          </span>
                        ) : isCompleted ? (
                          <span className="status-completed">
                            <span className="status-icon">✓</span>
                            <span className="status-text">Completed</span>
                          </span>
                        ) : (
                          <span className="status-pending">
                            <span className="status-icon">○</span>
                            <span className="status-text">Available</span>
                          </span>
                        )}
                      </td>
                      <td data-label="Actions">
                        <div style={{ display: 'flex', gap: '8px', flexDirection: 'column' }}>
                          {isModuleLocked ? (
                            <>
                              <button className="download-btn" disabled style={{ background: '#6c757d', color: 'white' }} title="Complete checkpoint quiz to unlock">
                                🔒 Locked
                              </button>
                              <button className="module-btn" disabled style={{ background: '#6c757d', color: 'white' }} title="Complete checkpoint quiz to unlock">
                                🔒 Locked
                              </button>
                            </>
                          ) : (
                            <>
                              <button 
                                className="download-btn" 
                                onClick={() => handleDownload(module)} 
                                disabled={!module.hasContent}
                                title={!module.hasContent ? "No content uploaded for this module" : "Download module content"}
                              >
                                Download
                              </button>
                              <button 
                                className="module-btn" 
                                onClick={() => handleView(module)} 
                                disabled={!module.hasContent}
                                title={!module.hasContent ? "No content uploaded for this module" : "View module content"}
                              >
                                View
                              </button>
                              {!module.hasContent && (
                                <small style={{ color: '#dc3545', fontSize: '11px', marginTop: '4px' }}>
                                  ⚠️ No content uploaded
                                </small>
                              )}
                            </>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                }
              });
            })()}
            
            {modules.length === 0 && checkpointQuizzes.length === 0 && !isLoading && (
              <tr>
                <td colSpan={4} style={{ textAlign: "center", padding: 20, color: "#777" }}>
                  No modules or quizzes available. Please contact your administrator.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {isLoading && <div style={{ textAlign: "center", padding: "40px", color: "#666" }}>Loading your personalized modules...</div>}

      {viewingPdf && <PDFViewer pdfUrl={getPdfUrl(viewingPdf)} fileName={viewingPdf.name} onClose={closePdfViewer} onDownload={() => handleDownload(viewingPdf)} />}
    </div>
  );
}

export default UserModuleManagement;

