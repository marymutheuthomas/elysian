import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useProgramStore } from '../store/useProgramStore';
import { BentoCard } from '../components/BentoCard';

export const StudentRegistration: React.FC = () => {
  const { registerStudent } = useProgramStore();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [generatedUin, setGeneratedUin] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);
  const navigate = useNavigate();

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim() || !email.trim()) return;

    const uin = registerStudent(name.trim(), email.trim());
    setGeneratedUin(uin);
  };

  const handleCopy = () => {
    if (!generatedUin) return;
    navigator.clipboard.writeText(generatedUin);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const handleProceed = () => {
    navigate('/programs');
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
        <BentoCard className="p-8 border border-slate-100 bg-white/80 glass shadow-xl rounded-3xl overflow-hidden transition-all duration-300">
          {!generatedUin ? (
            <>
              <h1 className="text-2xl font-bold text-slate-800 text-center mb-1 font-outfit">Begin Diagnostic</h1>
              <p className="text-xs text-slate-400 text-center mb-6">
                Register to start your strategic growth path and generate your secure UIN.
              </p>

              <form onSubmit={handleSubmit} className="space-y-4">
                <div className="flex flex-col gap-1.5">
                  <label className="elysian-label">Full Name</label>
                  <input
                    type="text"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="Enter your full name"
                    className="elysian-input"
                    required
                  />
                </div>

                <div className="flex flex-col gap-1.5">
                  <label className="elysian-label">Email Address</label>
                  <input
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="name@example.com"
                    className="elysian-input"
                    required
                  />
                </div>

                <button type="submit" className="w-full elysian-btn elysian-btn-gold mt-2 py-3.5">
                  Generate UIN & Register
                </button>
              </form>

              <div className="mt-6 pt-5 border-t border-slate-100 text-center text-xs">
                <span className="text-slate-400">Already registered? </span>
                <Link to="/login" className="text-amber-500 font-bold hover:underline">
                  Log in here
                </Link>
              </div>
            </>
          ) : (
            <div className="animate-fade-in text-center">
              <div className="w-14 h-14 bg-emerald-50 border border-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg className="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                </svg>
              </div>

              <h1 className="text-xl font-bold text-slate-800 mb-1">Registration Complete</h1>
              <p className="text-xs text-slate-400 mb-6">
                Your Permanent ID (UIN) has been securely generated. Save this ID. You will need it to log in again.
              </p>

              <div className="bg-slate-50 border border-slate-100 rounded-2xl p-4 mb-6 relative">
                <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider block mb-1">
                  Your Permanent ID
                </span>
                <span className="text-base font-mono font-bold text-slate-800 tracking-wider select-all">
                  {generatedUin}
                </span>

                <button
                  onClick={handleCopy}
                  className="absolute right-3 top-1/2 -translate-y-1/2 p-2 hover:bg-slate-200/60 rounded-lg text-slate-400 hover:text-slate-600 transition-colors"
                  title="Copy UIN"
                >
                  {copied ? (
                    <span className="text-[10px] font-bold text-emerald-600">Copied!</span>
                  ) : (
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m-2 4h.01M9 16h5m-5-4h5"
                      />
                    </svg>
                  )}
                </button>
              </div>

              <button
                onClick={handleProceed}
                className="w-full elysian-btn elysian-btn-gold py-3.5 flex items-center justify-center gap-2"
              >
                Proceed to Path Selection
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
              </button>
            </div>
          )}
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
