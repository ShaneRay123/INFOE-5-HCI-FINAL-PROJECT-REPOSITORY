import React, { useState, useEffect, useCallback } from "react";
import { useNavigate } from "react-router-dom";
import "../../PageLayout.css";
import "../../PreAssessment.css";
import backIcon from "../../assets/back.png";
import logo from "../../assets/PYlot white.png";
import { api } from "../../utils/api";
import examSessionManager from "../../utils/examSession";

function UserExam() {
  const navigate = useNavigate();

  const [selectedExam, setSelectedExam] = useState(null);
  const [currentQuestion, setCurrentQuestion] = useState(0);
  const [answers, setAnswers] = useState({});
  const [timeLeft, setTimeLeft] = useState(0);
  const [quizStarted, setQuizStarted] = useState(false);

  const [isLoading, setIsLoading] = useState(true);
  const [timeSpent, setTimeSpent] = useState(0);
  const [lastResult, setLastResult] = useState(null);
  const [error, setError] = useState("");
  const [indicatorPage, setIndicatorPage] = useState(0);
  const [indicatorsPerPage] = useState(10);
  const [restoringSession, setRestoringSession] = useState(true);

  const getLocalStateKey = useCallback(() => {
    const username = localStorage.getItem('username') || 'anonymous';
    return `exam_state_${username}`;
  }, []);

  const loadLocalState = useCallback(() => {
    try {
      const raw = localStorage.getItem(getLocalStateKey());
      return raw ? JSON.parse(raw) : null;
    } catch (e) {
      return null;
    }
  }, [getLocalStateKey]);

  const saveLocalState = useCallback((partial = {}) => {
    try {
      const existing = loadLocalState() || {};
      const merged = {
        ...existing,
        ...partial,
        lastSaved: new Date().toISOString(),
      };
      localStorage.setItem(getLocalStateKey(), JSON.stringify(merged));
    } catch (e) {
    }
  }, [loadLocalState, getLocalStateKey]);

  const clearLocalState = useCallback(() => {
    try { localStorage.removeItem(getLocalStateKey()); } catch {}
  }, [getLocalStateKey]);

  const startFreshExam = useCallback(async (exam) => {
    try {
      const sessionResult = await examSessionManager.startSession(exam._id, 'exam');

      setSelectedExam(exam);
      setCurrentQuestion(0);
      setAnswers({});
      setTimeLeft(exam.timeLimit * 60);
      setTimeSpent(0);
      setQuizStarted(true);
      setIndicatorPage(0);

      saveLocalState({
        examSnapshot: {
          examId: exam._id,
          title: exam.title,
          passingScore: exam.passingScore,
          timeLimit: exam.timeLimit,
          questions: exam.questions,
        },
        currentQuestion: 0,
        answers: {},
        timeLeft: exam.timeLimit * 60,
        timeSpent: 0,
      });

      if (!sessionResult) {
        console.info('Exam started without session persistence - please login for full features');
      }
    } catch (error) {
      console.error('Error starting exam session:', error);
      setError("Failed to start exam session. Please try again.");
    }
  }, [saveLocalState]);

  const loadExamWithSession = useCallback(async () => {
    setIsLoading(true);
    setRestoringSession(true);
    try {
      const username = localStorage.getItem('username');
      if (username) {
        const progress = await api.get(`/api/user-progress/${username}`);
        if (progress && progress.hasCompletedPreAssessment) {
          setError("You have already completed the assessment.");
          return;
        }
      }

      const localState = loadLocalState();
      const activeSession = await examSessionManager.loadActiveSession('exam');
      const activeExam = await api.get('/api/exams/active');

      if (!activeExam) {
        setError("No exam available. Please contact your administrator.");
        return;
      }

      const restoredExam = (localState && localState.examSnapshot && (localState.examSnapshot.examId === activeExam._id))
        ? localState.examSnapshot
        : null;

      if (activeSession && (activeSession.examId === activeExam._id || activeSession.isLocal)) {
        const examToUse = restoredExam || activeExam;
        setSelectedExam(examToUse);
        setCurrentQuestion(localState?.currentQuestion ?? activeSession.currentQuestion ?? 0);
        setAnswers(localState?.answers || activeSession.answers || {});
        setTimeLeft(localState?.timeLeft ?? activeSession.timeLeft ?? (examToUse.timeLimit * 60));
        setTimeSpent(activeSession.timeSpent || 0);
        setQuizStarted(true);
      } else {
        if (restoredExam && localState) {
          setSelectedExam(restoredExam);
          setCurrentQuestion(localState.currentQuestion || 0);
          setAnswers(localState.answers || {});
          setTimeLeft(localState.timeLeft != null ? localState.timeLeft : (restoredExam.timeLimit * 60));
          setTimeSpent(localState.timeSpent || 0);
          setQuizStarted(true);
        } else {
          await startFreshExam(activeExam);
        }
      }
    } catch (error) {
      console.error('Error loading exam:', error);
      setError("Failed to load exam. Please try again.");
    } finally {
      setIsLoading(false);
      setRestoringSession(false);
    }
  }, [loadLocalState, startFreshExam]);

  useEffect(() => {
    loadExamWithSession();
    return () => {
      examSessionManager.cleanup();
    };
  }, [loadExamWithSession]);

  const handleAnswerSelect = (questionId, answerIndex) => {
    const newAnswers = {
      ...answers,
      [questionId]: answerIndex
    };
    setAnswers(newAnswers);

    saveLocalState({ answers: newAnswers });
    examSessionManager.autoSave(currentQuestion, newAnswers, timeLeft);
  };

  const nextQuestion = async () => {
    if (!selectedExam) return;
    if (currentQuestion < selectedExam.questions.length - 1) {
      const newQuestionIndex = currentQuestion + 1;
      setCurrentQuestion(newQuestionIndex);

      const newPage = Math.floor(newQuestionIndex / indicatorsPerPage);
      setIndicatorPage(newPage);

      saveLocalState({ currentQuestion: newQuestionIndex });
      await examSessionManager.immediateSave(newQuestionIndex, answers, timeLeft);
    }
  };

  const prevQuestion = async () => {
    if (!selectedExam) return;
    if (currentQuestion > 0) {
      const newQuestionIndex = currentQuestion - 1;
      setCurrentQuestion(newQuestionIndex);

      const newPage = Math.floor(newQuestionIndex / indicatorsPerPage);
      setIndicatorPage(newPage);

      saveLocalState({ currentQuestion: newQuestionIndex });
      await examSessionManager.immediateSave(newQuestionIndex, answers, timeLeft);
    }
  };

  const calculateScore = useCallback(() => {
    if (!selectedExam) return { score: 0, percentage: 0, correct: 0, total: 0 };

    const toAnswerIndex = (val, options) => {
      const letterMap = { A: 0, B: 1, C: 2, D: 3, E: 4, F: 5 };
      if (typeof val === 'number' && !Number.isNaN(val)) return val;
      if (typeof val === 'string') {
        const s = val.trim();
        if (!s) return -1;
        const opts = (options || []).map((o) => String(o));
        const textIdx = opts.findIndex((opt) => opt.trim().toLowerCase() === s.toLowerCase());
        if (textIdx !== -1) return textIdx;
        const upper = s.toUpperCase();
        if (letterMap[upper] !== undefined) return letterMap[upper];
        const asNum = Number(s);
        if (!Number.isNaN(asNum)) return asNum;
        return -1;
      }
      return -1;
    };

    let correct = 0;
    const total = selectedExam.questions.length;

    selectedExam.questions.forEach((question) => {
      const options = (question.options || []).map((o) => String(o));
      const selectedIndex = toAnswerIndex(answers[question.id], options);
      const correctIndex = toAnswerIndex(question.correctAnswer, options);
      if (correctIndex >= 0 && selectedIndex === correctIndex) {
        correct++;
      }
    });

    const percentage = Math.round((correct / total) * 100);

    return { score: correct, percentage, correct, total };
  }, [selectedExam, answers]);

  const submitQuiz = useCallback(async () => {
    if (!selectedExam) return;

    const allQuestionsAnswered = selectedExam.questions.every(q => answers[q.id] !== undefined);
    if (!allQuestionsAnswered) {
      alert('Please answer all questions before submitting the quiz.');
      return;
    }

    const totalTime = selectedExam.timeLimit * 60;
    const timeUsed = totalTime - timeLeft;
    setTimeSpent(timeUsed);

    try {
      const sessionResponse = await examSessionManager.completeSession(answers, timeUsed);

      clearLocalState();

      const username = localStorage.getItem('username') || 'anonymous';

      let percentage = 0;
      let correct = 0;
      let total = selectedExam.questions.length;

      if (sessionResponse && sessionResponse.result) {
        percentage = typeof sessionResponse.result.score === 'number' ? sessionResponse.result.score : 0;
        correct = typeof sessionResponse.result.correctAnswers === 'number' ? sessionResponse.result.correctAnswers : 0;
        total = typeof sessionResponse.result.totalQuestions === 'number' ? sessionResponse.result.totalQuestions : total;
      } else {
        const localScore = calculateScore();
        percentage = localScore.percentage;
        correct = localScore.correct;
        total = localScore.total;
      }

      setLastResult({ percentage, correct, total });

      await api.post(`/api/user-progress/${username}/exam`, {
        score: percentage
      });

      navigate('/user/modules');
    } catch (error) {
      console.error('Error submitting exam:', error);
      clearLocalState();
      navigate('/user');
    } finally {
      setQuizStarted(false);
    }
  }, [selectedExam, answers, timeLeft, calculateScore, clearLocalState, navigate]);

  useEffect(() => {
    let timer;
    if (quizStarted && timeLeft > 0) {
      timer = setTimeout(async () => {
        const newTime = timeLeft - 1;
        setTimeLeft(newTime);

        saveLocalState({ timeLeft: newTime });
        examSessionManager.autoSave(currentQuestion, answers, newTime);
      }, 1000);
    } else if (timeLeft === 0 && quizStarted) {
      submitQuiz();
    }
    return () => clearTimeout(timer);
  }, [timeLeft, quizStarted, submitQuiz, currentQuestion, answers, saveLocalState]);

  useEffect(() => {
    if (selectedExam && selectedExam.questions.length > 0) {
      const newPage = Math.floor(currentQuestion / indicatorsPerPage);
      setIndicatorPage(newPage);
    }
  }, [currentQuestion, selectedExam, indicatorsPerPage]);

  const formatTime = (seconds) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  };

  const getTotalPages = () => {
    if (!selectedExam) return 0;
    return Math.ceil(selectedExam.questions.length / indicatorsPerPage);
  };

  const getCurrentPageIndicators = () => {
    if (!selectedExam) return [];
    const startIndex = indicatorPage * indicatorsPerPage;
    const endIndex = Math.min(startIndex + indicatorsPerPage, selectedExam.questions.length);
    return selectedExam.questions.slice(startIndex, endIndex).map((_, index) => startIndex + index);
  };

  const goToIndicatorPage = (page) => {
    const totalPages = getTotalPages();
    if (page >= 0 && page < totalPages) {
      setIndicatorPage(page);
    }
  };

  const goToQuestion = async (questionIndex) => {
    setCurrentQuestion(questionIndex);

    const newPage = Math.floor(questionIndex / indicatorsPerPage);
    setIndicatorPage(newPage);

    saveLocalState({ currentQuestion: questionIndex });
    await examSessionManager.immediateSave(questionIndex, answers, timeLeft);
  };

  const resetQuiz = async () => {
    if (examSessionManager.hasActiveSession()) {
      await examSessionManager.cancelSession();
    }
    clearLocalState();
    navigate("/user");
  };

  if (isLoading) {
    return (
      <div className="page-container">
        <header className="page-header preassessment-header">
          <img
            src={backIcon}
            alt="Back"
            className="back-img"
            onClick={() => navigate("/user")}
          />
          <h2>Assessment</h2>
          <img src={logo} alt="PYlot Logo" className="logo-img" />
        </header>
        <div style={{ textAlign: "center", padding: "40px", color: "#666" }}>
          {restoringSession ? "Restoring your exam session..." : "Loading assessment..."}
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="page-container">
        <header className="page-header preassessment-header">
          <img
            src={backIcon}
            alt="Back"
            className="back-img"
            onClick={() => navigate("/user")}
          />
          <h2>Assessment</h2>
          <img src={logo} alt="PYlot Logo" className="logo-img" />
        </header>
        <div style={{ textAlign: "center", padding: "40px", color: "#666" }}>
          <h3>{error}</h3>
          <button
            className="apply-btn"
            onClick={() => navigate("/user")}
            style={{ marginTop: "20px" }}
          >
            Back to Dashboard
          </button>
        </div>
      </div>
    );
  }

  if (selectedExam) {
    const question = selectedExam.questions[currentQuestion];
    const isLastQuestion = currentQuestion === selectedExam.questions.length - 1;
    const isFirstQuestion = currentQuestion === 0;

    return (
      <div className="page-container">
        <header className="page-header preassessment-header">
          <img
            src={backIcon}
            alt="Back"
            className="back-img"
            onClick={resetQuiz}
          />
          <h2>Assessment</h2>
          <div className="quiz-timer">
            <span className="timer-text">Time: {formatTime(timeLeft)}</span>
          </div>
        </header>

        <div className="quiz-container">
          <div className="quiz-progress">
            <div className="progress-bar">
              <div
                className="progress-fill"
                style={{ width: `${((currentQuestion + 1) / selectedExam.questions.length) * 100}%` }}
              ></div>
            </div>
            <div className="progress-text">
              <span>Question {currentQuestion + 1} of {selectedExam.questions.length}</span>
              <div className="progress-stats">
                <span className="answered-count">
                  ✓ {selectedExam.questions.filter(q => answers[q.id] !== undefined).length} answered
                </span>
                <span className="unanswered-count">
                  ○ {selectedExam.questions.filter(q => answers[q.id] === undefined).length} unanswered
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
                  <span className="status-unanswered">○ Unanswered</span>
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
                  onClick={submitQuiz}
                >
                  Submit
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
              <div className="question-indicators">
                {getCurrentPageIndicators().map((index) => {
                  const isAnswered = answers[selectedExam.questions[index].id] !== undefined;
                  const isCurrent = index === currentQuestion;
                  return (
                    <button
                      key={index}
                      className={`indicator ${isCurrent ? 'active' : ''} ${
                        isAnswered ? 'answered' : 'unanswered'
                      }`}
                      onClick={() => goToQuestion(index)}
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

              {getTotalPages() > 1 && (
                <div className="indicator-pagination">
                  <button
                    className="pagination-btn"
                    onClick={() => goToIndicatorPage(indicatorPage - 1)}
                    disabled={indicatorPage === 0}
                    title="Previous page"
                  >
                    ‹
                  </button>

                  <span className="pagination-info">
                    Page {indicatorPage + 1} of {getTotalPages()}
                  </span>

                  <button
                    className="pagination-btn"
                    onClick={() => goToIndicatorPage(indicatorPage + 1)}
                    disabled={indicatorPage >= getTotalPages() - 1}
                    title="Next page"
                  >
                    ›
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    );
  }

  const { percentage } = lastResult || calculateScore();
  return (
    <div className="page-container">
      <header className="page-header preassessment-header">
        <img
          src={backIcon}
          alt="Back"
          className="back-img"
          onClick={() => navigate("/user")}
        />
        <h2>Assessment</h2>
        <img src={logo} alt="PYlot Logo" className="logo-img" />
      </header>

      <div style={{ textAlign: "center", padding: "40px", color: "#666" }}>
        <h3>Score: {percentage}%</h3>
        <button className="apply-btn" onClick={() => navigate('/user/modules')} style={{ marginTop: 20 }}>
          Go to Modules
        </button>
      </div>
    </div>
  );
}

export default UserExam;
