import React, { useState, useEffect, useCallback } from "react";
import { useNavigate, useLocation } from "react-router-dom";
import { api } from "../utils/api";
import "./UserCheckpointQuizzes.css";

function UserCheckpointQuizzes() {
  const navigate = useNavigate();
  const location = useLocation();
  const [quizzes, setQuizzes] = useState([]);
  const [userProgress, setUserProgress] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [messageType, setMessageType] = useState("");
  const username = localStorage.getItem("username");

  const loadQuizzes = useCallback(async () => {
    try {
      setIsLoading(true);
      setError("");
      
      
      const quizzesData = await api.get("/api/checkpoint-quizzes");
      setQuizzes(quizzesData);
      
      
      const progress = await api.get(`/api/user-progress/${username}`);
      setUserProgress(progress);
      
    } catch (error) {
      console.error("Error loading checkpoint quizzes:", error);
      setError("Failed to load checkpoint quizzes");
    } finally {
      setIsLoading(false);
    }
  }, [username]);

  useEffect(() => {
    loadQuizzes();
    
    
    if (location.state?.message) {
      setMessage(location.state.message);
      setMessageType(location.state.success ? 'success' : 'error');
      
      
      setTimeout(() => {
        setMessage("");
        setMessageType("");
      }, 5000);
    }
  }, [loadQuizzes, location.state]);

  const checkQuizEligibility = (quiz) => {
    if (!userProgress) return { canTake: false, reason: "Loading..." };
    
    
    const completedRequiredModules = quiz.requiredModuleIds.filter(moduleId => 
      userProgress.completedModules.includes(moduleId)
    );
    
    const hasCompletedRequired = completedRequiredModules.length >= quiz.requiredModulesCount;
    
    
    const alreadyCompleted = userProgress.completedCheckpointQuizzes?.some(
      q => q.checkpointNumber === quiz.checkpointNumber && q.passed
    );
    
    if (alreadyCompleted) {
      return { canTake: false, reason: "Already completed", completed: true };
    }
    
    if (!hasCompletedRequired) {
      return { 
        canTake: false, 
        reason: `Complete ${quiz.requiredModulesCount} modules first`,
        completed: false 
      };
    }
    
    return { canTake: true, reason: "Available", completed: false };
  };

  const startQuiz = (quiz) => {

    navigate(`/user/checkpoint-quiz?checkpoint=${quiz.checkpointNumber}`, { 
      state: { quiz, userProgress } 
    });
  };

  if (isLoading) {
    return (
      <div className="checkpoint-quizzes-container">
        <div className="loading-text">Loading checkpoint quizzes...</div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="checkpoint-quizzes-container">
        <div className="error-message">{error}</div>
        <button onClick={loadQuizzes} className="retry-btn">Retry</button>
      </div>
    );
  }

  return (
    <div className="checkpoint-quizzes-container">
      {message && (
        <div className={`message ${messageType}`}>
          {message}
        </div>
      )}
      
      <div className="quizzes-header">
        <h2>Checkpoint Quizzes</h2>
        <p>Complete these quizzes to unlock new modules and advance your learning journey.</p>
      </div>

      <div className="quizzes-grid">
        {quizzes.map(quiz => {
          const eligibility = checkQuizEligibility(quiz);
          const completedQuiz = userProgress?.completedCheckpointQuizzes?.find(
            q => q.checkpointNumber === quiz.checkpointNumber
          );
          
          return (
            <div 
              key={quiz._id} 
              className={`quiz-card ${eligibility.completed ? 'completed' : ''} ${
                !eligibility.canTake && !eligibility.completed ? 'locked' : ''
              }`}
            >
              <div className="quiz-header">
                <h3>{quiz.title}</h3>
                <div className="quiz-number">Checkpoint {quiz.checkpointNumber}</div>
              </div>
              
              <div className="quiz-content">
                <p className="quiz-description">{quiz.description}</p>
                
                <div className="quiz-requirements">
                  <div className="requirement-item">
                    <span className="requirement-label">Required Modules:</span>
                    <span className="requirement-value">{quiz.requiredModulesCount}</span>
                  </div>
                  <div className="requirement-item">
                    <span className="requirement-label">Questions:</span>
                    <span className="requirement-value">{quiz.questions.length}</span>
                  </div>
                  <div className="requirement-item">
                    <span className="requirement-label">Time Limit:</span>
                    <span className="requirement-value">{quiz.timeLimit} minutes</span>
                  </div>
                  <div className="requirement-item">
                    <span className="requirement-label">Passing Score:</span>
                    <span className="requirement-value">{quiz.passingScore}%</span>
                  </div>
                </div>

                {completedQuiz && (
                  <div className="quiz-result">
                    <div className={`result-badge ${completedQuiz.passed ? 'passed' : 'failed'}`}>
                      {completedQuiz.passed ? '? Passed' : '? Failed'}
                    </div>
                    <div className="result-score">Score: {completedQuiz.score}%</div>
                  </div>
                )}
              </div>

              <div className="quiz-footer">
                {eligibility.completed ? (
                  <div className="quiz-status completed-status">
                    ? Completed
                  </div>
                ) : eligibility.canTake ? (
                  <button 
                    className="start-quiz-btn"
                    onClick={() => startQuiz(quiz)}
                  >
                    Start Quiz
                  </button>
                ) : (
                  <div className="quiz-status locked-status">
                    ?? {eligibility.reason}
                  </div>
                )}
              </div>
            </div>
          );
        })}
      </div>

      {quizzes.length === 0 && (
        <div className="no-quizzes">
          <p>No checkpoint quizzes available at the moment.</p>
        </div>
      )}
    </div>
  );
}

export default UserCheckpointQuizzes;

