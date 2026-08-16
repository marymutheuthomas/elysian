import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useProgramStore } from '../store/useProgramStore';
import { BentoCard } from '../components/BentoCard';
import { DropdownCard } from '../components/cards/DropdownCard';

export const ProgramSelection: React.FC = () => {
  const { programs, currentStudentID, selectProgram, students } = useProgramStore();
  const navigate = useNavigate();

  const [selectedId, setSelectedId] = useState<string>('');

  const currentStudent = students.find((s) => s.permanentID === currentStudentID);

  // Authentication guard
  useEffect(() => {
    if (!currentStudentID || !currentStudent) {
      navigate('/login');
    }
  }, [currentStudentID, currentStudent, navigate]);

  const handleSelect = () => {
    if (!selectedId) return;
    selectProgram(selectedId);
    navigate('/payment');
  };

  const handleLogout = () => {
    useProgramStore.getState().logoutStudent();
    navigate('/login');
  };

  const activePrograms = programs.filter((p) => p.isActive);

  // Map active programs to options format for DropdownCard
  const dropdownOptions = activePrograms.map((p) => ({
    value: p.id,
    label: `${p.code} - ${p.title} (${p.duration})`,
  }));

  const selectedProgram = activePrograms.find((p) => p.id === selectedId);

  if (!currentStudent) return null;

  return (
    <div className="min-h-screen w-screen bg-[#F2F7FD] pb-16 pt-8 px-6 relative">
      {/* Decorative blurs */}
      <div className="absolute top-1/4 left-1/4 w-96 h-96 bg-amber-500/5 rounded-full blur-3xl -z-10 animate-pulse-gold"></div>

      {/* Header Bar */}
      <div className="max-w-4xl mx-auto flex items-center justify-between mb-12">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-slate-900 flex items-center justify-center shadow-lg">
            <span className="text-white font-extrabold text-base">E</span>
          </div>
          <div>
            <h2 className="font-extrabold text-base tracking-wide uppercase text-slate-800 font-outfit">
              Elysian Accelerator
            </h2>
            <p className="text-[10px] text-slate-400 font-semibold font-mono uppercase">
              UIN: {currentStudent.permanentID}
            </p>
          </div>
        </div>

        <button
          onClick={handleLogout}
          className="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-700 bg-slate-100/50 hover:bg-slate-100 rounded-xl transition-all border border-slate-200/40"
        >
          Logout
        </button>
      </div>

      {/* Main content */}
      <div className="max-w-2xl mx-auto">
        <div className="text-center mb-10">
          <span className="gold-badge mb-3">Diagnostic Selection</span>
          <h1 className="text-3xl font-extrabold text-slate-800 tracking-tight mb-2 font-outfit">
            Select Your Transformation Path
          </h1>
          <p className="text-sm text-slate-450 max-w-lg mx-auto leading-relaxed">
            Choose from our 15 specialized program tracks. Use the search input inside the dropdown to filter by name or code.
          </p>
        </div>

        {activePrograms.length === 0 ? (
          <div className="text-center py-12 text-slate-400 text-sm">
            No active programs available at the moment. Please contact support.
          </div>
        ) : (
          <div className="space-y-6">
            {/* Dropdown UI Selection */}
            <div className="flex flex-col gap-2">
              <DropdownCard
                question="Choose your accelerator track:"
                placeholder="Search and select a program..."
                options={dropdownOptions}
                value={selectedId}
                onChange={(val) => setSelectedId(val)}
                required
              />

              {/* Contextual Fee Centered Text */}
              <p className="text-slate-500 text-sm text-center mt-2 font-medium">
                All mentorship programs are currently KESH 1440 / 12 USD
              </p>
            </div>

            {/* Smart Preview & CTA */}
            {selectedProgram && (
              <div className="animate-slide-up">
                <BentoCard className="p-6 md:p-8 bg-white border border-slate-100 shadow-lg rounded-3xl space-y-6">
                  {/* Header */}
                  <div>
                    <span className="text-[10px] font-bold text-amber-600 bg-amber-50 border border-amber-200/20 px-2.5 py-1 rounded-full uppercase tracking-wider">
                      Program Preview
                    </span>
                    <h2 className="text-2xl font-bold text-slate-800 mt-3 font-outfit">
                      {selectedProgram.title}
                    </h2>
                    <p className="text-xs text-slate-400 mt-1 font-semibold font-mono uppercase">
                      Code: {selectedProgram.code} | Duration: {selectedProgram.duration}
                    </p>
                  </div>

                  {/* Description */}
                  <div>
                    <h3 className="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1.5">
                      Overview
                    </h3>
                    <p className="text-sm text-slate-600 leading-relaxed">
                      {selectedProgram.description}
                    </p>
                  </div>

                  {/* Key Focus Areas (Outcomes) */}
                  <div className="space-y-2">
                    <h3 className="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">
                      Key Focus Areas
                    </h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                      {selectedProgram.outcomes.map((outcome, index) => (
                        <div key={index} className="flex gap-2 items-start text-xs text-slate-600 leading-relaxed bg-slate-50/50 p-2.5 border border-slate-100 rounded-xl">
                          <svg className="w-4 h-4 text-emerald-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M5 13l4 4L19 7" />
                          </svg>
                          <span>{outcome}</span>
                        </div>
                      ))}
                    </div>
                  </div>

                  {/* CTA button (Proceed to Payment) */}
                  <div className="pt-4 border-t border-slate-50 flex items-center justify-end">
                    <button
                      onClick={handleSelect}
                      className="elysian-btn elysian-btn-gold px-8 py-3 text-sm font-bold shadow-md shadow-amber-500/10 flex items-center gap-2"
                    >
                      Proceed to Payment
                      <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                      </svg>
                    </button>
                  </div>
                </BentoCard>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
};
