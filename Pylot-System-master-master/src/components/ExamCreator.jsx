import React, { useState, useEffect } from 'react';
import { api } from '../utils/api';
import './ExamCreator.css';


const examDifficulties = ['Beginner', 'Intermediate', 'Advanced'];
const examSubjects = ['Python', 'JavaScript', 'Java', 'C++', 'Web Development', 'Data Science'];
const examStatuses = ['Active', 'Hidden', 'Draft'];


const validateExam = (exam) => {
  const errors = [];
  if (!exam.title?.trim()) errors.push('Title is required');
  if (!exam.subject) errors.push('Subject is required');
  if (!exam.difficulty) errors.push('Difficulty is required');
  if (!exam.timeLimit || exam.timeLimit < 1) errors.push('Time limit must be at least 1 minute');
  if (!exam.passingScore || exam.passingScore < 0 || exam.passingScore > 100) errors.push('Passing score must be between 0-100%');
  if (!exam.questions || exam.questions.length === 0) errors.push('At least one question is required');
  return errors;
};

const validateQuestion = (question) => {
  const errors = [];
  if (!question.question?.trim()) errors.push('Question text is required');
  if (!question.options || question.options.length < 2) errors.push('At least 2 options are required');
  if (!question.correctAnswer?.trim()) errors.push('Correct answer is required');
  return errors;
};

