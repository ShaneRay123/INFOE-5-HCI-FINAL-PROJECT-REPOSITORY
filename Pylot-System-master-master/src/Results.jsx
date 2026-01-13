import React, { useState, useEffect, useMemo } from "react";
import "./pages/admin/AdminPage.css";
import { api } from "./utils/api";

function Results() {
  const [allResults, setAllResults] = useState({});
  const [isLoading, setIsLoading] = useState(true);
  const [query, setQuery] = useState("");
  const [showPass, setShowPass] = useState(true);
  const [showFail, setShowFail] = useState(true);
  const [selectedExam, setSelectedExam] = useState("all");
  const [selectedUser, setSelectedUser] = useState("all");
  const [dateRange, setDateRange] = useState("all");
  const [viewMode, setViewMode] = useState("table"); 

  
  useEffect(() => {
    try {
      const cached = JSON.parse(localStorage.getItem('adminResultsCache') || '{}');
      if (cached && typeof cached === 'object' && Object.keys(cached).length > 0) {
        setAllResults(cached);
        setIsLoading(false);
      }
    } catch {}

    loadAllResults();

    
    const intervalId = setInterval(() => {
      loadAllResults();
    }, 60000); 

    return () => clearInterval(intervalId);
  }, []);

  const loadAllResults = async () => {
    setIsLoading(true);
    try {
      const backendResults = await api.get('/api/results').catch(() => null);
      if (Array.isArray(backendResults) && backendResults.length > 0) {
        const grouped = backendResults.reduce((acc, r) => {
          const username = r.username || 'unknown';
          acc[username] = acc[username] || [];
          acc[username].push({
            id: r._id || r.id || `${username}-${r.examId || 'unknown'}-${r.createdAt || r.timestamp || Date.now()}`,
            timestamp: r.createdAt || r.timestamp,
            examId: r.examId,
            examTitle: r.examTitle,
            examSubject: r.examSubject,
            examDifficulty: r.examDifficulty,
            totalQuestions: r.totalQuestions,
            correctAnswers: r.correctAnswers,
            percentage: r.percentage,
            passed: r.passed,
            timeSpent: r.timeSpent,
            passingScore: r.passingScore,
            answers: r.answers || {},
          });
          return acc;
        }, {});
        setAllResults(grouped);
        try {
          localStorage.setItem('adminResultsCache', JSON.stringify(grouped));
        } catch {}
      } else {
        
        setAllResults({});
      }
    } catch (error) {
      console.error('Error loading results:', error);
      
      try {
        const cached = JSON.parse(localStorage.getItem('adminResultsCache') || '{}');
        if (cached && typeof cached === 'object') {
          setAllResults(prev => (Object.keys(prev || {}).length > 0 ? prev : cached));
        }
      } catch {}
    } finally {
      setIsLoading(false);
    }
  };

  const deleteResult = async (username, result) => {
    
    try {
      const ok = window.confirm(`Are you sure you want to delete this result for ${username}? This action cannot be undone.`);
      if (!ok) return; 
      
      
      setAllResults(prev => {
        const updated = { ...prev };
        if (updated[username]) {
          updated[username] = updated[username].filter(r => r.id !== result.id);
          if (updated[username].length === 0) {
            delete updated[username];
          }
        }
        return updated;
      });
      
      
      if (result.id) {
        await api.del(`/api/results/${result.id}`).catch(() => { throw new Error('API delete failed'); });
      }
      
      
      try { alert('Result deleted'); } catch {}
    } catch (e) {
      console.error('Error deleting result:', e);
      
      await loadAllResults();
      try { alert('Failed to delete result'); } catch {}
    }
  };

  
  const flattenedResults = useMemo(() => {
    const results = [];
    Object.entries(allResults).forEach(([username, userResults]) => {
      userResults.forEach(result => {
        results.push({
          ...result,
          username,
          candidate: username
        });
      });
    });
    return results.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
  }, [allResults]);

  
  const uniqueExams = useMemo(() => {
    const exams = [...new Set(flattenedResults.map(r => r.examTitle))];
    return exams.sort();
  }, [flattenedResults]);

  const uniqueUsers = useMemo(() => {
    const users = [...new Set(flattenedResults.map(r => r.username))];
    return users.sort();
  }, [flattenedResults]);

  
  const filteredResults = useMemo(() => {
    let filtered = flattenedResults;

    
    if (query.trim()) {
      const q = query.trim().toLowerCase();
      filtered = filtered.filter(r =>
        r.candidate?.toLowerCase().includes(q) ||
        r.examTitle?.toLowerCase().includes(q) ||
        r.examSubject?.toLowerCase().includes(q) ||
        r.examDifficulty?.toLowerCase().includes(q) ||
        String(r.percentage).includes(q) ||
        r.timestamp?.includes(q) ||
        (r.passed ? 'pass' : 'fail').includes(q)
      );
    }

    
    if (showPass && !showFail) {
      filtered = filtered.filter(r => r.passed);
    } else if (!showPass && showFail) {
      filtered = filtered.filter(r => !r.passed);
    } else if (!showPass && !showFail) {
      filtered = [];
    }

    
    if (selectedExam !== "all") {
      filtered = filtered.filter(r => r.examTitle === selectedExam);
    }

    
    if (selectedUser !== "all") {
      filtered = filtered.filter(r => r.username === selectedUser);
    }

    
    if (dateRange !== "all") {
      const now = new Date();
      const filterDate = new Date();
      
      switch (dateRange) {
        case "today":
          filterDate.setHours(0, 0, 0, 0);
          break;
        case "week":
          filterDate.setDate(now.getDate() - 7);
          break;
        case "month":
          filterDate.setMonth(now.getMonth() - 1);
          break;
        case "year":
          filterDate.setFullYear(now.getFullYear() - 1);
          break;
        default:
          
          break;
      }
      
      filtered = filtered.filter(r => new Date(r.timestamp) >= filterDate);
    }

    return filtered;
  }, [flattenedResults, query, showPass, showFail, selectedExam, selectedUser, dateRange]);

  
  const analytics = useMemo(() => {
    const total = filteredResults.length;
    const passed = filteredResults.filter(r => r.passed).length;
    const failed = total - passed;
    const averageScore = total > 0 ? Math.round(filteredResults.reduce((sum, r) => sum + r.percentage, 0) / total) : 0;
    
    
    const scoreRanges = {
      "90-100": filteredResults.filter(r => r.percentage >= 90).length,
      "80-89": filteredResults.filter(r => r.percentage >= 80 && r.percentage < 90).length,
      "70-79": filteredResults.filter(r => r.percentage >= 70 && r.percentage < 80).length,
      "60-69": filteredResults.filter(r => r.percentage >= 60 && r.percentage < 70).length,
      "0-59": filteredResults.filter(r => r.percentage < 60).length
    };

    
    const examStats = {};
    filteredResults.forEach(r => {
      if (!examStats[r.examTitle]) {
        examStats[r.examTitle] = { total: 0, passed: 0, totalScore: 0 };
      }
      examStats[r.examTitle].total++;
      if (r.passed) examStats[r.examTitle].passed++;
      examStats[r.examTitle].totalScore += r.percentage;
    });

    
    const userStats = {};
    filteredResults.forEach(r => {
      if (!userStats[r.username]) {
        userStats[r.username] = { total: 0, passed: 0, totalScore: 0 };
      }
      userStats[r.username].total++;
      if (r.passed) userStats[r.username].passed++;
      userStats[r.username].totalScore += r.percentage;
    });

    return {
      total,
      passed,
      failed,
      averageScore,
      passRate: total > 0 ? Math.round((passed / total) * 100) : 0,
      scoreRanges,
      examStats,
      userStats
    };
  }, [filteredResults]);

  const formatDate = (timestamp) => {
    return new Date(timestamp).toLocaleDateString() + ' ' + new Date(timestamp).toLocaleTimeString();
  };

  const formatTime = (seconds) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  };

  if (isLoading) {
    return (
      <div>
        <div className="page-header">
          <h2>Results Dashboard</h2>
        </div>
        <div style={{ textAlign: 'center', padding: '40px' }}>
          <p>Loading results...</p>
        </div>
      </div>
    );
  }

  return (
    <div>
      <div className="page-header">
        <h2>Results Dashboard</h2>
        <div style={{ display: 'flex', gap: '10px' }}>
          <button
            className={`admin-btn ${viewMode === 'table' ? 'admin-btn--primary' : 'admin-btn--ghost'}`}
            onClick={() => setViewMode('table')}
          >
            Table View
          </button>
          <button
            className={`admin-btn ${viewMode === 'analytics' ? 'admin-btn--primary' : 'admin-btn--ghost'}`}
            onClick={() => setViewMode('analytics')}
          >
            Analytics
          </button>
        </div>
      </div>

      {}
      <div className="filters" style={{ marginBottom: '20px' }}>
        <div style={{ 
          display: 'flex', 
          gap: '10px', 
          alignItems: 'center', 
          flexWrap: 'nowrap',
          overflowX: 'auto',
          paddingBottom: '5px'
        }}>
          <input
            type="text"
            placeholder="Search..."
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            style={{ 
              width: '180px', 
              minWidth: '150px',
              padding: '6px 8px', 
              borderRadius: '6px', 
              border: '1px solid #ddd',
              fontSize: '13px'
            }}
          />
          
          <select
            value={selectedExam}
            onChange={(e) => setSelectedExam(e.target.value)}
            style={{ 
              padding: '6px 8px', 
              borderRadius: '6px', 
              border: '1px solid #ddd',
              fontSize: '13px',
              minWidth: '120px'
            }}
          >
            <option value="all">All Exams</option>
            {uniqueExams.map(exam => (
              <option key={exam} value={exam}>{exam}</option>
            ))}
          </select>

          <select
            value={selectedUser}
            onChange={(e) => setSelectedUser(e.target.value)}
            style={{ 
              padding: '6px 8px', 
              borderRadius: '6px', 
              border: '1px solid #ddd',
              fontSize: '13px',
              minWidth: '120px'
            }}
          >
            <option value="all">All Users</option>
            {uniqueUsers.map(user => (
              <option key={user} value={user}>{user}</option>
            ))}
          </select>

          <select
            value={dateRange}
            onChange={(e) => setDateRange(e.target.value)}
            style={{ 
              padding: '6px 8px', 
              borderRadius: '6px', 
              border: '1px solid #ddd',
              fontSize: '13px',
              minWidth: '100px'
            }}
          >
            <option value="all">All Time</option>
            <option value="today">Today</option>
            <option value="week">Last 7 Days</option>
            <option value="month">Last Month</option>
            <option value="year">Last Year</option>
          </select>

          <label style={{ 
            display: 'flex', 
            alignItems: 'center', 
            gap: '4px',
            fontSize: '13px',
            whiteSpace: 'nowrap'
          }}>
            <input 
              type="checkbox" 
              checked={showPass} 
              onChange={() => setShowPass(!showPass)}
              style={{ transform: 'scale(0.9)' }}
            />
            Pass
          </label>
          <label style={{ 
            display: 'flex', 
            alignItems: 'center', 
            gap: '4px',
            fontSize: '13px',
            whiteSpace: 'nowrap'
          }}>
            <input 
              type="checkbox" 
              checked={showFail} 
              onChange={() => setShowFail(!showFail)}
              style={{ transform: 'scale(0.9)' }}
            />
            Fail
          </label>
        </div>
      </div>

      {}
      {viewMode === 'analytics' && (
        <div style={{ marginBottom: '30px' }}>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '20px', marginBottom: '30px' }}>
            <div style={{ background: '#f8f9fa', padding: '20px', borderRadius: '8px', textAlign: 'center' }}>
              <h3 style={{ margin: '0 0 10px 0', color: '#333' }}>Total Attempts</h3>
              <div style={{ fontSize: '32px', fontWeight: 'bold', color: '#3b1769' }}>{analytics.total}</div>
            </div>
            <div style={{ background: '#f8f9fa', padding: '20px', borderRadius: '8px', textAlign: 'center' }}>
              <h3 style={{ margin: '0 0 10px 0', color: '#333' }}>Pass Rate</h3>
              <div style={{ fontSize: '32px', fontWeight: 'bold', color: '#28a745' }}>{analytics.passRate}%</div>
            </div>
            <div style={{ background: '#f8f9fa', padding: '20px', borderRadius: '8px', textAlign: 'center' }}>
              <h3 style={{ margin: '0 0 10px 0', color: '#333' }}>Average Score</h3>
              <div style={{ fontSize: '32px', fontWeight: 'bold', color: '#6f42c1' }}>{analytics.averageScore}%</div>
            </div>
            <div style={{ background: '#f8f9fa', padding: '20px', borderRadius: '8px', textAlign: 'center' }}>
              <h3 style={{ margin: '0 0 10px 0', color: '#333' }}>Failed Attempts</h3>
              <div style={{ fontSize: '32px', fontWeight: 'bold', color: '#dc3545' }}>{analytics.failed}</div>
            </div>
          </div>

          {}
          <div style={{ background: '#f8f9fa', padding: '20px', borderRadius: '8px', marginBottom: '20px' }}>
            <h3>Score Distribution</h3>
            <div style={{ display: 'flex', gap: '20px', flexWrap: 'wrap' }}>
              {Object.entries(analytics.scoreRanges).map(([range, count]) => (
                <div key={range} style={{ textAlign: 'center' }}>
                  <div style={{ fontSize: '24px', fontWeight: 'bold', color: '#3b1769' }}>{count}</div>
                  <div style={{ fontSize: '14px', color: '#666' }}>{range}%</div>
                </div>
              ))}
            </div>
          </div>

          {}
          <div style={{ background: '#f8f9fa', padding: '20px', borderRadius: '8px', marginBottom: '20px' }}>
            <h3>Exam Performance</h3>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: '15px' }}>
              {Object.entries(analytics.examStats).map(([exam, stats]) => (
                <div key={exam} style={{ background: 'white', padding: '15px', borderRadius: '6px', border: '1px solid #e0e0e0' }}>
                  <h4 style={{ margin: '0 0 10px 0' }}>{exam}</h4>
                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '14px' }}>
                    <span>Attempts: {stats.total}</span>
                    <span>Pass Rate: {Math.round((stats.passed / stats.total) * 100)}%</span>
                    <span>Avg Score: {Math.round(stats.totalScore / stats.total)}%</span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {}
      <div className="exams-table-container">
        <table className="exam-table">
          <thead>
            <tr>
              <th>Candidate</th>
              <th>Assessment Type</th>
              <th>Score</th>
              <th>Status</th>
              <th>Time Spent</th>
              <th>Date & Time</th>
              <th>Attempt #</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {filteredResults.map((result, index) => {
              
              const userExamResults = flattenedResults
                .filter(r => r.username === result.username && r.examTitle === result.examTitle)
                .sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp));
              const attemptNumber = userExamResults.findIndex(r => r.id === result.id) + 1;

              return (
                <tr key={result.id}>
                  <td>{result.candidate}</td>
                  <td>{result.examSubject || '—'}</td>
                  <td>
                    <span style={{ 
                      fontWeight: 'bold', 
                      color: result.percentage >= 70 ? '#28a745' : '#dc3545' 
                    }}>
                      {result.percentage}%
                    </span>
                  </td>
                  <td>
                    <span className={`status ${result.passed ? 'active' : 'hidden'}`}>
                      {result.passed ? 'PASS' : 'FAIL'}
                    </span>
                  </td>
                  <td>{formatTime(result.timeSpent)}</td>
                  <td>{formatDate(result.timestamp)}</td>
                  <td>
                    <span style={{ 
                      background: '#e9ecef', 
                      padding: '4px 8px', 
                      borderRadius: '4px', 
                      fontSize: '12px' 
                    }}>
                      #{attemptNumber}
                    </span>
                  </td>
                  <td>
                    <button
                      className="admin-btn admin-btn--danger"
                      onClick={() => deleteResult(result.username || result.candidate, result)}
                    >
                      Delete
                    </button>
                  </td>
                </tr>
              );
            })}
            {filteredResults.length === 0 && (
              <tr>
                <td colSpan="8" style={{ textAlign: "center", padding: "40px", color: "#777" }}>
                  No results found matching your criteria
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {}
      {filteredResults.length > 0 && (
        <div style={{ 
          marginTop: '20px', 
          padding: '15px', 
          background: '#f8f9fa', 
          borderRadius: '8px',
          fontSize: '14px',
          color: '#666'
        }}>
          Showing {filteredResults.length} result{filteredResults.length !== 1 ? 's' : ''} 
          {analytics.total !== filteredResults.length && ` of ${analytics.total} total`}
        </div>
      )}
    </div>
  );
}

export default Results;

