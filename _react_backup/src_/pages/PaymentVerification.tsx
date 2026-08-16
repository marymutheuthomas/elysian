import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useProgramStore } from '../store/useProgramStore';
import { BentoCard } from '../components/BentoCard';

export const PaymentVerification: React.FC = () => {
  const {
    currentStudentID,
    students,
    programs,
    submitPayment,
    getStudentPayment,
  } = useProgramStore();

  const navigate = useNavigate();
  const [ttid, setTtid] = useState('');
  const [error, setError] = useState('');

  const currentStudent = students.find((s) => s.permanentID === currentStudentID);
  const selectedProgram = currentStudent?.selectedProgramId
    ? programs.find((p) => p.id === currentStudent.selectedProgramId)
    : null;

  const paymentRecord = currentStudentID ? getStudentPayment(currentStudentID) : null;

  // Navigation and auth guard
  useEffect(() => {
    if (!currentStudentID || !currentStudent) {
      navigate('/login');
      return;
    }
    if (!currentStudent.selectedProgramId) {
      navigate('/programs');
      return;
    }
    // If student has already been verified and status is active or completed, bypass payment page
    if (currentStudent.status === 'active') {
      navigate('/tunnel');
    } else if (currentStudent.status === 'completed') {
      navigate('/completed');
    }
  }, [currentStudentID, currentStudent, navigate]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    if (!ttid.trim()) return;

    // Call submitPayment from store
    submitPayment(ttid.trim());
  };

  const handleLogout = () => {
    useProgramStore.getState().logoutStudent();
    navigate('/login');
  };

  if (!currentStudent || !selectedProgram) return null;

  return (
    <div className="min-h-screen w-screen bg-[#F2F7FD] flex flex-col items-center justify-center px-4 relative">
      {/* Decorative blurs */}
      <div className="absolute top-1/4 left-1/4 w-96 h-96 bg-amber-500/5 rounded-full blur-3xl -z-10 animate-pulse-gold"></div>

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
          {!paymentRecord ? (
            <>
              <h1 className="text-2xl font-bold text-slate-800 text-center mb-1 font-outfit">Unlock Assessment</h1>
              <p className="text-xs text-slate-400 text-center mb-6">
                Enter your Transaction ID (TTID) to unlock validation.
              </p>

              {error && (
                <div className="mb-4 p-3 bg-red-50 border border-red-100 rounded-xl text-xs text-red-600">
                  {error}
                </div>
              )}

              {/* Diagnostic Path Info */}
              <div className="bg-slate-50 border border-slate-100 rounded-2xl p-4 mb-6 space-y-3 font-medium">
                <div className="flex justify-between items-center text-xs">
                  <span className="text-slate-400">Permanent ID (UIN)</span>
                  <span className="font-mono text-slate-700 font-bold select-all">
                    {currentStudent.permanentID}
                  </span>
                </div>
                <div className="border-t border-slate-200/50 pt-2.5 flex justify-between items-center text-xs">
                  <span className="text-slate-400">Selected Program</span>
                  <span className="text-slate-700 font-bold max-w-[200px] truncate text-right">
                    {selectedProgram.title}
                  </span>
                </div>
                <div className="border-t border-slate-200/50 pt-2.5 flex justify-between items-center text-xs">
                  <span className="text-slate-400">Strategic Fee</span>
                  <span className="text-slate-800 font-extrabold text-sm">
                    ${selectedProgram.fee.toLocaleString()}
                  </span>
                </div>
              </div>

              <form onSubmit={handleSubmit} className="space-y-4">
                <div className="flex flex-col gap-1.5">
                  <label className="elysian-label">Transaction ID (TTID)</label>
                  <input
                    type="text"
                    value={ttid}
                    onChange={(e) => setTtid(e.target.value)}
                    placeholder="Enter wire/payment reference number"
                    className="elysian-input text-center font-mono tracking-wide"
                    required
                  />
                </div>

                <button type="submit" className="w-full elysian-btn elysian-btn-gold mt-2 py-3.5">
                  Submit Verification
                </button>
              </form>
            </>
          ) : (
            <div className="text-center py-4 animate-fade-in">
              <div className="w-16 h-16 bg-amber-50 border border-amber-100 rounded-full flex items-center justify-center mx-auto mb-5 animate-pulse">
                <svg className="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>

              <h1 className="text-xl font-bold text-slate-800 mb-2 font-outfit">Verification In Progress</h1>
              <p className="text-xs text-slate-400 leading-relaxed mb-6">
                Your transaction (TTID: <span className="font-mono font-bold text-slate-600">{paymentRecord.ttid}</span>) is pending review by the Elysian team.
              </p>

              <div className="p-4 bg-slate-50 border border-slate-100 rounded-2xl text-left text-xs space-y-2 text-slate-600 mb-6">
                <p>• Verification typically takes less than 2 hours during active sessions.</p>
                <p>• Mentors can verify your payment instantly from the Mentor Dashboard.</p>
                <p>• Once verified, your status will update automatically and unlock your path.</p>
              </div>

              <div className="flex flex-col gap-2.5">
                <button
                  onClick={() => {
                    // Check store state manually and reload
                    const freshStudent = useProgramStore.getState().students.find((s) => s.permanentID === currentStudentID);
                    if (freshStudent?.status === 'active') {
                      navigate('/tunnel');
                    }
                  }}
                  className="w-full elysian-btn bg-slate-900 hover:bg-slate-800 text-white py-3 text-xs font-semibold"
                >
                  Refresh Verification Status
                </button>
                <button
                  onClick={handleLogout}
                  className="w-full elysian-btn elysian-btn-ghost py-3 text-xs font-semibold"
                >
                  Logout
                </button>
              </div>
            </div>
          )}
        </BentoCard>
      </div>
    </div>
  );
};
