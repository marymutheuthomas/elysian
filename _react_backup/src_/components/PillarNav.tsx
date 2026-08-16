import React from 'react';
import type { Program, StudentRecord } from '../types/index';
import { useProgramStore } from '../store/useProgramStore';

interface PillarNavProps {
  program: Program;
  student: StudentRecord;
}

export const PillarNav: React.FC<PillarNavProps> = ({ program, student }) => {
  const { getVisibleBlocks } = useProgramStore();
  const visibleBlocks = getVisibleBlocks(student, program);
  const currentBlock = visibleBlocks[student.activeBlockIndex];

  // Helper to determine if a pillar is complete (all its visible blocks are answered)
  const isPillarComplete = (pillarId: string) => {
    const pillar = program.pillars.find((p) => p.id === pillarId);
    if (!pillar) return false;
    const pillarBlocks = pillar.blocks.filter((b) =>
      visibleBlocks.some((vb) => vb.id === b.id)
    );
    if (pillarBlocks.length === 0) return true; // Empty is technically satisfied
    return pillarBlocks.every((b) => student.answers[b.id] !== undefined && student.answers[b.id] !== '');
  };

  // Helper to check if this pillar is the active one
  const isPillarActive = (pillarId: string) => {
    if (!currentBlock) return false;
    const pillar = program.pillars.find((p) => p.id === pillarId);
    return pillar?.blocks.some((b) => b.id === currentBlock.id) ?? false;
  };

  return (
    <div className="flex flex-col h-full bg-slate-900 text-white py-6 px-4 border-r border-slate-800">
      {/* Brand Header */}
      <div className="flex items-center gap-3 px-3 mb-8">
        <div className="w-8 h-8 rounded-lg bg-amber-500 flex items-center justify-center shadow-lg shadow-amber-500/20">
          <span className="text-sm font-extrabold text-white">E</span>
        </div>
        <div>
          <h2 className="font-extrabold text-sm tracking-wide uppercase text-amber-500">
            Elysian Portal
          </h2>
          <p className="text-[10px] text-slate-400 font-medium">Strategic Diagnostics</p>
        </div>
      </div>

      {/* Program Summary Info */}
      <div className="px-3 py-3.5 mb-6 rounded-xl bg-slate-800/40 border border-slate-800/60">
        <h4 className="text-xs font-bold text-slate-200 truncate">{program.title}</h4>
        <div className="flex justify-between items-center mt-2.5">
          <span className="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">
            Total Progress
          </span>
          <span className="text-xs font-bold text-amber-500">
            {Math.round(
              (visibleBlocks.filter((b) => student.answers[b.id] !== undefined && student.answers[b.id] !== '').length /
                Math.max(1, visibleBlocks.length)) *
                100
            )}
            %
          </span>
        </div>
        <div className="w-full h-1 bg-slate-800 rounded-full mt-2 overflow-hidden">
          <div
            className="h-full bg-amber-500 transition-all duration-500 ease-out"
            style={{
              width: `${
                (visibleBlocks.filter((b) => student.answers[b.id] !== undefined && student.answers[b.id] !== '').length /
                  Math.max(1, visibleBlocks.length)) *
                100
              }%`,
            }}
          />
        </div>
      </div>

      {/* Stepper Navigation */}
      <div className="flex-1 overflow-y-auto space-y-1 pr-1 custom-scrollbar">
        <span className="text-[10px] font-bold text-slate-500 uppercase tracking-widest px-3 mb-2 block">
          Pillars ({program.pillars.length})
        </span>
        {program.pillars.map((pillar, idx) => {
          const active = isPillarActive(pillar.id);
          const complete = isPillarComplete(pillar.id);

          return (
            <div
              key={pillar.id}
              className={`flex items-center gap-3.5 px-3 py-2.5 rounded-xl transition-all duration-200 ${
                active
                  ? 'bg-amber-500/10 border border-amber-500/20 text-amber-500'
                  : 'text-slate-400 hover:bg-slate-800/50 hover:text-slate-200 border border-transparent'
              }`}
            >
              {/* Stepper Node */}
              <div
                className={`w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold border transition-all duration-200 ${
                  active
                    ? 'bg-amber-500 border-amber-500 text-white shadow-md shadow-amber-500/20'
                    : complete
                    ? 'bg-emerald-500 border-emerald-500 text-white'
                    : 'border-slate-700 bg-slate-800 text-slate-400'
                }`}
              >
                {complete ? (
                  <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" />
                  </svg>
                ) : (
                  idx + 1
                )}
              </div>

              {/* Title / Description */}
              <div className="flex-1 min-w-0">
                <p
                  className={`text-xs font-bold truncate transition-colors ${
                    active ? 'text-amber-500' : 'text-slate-200'
                  }`}
                >
                  {pillar.title.replace(/^Pillar \d+:\s*/i, '')}
                </p>
                <p className="text-[9px] text-slate-500 truncate mt-0.5">
                  {pillar.description || 'Pillar assessment'}
                </p>
              </div>
            </div>
          );
        })}
      </div>

      {/* User Footer Profile */}
      <div className="mt-auto pt-4 border-t border-slate-800/80 px-2 flex items-center justify-between">
        <div className="min-w-0">
          <p className="text-xs font-bold text-slate-200 truncate">{student.name}</p>
          <p className="text-[9px] text-slate-500 truncate font-mono">{student.permanentID}</p>
        </div>
        <div className="w-1.5 h-1.5 rounded-full bg-emerald-500"></div>
      </div>
    </div>
  );
};
