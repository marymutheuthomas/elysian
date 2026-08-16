import React from 'react';
import { useProgramStore } from '../../store/useProgramStore';
import type { StudentRecord } from '../../types/index';

interface StudentInboxProps {
  onSelectStudent: (studentId: string) => void;
  selectedStudentId: string | null;
}

export const StudentInbox: React.FC<StudentInboxProps> = ({
  onSelectStudent,
  selectedStudentId,
}) => {
  const { students, programs } = useProgramStore();

  const getProgramTitle = (programId: string | null) => {
    if (!programId) return 'No Program Selected';
    return programs.find((p) => p.id === programId)?.title || 'Unknown Program';
  };

  const getStatusBadge = (status: StudentRecord['status']) => {
    switch (status) {
      case 'registration':
        return <span className="px-2.5 py-1 text-[10px] font-bold rounded-full bg-slate-100 text-slate-600 border border-slate-200/40">Registering</span>;
      case 'program_selection':
        return <span className="px-2.5 py-1 text-[10px] font-bold rounded-full bg-blue-50 text-blue-600 border border-blue-200/30">Selecting Path</span>;
      case 'payment_pending':
        return <span className="px-2.5 py-1 text-[10px] font-bold rounded-full bg-amber-50 text-amber-700 border border-amber-200/40 animate-pulse">Payment Pending</span>;
      case 'active':
        return <span className="px-2.5 py-1 text-[10px] font-bold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200/30">Active Assessment</span>;
      case 'completed':
        return <span className="px-2.5 py-1 text-[10px] font-bold rounded-full bg-purple-50 text-purple-700 border border-purple-200/30">Completed</span>;
      default:
        return <span className="px-2.5 py-1 text-[10px] font-bold rounded-full bg-slate-100 text-slate-600">{status}</span>;
    }
  };

  return (
    <div className="flex flex-col h-full bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-2xl">
      {/* Header */}
      <div className="px-5 py-4 border-b border-slate-800 bg-slate-950 flex items-center justify-between">
        <div>
          <h3 className="font-bold text-sm text-slate-100 font-outfit">Student Inbox</h3>
          <p className="text-[10px] text-slate-500 font-mono">Cohort: {students.length} Total Users</p>
        </div>
        <div className="w-2.5 h-2.5 bg-amber-500 rounded-full"></div>
      </div>

      {/* List */}
      <div className="flex-1 overflow-y-auto divide-y divide-slate-800/60 custom-scrollbar">
        {students.length === 0 ? (
          <div className="p-8 text-center text-slate-500 text-xs">
            No students registered in the system yet.
          </div>
        ) : (
          students.map((student) => {
            const isSelected = selectedStudentId === student.permanentID;
            return (
              <div
                key={student.permanentID}
                onClick={() => onSelectStudent(student.permanentID)}
                className={`p-4 transition-all duration-150 cursor-pointer flex flex-col gap-2 ${
                  isSelected
                    ? 'bg-slate-800/70 border-l-4 border-amber-500 pl-3'
                    : 'hover:bg-slate-850 hover:text-slate-100 border-l-4 border-transparent'
                }`}
              >
                {/* Status + Date */}
                <div className="flex justify-between items-center">
                  {getStatusBadge(student.status)}
                  <span className="text-[9px] text-slate-500 font-mono">
                    {new Date(student.registeredAt).toLocaleDateString([], {
                      month: 'short',
                      day: 'numeric',
                    })}
                  </span>
                </div>

                {/* Name & UIN */}
                <div>
                  <h4 className={`text-xs font-bold ${isSelected ? 'text-white' : 'text-slate-300'}`}>
                    {student.name}
                  </h4>
                  <p className="text-[9px] font-mono text-slate-500 truncate mt-0.5">
                    {student.permanentID}
                  </p>
                </div>

                {/* Selected Program */}
                <div className="text-[10px] text-slate-400 truncate bg-slate-950/30 px-2 py-1.5 rounded-lg border border-slate-800/40">
                  <span className="text-slate-500 font-semibold block text-[8px] uppercase tracking-wider">
                    Assigned Path
                  </span>
                  <span className="font-semibold block truncate mt-0.5">
                    {getProgramTitle(student.selectedProgramId)}
                  </span>
                </div>
              </div>
            );
          })
        )}
      </div>
    </div>
  );
};
