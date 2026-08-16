import React, { useState } from 'react';
import { useProgramStore } from '../../store/useProgramStore';
import type { Program } from '../../types/index';

export const ProgramRegistry: React.FC = () => {
  const { programs, addProgram, updateProgram, deleteProgram, toggleProgramActive } = useProgramStore();

  const [editingProgramId, setEditingProgramId] = useState<string | null>(null);
  const [isAdding, setIsAdding] = useState(false);

  // Form states
  const [code, setCode] = useState('');
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [fee, setFee] = useState(0);
  const [duration, setDuration] = useState('');
  const [outcomesText, setOutcomesText] = useState('');
  const [isActive, setIsActive] = useState(true);

  const resetForm = () => {
    setCode('');
    setTitle('');
    setDescription('');
    setFee(0);
    setDuration('');
    setOutcomesText('');
    setIsActive(true);
    setEditingProgramId(null);
    setIsAdding(false);
  };

  const handleStartAdd = () => {
    resetForm();
    setIsAdding(true);
  };

  const handleStartEdit = (p: Program) => {
    resetForm();
    setEditingProgramId(p.id);
    setCode(p.code);
    setTitle(p.title);
    setDescription(p.description);
    setFee(p.fee);
    setDuration(p.duration);
    setOutcomesText(p.outcomes.join('\n'));
    setIsActive(p.isActive);
  };

  const handleSave = (e: React.FormEvent) => {
    e.preventDefault();
    const outcomes = outcomesText
      .split('\n')
      .map((s) => s.trim())
      .filter((s) => s.length > 0);

    if (isAdding) {
      const newProgram: Program = {
        id: `PROG-${Date.now()}`,
        code: code.trim(),
        title: title.trim(),
        description: description.trim(),
        fee: Number(fee),
        duration: duration.trim(),
        outcomes,
        isActive,
        pillars: [
          {
            id: `p-${Date.now()}-1`,
            title: 'Pillar 1: Introduction Assessment',
            description: 'Define your preliminary parameters.',
            blocks: [
              {
                id: `b-${Date.now()}-1`,
                type: 'free_text',
                question: 'What is your primary goal for this program?',
                placeholder: 'Describe your objectives...',
                required: true,
              },
            ],
          },
        ],
      };
      addProgram(newProgram);
    } else if (editingProgramId) {
      updateProgram(editingProgramId, {
        code: code.trim(),
        title: title.trim(),
        description: description.trim(),
        fee: Number(fee),
        duration: duration.trim(),
        outcomes,
        isActive,
      });
    }

    resetForm();
  };

  return (
    <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 h-full min-h-0 text-white">
      {/* Programs List */}
      <div className="lg:col-span-5 flex flex-col h-full min-h-0 bg-slate-900 border border-slate-800 rounded-2xl p-5 overflow-y-auto custom-scrollbar">
        <div className="flex justify-between items-center pb-4 border-b border-slate-800 mb-4">
          <div>
            <h3 className="font-bold text-sm text-slate-100 font-outfit">Program Registry</h3>
            <p className="text-[10px] text-slate-500 font-mono">
              CRUD Actions ({programs.length} Paths)
            </p>
          </div>
          <button
            onClick={handleStartAdd}
            className="px-3.5 py-1.5 bg-amber-500 hover:bg-amber-600 active:scale-95 text-white text-xs font-bold rounded-xl transition-all shadow-sm"
          >
            Add Program
          </button>
        </div>

        <div className="space-y-3 pr-1">
          {programs.map((p) => {
            const isSelected = editingProgramId === p.id;
            return (
              <div
                key={p.id}
                className={`p-4 rounded-xl border transition-all cursor-pointer ${
                  isSelected
                    ? 'bg-slate-800/80 border-amber-500/40 shadow-lg'
                    : 'bg-slate-950/20 border-slate-800/60 hover:bg-slate-850'
                }`}
                onClick={() => handleStartEdit(p)}
              >
                <div className="flex justify-between items-start mb-2.5">
                  <div className="flex gap-2 items-center">
                    <span className="text-[9px] font-bold text-slate-400 font-mono bg-slate-950 border border-slate-800 px-2 py-0.5 rounded">
                      {p.code}
                    </span>
                    <span
                      className={`w-2 h-2 rounded-full ${
                        p.isActive ? 'bg-emerald-500' : 'bg-slate-600'
                      }`}
                    ></span>
                  </div>
                  <span className="text-[10px] font-bold text-amber-500 font-mono">
                    ${p.fee.toLocaleString()}
                  </span>
                </div>

                <h4 className="text-xs font-bold text-slate-200 truncate">{p.title}</h4>
                <p className="text-[10px] text-slate-500 line-clamp-2 mt-1 leading-relaxed">
                  {p.description}
                </p>

                <div className="flex justify-between items-center mt-3 pt-3 border-t border-slate-850/60 text-[9px] text-slate-500 font-semibold uppercase">
                  <span>Duration: {p.duration}</span>
                  <div className="flex gap-2">
                    <button
                      onClick={(e) => {
                        e.stopPropagation();
                        toggleProgramActive(p.id);
                      }}
                      className="hover:text-amber-500"
                    >
                      {p.isActive ? 'Deactivate' : 'Activate'}
                    </button>
                    <button
                      onClick={(e) => {
                        e.stopPropagation();
                        if (confirm('Delete program? This cannot be undone.')) {
                          deleteProgram(p.id);
                          if (editingProgramId === p.id) resetForm();
                        }
                      }}
                      className="text-red-500 hover:text-red-400"
                    >
                      Delete
                    </button>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Editor Panel */}
      <div className="lg:col-span-7 flex flex-col h-full min-h-0 bg-slate-900 border border-slate-800 rounded-2xl p-5 overflow-y-auto custom-scrollbar">
        {isAdding || editingProgramId ? (
          <form onSubmit={handleSave} className="space-y-4">
            <h3 className="text-sm font-bold text-slate-100 uppercase tracking-wider border-b border-slate-850 pb-3 font-outfit">
              {isAdding ? 'Create Accelerator Path' : `Edit: ${title}`}
            </h3>

            <div className="grid grid-cols-2 gap-4">
              <div className="flex flex-col gap-1.5">
                <label className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                  Program Code
                </label>
                <input
                  type="text"
                  value={code}
                  onChange={(e) => setCode(e.target.value)}
                  placeholder="e.g. LDR-004"
                  className="rounded-xl border border-slate-800 px-3.5 py-2.5 text-xs text-slate-100 bg-[#0A0F1C] focus:outline-none focus:ring-2 focus:ring-amber-500"
                  required
                />
              </div>

              <div className="flex flex-col gap-1.5">
                <label className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                  Duration
                </label>
                <input
                  type="text"
                  value={duration}
                  onChange={(e) => setDuration(e.target.value)}
                  placeholder="e.g. 6 Weeks"
                  className="rounded-xl border border-slate-800 px-3.5 py-2.5 text-xs text-slate-100 bg-[#0A0F1C] focus:outline-none focus:ring-2 focus:ring-amber-500"
                  required
                />
              </div>
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                Program Title
              </label>
              <input
                type="text"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                placeholder="Enter path title"
                className="rounded-xl border border-slate-800 px-3.5 py-2.5 text-xs text-slate-100 bg-[#0A0F1C] focus:outline-none focus:ring-2 focus:ring-amber-500"
                required
              />
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                Strategic Fee ($)
              </label>
              <input
                type="number"
                value={fee}
                onChange={(e) => setFee(Number(e.target.value))}
                placeholder="e.g. 2500"
                className="rounded-xl border border-slate-800 px-3.5 py-2.5 text-xs text-slate-100 bg-[#0A0F1C] focus:outline-none focus:ring-2 focus:ring-amber-500 font-mono"
                required
              />
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                Description
              </label>
              <textarea
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Write a brief description..."
                rows={3}
                className="rounded-xl border border-slate-800 p-3.5 text-xs text-slate-100 bg-[#0A0F1C] focus:outline-none focus:ring-2 focus:ring-amber-500 resize-none"
                required
              />
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                Outcomes (One outcome per line)
              </label>
              <textarea
                value={outcomesText}
                onChange={(e) => setOutcomesText(e.target.value)}
                placeholder="Outcome 1&#10;Outcome 2&#10;Outcome 3"
                rows={4}
                className="rounded-xl border border-slate-800 p-3.5 text-xs text-slate-100 bg-[#0A0F1C] focus:outline-none focus:ring-2 focus:ring-amber-500 font-sans resize-none"
                required
              />
            </div>

            <div className="flex items-center gap-2 pt-2">
              <input
                type="checkbox"
                id="isActiveCheckbox"
                checked={isActive}
                onChange={(e) => setIsActive(e.target.checked)}
                className="rounded text-amber-500 bg-[#0A0F1C] border-slate-800 focus:ring-amber-500 w-4 h-4"
              />
              <label htmlFor="isActiveCheckbox" className="text-xs font-semibold text-slate-300">
                Mark as Active (Visible to students)
              </label>
            </div>

            <div className="flex justify-end gap-3 pt-4 border-t border-slate-850">
              <button
                type="button"
                onClick={resetForm}
                className="px-4 py-2 bg-transparent hover:bg-slate-800 text-slate-400 hover:text-slate-200 border border-slate-800 rounded-xl text-xs font-bold transition-all"
              >
                Cancel
              </button>
              <button
                type="submit"
                className="px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-xl text-xs transition-all shadow-sm"
              >
                {isAdding ? 'Create Path' : 'Save Changes'}
              </button>
            </div>
          </form>
        ) : (
          <div className="flex flex-col items-center justify-center h-full text-slate-500 text-xs py-12">
            <svg
              className="w-12 h-12 text-slate-700 mb-3"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1.5}
                d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"
              />
            </svg>
            Select a program to edit or create a new program path.
          </div>
        )}
      </div>
    </div>
  );
};
