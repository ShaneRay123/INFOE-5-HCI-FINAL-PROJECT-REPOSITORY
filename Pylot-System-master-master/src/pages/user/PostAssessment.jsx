import React, { useEffect } from "react";
import { useNavigate } from "react-router-dom";

function PostAssessment() {
  const navigate = useNavigate();

  useEffect(() => {
    navigate('/user/exam', { replace: true });
  }, [navigate]);

  return null;
}

export default PostAssessment;

