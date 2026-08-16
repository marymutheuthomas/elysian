import React, { useState } from 'react';
import { BentoCard } from '../BentoCard';

interface DisplayData {
  title: string;
  strengths: string[];
  weaknesses: string[];
  suggested_goals: string[];
}

interface ResultRevealCardProps {
  displayData: DisplayData;
  actionPrompt: string;
  /**
   * Called when the user submits their rewritten goal.
   * Receives the textarea value.
   */
  onSubmit: (value: string) => void;
  /** Optional initial value for the textarea (e.g., pre‑filled goal). */
  initialValue?: string;
}

export const ResultRevealCard: React.FC<ResultRevealCardProps> = ({
  displayData,
  actionPrompt,
  onSubmit,
  initialValue = '',
}) => {
  const [goalText, setGoalText] = useState(initialValue);
  const [isSaved, setIsSaved] = useState(false);

  const handleChange = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
    setGoalText(e.target.value);
    setIsSaved(false);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    onSubmit(goalText);
    setIsSaved(true);
  };

  return (
    <BentoCard className="w-full max-w-2xl mx-auto p-6 md:p-8">
      {/* Header */}
      <h2 className="text-3xl font-extrabold text-[#0F172A] mb-4 text-center">
        {displayData.title}
      </h2>

      {/* Traits Section */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        {/* Strengths */}
        <div>
          <h3 className="text-sm font-bold text-[#0F172A] uppercase tracking-wider mb-2 opacity-50">Strengths</h3>
          <ul className="text-sm text-[#0F172A] space-y-1">
            {displayData.strengths.map((s, i) => (
              <li key={i} className="flex items-start gap-2"><span className="text-[#C99700] mt-0.5">✓</span>{s}</li>
            ))}
          </ul>
        </div>
        {/* Weaknesses */}
        <div>
          <h3 className="text-sm font-bold text-[#0F172A] uppercase tracking-wider mb-2 opacity-50">Growth Areas</h3>
          <ul className="text-sm text-[#0F172A] space-y-1">
            {displayData.weaknesses.map((w, i) => (
              <li key={i} className="flex items-start gap-2"><span className="text-[#0F172A] opacity-40 mt-0.5">→</span>{w}</li>
            ))}
          </ul>
        </div>
      </div>

      {/* Suggestions Box */}
      <div className="bg-[#F2F7FD] rounded-xl border border-[#E2E8F0] p-4 mb-6">
        <h3 className="text-sm font-bold text-[#0F172A] uppercase tracking-wider mb-3 opacity-50">Suggested Goals</h3>
        <ul className="text-sm text-[#0F172A] space-y-2">
          {displayData.suggested_goals.map((g, i) => (
            <li key={i} className="flex items-start gap-2"><span className="text-[#C99700] font-bold mt-0.5">→</span>{g}</li>
          ))}
        </ul>
      </div>

      {/* Action Prompt */}
      <p className="text-base text-[#0F172A] font-medium mb-3 leading-relaxed">{actionPrompt}</p>

      {/* Input Area */}
      <form onSubmit={handleSubmit} className="flex flex-col gap-3">
        <textarea
          value={goalText}
          onChange={handleChange}
          rows={5}
          placeholder="Rewrite your goals here..."
          className="w-full rounded-xl border border-[#E2E8F0] p-4 text-sm text-[#0F172A] placeholder-[#0F172A]/40 bg-[#FFFFFF] focus:outline-none focus:ring-2 focus:ring-[#C99700] focus:border-[#C99700] transition-all resize-none"
          required
        />

        {/* Saved indicator */}
        {isSaved && (
          <div className="flex items-center gap-1.5 text-[#C99700] text-xs font-semibold animate-card-enter">
            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            Saved securely
          </div>
        )}

        <button
          type="submit"
          className="self-end px-6 py-2.5 rounded-xl bg-[#C99700] hover:bg-[#b38600] active:scale-95 text-white font-semibold transition-all text-sm tracking-wide shadow-sm"
        >
          Submit Goal
        </button>
      </form>
    </BentoCard>
  );
};
