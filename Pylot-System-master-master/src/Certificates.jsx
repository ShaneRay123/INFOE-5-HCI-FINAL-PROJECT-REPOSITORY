import React, { useMemo, useState, useEffect } from "react";
import "./pages/admin/AdminPage.css";
import { api } from "./utils/api";

function Certificates() {
  const [certificates, setCertificates] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState("");

  const responsiveStyles = `
    @media (max-width: 1024px) {
      .certificates-layout {
        grid-template-columns: 1fr !important;
        gap: 15px !important;
      }
      
      .certificate-preview {
        min-height: 400px !important;
        max-height: 500px !important;
        padding: 20px 15px !important;
      }
    }
    
    @media (max-width: 768px) {
      .certificates-layout {
        grid-template-columns: 1fr !important;
        gap: 15px !important;
      }
      
      .certificate-preview {
        min-height: 350px !important;
        max-height: 450px !important;
        padding: 15px 10px !important;
      }
      
      .exam-table {
        font-size: 12px !important;
      }
      
      .exam-table th,
      .exam-table td {
        padding: 8px 4px !important;
      }
      
      .exam-table th:nth-child(1),
      .exam-table td:nth-child(1) {
        width: 15% !important;
      }
      
      .exam-table th:nth-child(2),
      .exam-table td:nth-child(2) {
        width: 20% !important;
      }
      
      .exam-table th:nth-child(3),
      .exam-table td:nth-child(3) {
        width: 25% !important;
      }
      
      .exam-table th:nth-child(4),
      .exam-table td:nth-child(4) {
        width: 15% !important;
      }
      
      .exam-table th:nth-child(5),
      .exam-table td:nth-child(5) {
        width: 25% !important;
      }
    }
    
    @media (max-width: 480px) {
      .certificate-preview {
        min-height: 300px !important;
        max-height: 400px !important;
        padding: 10px 8px !important;
      }
      
      .exam-table {
        font-size: 11px !important;
      }
      
      .exam-table th,
      .exam-table td {
        padding: 6px 2px !important;
      }
      
      .filters {
        flex-direction: column !important;
        gap: 10px !important;
      }
      
      .filters input {
        max-width: 100% !important;
      }
    }
    
    
    @media (max-width: 768px) {
      .certificates-layout > div:first-child {
        max-height: 60vh !important;
        overflow-x: auto !important;
      }
    }
  `;
  useEffect(() => {
    loadCertificates();
  }, []);

  const loadCertificates = async () => {
    try {
      setIsLoading(true);
      setError("");
      
      
      const users = await api.get('/admin/users').catch(() => []);
      const userMap = new Map(users.map(user => [user.username, user]));
      
      
      const existingCertificates = await api.get('/api/certificates').catch(() => ({ certificates: [] }));
      const certMap = new Map(existingCertificates.certificates?.map(cert => [cert.username, cert]) || []);
      
      
      const certificateData = [];
      
      
      for (const cert of existingCertificates.certificates || []) {
        const user = userMap.get(cert.username) || { username: cert.username, fullName: cert.fullName };
        certificateData.push({
          id: cert._id,
          candidate: cert.username,
          fullName: cert.fullName || user.fullName || 'N/A',
          age: user.age || 'N/A',
          gender: user.gender || 'N/A',
          address: user.address || 'N/A',
          exam: 'Assessment',
          score: cert.postAssessmentScore || 0,
          date: new Date(cert.completionDate).toLocaleDateString(),
          certificateId: cert.certificateId,
          hasGenerated: true,
          preScore: cert.preAssessmentScore || 0,
          postScore: cert.postAssessmentScore || 0,
          improvement: cert.improvementScore || 0,
          modulesCompleted: cert.modulesCompleted || 0,
          user: user
        });
      }
      

      for (const user of users) {
        
        if (certMap.has(user.username)) continue;
        
        try {
          
          const eligibilityData = await api.get(`/api/certificates/eligibility/${user.username}`);
          
          if (eligibilityData.success && eligibilityData.isEligible) {
            const userProgress = eligibilityData.userProgress;
            
            certificateData.push({
              id: `eligible-${user.username}`,
              candidate: user.username,
              fullName: user.fullName || 'N/A',
              age: user.age || 'N/A',
              gender: user.gender || 'N/A',
              address: user.address || 'N/A',
              exam: 'Assessment',
              score: userProgress.postAssessmentScore || 0,
              date: 'Ready to Generate',
              certificateId: 'Not Generated',
              hasGenerated: false,
              preScore: userProgress.preAssessmentScore || 0,
              postScore: userProgress.postAssessmentScore || 0,
              improvement: (userProgress.postAssessmentScore || 0) - (userProgress.preAssessmentScore || 0),
              modulesCompleted: userProgress.modulesCompleted || 0,
              user: user
            });
          }
        } catch (err) {
          const msg = String(err.message || '').toLowerCase();

          if (msg.includes('not found') || msg.includes('user progress not found')) {
            continue;
          }

          console.warn(`Failed to check eligibility for ${user.username}:`, err);
        }
      }
      
      setCertificates(certificateData);
    } catch (error) {
      setError('Failed to load certificates');
      setCertificates([]);
    } finally {
      setIsLoading(false);
    }
  };

  const [query, setQuery] = useState("");
  const [selectedId, setSelectedId] = useState(null);

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return certificates;
    return certificates.filter((c) =>
      [
        c.candidate.toLowerCase(),
        c.exam.toLowerCase(),
        String(c.score),
        c.date,
        c.certificateId.toLowerCase(),
      ].some((v) => v.includes(q))
    );
  }, [certificates, query]);

  const selected = useMemo(
    () => filtered.find((c) => c.id === selectedId) || filtered[0] || null,
    [filtered, selectedId]
  );

  const handlePrint = () => {
    if (!selected) {
      alert('Please select a certificate to print');
      return;
    }

    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
      <!DOCTYPE html>
      <html>
        <head>
          <title>Certificate - ${selected.fullName}</title>
          <style>
            @page {
              size: A4 landscape;
              margin: 0.5in;
            }
            body {
              margin: 0;
              padding: 0;
              font-family: 'Georgia', serif;
              background: white;
            }
            .certificate-print {
              width: 100%;
              height: 100vh;
              background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
              border: 3px solid #FFD700;
              border-radius: 20px;
              padding: 40px;
              box-sizing: border-box;
              display: flex;
              flex-direction: column;
              justify-content: space-between;
              position: relative;
              overflow: hidden;
            }
            .certificate-print::before {
              content: '';
              position: absolute;
              top: 0;
              left: 0;
              right: 0;
              bottom: 0;
              background: linear-gradient(45deg, #FFD700, #FFA500, #FFD700, #FFA500);
              border-radius: 20px;
              z-index: -1;
            }
            .header {
              text-align: center;
              z-index: 3;
              position: relative;
            }
            .logo {
              width: 80px;
              height: 80px;
              background: linear-gradient(135deg, #FFD700, #FFA500);
              margin: 0 auto 20px;
              border-radius: 50%;
              display: flex;
              align-items: center;
              justify-content: center;
              color: #1a1a2e;
              font-weight: bold;
              font-size: 20px;
              border: 3px solid #fff;
            }
            .title {
              font-size: 36px;
              font-weight: 300;
              color: #FFD700;
              margin-bottom: 10px;
              letter-spacing: 2px;
              text-transform: uppercase;
            }
            .subtitle {
              font-size: 20px;
              color: #FFA500;
              font-style: italic;
              letter-spacing: 1px;
            }
            .content {
              flex: 1;
              display: flex;
              flex-direction: column;
              justify-content: center;
              text-align: center;
              z-index: 3;
              position: relative;
            }
            .presented-text {
              font-size: 18px;
              color: #E8E8E8;
              margin-bottom: 20px;
            }
            .name {
              font-size: 40px;
              font-weight: bold;
              color: #FFD700;
              margin-bottom: 15px;
              text-shadow: 3px 3px 6px rgba(0,0,0,0.7);
            }
            .achievement {
              font-size: 18px;
              color: #D4D4D4;
              margin-bottom: 15px;
            }
            .course {
              font-size: 24px;
              font-weight: 600;
              color: #FFA500;
              margin-bottom: 20px;
            }
            .footer {
              display: flex;
              justify-content: space-between;
              align-items: flex-end;
              z-index: 3;
              position: relative;
            }
            .cert-id {
              font-size: 12px;
              color: #B8B8B8;
            }
            .seal {
              width: 60px;
              height: 60px;
              background: linear-gradient(135deg, #FFD700, #FFA500);
              border-radius: 50%;
              display: flex;
              align-items: center;
              justify-content: center;
              font-size: 10px;
              font-weight: bold;
              color: #1a1a2e;
              border: 2px solid #fff;
              flex-direction: column;
            }
            .organization {
              font-size: 12px;
              color: #D4D4D4;
              text-align: right;
            }
          </style>
        </head>
        <body>
          <div class="certificate-print">
            <div class="header">
              <div class="logo">PYlot</div>
              <div class="title">Certificate</div>
              <div class="subtitle">of Completion</div>
            </div>
            
            <div class="content">
              <div class="presented-text">This certificate is proudly presented to</div>
              <div class="name">${selected.fullName}</div>
              <div class="achievement">for outstanding achievement in</div>
              <div class="course">Python Programming & Development</div>
            </div>
            
            <div class="footer">
              <div class="cert-id">
                Certificate ID: ${selected.certificateId || 'PYL-' + Date.now().toString().slice(-6)}<br>
                Training Hours: ${selected.modulesCompleted * 2 || 16} hours
              </div>
              <div class="seal">
                <div>PYLOT</div>
                <div style="font-size: 6px;">CERT</div>
                <div style="font-size: 8px;">${new Date().getFullYear()}</div>
              </div>
              <div class="organization">PYlot Learning Academy</div>
            </div>
          </div>
        </body>
      </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
  };

  const handleDownload = async () => {
    if (!selected) {
      alert('Please select a certificate to download');
      return;
    }

    const downloadBtn = document.querySelector('.add-btn');
    const originalText = downloadBtn?.textContent;
    if (downloadBtn) {
      downloadBtn.textContent = 'Downloading...';
      downloadBtn.disabled = true;
    }

    try {

      if (!selected.hasGenerated) {
        const eligibilityData = await api.get(`/api/certificates/eligibility/${selected.candidate}`);
        
        if (!eligibilityData.success || !eligibilityData.isEligible) {
          const requirements = Object.entries(eligibilityData.requirements || {})
            .map(([key, value]) => `• ${key}: ${value ? '✓' : '✗'}`)
            .join('\n');
          alert(`${selected.fullName} is not eligible for a certificate yet.\n\nRequirements:\n${requirements}`);
          return;
        }
      }

      
      let response;
      if (selected.hasGenerated) {

        response = await fetch(`/api/certificates/generate/${selected.candidate}?force=true`, {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json'
          }
        });
      } else {

        response = await fetch(`/api/certificates/generate/${selected.candidate}`, {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json'
          }
        });
      }

      if (!response.ok) {
        let errorMessage;
        try {
          const errorData = await response.json();
          errorMessage = errorData.message || `HTTP ${response.status}: ${response.statusText}`;
        } catch {
          errorMessage = `HTTP ${response.status}: ${response.statusText}`;
        }
        throw new Error(errorMessage);
      }

      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/pdf')) {
        throw new Error('Server did not return a valid PDF file');
      }

      const blob = await response.blob();

      if (blob.size === 0) {
        throw new Error('Downloaded file is empty');
      }
      
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `Certificate_${selected.fullName.replace(/\s+/g, '_')}_${selected.candidate}.pdf`;
      link.style.display = 'none';
      document.body.appendChild(link);
      link.click();

      setTimeout(() => {
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
      }, 100);

      alert(`Certificate for ${selected.fullName} downloaded successfully!`);
      
    } catch (error) {
      console.error('Certificate download error:', error);

      let errorMessage = 'Failed to download certificate. ';
      if (error.message.includes('fetch')) {
        errorMessage += 'Please check your internet connection and try again.';
      } else if (error.message.includes('PDF')) {
        errorMessage += 'The server did not return a valid certificate file.';
      } else {
        errorMessage += error.message;
      }
      
      alert(errorMessage);
    } finally {

      if (downloadBtn) {
        downloadBtn.textContent = originalText;
        downloadBtn.disabled = false;
      }
    }
  };

  if (isLoading) {
    return (
      <div>
        <div className="page-header">
          <h2>Certificates</h2>
        </div>
        <div style={{ textAlign: 'center', padding: '40px', color: '#666' }}>
          Loading certificates...
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div>
        <div className="page-header">
          <h2>Certificates</h2>
        </div>
        <div style={{ textAlign: 'center', padding: '40px', color: '#dc3545' }}>
          <h3>Error</h3>
          <p>{error}</p>
          <button className="apply-btn" onClick={loadCertificates}>
            Retry
          </button>
        </div>
      </div>
    );
  }

  return (
    <div>
      <style>{responsiveStyles}</style>
      <div className="page-header">
        <h2>Certificates</h2>
      </div>
      <p style={{ color: '#666', marginBottom: '20px' }}>
        Certificates are automatically generated for users who have passed the post-assessment.
      </p>

      <div className="filters" style={{ justifyContent: "space-between" }}>
        <input
          type="text"
          placeholder="Search by candidate, exam, ID..."
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          style={{ flex: 1, maxWidth: 420, padding: 8, borderRadius: 8, border: "1px solid #ddd" }}
        />
        <div style={{ display: "flex", gap: 8 }}>
          <button className="apply-btn" onClick={handlePrint}>Print</button>
          <button className="add-btn" onClick={handleDownload}>Download</button>
        </div>
      </div>

      <div style={{ 
        display: "grid", 
        gridTemplateColumns: "minmax(0, 1fr) minmax(320px, 1fr)", 
        gap: 20,
        minHeight: "600px",
        alignItems: "start"
      }}
      className="certificates-layout"
      >
        <div style={{ 
          overflow: "auto",
          minWidth: 0,
          maxHeight: "80vh"
        }}>
          <div style={{ 
            overflow: "auto",
            border: "1px solid #e0e0e0",
            borderRadius: "8px",
            backgroundColor: "white"
          }}>
            <table className="exam-table" style={{ 
              width: "100%",
              tableLayout: "fixed",
              margin: 0,
              borderCollapse: "separate",
              borderSpacing: 0
            }}>
              <thead style={{ 
                position: "sticky", 
                top: 0, 
                backgroundColor: "#f8f9fa",
                zIndex: 10
              }}>
                <tr>
                  <th style={{ 
                    width: "18%", 
                    padding: "12px 8px",
                    borderBottom: "2px solid #dee2e6",
                    fontSize: "14px",
                    fontWeight: "600",
                    textAlign: "left"
                  }}>Candidate</th>
                  <th style={{ 
                    width: "22%", 
                    padding: "12px 8px",
                    borderBottom: "2px solid #dee2e6",
                    fontSize: "14px",
                    fontWeight: "600",
                    textAlign: "left"
                  }}>Full Name</th>
                  <th style={{ 
                    width: "20%", 
                    padding: "12px 8px",
                    borderBottom: "2px solid #dee2e6",
                    fontSize: "14px",
                    fontWeight: "600",
                    textAlign: "center"
                  }}>Pre/Post Score</th>
                  <th style={{ 
                    width: "15%", 
                    padding: "12px 8px",
                    borderBottom: "2px solid #dee2e6",
                    fontSize: "14px",
                    fontWeight: "600",
                    textAlign: "center"
                  }}>Status</th>
                  <th style={{ 
                    width: "25%", 
                    padding: "12px 8px",
                    borderBottom: "2px solid #dee2e6",
                    fontSize: "14px",
                    fontWeight: "600",
                    textAlign: "left"
                  }}>Certificate ID</th>
                </tr>
              </thead>
              <tbody>
                {filtered.map((c) => (
                  <tr
                    key={c.id}
                    onClick={() => setSelectedId(c.id)}
                    style={{ 
                      cursor: "pointer", 
                      background: selected?.id === c.id ? "#f8f4ff" : "transparent",
                      transition: "background-color 0.2s ease"
                    }}
                    onMouseEnter={(e) => {
                      if (selected?.id !== c.id) {
                        e.target.style.backgroundColor = "#f5f5f5";
                      }
                    }}
                    onMouseLeave={(e) => {
                      if (selected?.id !== c.id) {
                        e.target.style.backgroundColor = "transparent";
                      }
                    }}
                  >
                    <td style={{ 
                      padding: "10px 8px",
                      overflow: "hidden", 
                      textOverflow: "ellipsis", 
                      whiteSpace: "nowrap",
                      fontSize: "13px",
                      borderBottom: "1px solid #f0f0f0"
                    }}>{c.candidate}</td>
                    <td style={{ 
                      padding: "10px 8px",
                      overflow: "hidden", 
                      textOverflow: "ellipsis", 
                      whiteSpace: "nowrap",
                      fontSize: "13px",
                      borderBottom: "1px solid #f0f0f0"
                    }}>{c.fullName}</td>
                    <td style={{ 
                      padding: "10px 8px",
                      textAlign: "center",
                      borderBottom: "1px solid #f0f0f0"
                    }}>
                      <div style={{ fontSize: '12px', lineHeight: 1.4 }}>
                        <div style={{ marginBottom: "2px" }}>
                          Pre: <span className="status active" style={{ 
                            padding: "2px 6px", 
                            borderRadius: "4px",
                            fontSize: "11px"
                          }}>{c.preScore}%</span>
                        </div>
                        <div style={{ marginBottom: "2px" }}>
                          Post: <span className="status active" style={{ 
                            padding: "2px 6px", 
                            borderRadius: "4px",
                            fontSize: "11px"
                          }}>{c.postScore}%</span>
                        </div>
                        <div style={{ 
                          color: c.improvement > 0 ? '#38a169' : '#e53e3e',
                          fontWeight: "600",
                          fontSize: "11px"
                        }}>+{c.improvement}%</div>
                      </div>
                    </td>
                    <td style={{ 
                      padding: "10px 8px",
                      textAlign: "center",
                      borderBottom: "1px solid #f0f0f0"
                    }}>
                      {c.hasGenerated ? (
                        <span className="status active" style={{ 
                          padding: "4px 8px", 
                          borderRadius: "4px",
                          fontSize: "11px"
                        }}>Generated</span>
                      ) : (
                        <span className="status" style={{ 
                          background: '#fbbf24', 
                          color: 'white',
                          padding: "4px 8px", 
                          borderRadius: "4px",
                          fontSize: "11px"
                        }}>Ready</span>
                      )}
                    </td>
                    <td style={{ 
                      padding: "10px 8px",
                      fontFamily: "monospace", 
                      fontSize: '11px',
                      overflow: "hidden", 
                      textOverflow: "ellipsis", 
                      whiteSpace: "nowrap",
                      borderBottom: "1px solid #f0f0f0",
                      color: "#666"
                    }}>{c.certificateId}</td>
                  </tr>
                ))}
                {filtered.length === 0 && (
                  <tr>
                    <td colSpan="5" style={{ 
                      textAlign: "center", 
                      padding: "40px 20px", 
                      color: "#777",
                      fontSize: "14px",
                      borderBottom: "1px solid #f0f0f0"
                    }}>
                      {certificates.length === 0 
                        ? "No certificates available. Users must pass the post-assessment to receive certificates."
                        : "No certificates found matching your search criteria."
                      }
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>

        <aside style={{ 
          background: "white", 
          borderRadius: 8, 
          padding: 20, 
          border: "1px solid #eee", 
          minHeight: "600px",
          position: "sticky",
          top: "20px",
          overflow: "hidden"
        }}>
          <h3 style={{ marginTop: 0, marginBottom: 20 }}>Certificate Preview</h3>
          {selected ? (
            <div className="certificate-preview" style={{ 
              border: "none",
              borderRadius: 20,
              padding: "30px 20px",
              background: "linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%)",
              fontFamily: "'Georgia', serif",
              textAlign: "center",
              position: "relative",
              minHeight: "500px",
              maxHeight: "600px",
              display: "flex",
              flexDirection: "column",
              justifyContent: "space-between",
              aspectRatio: "4/3",
              boxShadow: "0 20px 40px rgba(0,0,0,0.1), inset 0 0 0 2px #FFD700",
              overflow: "hidden",
              width: "100%",
              boxSizing: "border-box"
            }}>
              {}
              <div style={{
                position: "absolute",
                top: 0,
                left: 0,
                right: 0,
                bottom: 0,
                background: "linear-gradient(45deg, #FFD700, #FFA500, #FFD700, #FFA500)",
                borderRadius: 20,
                zIndex: -1
              }}></div>
              
              {}
              <div style={{
                position: "absolute",
                top: 15,
                left: 15,
                width: "60px",
                height: "60px",
                background: "linear-gradient(45deg, #FFD700, #FFA500)",
                clipPath: "polygon(0 0, 100% 0, 0 100%)",
                opacity: 0.3,
                zIndex: 1
              }}></div>
              
              <div style={{
                position: "absolute",
                bottom: 15,
                right: 15,
                width: "60px",
                height: "60px",
                background: "linear-gradient(45deg, #FFD700, #FFA500)",
                clipPath: "polygon(100% 100%, 0 100%, 100% 0)",
                opacity: 0.3,
                zIndex: 1
              }}></div>
              
              {}
              <div style={{
                position: "absolute",
                top: 20,
                left: 20,
                right: 20,
                bottom: 20,
                border: "2px solid #FFD700",
                borderRadius: 15,
                opacity: 0.6,
                zIndex: 1
              }}></div>
              
              <div style={{
                position: "absolute",
                top: 30,
                left: 30,
                right: 30,
                bottom: 30,
                border: "1px solid #FFA500",
                borderRadius: 10,
                opacity: 0.4,
                zIndex: 1
              }}></div>
              
              {}
              <div style={{ zIndex: 3, position: 'relative', marginBottom: "20px" }}>
                {}
                <div style={{
                  width: "80px",
                  height: "80px",
                  background: "linear-gradient(135deg, #FFD700, #FFA500)",
                  margin: "0 auto 15px",
                  borderRadius: "50%",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  color: "#1a1a2e",
                  fontWeight: "bold",
                  fontSize: "20px",
                  fontFamily: "'Georgia', serif",
                  boxShadow: "0 8px 16px rgba(255, 215, 0, 0.3), inset 0 2px 4px rgba(255, 255, 255, 0.3)",
                  border: "3px solid #fff"
                }}>
                  PYlot
                </div>
                
                {}
                <div style={{
                  width: "150px",
                  height: "2px",
                  background: "linear-gradient(90deg, transparent, #FFD700, transparent)",
                  margin: "0 auto 15px"
                }}></div>
                
                <div style={{ 
                  fontSize: "32px", 
                  fontWeight: "300", 
                  color: "#FFD700", 
                  marginBottom: "8px",
                  fontFamily: "'Georgia', serif",
                  letterSpacing: "2px",
                  textShadow: "2px 2px 4px rgba(0,0,0,0.5)",
                  textTransform: "uppercase",
                  lineHeight: 1.2
                }}>
                  Certificate
                </div>
                
                <div style={{ 
                  fontSize: "18px", 
                  fontWeight: "normal", 
                  color: "#FFA500", 
                  marginBottom: "15px",
                  fontFamily: "'Georgia', serif",
                  letterSpacing: "1px",
                  fontStyle: "italic"
                }}>
                  of Completion
                </div>
              </div>

              {}
              <div style={{ 
                flex: 1, 
                display: "flex", 
                flexDirection: "column", 
                justifyContent: "center", 
                zIndex: 3, 
                position: 'relative',
                padding: "10px 0"
              }}>
                {}
                <div style={{ 
                  fontSize: "18px", 
                  marginBottom: "20px", 
                  color: "#E8E8E8", 
                  fontWeight: 300,
                  fontFamily: "'Georgia', serif",
                  letterSpacing: "1px"
                }}>
                  This certificate is proudly presented to
                </div>
                
                {}
                <div style={{ marginBottom: "25px" }}>
                  <div style={{ 
                    fontSize: "36px", 
                    fontWeight: "bold", 
                    color: "#FFD700", 
                    marginBottom: "8px",
                    fontFamily: "'Georgia', serif",
                    letterSpacing: "1px",
                    textShadow: "3px 3px 6px rgba(0,0,0,0.7)",
                    lineHeight: 1.2,
                    wordBreak: "break-word"
                  }}>
                    {selected.fullName}
                  </div>
                  <div style={{
                    width: "200px",
                    height: "2px",
                    background: "linear-gradient(90deg, transparent, #FFD700, transparent)",
                    margin: "8px auto"
                  }}></div>
                </div>
                
                {}
                <div style={{ marginBottom: "20px" }}>
                  <div style={{ 
                    fontSize: "16px", 
                    color: "#D4D4D4", 
                    marginBottom: "12px",
                    fontFamily: "'Georgia', serif",
                    letterSpacing: "1px"
                  }}>
                    for outstanding achievement in
                  </div>
                  
                  <div style={{ 
                    fontSize: "22px", 
                    fontWeight: "600", 
                    color: "#FFA500", 
                    marginBottom: "12px",
                    fontFamily: "'Georgia', serif",
                    letterSpacing: "1px",
                    textShadow: "2px 2px 4px rgba(0,0,0,0.5)"
                  }}>
                    Python Programming & Development
                  </div>
                  
                  <div style={{ 
                    fontSize: "14px", 
                    color: "#B8B8B8", 
                    marginBottom: "15px",
                    fontStyle: "italic",
                    lineHeight: 1.4
                  }}>
                    Demonstrating mastery of programming concepts,
                    <br />
                    problem-solving skills, and software development practices
                  </div>
                </div>
                
                {}
                <div style={{
                  background: "rgba(255, 215, 0, 0.1)",
                  padding: "15px",
                  borderRadius: 12,
                  border: "1px solid rgba(255, 215, 0, 0.3)",
                  marginBottom: "15px"
                }}>
                  <div style={{ 
                    fontSize: "14px", 
                    color: "#FFA500", 
                    marginBottom: "6px",
                    fontWeight: "600"
                  }}>
                    Achievement Level: {selected.postScore >= 90 ? 'Excellence' : selected.postScore >= 80 ? 'Distinction' : 'Merit'}
                  </div>
                  <div style={{ 
                    fontSize: "12px", 
                    color: "#D4D4D4"
                  }}>
                    Completed on {new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
                  </div>
                </div>
              </div>

              {}
              <div style={{ 
                display: "flex",
                justifyContent: "space-between",
                alignItems: "flex-end",
                paddingTop: "20px",
                zIndex: 3,
                position: 'relative',
                marginTop: "auto"
              }}>
                {}
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{
                    fontSize: "10px",
                    color: "#B8B8B8",
                    marginBottom: "3px",
                    wordBreak: "break-all"
                  }}>
                    ID: {selected.certificateId || 'PYL-' + Date.now().toString().slice(-6)}
                  </div>
                  <div style={{
                    fontSize: "10px",
                    color: "#B8B8B8"
                  }}>
                    Hours: {selected.modulesCompleted * 2 || 16}h
                  </div>
                </div>
                
                {}
                <div style={{
                  width: "60px",
                  height: "60px",
                  background: "linear-gradient(135deg, #FFD700, #FFA500)",
                  borderRadius: "50%",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  fontSize: "10px",
                  fontWeight: "bold",
                  color: "#1a1a2e",
                  textAlign: "center",
                  border: "2px solid #fff",
                  boxShadow: "0 4px 8px rgba(255, 215, 0, 0.3)",
                  flexDirection: "column",
                  lineHeight: 1,
                  flexShrink: 0
                }}>
                  <div>PYLOT</div>
                  <div style={{ fontSize: "6px" }}>CERT</div>
                  <div style={{ fontSize: "8px" }}>{new Date().getFullYear()}</div>
                </div>
                
                {}
                <div style={{ flex: 1, textAlign: "right", minWidth: 0 }}>
                  <div style={{ 
                    fontSize: "10px", 
                    color: "#D4D4D4", 
                    textAlign: "right", 
                    marginTop: "3px",
                    wordBreak: "break-word"
                  }}>
                    PYlot Learning Academy
                  </div>
                </div>
              </div>
              
              {}
              <div style={{
                fontSize: "10px",
                color: "#888",
                textAlign: "center",
                marginTop: "15px",
                zIndex: 3,
                position: 'relative',
                fontStyle: "italic",
                letterSpacing: "1px"
              }}>
                "Excellence in Education, Innovation in Learning"
              </div>
            </div>
          ) : (
            <div style={{ color: "#777", textAlign: "center", padding: 40 }}>
              Select a certificate to preview the professional certificate design
            </div>
          )}
        </aside>
      </div>
    </div>
  );
}

export default Certificates;

