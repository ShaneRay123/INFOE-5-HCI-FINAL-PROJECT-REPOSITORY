import { api } from './api';

class ExamSessionManager {
  constructor() {
    this.currentSession = null;
    this.saveTimeout = null;
    this.SAVE_DELAY = 2000;
    this._hasLoggedNoSession = false;
    this._hasLoggedLocalFallback = false;
    this._currentExamType = null;
  }

  _getLocalKey(examType) {
    const username = localStorage.getItem('username') || 'anonymous';
    return `exam_session_${username}_${examType}`;
  }

  async startSession(examId, examType) {
    const effectiveExamType = examType || 'exam';
    this._currentExamType = effectiveExamType;
    
    try {
      const response = await api.post('/api/exam-sessions/start', {
        examId,
        examType: effectiveExamType
      });
      
      this.currentSession = response.session;
      return response.session;
    } catch (error) {
      console.error('Error starting exam session:', error);
      
      if (error.message === 'Unauthorized') {
        console.warn('Database session not available, will use local storage fallback');
        return null;
      }
      throw error;
    }
  }

  async loadActiveSession(examType) {
    const effectiveExamType = examType || 'exam';
    try {
      const response = await api.get(`/api/exam-sessions/active`);
      
      if (response.session) {
        this.currentSession = response.session;
        return response.session;
      }
      
      return this._loadLocalSession(effectiveExamType);
    } catch (error) {
      console.error('Error loading active session:', error);
      
      return this._loadLocalSession(effectiveExamType);
    }
  }

  async saveProgress(currentQuestion, answers, timeLeft, immediate = false) {
    if (this.currentSession && !this.currentSession.isLocal) {
      return this._saveDatabaseSession(currentQuestion, answers, timeLeft, immediate);
    } else {
      if (!this._hasLoggedLocalFallback) {
        console.info('Using local storage for progress - data will persist across refreshes but not across devices');
        this._hasLoggedLocalFallback = true;
      }
      
      const examType = this.currentSession?.examType || this._currentExamType || 'exam';
      
      if (this.saveTimeout) {
        clearTimeout(this.saveTimeout);
        this.saveTimeout = null;
      }
      
      if (immediate) {
        this._saveLocalSession(examType, currentQuestion, answers, timeLeft);
      } else {
        this.saveTimeout = setTimeout(() => {
          this._saveLocalSession(examType, currentQuestion, answers, timeLeft);
        }, this.SAVE_DELAY);
      }
    }
  }

  async _saveDatabaseSession(currentQuestion, answers, timeLeft, immediate = false) {
    if (!this.currentSession || !this.currentSession._id) {
      return;
    }
    const sessionId = this.currentSession._id;

    if (this.saveTimeout) {
      clearTimeout(this.saveTimeout);
      this.saveTimeout = null;
    }

    const saveFunction = async () => {
      try {
        const response = await api.put(`/api/exam-sessions/${sessionId}/progress`, {
          currentQuestion,
          answers,
          timeLeft
        });
        
        if (response && response.session) {
          this.currentSession = response.session;
        }
        return response.session;
      } catch (error) {
        console.error('Error saving progress:', error);
        
        if (error.message === 'Unauthorized') {
          console.warn('Session authentication failed, disabling session persistence');
          this.currentSession = null;
        }
        
      }
    };

    if (immediate) {
      return await saveFunction();
    } else {
      this.saveTimeout = setTimeout(saveFunction, this.SAVE_DELAY);
    }
  }

  async completeSession(finalAnswers, timeSpent) {
    if (!this.currentSession) {
      console.warn('No active session to complete');
      return;
    }

    const examType = this.currentSession.examType || this._currentExamType || 'exam';

    try {
      const response = await api.post(`/api/exam-sessions/${this.currentSession._id}/complete`, {
        finalAnswers,
        timeSpent
      });
      
      this._clearLocalSession(examType);
      this.currentSession = null;
      return response;
    } catch (error) {
      console.error('Error completing session:', error);
      
      this._clearLocalSession(examType);
      this.currentSession = null;
      
      if (error.message === 'Unauthorized') {
        console.warn('Session completion failed due to authentication, but exam can still be submitted');
        return null;
      }
      throw error;
    }
  }

  async cancelSession() {
    if (!this.currentSession) {
      return;
    }

    const examType = this.currentSession.examType || this._currentExamType || 'exam';

    try {
      await api.delete(`/api/exam-sessions/${this.currentSession._id}`);
      this._clearLocalSession(examType);
      this.currentSession = null;
    } catch (error) {
      console.error('Error cancelling session:', error);
      
      this._clearLocalSession(examType);
    }
  }

  getCurrentSession() {
    return this.currentSession;
  }

  hasActiveSession() {
    return this.currentSession !== null;
  }

  clearSession() {
    const examType = this.currentSession?.examType || this._currentExamType || 'exam';
    this.currentSession = null;
    this._hasLoggedNoSession = false;
    this._hasLoggedLocalFallback = false;
    if (this.saveTimeout) {
      clearTimeout(this.saveTimeout);
      this.saveTimeout = null;
    }
    this._clearLocalSession(examType);
  }

  autoSave(currentQuestion, answers, timeLeft) {
    this.saveProgress(currentQuestion, answers, timeLeft, false);
  }

  immediateSave(currentQuestion, answers, timeLeft) {
    return this.saveProgress(currentQuestion, answers, timeLeft, true);
  }

  cleanup() {
    if (this.saveTimeout) {
      clearTimeout(this.saveTimeout);
      this.saveTimeout = null;
    }
  }

  _loadLocalSession(examType) {
    try {
      const deprecatedKey = `exam_session_${examType}`;
      if (localStorage.getItem(deprecatedKey)) {
        localStorage.removeItem(deprecatedKey);
      }

      const key = this._getLocalKey(examType);
      const sessionData = localStorage.getItem(key);
      if (sessionData) {
        const session = JSON.parse(sessionData);
        
        const sessionAge = Date.now() - new Date(session.lastSaved).getTime();
        if (sessionAge < 24 * 60 * 60 * 1000) {
          console.log('✅ Restored local session for', examType, '- Age:', Math.round(sessionAge / 1000), 'seconds');
          return {
            examId: session.examId,
            examType,
            currentQuestion: session.currentQuestion || 0,
            answers: session.answers || {},
            timeLeft: session.timeLeft || 0,
            timeSpent: session.timeSpent || 0,
            isLocal: true 
          };
        } else {

          console.log('🕐 Local session expired for', examType);
          localStorage.removeItem(key);
        }
      }
    } catch (error) {
      console.error('Error loading local session:', error);
    }
    return null;
  }

  _saveLocalSession(examType, currentQuestion, answers, timeLeft) {
    try {
      const sessionData = {
        examId: this.currentSession?.examId || this.currentSession?._id,
        examType,
        currentQuestion,
        answers,
        timeLeft,
        timeSpent: this.currentSession?.timeSpent || 0,
        lastSaved: new Date().toISOString()
      };
      const key = this._getLocalKey(examType);
      localStorage.setItem(key, JSON.stringify(sessionData));
    } catch (error) {
      console.error('Error saving local session:', error);
    }
  }

  _clearLocalSession(examType) {
    try {
      const key = this._getLocalKey(examType);
      localStorage.removeItem(key);
    } catch (error) {
      console.error('Error clearing local session:', error);
    }
  }
}

const examSessionManager = new ExamSessionManager();

export default examSessionManager;
