import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useProgramStore } from '../store/useProgramStore';
import { BentoCard } from '../components/BentoCard';

export const StudentLogin: React.FC = () => {
  const { loginStudent } = useProgramStore();
  const [uin, setUin] = useState('');
  const [error, setError] = useState('');
  const navigate = useNavigate();

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    if (!uin.trim()) return;

    const success = loginStudent(uin.trim());
    if (success) {
      // Fetch the student we just logged in to see where they should go
      const storeState = useProgramStore.getState();
      const currentStudent = storeState.students.find((s) => s.permanentID === uin.trim());
      if (currentStudent) {
        if (currentStudent.status === 'program_selection') {
          navigate('/programs');
        } else if (currentStudent.status === 'payment_pending') {
          navigate('/payment');
        } else if (currentStudent.status === 'active') {
          navigate('/tunnel');
        } else if (currentStudent.status === 'completed') {
          navigate('/completed');
        }
      }
    } else {
      setError('UIN not found. Please register or check your spelling.');
    }
  };

  return (
    <div className="min-h-screen w-screen bg-[#F2F7FD] flex flex-col items-center justify-center px-4 relative">
      {/* Decorative Blur Elements */}
      <div className="absolute top-1/4 left-1/4 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl -z-10 animate-pulse-gold"></div>
      <div className="absolute bottom-1/4 right-1/4 w-96 h-96 bg-blue-500/10 rounded-full blur-3xl -z-10"></div>

      <div className="w-full max-w-md">
        {/* Logo */}
        <div className="text-center mb-8">
          <span className="text-amber-500 font-extrabold text-3xl tracking-tight uppercase block font-outfit" style={{ fontFamily: 'Outfit, sans-serif' }}>
            ELYSIAN
          </span>
          <span className="text-[10px] uppercase font-bold tracking-widest text-slate-400 mt-1 block">
            Success & Diagnostics
          </span>
        </div>

        {/* Card */}
        <BentoCard className="p-8 border border-slate-100 bg-white/80 glass shadow-xl rounded-3xl">
          <h1 className="text-2xl font-bold text-slate-800 text-center mb-1">Welcome Back</h1>
          <p className="text-xs text-slate-400 text-center mb-6">
            Enter your Permanent ID (UIN) to resume your path.
          </p>

          {error && (
            <div className="mb-5 p-3.5 bg-red-50 border border-red-100 rounded-xl text-xs font-medium text-red-600 flex items-center gap-2">
              <svg className="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              {error}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="flex flex-col gap-1.5">
              <label className="elysian-label">Permanent ID (UIN)</label>
              <input
                type="text"
                value={uin}
                onChange={(e) => setUin(e.target.value)}
                placeholder="ES-XXXX-X-XXXX"
                className="elysian-input text-center font-mono tracking-widest uppercase"
                required
              />
            </div>

            <button type="submit" className="w-full elysian-btn elysian-btn-gold mt-2 py-3.5">
              Resume Diagnostic
            </button>
          </form>

          <div className="mt-6 pt-5 border-t border-slate-100 text-center text-xs">
            <span className="text-slate-400">First time here? </span>
            <Link to="/register" className="text-amber-500 font-bold hover:underline">
              Create an account
            </Link>
          </div>
        </BentoCard>

        {/* Mentor Portal Link */}
        <div className="text-center mt-6">
          <Link to="/mentor/login" className="text-xs text-slate-400 font-medium hover:text-amber-500 transition-colors">
            Access Mentor Portal
          </Link>
        </div>
      </div>
    </div>
  );
};
