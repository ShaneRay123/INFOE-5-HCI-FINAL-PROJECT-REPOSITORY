import React, { useEffect } from "react";
import { useNavigate } from "react-router-dom";

function PreAssessment() {
  const navigate = useNavigate();

  useEffect(() => {
    navigate('/user/exam', { replace: true });
  }, [navigate]);

  return null;
}
export default PreAssessment;