const ExamCreator = ({ exam, onSave, onCancel, isEditing = false }) => {
  const [formData, setFormData] = useState({
    title: '',
    description: '',
    subject: '',
    difficulty: '',
    status: 'Draft',
    timeLimit: 10,
    totalQuestions: 0,
    passingScore: 70,
    questions: []
  });

  const [currentQuestion, setCurrentQuestion] = useState(0);
  const [errors, setErrors] = useState([]);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    if (exam && isEditing) {
      setFormData({
        ...exam,
        questions: exam.questions || []
      });
    }
  }, [exam, isEditing]);

  
  const questions = formData.questions || [];

  const handleInputChange = (field, value) => {
    setFormData(prev => ({
      ...prev,
      [field]: value
    }));
    
    if (errors.length > 0) {
      setErrors([]);
    }
  };

  const handleQuestionChange = (questionIndex, field, value) => {
    const updatedQuestions = [...questions];
    const currentQuestion = updatedQuestions[questionIndex] || {
      question: '',
      options: ['', '', '', ''],
      correct: 0,
      explanation: ''
    };
    
    if (field === 'options') {
      updatedQuestions[questionIndex] = {
        ...currentQuestion,
        options: value
      };
    } else {
      updatedQuestions[questionIndex] = {
        ...currentQuestion,
        [field]: value
      };
    }
    
    setFormData(prev => ({
      ...prev,
      questions: updatedQuestions
    }));
  };

  const addQuestion = () => {
    const newQuestion = {
      id: Date.now(),
      question: '',
      options: ['', '', '', ''],
      correct: 0,
      explanation: ''
    };
    setFormData(prev => ({
      ...prev,
      questions: [...questions, newQuestion],
      totalQuestions: questions.length + 1
    }));
    setCurrentQuestion(questions.length);
  };

  
  useEffect(() => {
    if (!isEditing && questions.length === 0) {
      addQuestion();
    }
  }, [isEditing]);

  const removeQuestion = (index) => {
    if (questions.length > 1) {
      const updatedQuestions = questions.filter((_, i) => i !== index);
      setFormData(prev => ({
        ...prev,
        questions: updatedQuestions,
        totalQuestions: updatedQuestions.length
      }));
      if (currentQuestion >= updatedQuestions.length) {
        setCurrentQuestion(updatedQuestions.length - 1);
      }
    }
  };

  const duplicateQuestion = (index) => {
    const questionToDuplicate = { ...questions[index] };
    questionToDuplicate.id = Date.now();
    questionToDuplicate.question = questionToDuplicate.question + ' (Copy)';
    
    const updatedQuestions = [...questions];
    updatedQuestions.splice(index + 1, 0, questionToDuplicate);
    
    setFormData(prev => ({
      ...prev,
      questions: updatedQuestions,
      totalQuestions: updatedQuestions.length
    }));
    setCurrentQuestion(index + 1);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setIsSubmitting(true);
    
    const validationErrors = validateExam(formData, false); 
    if (validationErrors.length > 0) {
      setErrors(validationErrors);
      setIsSubmitting(false);
      return;
    }

    try {
      
      const processedData = {
        ...formData,
        questions: formData.questions.map(question => ({
          ...question,
          correctAnswer: question.options[question.correct] || '',
          id: question.id || Date.now().toString()
        }))
      };
      await onSave(processedData);
    } catch (error) {
      setErrors(['Failed to save exam. Please try again.']);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleSaveDraft = async () => {
    setIsSubmitting(true);
    
    const validationErrors = validateExam(formData, true); 
    if (validationErrors.length > 0) {
      setErrors(validationErrors);
      setIsSubmitting(false);
      return;
    }

    try {
      
      const processedData = {
        ...formData,
        status: 'Draft',
        questions: formData.questions.map(question => ({
          ...question,
          correctAnswer: question.options[question.correct] || '',
          id: question.id || Date.now().toString()
        }))
      };
      await onSave(processedData);
    } catch (error) {
      setErrors(['Failed to save draft. Please try again.']);
    } finally {
      setIsSubmitting(false);
    }
  };

  
  const safeCurrentQuestion = Math.max(0, Math.min(currentQuestion, questions.length - 1));
  const question = questions[safeCurrentQuestion] || {
    question: '',
    options: ['', '', '', ''],
    correct: 0,
    explanation: ''
  };

  return (
    <div className="exam-creator">
      <div className="exam-creator-header">
        <h2>{isEditing ? 'Edit Exam' : 'Create New Exam'}</h2>
        <div className="exam-creator-actions">
          <button 
            type="button" 
            className="cancel-btn"
            onClick={onCancel}
          >
            Cancel
          </button>
          <button 
            type="button" 
            className="save-draft-btn"
            onClick={handleSaveDraft}
            disabled={isSubmitting}
          >
            {isSubmitting ? 'Saving...' : 'Save Draft'}
          </button>
        </div>
      </div>

      {errors.length > 0 && (
        <div className="error-messages">
          <h4>Please fix the following errors:</h4>
          <ul>
            {errors.map((error, index) => (
              <li key={index}>{error}</li>
            ))}
          </ul>
        </div>
      )}

      <form onSubmit={handleSubmit} className="exam-form">
        {}
        <div className="form-section">
          <h3>Basic Information</h3>
          <div className="form-grid">
            <div className="form-group">
              <label htmlFor="title">Exam Title *</label>
              <input
                type="text"
                id="title"
                value={formData.title}
                onChange={(e) => handleInputChange('title', e.target.value)}
                placeholder="Enter exam title"
                required
              />
            </div>

            <div className="form-group">
              <label htmlFor="subject">Subject *</label>
              <select
                id="subject"
                value={formData.subject}
                onChange={(e) => handleInputChange('subject', e.target.value)}
                required
              >
                <option value="">Select Subject</option>
                {examSubjects.map(subject => (
                  <option key={subject} value={subject}>{subject}</option>
                ))}
              </select>
            </div>

            <div className="form-group">
              <label htmlFor="difficulty">Difficulty Level *</label>
              <select
                id="difficulty"
                value={formData.difficulty}
                onChange={(e) => handleInputChange('difficulty', e.target.value)}
                required
              >
                <option value="">Select Difficulty</option>
                {examDifficulties.map(difficulty => (
                  <option key={difficulty} value={difficulty}>{difficulty}</option>
                ))}
              </select>
            </div>

            <div className="form-group">
              <label htmlFor="status">Status</label>
              <select
                id="status"
                value={formData.status}
                onChange={(e) => handleInputChange('status', e.target.value)}
              >
                {examStatuses.map(status => (
                  <option key={status} value={status}>{status}</option>
                ))}
              </select>
            </div>

            <div className="form-group">
              <label htmlFor="timeLimit">Time Limit (minutes) *</label>
              <input
                type="number"
                id="timeLimit"
                value={formData.timeLimit}
                onChange={(e) => handleInputChange('timeLimit', parseInt(e.target.value))}
                min="1"
                required
              />
            </div>

            <div className="form-group">
              <label htmlFor="passingScore">Passing Score (%) *</label>
              <input
                type="number"
                id="passingScore"
                value={formData.passingScore}
                onChange={(e) => handleInputChange('passingScore', parseInt(e.target.value))}
                min="0"
                max="100"
                required
              />
            </div>
          </div>

          <div className="form-group full-width">
            <label htmlFor="description">Description *</label>
            <textarea
              id="description"
              value={formData.description}
              onChange={(e) => handleInputChange('description', e.target.value)}
              placeholder="Enter exam description"
              rows="3"
              required
            />
          </div>
        </div>

        {}
        <div className="form-section">
          <div className="questions-header">
            <h3>Questions ({questions.length})</h3>
            <button
              type="button"
              className="admin-btn admin-btn--success add-question-btn"
              onClick={addQuestion}
            >
              + Add Question
            </button>
          </div>

          {questions.length > 0 && (
            <div className="questions-navigation">
              <div className="question-tabs">
                {questions.map((_, index) => (
                  <button
                    key={index}
                    type="button"
                    className={`question-tab ${index === safeCurrentQuestion ? 'active' : ''}`}
                    onClick={() => setCurrentQuestion(index)}
                  >
                    Q{index + 1}
                  </button>
                ))}
              </div>
              <div className="question-actions">
                <button
                  type="button"
                  className="admin-btn admin-btn--outline admin-btn--small duplicate-btn"
                  onClick={() => duplicateQuestion(safeCurrentQuestion)}
                >
                  Duplicate
                </button>
                  <button
                    type="button"
                    className="admin-btn admin-btn--danger admin-btn--small delete-btn"
                    onClick={() => removeQuestion(safeCurrentQuestion)}
                    disabled={questions.length <= 1}
                  >
                    Delete
                  </button>
              </div>
            </div>
          )}

          {question && (
            <div className="question-editor">
              <div className="form-group">
                <label>Question Text *</label>
                <textarea
                  value={question.question || ''}
                  onChange={(e) => handleQuestionChange(safeCurrentQuestion, 'question', e.target.value)}
                  placeholder="Enter your question here..."
                  rows="3"
                  required
                />
              </div>

              <div className="options-section">
                <label>Answer Options *</label>
                {(question.options || []).map((option, optionIndex) => (
                  <div key={optionIndex} className="option-input-group">
                    <input
                      type="radio"
                      name={`correct-${safeCurrentQuestion}`}
                      checked={question.correct === optionIndex}
                      onChange={() => handleQuestionChange(safeCurrentQuestion, 'correct', optionIndex)}
                    />
                    <input
                      type="text"
                      value={option}
                      onChange={(e) => {
                        const newOptions = [...(question.options || [])];
                        newOptions[optionIndex] = e.target.value;
                        handleQuestionChange(safeCurrentQuestion, 'options', newOptions);
                      }}
                      placeholder={`Option ${optionIndex + 1}`}
                      required
                    />
                  </div>
                ))}
              </div>

              <div className="form-group">
                <label>Explanation *</label>
                <textarea
                  value={question.explanation || ''}
                  onChange={(e) => handleQuestionChange(safeCurrentQuestion, 'explanation', e.target.value)}
                  placeholder="Explain why this is the correct answer..."
                  rows="2"
                  required
                />
              </div>
            </div>
          )}
        </div>

        <div className="form-actions">
          <button
            type="button"
            className="admin-btn admin-btn--ghost cancel-btn"
            onClick={onCancel}
          >
            Cancel
          </button>
          <button
            type="submit"
            className="admin-btn admin-btn--primary save-btn"
            disabled={isSubmitting}
          >
            {isSubmitting ? 'Saving...' : (isEditing ? 'Update Exam' : 'Create Exam')}
          </button>
        </div>
      </form>
    </div>
  );
};

export default ExamCreator;

