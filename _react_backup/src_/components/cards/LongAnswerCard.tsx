import React, { useState, useEffect, useRef } from 'react';
import { BentoCard } from '../BentoCard';

interface LongAnswerCardProps {
  question: string;
  placeholder?: string;
  value: string;
  onChange: (value: string) => void;
  onSubmit?: () => void;
  required?: boolean;
}

export const LongAnswerCard: React.FC<LongAnswerCardProps> = ({
  question,
  placeholder = 'Type your answer here in detail...',
  value,
  onChange,
  onSubmit,
  required = false,
}) => {
  const [localValue, setLocalValue] = useState(value);
  const [isSaved, setIsSaved] = useState(false);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    setLocalValue(value);
  }, [value]);

  const handleChange = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
    const newVal = e.target.value;
    setLocalValue(newVal);
    setIsSaved(false);

    if (debounceRef.current) clearTimeout(debounceRef.current);

    debounceRef.current = setTimeout(() => {
      onChange(newVal);
      setIsSaved(true);
    }, 1500);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (debounceRef.current) clearTimeout(debounceRef.current);
    onChange(localValue);
    if (onSubmit) onSubmit();
  };

  return (
    <BentoCard className="w-full max-w-2xl mx-auto">
      <form onSubmit={handleSubmit} className="flex flex-col gap-4 text-left">
        <h2 className="text-xl font-bold text-[#0F172A] leading-tight">
          {question}
          {required && <span className="text-red-500 ml-1">*</span>}
        </h2>

        <textarea
          value={localValue}
          onChange={handleChange}
          placeholder={placeholder}
          rows={6}
          required={required}
          className="w-full rounded-xl border border-[#E2E8F0] p-4 text-sm text-[#0F172A] placeholder-[#0F172A]/40 bg-[#FFFFFF] focus:outline-none focus:ring-2 focus:ring-[#C99700] focus:border-[#C99700] transition-all resize-none"
        />

        <div className="flex justify-between items-center mt-1">
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
          <button
            type="submit"
            className="px-6 py-2.5 rounded-xl bg-[#C99700] hover:bg-[#b38600] active:scale-95 text-white font-semibold transition-all text-sm tracking-wide shadow-sm"
          >
            Submit Answer
          </button>
        </div>
      </form>
    </BentoCard>
  );
};
