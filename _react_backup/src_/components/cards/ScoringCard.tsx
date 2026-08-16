import React, { useState } from 'react';
import { BentoCard } from '../BentoCard';

interface ScoringOption {
  value: string;
  label: string;
  hidden_code: string;
}

interface ScoringCardProps {
  question: string;
  options: ScoringOption[];
  selectedValue: string;
  onSelect: (code: string) => void;
  required?: boolean;
}

export const ScoringCard: React.FC<ScoringCardProps> = ({
  question,
  options,
  selectedValue,
  onSelect,
  required = false,
}) => {
  const [isSaved, setIsSaved] = useState(false);

  const handleSelect = (code: string) => {
    onSelect(code);
    setIsSaved(true);
  };

  return (
    <BentoCard className="w-full max-w-2xl mx-auto">
      <div className="flex flex-col gap-4 text-left">
        {/* Question Heading */}
        <h2 className="text-xl font-bold text-[#0F172A] leading-tight">
          {question}
          {required && <span className="text-red-500 ml-1">*</span>}
        </h2>

        {/* Options Stack */}
        <div className="flex flex-col gap-3">
          {options.map((option) => {
            const isSelected = selectedValue === option.hidden_code;
            return (
              <button
                key={option.value}
                type="button"
                onClick={() => handleSelect(option.hidden_code)}
                className={`w-full text-left p-4 rounded-xl border-l-4 flex items-center gap-3 transition-all focus:outline-none ${
                  isSelected
                    ? 'border-l-[#C99700] bg-[#F2F7FD] border border-[#E2E8F0] border-l-4'
                    : 'border-l-transparent bg-[#FFFFFF] border border-[#E2E8F0] hover:bg-[#F2F7FD]'
                }`}
              >
                {/* Custom radio indicator */}
                <div
                  className={`w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0 transition-all ${
                    isSelected ? 'border-[#C99700]' : 'border-[#E2E8F0]'
                  }`}
                >
                  {isSelected && (
                    <div className="w-2.5 h-2.5 rounded-full bg-[#C99700]" />
                  )}
                </div>
 
                <span className={`text-sm font-medium ${isSelected ? 'text-[#0F172A] font-semibold' : 'text-[#0F172A]'}`}>
                  {option.label}
                </span>
              </button>
            );
          })}
        </div>

        {/* Saved indicator */}
        <div className="h-5">
          {isSaved && (
            <span className="flex items-center gap-1.5 text-[#C99700] text-xs font-semibold animate-card-enter">
              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
              </svg>
              Saved securely
            </span>
          )}
        </div>
      </div>
    </BentoCard>
  );
};
