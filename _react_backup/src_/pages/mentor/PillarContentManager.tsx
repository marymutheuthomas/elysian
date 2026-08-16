import React, { useState } from 'react';
import { useProgramStore } from '../../store/useProgramStore';
import type { Block, BlockType } from '../../types/index';

/**
 * PillarContentManager
 * ---------------------
 * Admin interface for managing pillars and their components for each mentorship program.
 * Provides a dropdown to select a program, lists its pillars, and allows adding a new block
 * (component) to a pillar via a simple modal. The new block is persisted to the
 * `programs` array in the Zustand store which automatically syncs to localStorage.
 */
export const PillarContentManager: React.FC = () => {
  const { programs, setPrograms } = useProgramStore();
  const activePrograms = programs.filter((p) => p.isActive);
  const [selectedProgramId, setSelectedProgramId] = useState<string>(
    activePrograms.length > 0 ? activePrograms[0].id : ''
  );
  const [showModal, setShowModal] = useState<boolean>(false);
  const [targetPillarId, setTargetPillarId] = useState<string>('');
  const [newPillarTitle, setNewPillarTitle] = useState<string>('');
  const [showPillarModal, setShowPillarModal] = useState<boolean>(false);
  const [isEditingPillar, setIsEditingPillar] = useState<boolean>(false);
  const [editingPillarId, setEditingPillarId] = useState<string>('');
  const [isEditing, setIsEditing] = useState<boolean>(false);
  const [newBlockType, setNewBlockType] = useState<BlockType>('short_answer');
  const [newQuestion, setNewQuestion] = useState<string>('');
  const [newPlaceholder, setNewPlaceholder] = useState<string>('');
  const [editingBlockId, setEditingBlockId] = useState<string>('');
  const selectedProgram = programs.find((p) => p.id === selectedProgramId);

  // Open modal for a specific pillar
  const handleAddComponent = (pillarId: string) => {
    setTargetPillarId(pillarId);
    setNewBlockType('short_answer');
    setNewQuestion('');
    setNewPlaceholder('');
    setIsEditing(false);
    setShowModal(true);
  };

  // Edit existing block
  const handleEditComponent = (pillarId: string, block: Block) => {
    setTargetPillarId(pillarId);
    setEditingBlockId(block.id);
    setNewBlockType(block.type);
    setNewQuestion(block.question);
    setNewPlaceholder(block.placeholder || '');
    setIsEditing(true);
    setShowModal(true);
  };

  // Delete a block from a pillar
  const handleDeleteBlock = (pillarId: string, blockId: string) => {
    if (!selectedProgram) return;
    const updatedPrograms = programs.map((prog) => {
      if (prog.id !== selectedProgram.id) return prog;
      const updatedPillars = prog.pillars.map((pillar) => {
        if (pillar.id !== pillarId) return pillar;
        return { ...pillar, blocks: pillar.blocks.filter((b) => b.id !== blockId) };
      });
      return { ...prog, pillars: updatedPillars };
    });
    setPrograms(updatedPrograms);
  };

  // Add a new pillar
  const handleAddPillar = () => {
    setNewPillarTitle('');
    setIsEditingPillar(false);
    setShowPillarModal(true);
  };

  // Edit an existing pillar
  const handleEditPillar = (pillarId: string, title: string) => {
    setNewPillarTitle(title);
    setEditingPillarId(pillarId);
    setIsEditingPillar(true);
    setShowPillarModal(true);
  };

  // Delete a pillar
  const handleDeletePillar = (pillarId: string) => {
    if (!selectedProgram) return;
    const updatedPrograms = programs.map((prog) => {
      if (prog.id !== selectedProgram.id) return prog;
      const updatedPillars = prog.pillars.filter((p) => p.id !== pillarId);
      return { ...prog, pillars: updatedPillars };
    });
    setPrograms(updatedPrograms);
  };

  // Save pillar (add or update)
  const savePillar = () => {
    if (!selectedProgram) return;
    if (isEditingPillar) {
      const updatedPrograms = programs.map((prog) => {
        if (prog.id !== selectedProgram.id) return prog;
        const updatedPillars = prog.pillars.map((p) =>
          p.id === editingPillarId ? { ...p, title: newPillarTitle } : p
        );
        return { ...prog, pillars: updatedPillars };
      });
      setPrograms(updatedPrograms);
    } else {
      const newPillar = { id: `pill-${Date.now()}`, title: newPillarTitle, blocks: [] };
      const updatedPrograms = programs.map((prog) => {
        if (prog.id !== selectedProgram.id) return prog;
        return { ...prog, pillars: [...prog.pillars, newPillar] };
      });
      setPrograms(updatedPrograms);
    }
    setShowPillarModal(false);
    setIsEditingPillar(false);
    setEditingPillarId('');
    setNewPillarTitle('');
  };

  // Persist a new block or update existing block to the store
  const saveNewBlock = () => {
    if (!selectedProgram) return;
    const newBlock: Block = {
      id: isEditing ? editingBlockId : `blk-${Date.now()}`,
      type: newBlockType,
      question: newQuestion || 'New question',
      placeholder: newPlaceholder,
    } as Block;

    const updatedPrograms = programs.map((prog) => {
      if (prog.id !== selectedProgram.id) return prog;
      const updatedPillars = prog.pillars.map((pillar) => {
        if (pillar.id !== targetPillarId) return pillar;
        if (isEditing) {
          const updatedBlocks = pillar.blocks.map((b) => (b.id === editingBlockId ? newBlock : b));
          return { ...pillar, blocks: updatedBlocks };
        }
        return { ...pillar, blocks: [...pillar.blocks, newBlock] };
      });
      return { ...prog, pillars: updatedPillars };
    });
    setPrograms(updatedPrograms);
    setShowModal(false);
    setIsEditing(false);
    setEditingBlockId('');
  };

  return (
    <div className="p-4 bg-[#0F172A] rounded-xl text-slate-100">
      <h2 className="text-lg font-bold mb-4">Pillar Content Manager</h2>
      {activePrograms.length === 0 ? (
        <p className="text-slate-400">No active programs available.</p>
      ) : (
        <>
          <select
            value={selectedProgramId}
            onChange={(e) => setSelectedProgramId(e.target.value)}
            className="w-full mb-4 p-2 bg-[#0A0F1C] border border-slate-700 rounded text-slate-200"
          >
            {activePrograms.map((p) => (
              <option key={p.id} value={p.id}>
                {p.title}
              </option>
            ))}
          </select>
          {selectedProgram ? (
            <>
              <button
                onClick={handleAddPillar}
                className="mb-4 px-3 py-1 bg-emerald-600 text-white rounded"
              >
                Add Pillar
              </button>
              <ul className="space-y-2">
                {selectedProgram.pillars.map((pillar) => (
                  <li key={pillar.id} className="border border-slate-600 p-3 rounded">
                    <div className="flex justify-between items-center">
                      <span className="font-medium text-slate-200">{pillar.title}</span>
                      <button
                        onClick={() => handleAddComponent(pillar.id)}
                        className="px-2 py-1 bg-amber-500/20 text-amber-500 rounded"
                      >
                        Add Component
                      </button>
                    </div>
                     {/* Pillar actions */}
                     <div className="flex space-x-2 mt-2">
                       <button
                         onClick={() => handleEditPillar(pillar.id, pillar.title)}
                         className="px-2 py-1 bg-sky-500/20 text-sky-500 rounded text-xs"
                       >
                         Edit Pillar
                       </button>
                       <button
                         onClick={() => handleDeletePillar(pillar.id)}
                         className="px-2 py-1 bg-red-600/20 text-red-500 rounded text-xs"
                       >
                         Delete Pillar
                       </button>
                     </div>
                    {/* List existing blocks */}
                    {pillar.blocks && pillar.blocks.length > 0 && (
                      <div className="mt-2 space-y-2">
                        {pillar.blocks.map((block) => (
                          <div key={block.id} className="flex justify-between items-center bg-slate-800 p-2 rounded">
                            <span className="text-sm text-slate-200">{block.question}</span>
                            <div className="space-x-2">
                              <button
                                onClick={() => handleEditComponent(pillar.id, block)}
                                className="px-2 py-1 bg-amber-500/20 text-amber-500 rounded text-xs"
                              >
                                Edit
                              </button>
                              <button
                                onClick={() => handleDeleteBlock(pillar.id, block.id)}
                                className="px-2 py-1 bg-red-600/20 text-red-500 rounded text-xs"
                              >
                                Delete
                              </button>
                            </div>
                          </div>
                        ))}
                      </div>
                    )}
                  </li>
                ))}
              </ul>
            </>
          ) : (
            <p className="text-slate-400">Select a program to view its pillars.</p>
          )}
        </>
      )}

      {/* Modal */}
      {showModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-[#0F172A] p-6 rounded-lg w-96 text-slate-200">
            <h3 className="text-lg font-semibold mb-4">{isEditing ? 'Edit Component' : 'Add New Component'}</h3>
            <label className="block mb-2">
              <span className="text-sm">Block Type</span>
              <select
                value={newBlockType}
                onChange={(e) => setNewBlockType(e.target.value as BlockType)}
                className="w-full mt-1 p-2 bg-[#0A0F1C] border border-slate-700 rounded text-slate-200"
              >
                <option value="short_answer">Short Answer</option>
                <option value="scoring">Scoring</option>
                <option value="dropdown">Dropdown</option>
                <option value="free_text">Free Text</option>
                <option value="branching">Branching</option>
                <option value="goal">Goal</option>
                <option value="result_reveal">Result Reveal</option>
              </select>
            </label>
            <label className="block mb-2">
              <span className="text-sm">Question Text</span>
              <input
                type="text"
                value={newQuestion}
                onChange={(e) => setNewQuestion(e.target.value)}
                className="w-full mt-1 p-2 bg-[#0A0F1C] border border-slate-700 rounded text-slate-200"
                placeholder="Enter question..."
              />
            </label>
            <label className="block mb-4">
              <span className="text-sm">Placeholder (optional)</span>
              <input
                type="text"
                value={newPlaceholder}
                onChange={(e) => setNewPlaceholder(e.target.value)}
                className="w-full mt-1 p-2 bg-[#0A0F1C] border border-slate-700 rounded text-slate-200"
                placeholder="Placeholder text"
              />
            </label>
            <div className="flex justify-end space-x-2">
              <button
                onClick={() => setShowModal(false)}
                className="px-3 py-1 bg-slate-700 text-slate-200 rounded"
              >
                Cancel
              </button>
              <button
                onClick={saveNewBlock}
                className="px-3 py-1 bg-amber-600 text-white rounded"
              >
                Save
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Pillar Modal */}
      {showPillarModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-[#0F172A] p-6 rounded-lg w-96 text-slate-200">
            <h3 className="text-lg font-semibold mb-4">{isEditingPillar ? 'Edit Pillar' : 'Add Pillar'}</h3>
            <label className="block mb-2">
              <span className="text-sm">Pillar Title</span>
              <input
                type="text"
                value={newPillarTitle}
                onChange={(e) => setNewPillarTitle(e.target.value)}
                className="w-full mt-1 p-2 bg-[#0A0F1C] border border-slate-700 rounded text-slate-200"
                placeholder="Enter pillar title"
              />
            </label>
            <div className="flex justify-end space-x-2">
              <button
                onClick={() => setShowPillarModal(false)}
                className="px-3 py-1 bg-slate-700 text-slate-200 rounded"
              >
                Cancel
              </button>
              <button
                onClick={savePillar}
                className="px-3 py-1 bg-emerald-600 text-white rounded"
              >
                Save
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
