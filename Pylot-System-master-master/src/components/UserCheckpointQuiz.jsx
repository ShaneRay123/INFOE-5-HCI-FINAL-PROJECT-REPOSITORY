import React, { useState, useEffect, useCallback, useRef } from "react";
import { useNavigate, useLocation } from "react-router-dom";
import { api } from "../utils/api";
import "./UserCheckpointQuiz.css";
import "../PageLayout.css";
import backIcon from "../assets/back.png";
import logo from "../assets/PYlot white.png";

function UserCheckpointQuiz() {
  const navigate = useNavigate();
  const location = useLocation();
  const { quiz: quizFromState } = location.state || {};
  
  const [quiz, setQuiz] = useState(null);
  const [currentQuestion, setCurrentQuestion] = useState(0);
  const [answers, setAnswers] = useState({});
  const [timeLeft, setTimeLeft] = useState(0);
  const [quizStarted, setQuizStarted] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState("");
  const indicatorsRef = useRef(null);
  const [showResults, setShowResults] = useState(false);
  const [quizResult, setQuizResult] = useState(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    if (quiz && quizStarted && !showResults) {
      const username = localStorage.getItem('username') || 'anonymous';
      const sessionKey = `checkpoint_quiz_session_${username}_${quiz.checkpointNumber}`;
      const sessionData = {
        quiz,
        currentQuestion,
        answers,
        timeLeft,
        quizStarted,
        lastSaved: new Date().toISOString()
      };
      localStorage.setItem(sessionKey, JSON.stringify(sessionData));
    }
  }, [quiz, currentQuestion, answers, timeLeft, quizStarted, showResults]);

  useEffect(() => {
    const loadQuiz = async () => {
      setIsLoading(true);

      if (quizFromState) {
        setQuiz(quizFromState);
        setTimeLeft(quizFromState.timeLimit * 60);
        setQuizStarted(true);
        setIsLoading(false);
        return;
      }

      const username = localStorage.getItem('username') || 'anonymous';
      const urlParams = new URLSearchParams(window.location.search);
      const checkpointNumber = urlParams.get('checkpoint');
      
      if (checkpointNumber) {
        const sessionKey = `checkpoint_quiz_session_${username}_${checkpointNumber}`;
        const savedSession = localStorage.getItem(sessionKey);
        
        if (savedSession) {
          try {
            const sessionData = JSON.parse(savedSession);
            const sessionAge = Date.now() - new Date(sessionData.lastSaved).getTime();

            if (sessionAge < 24 * 60 * 60 * 1000) {
              setQuiz(sessionData.quiz);
              setCurrentQuestion(sessionData.currentQuestion || 0);
              setAnswers(sessionData.answers || {});
              setTimeLeft(sessionData.timeLeft || 0);
              setQuizStarted(true);
              setIsLoading(false);
              return;
            } else {

              localStorage.removeItem(sessionKey);
            }
          } catch (error) {
            console.error('Error restoring checkpoint quiz session:', error);
            localStorage.removeItem(sessionKey);
          }
        }

        try {
          const quizData = await api.get(`/api/checkpoint-quizzes/${checkpointNumber}`);
          if (quizData) {
            setQuiz(quizData);
            setTimeLeft(quizData.timeLimit * 60);
            setQuizStarted(true);
            setIsLoading(false);
            return;
          }
        } catch (error) {
          console.error('Error fetching quiz:', error);
        }
      }

      navigate("/user/checkpoint-quizzes");
    };
    
    loadQuiz();
  }, [quizFromState, navigate]);

  const handleSubmitQuiz = useCallback(async () => {
    if (isSubmitting || !quiz) return;
    
    setIsSubmitting(true);
    setError("");

    try {
      const username = localStorage.getItem("username");
      
      const response = await api.post(`/api/checkpoint-quizzes/${quiz.checkpointNumber}/submit`, {
        username,
        answers,
        quizId: quiz._id 
      });

      
      setQuizResult({
        ...response,
        passed: response.passed,
        score: response.score,
        totalQuestions: quiz.questions.length,
        correctAnswers: response.correctAnswers || Math.round((response.score / 100) * quiz.questions.length),
        passingScore: quiz.passingScore,
        timeSpent: (quiz.timeLimit * 60) - timeLeft
      });
      setShowResults(true);

      const sessionKey = `checkpoint_quiz_session_${username}_${quiz.checkpointNumber}`;
      localStorage.removeItem(sessionKey);
    } catch (error) {
      console.error("Error submitting quiz:", error);
      setError("Failed to submit quiz. Please try again.");
    } finally {
      setIsSubmitting(false);
    }
  }, [quiz, answers, timeLeft, isSubmitting]);

  useEffect(() => {
    if (!quizStarted || timeLeft <= 0) return;

    const timer = setInterval(() => {
      setTimeLeft(prev => {
        if (prev <= 1) {
          handleSubmitQuiz();
          return 0;
        }
        return prev - 1;
      });
    }, 1000);

    return () => clearInterval(timer);
  }, [quizStarted, timeLeft, handleSubmitQuiz]);

  
  useEffect(() => {
    if (indicatorsRef.current && quiz) {
      const currentIndicator = indicatorsRef.current.children[currentQuestion];
      if (currentIndicator) {
        currentIndicator.scrollIntoView({
          behavior: 'smooth',
          block: 'nearest',
          inline: 'center'
        });
      }
    }
  }, [currentQuestion, quiz]);

  const handleAnswerSelect = (questionId, answerIndex) => {
    setAnswers(prev => ({
      ...prev,
      [questionId]: answerIndex
    }));
  };

  const nextQuestion = () => {
    if (currentQuestion < quiz.questions.length - 1) {
      setCurrentQuestion(currentQuestion + 1);
    }
  };

  const prevQuestion = () => {
    if (currentQuestion > 0) {
      setCurrentQuestion(currentQuestion - 1);
    }
  };


  const formatTime = (seconds) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  };

  const goBackToModules = () => {
    
    window.location.href = "/user/modules";
  };

  const retakeQuiz = () => {
    setCurrentQuestion(0);
    setAnswers({});
    setTimeLeft(quiz.timeLimit * 60);
    setQuizStarted(true);
    setShowResults(false);
    setQuizResult(null);
    setError("");

    const username = localStorage.getItem('username') || 'anonymous';
    const sessionKey = `checkpoint_quiz_session_${username}_${quiz.checkpointNumber}`;
    localStorage.removeItem(sessionKey);
  };

  if (isLoading || !quiz) {
    return (
      <div className="page-container">
        <header className="page-header">
          <img
            src={backIcon}
            alt="Back"
            className="back-img"
            onClick={() => navigate("/user/modules")}
          />
          <h2>Checkpoint Quiz</h2>
          <img src={logo} alt="PYlot Logo" className="logo-img" />
        </header>
        <div className="quiz-container">
          <div style={{ textAlign: 'center', padding: '40px', color: '#666' }}>
            {isLoading ? 'Loading quiz...' : 'Quiz not found. Redirecting...'}
          </div>
        </div>
      </div>
    );
  }

  
  if (showResults && quizResult) {
    return (
      <div className="page-container">
        <header className="page-header">
          <img
            src={backIcon}
            alt="Back"
            className="back-img"
            onClick={goBackToModules}
          />
          <h2>Quiz Results - {quiz.title}</h2>
          <img src={logo} alt="PYlot Logo" className="logo-img" />
        </header>

        <div className="results-container">
          <div className={`results-header ${quizResult.passed ? 'passed' : 'failed'}`}>
            <h3>{quizResult.passed ? 'Congratulations! 🎉' : 'Keep Learning! 📚'}</h3>
            <div className="score-display">
              <span className="score-number">{quizResult.score}%</span>
              <span className="score-text">Final Score</span>
            </div>
          </div>

          <div className="results-details">
            <div className="detail-item">
              <span className="detail-label">Status:</span>
              <span className={`detail-value ${quizResult.passed ? 'passed' : 'failed'}`}>
                {quizResult.passed ? 'PASSED ✓' : 'FAILED ✗'}
              </span>
            </div>
            <div className="detail-item">
              <span className="detail-label">Correct Answers:</span>
              <span className="detail-value">{quizResult.correctAnswers}/{quizResult.totalQuestions}</span>
            </div>
            <div className="detail-item">
              <span className="detail-label">Passing Score Required:</span>
              <span className="detail-value">{quizResult.passingScore}%</span>
            </div>
            <div className="detail-item">
              <span className="detail-label">Time Spent:</span>
              <span className="detail-value">{formatTime(quizResult.timeSpent)}</span>
            </div>
            {quizResult.passed && (
              <div className="detail-item success-message">
                <span className="detail-label">🎊 Great Job!</span>
                <span className="detail-value">New modules have been unlocked!</span>
              </div>
            )}
          </div>

          <div className="results-actions">
            <button className="action-btn back-btn-primary" onClick={goBackToModules}>
              Back to Modules Dashboard
            </button>
            {!quizResult.passed && (
              <button className="action-btn retake-btn" onClick={retakeQuiz}>
                Retake Quiz
              </button>
            )}
          </div>
        </div>
      </div>
    );
  }

  const question = quiz.questions[currentQuestion];
  const isLastQuestion = currentQuestion === quiz.questions.length - 1;
  const isFirstQuestion = currentQuestion === 0;

  return (
    <div className="page-container">
      <header className="page-header">
        <img
          src={backIcon}
          alt="Back"
          className="back-img"
          onClick={() => navigate("/user/modules")}
        />
        <h2>{quiz.title}</h2>
        <div className="quiz-timer">
          <span className="timer-text">Time: {formatTime(timeLeft)}</span>
        </div>
      </header>

      <div className="quiz-container">
        <div className="quiz-progress">
          <div className="progress-bar">
            <div 
              className="progress-fill"
              style={{ width: `${((currentQuestion + 1) / quiz.questions.length) * 100}%` }}
            ></div>
          </div>
          <div className="progress-text">
            <span>Question {currentQuestion + 1} of {quiz.questions.length}</span>
            <div className="progress-stats">
              <span className="answered-count">
                ✓ {quiz.questions.filter(q => answers[q.id] !== undefined).length} answered
              </span>
              <span className="skipped-count">
                ○ {quiz.questions.filter(q => answers[q.id] === undefined).length} skipped
              </span>
            </div>
          </div>
        </div>

        <div className="question-container">
          <div className="question-header">
            <h3 className="question-text">{question.question}</h3>
            <div className="question-status">
              {answers[question.id] !== undefined ? (
                <span className="status-answered">✓ Answered</span>
              ) : (
                <span className="status-skipped">○ Skipped</span>
              )}
            </div>
          </div>
          
          <div className="options-container">
            {question.options.map((option, index) => (
              <label key={index} className="option-label">
                <input
                  type="radio"
                  name={`question-${question.id}`}
                  value={index}
                  checked={answers[question.id] === index}
                  onChange={() => handleAnswerSelect(question.id, index)}
                  className="option-input"
                />
                <span className="option-text">{option}</span>
              </label>
            ))}
          </div>
        </div>

        <div className="quiz-navigation">
          <div className="nav-buttons">
            <button
              className="nav-btn prev-btn"
              onClick={prevQuestion}
              disabled={isFirstQuestion}
            >
              Previous
            </button>
            
            {isLastQuestion ? (
              <button
                className="nav-btn submit-btn"
                onClick={handleSubmitQuiz}
                disabled={isSubmitting}
              >
                {isSubmitting ? 'Submitting...' : 'Submit Quiz'}
              </button>
            ) : (
              <button
                className="nav-btn next-btn"
                onClick={nextQuestion}
              >
                Next
              </button>
            )}
          </div>
          
          <div className="question-indicators-container">
            <div className="question-indicators" ref={indicatorsRef}>
              {quiz.questions.map((_, index) => {
                const isAnswered = answers[quiz.questions[index].id] !== undefined;
                const isCurrent = index === currentQuestion;
                return (
                  <button
                    key={index}
                    className={`indicator ${isCurrent ? 'active' : ''} ${
                      isAnswered ? 'answered' : 'unanswered'
                    }`}
                    onClick={() => setCurrentQuestion(index)}
                    title={`Question ${index + 1} - ${isAnswered ? 'Answered' : 'Unanswered'}`}
                  >
                    <div className="indicator-content">
                      <span className="indicator-number">{index + 1}</span>
                      <span className="indicator-status">
                        {isAnswered ? '✓' : '○'}
                      </span>
                    </div>
                  </button>
                );
              })}
            </div>
          </div>
        </div>

        {error && (
          <div className="error-message">{error}</div>
        )}
      </div>
    </div>
  );
}

export default UserCheckpointQuiz;

