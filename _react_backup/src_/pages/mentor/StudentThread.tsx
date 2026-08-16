import React from 'react';
import { useProgramStore } from '../../store/useProgramStore';
import { ChatInterface } from '../../components/ChatInterface';
import { master_profiles } from '../../data/masterProfiles';

interface StudentThreadProps {
  studentId: string;
}

export const StudentThread: React.FC<StudentThreadProps> = ({ studentId }) => {
  const { students, programs, getVisibleBlocks, getStudentPayment, verifyPayment, rejectPayment } = useProgramStore();

  const student = students.find((s) => s.permanentID === studentId);
  if (!student) {
    return (
      <div className="flex items-center justify-center h-full text-slate-400 text-sm">
        Select a student from the inbox to view details.
      </div>
    );
  }

  const program = student.selectedProgramId
    ? programs.find((p) => p.id === student.selectedProgramId)
    : null;

  const payment = getStudentPayment(student.permanentID);
  const visibleBlocks = program ? getVisibleBlocks(student, program) : [];
  const profileCode = student.profileCode || '';
  const matchedProfile = master_profiles[profileCode.toUpperCase()];

  const getProgressPercent = () => {
    if (visibleBlocks.length === 0) return 0;
    const answered = visibleBlocks.filter((b) => student.answers[b.id] !== undefined && student.answers[b.id] !== '');
    return Math.round((answered.length / visibleBlocks.length) * 100);
  };

  return (
    <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 h-full min-h-0">
      {/* Student Details and Diagnostics */}
      <div className="lg:col-span-7 flex flex-col h-full min-h-0 bg-slate-900 border border-slate-800 rounded-2xl p-5 overflow-y-auto custom-scrollbar">
        {/* Profile Summary Header */}
        <div className="flex flex-wrap items-start justify-between gap-4 mb-6 pb-5 border-b border-slate-800">
          <div>
            <span className="text-[9px] font-bold text-amber-500 uppercase tracking-widest block font-mono">
              Diagnostic Session Profile
            </span>
            <h2 className="text-xl font-bold text-white mt-1 font-outfit">{student.name}</h2>
            <p className="text-xs text-slate-500 font-mono mt-0.5">{student.email}</p>
          </div>
          <div className="text-right">
            <span className="text-[10px] text-slate-500 font-semibold block uppercase tracking-wider">
              Permanent ID (UIN)
            </span>
            <span className="text-xs font-mono font-bold text-slate-300 block bg-slate-950 px-2 py-1 rounded border border-slate-800 mt-1 select-all">
              {student.permanentID}
            </span>
          </div>
        </div>

        {/* Payment actions directly inside student thread */}
        {payment && payment.status === 'pending' && (
          <div className="mb-6 p-4 bg-amber-500/10 border border-amber-500/20 rounded-2xl flex items-center justify-between">
            <div>
              <span className="text-[9px] font-bold text-amber-500 uppercase tracking-widest block font-mono">
                Action Required
              </span>
              <h4 className="text-xs font-bold text-slate-200 mt-0.5">Payment Verification Pending</h4>
              <p className="text-[10px] text-slate-400 font-mono mt-0.5">TTID: {payment.ttid} | Amount: ${payment.amount}</p>
            </div>
            <div className="flex gap-2">
              <button
                onClick={() => verifyPayment(payment.id)}
                className="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-xs font-bold rounded-xl transition-all"
              >
                Approve & Activate
              </button>
              <button
                onClick={() => rejectPayment(payment.id)}
                className="px-3.5 py-1.5 bg-red-650 hover:bg-red-750 active:scale-95 text-white text-xs font-bold rounded-xl transition-all border border-red-900/30"
              >
                Reject
              </button>
            </div>
          </div>
        )}

        {/* Progress Metrics */}
        <div className="grid grid-cols-2 gap-4 mb-6">
          <div className="p-3.5 bg-slate-950/40 border border-slate-800 rounded-xl">
            <span className="text-[9px] font-bold text-slate-500 uppercase tracking-wider block">
              Program Path
            </span>
            <span className="text-xs font-bold text-slate-200 block truncate mt-1">
              {program?.title || 'None selected'}
            </span>
          </div>
          <div className="p-3.5 bg-slate-950/40 border border-slate-800 rounded-xl">
            <span className="text-[9px] font-bold text-slate-500 uppercase tracking-wider block">
              Progress
            </span>
            <div className="flex items-center gap-2 mt-1.5">
              <div className="flex-1 h-1.5 bg-slate-800 rounded-full overflow-hidden">
                <div className="h-full bg-amber-500" style={{ width: `${getProgressPercent()}%` }}></div>
              </div>
              <span className="text-xs font-bold text-amber-500 font-mono">{getProgressPercent()}%</span>
            </div>
          </div>
        </div>

        {/* Master Profile Reveal if calculated */}
        {matchedProfile && (
          <div className="mb-6 p-4 bg-slate-950 border border-slate-800 rounded-2xl">
            <span className="text-[9px] font-bold text-slate-500 uppercase tracking-widest block font-mono">
              Calculated Personality Profile
            </span>
            <h4 className="text-sm font-bold text-slate-200 mt-1 mb-2 font-outfit">{matchedProfile.title}</h4>
            <div className="grid grid-cols-2 gap-4 text-[10px] text-slate-400">
              <div>
                <span className="text-slate-500 font-bold block mb-1">STRENGTHS</span>
                <ul className="list-disc list-inside space-y-0.5">
                  {matchedProfile.strengths.slice(0, 3).map((s, i) => (
                    <li key={i} className="truncate">{s}</li>
                  ))}
                </ul>
              </div>
              <div>
                <span className="text-slate-500 font-bold block mb-1">GROWTH</span>
                <ul className="list-disc list-inside space-y-0.5">
                  {matchedProfile.weaknesses.slice(0, 3).map((w, i) => (
                    <li key={i} className="truncate">{w}</li>
                  ))}
                </ul>
              </div>
            </div>
          </div>
        )}

        {/* Responses Log */}
        <div className="flex-1 min-h-0">
          <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 block">
            Response Log ({visibleBlocks.filter((b) => student.answers[b.id] !== undefined && student.answers[b.id] !== '').length} answered)
          </h3>

          {visibleBlocks.length === 0 ? (
            <div className="text-center py-6 text-slate-500 text-xs">
              No diagnostic blocks active or answered yet.
            </div>
          ) : (
            <div className="space-y-4 pr-1">
              {visibleBlocks.map((block) => {
                const answer = student.answers[block.id];
                const answered = answer !== undefined && answer !== '';
                return (
                  <div key={block.id} className="p-3 bg-slate-950/20 border border-slate-850 rounded-xl">
                    <div className="flex justify-between items-start gap-3">
                      <span className="text-[10px] font-semibold text-slate-400 leading-normal">
                        {block.question}
                      </span>
                      {answered ? (
                        <span className="text-[8px] font-bold text-emerald-500 uppercase bg-emerald-500/10 px-1.5 py-0.5 rounded border border-emerald-500/10">
                          Complete
                        </span>
                      ) : (
                        <span className="text-[8px] font-bold text-slate-600 uppercase bg-slate-950 px-1.5 py-0.5 rounded border border-slate-800">
                          Unanswered
                        </span>
                      )}
                    </div>
                    {answered && (
                      <p className="text-xs text-slate-200 mt-2 font-medium bg-slate-950/40 p-2 rounded-lg border border-slate-850/60 leading-relaxed break-words">
                        {String(answer)}
                      </p>
                    )}
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </div>

      {/* Persistent Chat Window */}
      <div className="lg:col-span-5 h-full min-h-0">
        <ChatInterface mode="mentor" studentId={studentId} />
      </div>
    </div>
  );
};
