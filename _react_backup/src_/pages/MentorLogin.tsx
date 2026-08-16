import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useProgramStore } from '../store/useProgramStore';
import { BentoCard } from '../components/BentoCard';

export const MentorLogin: React.FC = () => {
  const { mentorLogin } = useProgramStore();
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const navigate = useNavigate();

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setError('');

    const success = mentorLogin(password);
    if (success) {
      navigate('/mentor');
    } else {
      setError('Invalid mentor authorization password.');
    }
  };

  return (
    <div className="min-h-screen w-screen bg-[#0A0F1C] flex flex-col items-center justify-center px-4 relative text-white">
      {/* Dark background decorative gradient */}
      <div className="absolute top-1/4 left-1/4 w-96 h-96 bg-amber-500/5 rounded-full blur-3xl -z-10 animate-pulse-gold"></div>

      <div className="w-full max-w-md">
        {/* Logo */}
        <div className="text-center mb-8">
          <span className="text-amber-500 font-extrabold text-3xl tracking-tight uppercase block font-outfit" style={{ fontFamily: 'Outfit, sans-serif' }}>
            ELYSIAN
          </span>
          <span className="text-[10px] uppercase font-bold tracking-widest text-slate-500 mt-1 block">
            Mentor Administration
          </span>
        </div>

        {/* Card */}
        <BentoCard className="p-8 border border-slate-800 bg-[#0F172A] shadow-2xl rounded-3xl">
          <h1 className="text-xl font-bold text-slate-100 text-center mb-1 font-outfit">Mentor Authorization</h1>
          <p className="text-xs text-slate-400 text-center mb-6">
            Enter the administrative password to manage cohorts and verifications.
          </p>

          {error && (
            <div className="mb-4 p-3 bg-red-950/40 border border-red-900/40 rounded-xl text-xs text-red-400">
              {error}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="flex flex-col gap-1.5">
              <label className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                Security Password
              </label>
              <input
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••••••"
                className="w-full rounded-xl border border-slate-800 px-4 py-3 text-sm text-slate-100 bg-[#0A0F1C] focus:outline-none focus:ring-2 focus:ring-amber-500/80 transition-all text-center placeholder:text-slate-700"
                required
              />
            </div>

            <button
              type="submit"
              className="w-full py-3 bg-amber-500 hover:bg-amber-600 active:scale-95 text-white font-bold rounded-xl shadow-lg shadow-amber-500/10 transition-all text-sm mt-2"
            >
              Authorize Access
            </button>
          </form>

          <div className="mt-6 pt-5 border-t border-slate-800 text-center text-xs">
            <Link to="/login" className="text-slate-400 font-medium hover:text-amber-500 transition-colors">
              ← Return to Student Login
            </Link>
          </div>
        </BentoCard>
      </div>
    </div>
  );
};
