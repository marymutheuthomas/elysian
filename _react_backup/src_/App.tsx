import { useEffect } from 'react';
import { HashRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { StudentLogin } from './pages/StudentLogin';
import { StudentRegistration } from './pages/StudentRegistration';
import { ProgramSelection } from './pages/ProgramSelection';
import { PaymentVerification } from './pages/PaymentVerification';
import { LearningTunnel } from './pages/LearningTunnel';
import { CompletionScreen } from './pages/CompletionScreen';
import { MentorLogin } from './pages/MentorLogin';
import { MentorDashboard } from './pages/MentorDashboard';
import { useProgramStore } from './store/useProgramStore';

function App() {
  const loading = useProgramStore((state) => state.loading);
  const initialize = useProgramStore((state) => state.initialize);

  useEffect(() => {
    // Trigger store initialization on mount
    initialize();
  }, []);

  if (loading) {
    return (
      <div style={{
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'center',
        alignItems: 'center',
        height: '100vh',
        background: 'linear-gradient(135deg, #1e3c72, #2a5298)',
        color: '#fff',
        fontSize: '1.5rem',
        fontFamily: 'Inter, sans-serif',
      }}>
        {/* Simple spinner (CSS animation) */}
        <div style={{
          border: '8px solid rgba(255,255,255,0.2)',
          borderTop: '8px solid #fff',
          borderRadius: '50%',
          width: '60px',
          height: '60px',
          animation: 'spin 1s linear infinite',
          marginBottom: '1rem',
        }} />
        Loading your Elysian environment...
        {/* Add keyframes for spin */}
        <style>{`
          @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
          }
        `}</style>
      </div>
    );
  }

  return (
    <Router>
      <Routes>
        {/* Student Routes */}
        <Route path="/login" element={<StudentLogin />} />
        <Route path="/register" element={<StudentRegistration />} />
        <Route path="/programs" element={<ProgramSelection />} />
        <Route path="/payment" element={<PaymentVerification />} />
        <Route path="/tunnel" element={<LearningTunnel />} />
        <Route path="/completed" element={<CompletionScreen />} />

        {/* Mentor Routes */}
        <Route path="/mentor/login" element={<MentorLogin />} />
        <Route path="/mentor" element={<MentorDashboard />} />

        {/* Fallbacks */}
        <Route path="/" element={<Navigate to="/login" replace />} />
        <Route path="*" element={<Navigate to="/login" replace />} />
      </Routes>
    </Router>
  );
}

export default App;
