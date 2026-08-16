import React, { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useProgramStore } from '../store/useProgramStore';
import { BentoCard } from '../components/BentoCard';
import { master_profiles } from '../data/masterProfiles';

export const CompletionScreen: React.FC = () => {
  const { currentStudentID, students, programs, getVisibleBlocks } = useProgramStore();
  const navigate = useNavigate();

  const currentStudent = students.find((s) => s.permanentID === currentStudentID);
  const selectedProgram = currentStudent?.selectedProgramId
    ? programs.find((p) => p.id === currentStudent.selectedProgramId)
    : null;

  // Guard: Redirect if not logged in or not complete
  useEffect(() => {
    if (!currentStudentID || !currentStudent) {
      navigate('/login');
      return;
    }
    if (currentStudent.status !== 'completed') {
      if (currentStudent.status === 'active') {
        navigate('/tunnel');
      } else if (currentStudent.status === 'payment_pending') {
        navigate('/payment');
      } else {
        navigate('/programs');
      }
    }
  }, [currentStudentID, currentStudent, navigate]);

  if (!currentStudent || !selectedProgram) return null;

  const visibleBlocks = getVisibleBlocks(currentStudent, selectedProgram);
  const profileCode = currentStudent.profileCode || '';
  const matchedProfile = master_profiles[profileCode.toUpperCase()];

  const handleRestart = () => {
    // Log out student so they can log back in or start fresh
    useProgramStore.getState().logoutStudent();
    navigate('/login');
  };

  const handlePrint = () => {
    window.print();
  };

  return (
    <div className="min-h-screen w-screen bg-[#F2F7FD] pb-16 pt-8 px-6 relative">
      {/* ─── Screen Layout (Hidden on Print) ─── */}
      <div className="max-w-3xl mx-auto no-print">
        {/* Header */}
        <div className="flex justify-between items-center mb-8">
          <div className="flex items-center gap-3">
            <div className="w-8 h-8 rounded-lg bg-amber-500 flex items-center justify-center shadow-md">
              <span className="text-white font-extrabold text-sm">E</span>
            </div>
            <span className="text-sm font-extrabold text-slate-800 tracking-wide uppercase font-outfit">
              Elysian transformation
            </span>
          </div>
          <button
            onClick={handleRestart}
            className="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-700 bg-slate-100/50 hover:bg-slate-100 border border-slate-200/40 rounded-xl transition-all"
          >
            Start New Session
          </button>
        </div>

        {/* Success Card */}
        <BentoCard className="p-8 text-center bg-white border border-slate-100 shadow-xl rounded-3xl mb-8 relative overflow-hidden">
          <div className="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-amber-500 to-amber-600"></div>

          <div className="w-20 h-20 bg-amber-50 border border-amber-100 rounded-full flex items-center justify-center mx-auto mb-6 shadow-sm shadow-amber-500/10">
            <span className="text-3.5xl">🏆</span>
          </div>

          <span className="gold-badge mb-3">Diagnostic Complete</span>
          <h1 className="text-3xl font-extrabold text-slate-800 tracking-tight mb-3 font-outfit">
            Strategy Assessment Completed
          </h1>
          <p className="text-xs text-slate-400 max-w-md mx-auto leading-relaxed mb-6">
            Congratulations, {currentStudent.name}! You have successfully completed all diagnostic pillars for the{' '}
            <span className="font-bold text-slate-700">{selectedProgram.title}</span>.
          </p>

          <div className="flex justify-center gap-3.5">
            <button
              onClick={handlePrint}
              className="elysian-btn elysian-btn-gold px-6 py-3 text-xs flex items-center gap-2"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                />
              </svg>
              Export Strategy PDF
            </button>
          </div>
        </BentoCard>

        {/* Profile Card if matched */}
        {matchedProfile && (
          <BentoCard className="p-6 md:p-8 bg-white border border-slate-100 shadow-md rounded-3xl mb-8">
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1">
              Your Strategic Profile
            </span>
            <h2 className="text-2xl font-bold text-slate-800 mb-4 font-outfit">
              {matchedProfile.title}
            </h2>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
              <div>
                <h4 className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Core Strengths</h4>
                <ul className="text-xs text-slate-600 space-y-1.5">
                  {matchedProfile.strengths.map((s, i) => (
                    <li key={i} className="flex gap-2 items-start">
                      <span className="text-amber-500 font-bold">✓</span>
                      <span>{s}</span>
                    </li>
                  ))}
                </ul>
              </div>
              <div>
                <h4 className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Growth Focus Areas</h4>
                <ul className="text-xs text-slate-600 space-y-1.5">
                  {matchedProfile.weaknesses.map((w, i) => (
                    <li key={i} className="flex gap-2 items-start">
                      <span className="text-amber-500 font-bold">→</span>
                      <span>{w}</span>
                    </li>
                  ))}
                </ul>
              </div>
            </div>

            <div className="bg-slate-50 border border-slate-100 rounded-2xl p-4">
              <h4 className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2.5">
                Suggested Actions
              </h4>
              <ul className="text-xs text-slate-600 space-y-2">
                {matchedProfile.suggested_goals.map((g, i) => (
                  <li key={i} className="flex gap-2 items-start">
                    <span className="text-amber-500 font-bold">•</span>
                    <span>{g}</span>
                  </li>
                ))}
              </ul>
            </div>
          </BentoCard>
        )}

        {/* Responses Summary Card */}
        <BentoCard className="p-6 md:p-8 bg-white border border-slate-100 shadow-md rounded-3xl">
          <h2 className="text-lg font-bold text-slate-800 mb-4 font-outfit">Responses Summary</h2>
          <div className="space-y-4 max-h-96 overflow-y-auto pr-2 custom-scrollbar">
            {visibleBlocks.map((block) => {
              const answer = currentStudent.answers[block.id];
              if (answer === undefined || answer === '') return null;
              return (
                <div key={block.id} className="border-b border-slate-100 pb-3 last:border-b-0">
                  <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">
                    {block.question}
                  </span>
                  <span className="text-xs text-slate-700 whitespace-pre-wrap leading-relaxed">
                    {String(answer)}
                  </span>
                </div>
              );
            })}
          </div>
        </BentoCard>
      </div>

      {/* ─── Print Layout (Visible on Print Only) ─── */}
      <div className="hidden print:block w-full text-black">
        {/* Printable Header */}
        <div className="print-header flex justify-between items-center">
          <div>
            <h1 className="text-2xl font-bold tracking-tight uppercase" style={{ color: '#C99700' }}>
              ELYSIAN SUCCESS DIAGNOSTIC
            </h1>
            <p className="text-xs text-gray-500 font-mono">
              OFFICIAL STRATEGY REPORT • UIN: {currentStudent.permanentID}
            </p>
          </div>
          <div className="text-right text-xs text-gray-500">
            <p className="font-bold">{currentStudent.name}</p>
            <p>{currentStudent.email}</p>
            <p>Generated: {new Date().toLocaleDateString()}</p>
          </div>
        </div>

        {/* Path Info */}
        <div className="print-section bg-gray-50 p-4 border border-gray-200 rounded-lg mb-6">
          <h2 className="text-sm font-bold uppercase tracking-wider text-gray-700 mb-2">Program Assessment</h2>
          <div className="grid grid-cols-2 gap-4 text-xs">
            <div>
              <span className="text-gray-500 font-semibold block">Accelerator Path</span>
              <span className="font-bold text-gray-900">{selectedProgram.title}</span>
            </div>
            <div>
              <span className="text-gray-500 font-semibold block">Duration</span>
              <span className="font-bold text-gray-900">{selectedProgram.duration}</span>
            </div>
          </div>
        </div>

        {/* Profile Info if matched */}
        {matchedProfile && (
          <div className="print-section mb-6">
            <h2 className="text-sm font-bold uppercase tracking-wider text-gray-700 border-b border-gray-300 pb-1.5 mb-3">
              Strategic Profile: {matchedProfile.title}
            </h2>

            <div className="grid grid-cols-2 gap-6 mb-4 text-xs">
              <div>
                <span className="text-gray-500 font-bold block mb-1 uppercase tracking-wide">Core Strengths</span>
                <ul className="space-y-1">
                  {matchedProfile.strengths.map((s, i) => (
                    <li key={i} className="flex gap-1.5 items-start">
                      <span>✓</span> <span>{s}</span>
                    </li>
                  ))}
                </ul>
              </div>
              <div>
                <span className="text-gray-500 font-bold block mb-1 uppercase tracking-wide">Growth Focus Areas</span>
                <ul className="space-y-1">
                  {matchedProfile.weaknesses.map((w, i) => (
                    <li key={i} className="flex gap-1.5 items-start">
                      <span>→</span> <span>{w}</span>
                    </li>
                  ))}
                </ul>
              </div>
            </div>

            <div className="bg-gray-50 p-4 border border-gray-200 rounded-lg text-xs">
              <span className="text-gray-500 font-bold block mb-1.5 uppercase tracking-wide">Suggested Strategic Actions</span>
              <ul className="space-y-1">
                {matchedProfile.suggested_goals.map((g, i) => (
                  <li key={i} className="flex gap-1.5 items-start">
                    <span>•</span> <span>{g}</span>
                  </li>
                ))}
              </ul>
            </div>
          </div>
        )}

        {/* Answers Summary */}
        <div className="print-section page-break-before">
          <h2 className="text-sm font-bold uppercase tracking-wider text-gray-700 border-b border-gray-300 pb-1.5 mb-4">
            Diagnostic Response Log
          </h2>
          <div className="space-y-3.5">
            {visibleBlocks.map((block) => {
              const answer = currentStudent.answers[block.id];
              if (answer === undefined || answer === '') return null;
              return (
                <div key={block.id} className="print-answer-row">
                  <span className="text-[10px] font-bold text-gray-500 uppercase block tracking-wider mb-1">
                    {block.question}
                  </span>
                  <span className="text-xs text-gray-900 font-medium whitespace-pre-wrap leading-relaxed block">
                    {String(answer)}
                  </span>
                </div>
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
};
