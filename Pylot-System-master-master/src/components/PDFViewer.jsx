import React, { useState, useRef, useEffect, memo } from "react";
import "./PDFViewer.css";

const PDFViewer = memo(({ pdfUrl, fileName, onClose, onDownload }) => {
  const [isLoading, setIsLoading] = useState(true);
  const [pdfLoadError, setPdfLoadError] = useState(false);
  const [isMobile, setIsMobile] = useState(false);
  const iframeRef = useRef(null);

  useEffect(() => {
    console.log('PDFViewer: Loading PDF from URL:', pdfUrl);
    setIsLoading(true);
    setPdfLoadError(false);
  }, [pdfUrl]);

  
  useEffect(() => {
    const checkMobile = () => {
      setIsMobile(window.innerWidth <= 768 || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent));
    };
    
    checkMobile();
    window.addEventListener('resize', checkMobile);
    return () => window.removeEventListener('resize', checkMobile);
  }, []);

  const handleIframeLoad = () => {
    setIsLoading(false);
    setPdfLoadError(false);
  };

  const handleIframeError = (error) => {
    console.error('PDF iframe load error:', error);
    console.log('Failed PDF URL:', pdfUrl);
    setPdfLoadError(true);
    setIsLoading(false);
  };

  const openInNewTab = () => {
    window.open(pdfUrl, "_blank");
  };

  
  const handleTouchStart = (e) => {
    
    if (isMobile) {
      e.stopPropagation();
    }
  };

  
  useEffect(() => {
    const handleKeyDown = (event) => {
      if (event.key === 'Escape') {
        onClose();
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [onClose]);

  const renderViewer = () => {
    if (pdfLoadError) {
      return (
        <div className="pdf-error">
          <p>Failed to load PDF: <code>{pdfUrl}</code></p>
          <p>Please try again or download the file.</p>
          <div className="error-actions">
            <button onClick={onDownload} className="action-btn download-btn">
              Download PDF
            </button>
            <button onClick={onClose} className="action-btn close-btn">
              Close
            </button>
          </div>
        </div>
      );
    }

    return (
      <iframe
        ref={iframeRef}
        src={pdfUrl}
        title={`PDF Viewer - ${fileName}`}
        className="pdf-iframe"
        onLoad={handleIframeLoad}
        onError={handleIframeError}
      />
    );
  };

  return (
    <div 
      className="pdf-viewer-modal" 
      role="dialog" 
      aria-modal="true" 
      aria-labelledby="pdf-title"
      onTouchStart={handleTouchStart}
    >
      <div className="pdf-viewer-header">
        <div className="pdf-viewer-left">
          <h3 id="pdf-title">{fileName}</h3>
        </div>

        <div className="pdf-viewer-right">
          <button 
            onClick={openInNewTab} 
            className="action-btn" 
            aria-label="Open PDF in new tab"
            onTouchStart={handleTouchStart}
          >
            {isMobile ? "Open" : "Open in New Tab"}
          </button>
          <button 
            onClick={onDownload} 
            className="action-btn download-btn" 
            aria-label="Download PDF"
            onTouchStart={handleTouchStart}
          >
            Download
          </button>
          <button 
            onClick={onClose} 
            className="action-btn close-btn" 
            aria-label="Close PDF viewer"
            onTouchStart={handleTouchStart}
          >
            {isMobile ? "✕" : "✕ Close"}
          </button>
        </div>
      </div>

      <div className="pdf-viewer-content">
        {isLoading && (
          <div className="pdf-loading">
            <div className="spinner"></div>
            <p>Loading PDF...</p>
          </div>
        )}
        {renderViewer()}
      </div>
    </div>
  );
});

PDFViewer.displayName = 'PDFViewer';

export default PDFViewer;

