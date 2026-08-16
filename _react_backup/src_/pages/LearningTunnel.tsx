import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useProgramStore } from '../store/useProgramStore';
import { PillarNav } from '../components/PillarNav';
import { ChatInterface } from '../components/ChatInterface';

// Import diagnostic card components
import { ShortAnswerCard } from '../components/cards/ShortAnswerCard';
import { LongAnswerCard } from '../components/cards/LongAnswerCard';
import { DropdownCard } from '../components/cards/DropdownCard';
import { MultipleChoiceCard } from '../components/cards/MultipleChoiceCard';
import { ScoringCard } from '../components/cards/ScoringCard';
import { ResultRevealCard } from '../components/cards/ResultRevealCard';
import { GoalCard } from '../components/cards/GoalCard';
import { BentoCard } from '../components/BentoCard';
import { master_profiles } from '../data/masterProfiles';

export const LearningTunnel: React.FC = () => {
  const {
    currentStudentID,
    students,
    programs,
    setAnswer,
    advanceBlock,
    getVisibleBlocks,
  } = useProgramStore();

  const navigate = useNavigate();
  const [phase, setPhase] = useState<'entering' | 'idle' | 'exiting'>('entering');
  const [isChatOpen, setIsChatOpen] = useState(true);

  const currentStudent = students.find((s) => s.permanentID === currentStudentID);
  const selectedProgram = currentStudent?.selectedProgramId
    ? programs.find((p) => p.id === currentStudent.selectedProgramId)
    : null;

  // Auth and status guard
  useEffect(() => {
    if (!currentStudentID || !currentStudent) {
      navigate('/login');
      return;
    }
    if (!currentStudent.selectedProgramId) {
      navigate('/programs');
      return;
    }
    if (currentStudent.status === 'payment_pending') {
      navigate('/payment');
      return;
    }
    if (currentStudent.status === 'completed') {
      navigate('/completed');
      return;
    }
  }, [currentStudentID, currentStudent, navigate]);

  // Handle slide animations on block changes
  useEffect(() => {
    setPhase('entering');
    const timer = setTimeout(() => setPhase('idle'), 400);
    return () => clearTimeout(timer);
  }, [currentStudent?.activeBlockIndex]);

  if (!currentStudent || !selectedProgram) return null;

  const visibleBlocks = getVisibleBlocks(currentStudent, selectedProgram);
  const currentBlock = visibleBlocks[currentStudent.activeBlockIndex];

  // If we run out of visible blocks but store hasn't updated status yet
  useEffect(() => {
    if (currentStudent.activeBlockIndex >= visibleBlocks.length && visibleBlocks.length > 0) {
      useProgramStore.getState().completeProgram();
      navigate('/completed');
    }
  }, [currentStudent.activeBlockIndex, visibleBlocks.length, navigate]);

  const handleAnswerChange = (blockId: string, value: string | number | boolean) => {
    setAnswer(blockId, value);
  };

  const handleBlockSubmit = () => {
    setPhase('exiting');
    setTimeout(() => {
      advanceBlock();
      // If we just completed, store updates status and redirects via guards
    }, 320);
  };

  const renderActiveCard = () => {
    if (!currentBlock) return null;
    const answer = currentStudent.answers[currentBlock.id] ?? '';

    switch (currentBlock.type) {
      case 'free_text':
        return (
          <LongAnswerCard
            question={currentBlock.question}
            placeholder={currentBlock.placeholder}
            value={String(answer)}
            onChange={(val) => handleAnswerChange(currentBlock.id, val)}
            onSubmit={handleBlockSubmit}
          />
        );

      case 'short_answer':
        return (
          <ShortAnswerCard
            question={currentBlock.question}
            placeholder={currentBlock.placeholder}
            value={String(answer)}
            onChange={(val) => handleAnswerChange(currentBlock.id, val)}
            onSubmit={handleBlockSubmit}
            required={currentBlock.required}
          />
        );

      case 'dropdown':
        return (
          <DropdownCard
            question={currentBlock.question}
            options={(currentBlock as any).options ?? []}
            value={String(answer)}
            onChange={(val) => {
              handleAnswerChange(currentBlock.id, val);
              handleBlockSubmit();
            }}
            placeholder={currentBlock.placeholder}
            required={currentBlock.required}
          />
        );

      case 'branching':
        return (
          <MultipleChoiceCard
            question={currentBlock.question}
            options={(currentBlock as any).options ?? []}
            selectedValue={String(answer)}
            onSelect={(val) => {
              handleAnswerChange(currentBlock.id, val);
              handleBlockSubmit();
            }}
            required={currentBlock.required}
          />
        );

      case 'scoring':
        return (
          <ScoringCard
            question={currentBlock.question}
            options={(currentBlock as any).options.map((o: any) => ({
              value: o.value,
              label: o.label,
              hidden_code: String(o.hidden_code || o.value),
            }))}
            selectedValue={String(answer)}
            onSelect={(val) => {
              handleAnswerChange(currentBlock.id, val);
              handleBlockSubmit();
            }}
            required={currentBlock.required}
          />
        );

      case 'goal':
        return (
          <GoalCard
            question={currentBlock.question}
            placeholder={currentBlock.placeholder}
            value={String(answer)}
            onChange={(val) => handleAnswerChange(currentBlock.id, val)}
            onSubmit={handleBlockSubmit}
            required={currentBlock.required}
          />
        );

      case 'result_reveal': {
        const profileCode = currentStudent.profileCode || '';
        const matchedProfile = master_profiles[profileCode.toUpperCase()];
        if (!matchedProfile) {
          return (
            <BentoCard className="p-8 text-center flex flex-col items-center gap-6 border border-slate-100 shadow-md rounded-2xl bg-white">
              <div className="w-12 h-12 border-4 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
              <div>
                <h2 className="text-xl font-bold text-slate-800 mb-1">Assembling Profile...</h2>
                <p className="text-slate-400 text-xs max-w-xs leading-relaxed">
                  Compiling structural responses to reveal your customized strategy.
                </p>
              </div>
            </BentoCard>
          );
        }
        return (
          <ResultRevealCard
            displayData={matchedProfile}
            actionPrompt={(currentBlock as any).action_prompt || "Based on your scores, custom goals have been recommended. Rewrite or save them below."}
            initialValue={String(answer)}
            onSubmit={(val) => {
              handleAnswerChange(currentBlock.id, val);
              handleBlockSubmit();
            }}
          />
        );
      }

      default:
        return (
          <div className="text-center p-6 bg-white border border-slate-100 rounded-2xl">
            Block type not supported.
          </div>
        );
    }
  };

  const animClass =
    phase === 'entering'
      ? 'animate-card-enter'
      : phase === 'exiting'
      ? 'animate-card-exit'
      : '';

  return (
    <div className="min-h-screen w-screen bg-[#F2F7FD] flex overflow-hidden">
      {/* 1. Left Pillar Nav Sidebar */}
      <div className="w-72 flex-shrink-0 h-screen hidden md:block">
        <PillarNav program={selectedProgram} student={currentStudent} />
      </div>

      {/* 2. Central Active Workstation */}
      <div className="flex-1 flex flex-col h-screen overflow-y-auto relative px-6 py-8">
        {/* Top bar */}
        <div className="flex justify-between items-center mb-8">
          <div className="md:hidden flex items-center gap-2">
            <span className="text-amber-500 font-extrabold text-lg uppercase tracking-tight font-outfit">E</span>
            <span className="text-[10px] text-slate-400 font-bold uppercase tracking-wider font-mono">
              ESA
            </span>
          </div>

          <div className="text-xs text-slate-400 font-medium">
            Active: <span className="font-bold text-slate-700">{selectedProgram.title}</span>
          </div>

          <button
            onClick={() => setIsChatOpen(!isChatOpen)}
            className="flex items-center gap-2 px-3 py-1.5 bg-white border border-slate-200/60 rounded-xl shadow-sm hover:bg-slate-50 transition-colors text-xs font-semibold text-slate-600"
          >
            <svg className="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
            {isChatOpen ? 'Hide Support' : 'Show Support'}
          </button>
        </div>

        {/* Progress Tracker (Horizontal Indicator for mobile/tablet) */}
        {visibleBlocks.length > 0 && (
          <div className="w-full bg-slate-200 h-1.5 rounded-full overflow-hidden mb-8">
            <div
              className="bg-amber-500 h-full transition-all duration-500 ease-out"
              style={{
                width: `${((currentStudent.activeBlockIndex) / visibleBlocks.length) * 100}%`,
              }}
            />
          </div>
        )}

        {/* Active Question Panel */}
        <div className="flex-1 flex items-center justify-center">
          {currentBlock ? (
            <div className={`w-full max-w-2xl ${animClass}`}>
              <div className="text-center mb-6">
                <span className="text-[10px] font-bold text-amber-600 bg-amber-50 border border-amber-200/30 px-2.5 py-1 rounded-full uppercase tracking-widest">
                  Block {currentStudent.activeBlockIndex + 1} of {visibleBlocks.length}
                </span>
              </div>
              {renderActiveCard()}
            </div>
          ) : (
            <div className="text-center text-slate-400 py-12">
              All tasks complete. Finalizing strategy...
            </div>
          )}
        </div>

        {/* Footer label */}
        <div className="text-center text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-8">
          Elysian Strategic Assessment System © 2026
        </div>
      </div>

      {/* 3. Right Chat Sidebar */}
      {isChatOpen && (
        <div className="w-80 h-screen border-l border-slate-200/50 flex-shrink-0 animate-fade-in">
          <ChatInterface mode="student" />
        </div>
      )}
    </div>
  );
};
