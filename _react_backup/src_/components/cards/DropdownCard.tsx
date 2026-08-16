import React, { useState, useRef, useEffect } from 'react';
import { BentoCard } from '../BentoCard';

interface Option {
  value: string;
  label: string;
}

interface DropdownCardProps {
  question: string;
  options: Option[];
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  required?: boolean;
}

export const DropdownCard: React.FC<DropdownCardProps> = ({
  question,
  options,
  value,
  onChange,
  placeholder = 'Select an option...',
  required = false,
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [isSaved, setIsSaved] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);
  const searchInputRef = useRef<HTMLInputElement>(null);

  const selectedOption = options.find((opt) => opt.value === value);

  // Filter options based on search query
  const filteredOptions = options.filter((opt) =>
    opt.label.toLowerCase().includes(searchQuery.toLowerCase())
  );

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Focus search input when dropdown opens
  useEffect(() => {
    if (isOpen && searchInputRef.current) {
      searchInputRef.current.focus();
    }
  }, [isOpen]);

  const handleSelect = (val: string) => {
    onChange(val);
    setIsOpen(false);
    setSearchQuery('');
    if (val) {
      setIsSaved(true);
      // Auto-clear success message after a few seconds
      setTimeout(() => setIsSaved(false), 2000);
    }
  };

  return (
    <BentoCard className="w-full max-w-2xl mx-auto">
      <div className="flex flex-col gap-4 text-left">
        {/* Question Heading */}
        <h2 className="text-xl font-bold text-[#0F172A] leading-tight flex items-center justify-between">
          <span>
            {question}
            {required && <span className="text-red-500 ml-1">*</span>}
          </span>
        </h2>

        {/* Searchable Dropdown Selector */}
        <div className="relative w-full" ref={containerRef}>
          {/* Trigger Button */}
          <button
            type="button"
            onClick={() => setIsOpen(!isOpen)}
            className="w-full rounded-xl border border-[#E2E8F0] px-4 py-3 bg-white text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#C99700] focus:border-[#C99700] flex justify-between items-center cursor-pointer transition-all shadow-sm text-left"
          >
            <span className={selectedOption ? 'text-[#0F172A] font-medium' : 'text-slate-400'}>
              {selectedOption ? selectedOption.label : placeholder}
            </span>
            <svg className="w-5 h-5 text-slate-450 ml-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          {/* Dropdown Options Box */}
          {isOpen && (
            <div className="absolute left-0 right-0 mt-2 bg-white border border-[#E2E8F0] rounded-xl shadow-xl z-50 overflow-hidden animate-fade-in max-h-72 flex flex-col">
              {/* Search input container */}
              <div className="p-2 border-b border-slate-100 bg-slate-50/50">
                <div className="relative flex items-center">
                  <svg className="w-4 h-4 text-slate-400 absolute left-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                  <input
                    type="text"
                    ref={searchInputRef}
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    placeholder="Type to filter..."
                    className="w-full rounded-lg border border-slate-200/80 pl-8 pr-3 py-1.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#C99700] focus:border-[#C99700]"
                  />
                  {searchQuery && (
                    <button
                      type="button"
                      onClick={() => setSearchQuery('')}
                      className="absolute right-2.5 text-slate-400 hover:text-slate-600 p-0.5"
                    >
                      <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  )}
                </div>
              </div>

              {/* Options list */}
              <div className="overflow-y-auto divide-y divide-slate-50 flex-1">
                {filteredOptions.length === 0 ? (
                  <div className="p-4 text-center text-xs text-slate-400">
                    No matching options found
                  </div>
                ) : (
                  filteredOptions.map((option) => {
                    const isCurrent = option.value === value;
                    return (
                      <button
                        key={option.value}
                        type="button"
                        onClick={() => handleSelect(option.value)}
                        className={`w-full text-left px-4 py-2.5 text-xs transition-colors flex items-center justify-between ${
                          isCurrent
                            ? 'bg-amber-50/60 font-bold text-amber-700'
                            : 'hover:bg-slate-50 text-slate-700'
                        }`}
                      >
                        <span className="truncate">{option.label}</span>
                        {isCurrent && (
                          <svg className="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M5 13l4 4L19 7" />
                          </svg>
                        )}
                      </button>
                    );
                  })
                )}
              </div>
            </div>
          )}
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
