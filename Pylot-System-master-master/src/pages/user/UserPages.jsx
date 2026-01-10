import React, { useState, useEffect, useCallback } from "react";
import { useNavigate } from "react-router-dom";
import "../../UserPage.css";
import PreIcon from "../../assets/pre.png";
import ModuleIcon from "../../assets/module.png";
import PadlockIcon from "../../assets/padlock.png";
import Logo from "../../assets/PYlot white.png";
import { api } from "../../utils/api";

function UserPage() {
  const navigate = useNavigate();
  const [userProgress, setUserProgress] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [showLockedPopup, setShowLockedPopup] = useState(false);
  const username = localStorage.getItem("username");

  const handleLockedCardClick = () => {
    setShowLockedPopup(true);
  };

  const handleCloseLockedPopup = () => {
    setShowLockedPopup(false);
  };

  const loadUserProgress = useCallback(async () => {
    try {
      setIsLoading(true);
      const progress = await api.get(`/api/user-progress/${username}`);
      setUserProgress(progress);
    } catch (error) {
      console.error("Error loading user progress:", error);
      setUserProgress(null);
    } finally {
      setIsLoading(false);
    }
  }, [username]);

  useEffect(() => {
    loadUserProgress();
  }, [loadUserProgress]);

  const handleLogout = () => {
    
    const existingUsername = localStorage.getItem('username') || 'anonymous';
    
    localStorage.removeItem("role");
    localStorage.removeItem("username");
    localStorage.removeItem("isApproved");
    localStorage.removeItem("accessToken");
    localStorage.removeItem("refreshToken");
    
    try {
      localStorage.removeItem(`exam_session_${existingUsername}_exam`);
      
      localStorage.removeItem('exam_session_exam');
    } catch {}
    navigate("/");
  };

  if (isLoading) {
    return (
      <div className="user-dashboard">
        <header className="user-header">
          <img src={Logo} alt="PYlot Logo" className="user-logo" />
          <button className="logout-btn" onClick={handleLogout}>Logout</button>
        </header>
        <div className="loading-text">Loading your progress...</div>
      </div>
    );
  }

  const hasCompletedPreAssessment = userProgress?.hasCompletedPreAssessment;

  return (
    <div
      className={`user-dashboard ${
        hasCompletedPreAssessment ? "" : "no-progress"
      }`}
    >
      <header className="user-header">
        <img src={Logo} alt="PYlot Logo" className="user-logo" />
        <button className="logout-btn" onClick={handleLogout}>Logout</button>
      </header>

      {hasCompletedPreAssessment && (
        <div className="progress-section">
          <h3>Your Progress</h3>
          <div className="progress-badges">
            <div className={`badge ${userProgress?.hasCompletedPreAssessment ? "completed" : ""}`}>
              ✓ Assessment
            </div>
            <div className={`badge ${userProgress?.completedModules?.length > 0 ? "completed" : ""}`}>
              ✓ Modules
            </div>
          </div>

          <div className="progress-bar">
            <div
              className="progress-fill"
              style={{
                width: `${(() => {
                  let progress = 33;
                  if (userProgress?.completedModules?.length > 0) progress += 33;
                  if (userProgress?.hasCompletedPostAssessment) progress += 34;
                  return progress;
                })()}%`,
              }}
            ></div>
          </div>

          <div className="progress-labels">
            <span>Assessment</span>
            <span>Modules</span>
          </div>

          <div className="progress-stats">
            <span><strong>Modules Completed:</strong> {userProgress?.completedModules?.length || 0}</span>
            {userProgress?.hasCompletedPostAssessment && (
              <span><strong>Historical Score:</strong> {userProgress?.postAssessmentScore || 0}%</span>
            )}
          </div>
        </div>
      )}

      <div className="card-grid">
        {!hasCompletedPreAssessment && (
          <div
            className="card card-pre"
            onClick={() => navigate("/user/exam")}
          >
            <img src={PreIcon} alt="Pre Assessment" className="card-icon" />
            <p>Assessment</p>
          </div>
        )}

        {hasCompletedPreAssessment ? (
          <div className="card card-module" onClick={() => navigate("/user/modules")}>
            <img src={ModuleIcon} alt="Modules" className="card-icon" />
            <p>Course Modules</p>
            <small>Personalized for your level</small>
          </div>
        ) : (
          <div className="card card-module disabled" onClick={handleLockedCardClick}>
            <img src={ModuleIcon} alt="Modules" className="card-icon" />
            <p>Course Modules</p>
            <div className="locked-overlay">
              <img src={PadlockIcon} alt="Locked" className="padlock-icon" />
              <span>Complete the assessment first</span>
            </div>
          </div>
        )}

      </div>

      {showLockedPopup && (
        <div className="locked-modal-backdrop" onClick={handleCloseLockedPopup}>
          <div
            className="locked-modal"
            role="dialog"
            aria-modal="true"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="locked-modal-title">Locked</div>
            <div className="locked-modal-body">Complete the assessment first</div>
            <div className="locked-modal-actions">
              <button className="locked-modal-btn" onClick={handleCloseLockedPopup}>
                OK
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

export default UserPage;

