import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useProgramStore } from '../store/useProgramStore';
import { StudentInbox } from './mentor/StudentInbox';
import { StudentThread } from './mentor/StudentThread';
import { PaymentPanel } from './mentor/PaymentPanel';
import { ProgramRegistry } from './mentor/ProgramRegistry';

type DashboardTab = 'students' | 'payments' | 'programs' | 'pillars';

// Import PillarContentManager
import { PillarContentManager } from './mentor/PillarContentManager';

export const MentorDashboard: React.FC = () => {
  const { mentorLoggedIn, mentorLogout, currentViewStudentId, setCurrentViewStudent } = useProgramStore();
  const navigate = useNavigate();
  const [activeTab, setActiveTab] = useState<DashboardTab>('students');

  // Authorization gate
  useEffect(() => {
    if (!mentorLoggedIn) {
      navigate('/mentor/login');
    }
  }, [mentorLoggedIn, navigate]);

  const handleLogout = () => {
    mentorLogout();
    navigate('/mentor/login');
  };

  if (!mentorLoggedIn) return null;

  return (
    <div className="min-h-screen w-screen bg-[#0A0F1C] text-slate-100 flex flex-col overflow-hidden font-sans">
      {/* Top Header Navigation */}
      <header className="h-16 border-b border-slate-800 bg-[#0F172A] flex items-center justify-between px-6 z-10 flex-shrink-0 shadow-md">
        <div className="flex items-center gap-3">
          <div className="w-8 h-8 rounded-lg bg-amber-500 flex items-center justify-center shadow-lg shadow-amber-500/10">
            <span className="text-white font-extrabold text-sm">E</span>
          </div>
          <div>
            <h2 className="font-extrabold text-sm tracking-wide uppercase text-amber-500 font-outfit" style={{ fontFamily: 'Outfit, sans-serif' }}>
              Elysian Administration
            </h2>
            <p className="text-[10px] text-slate-500 font-mono">Cohort Management Engine</p>
          </div>
        </div>

        <div className="flex items-center gap-4">
          <span className="text-xs font-semibold text-slate-400">
            Authorized Account
          </span>
          <button
            onClick={handleLogout}
            className="px-3.5 py-1.5 bg-slate-800 hover:bg-slate-700 active:scale-95 text-xs font-semibold text-slate-300 rounded-xl transition-all border border-slate-700/50"
          >
            Logout
          </button>
        </div>
      </header>

      {/* Main Body */}
      <div className="flex-1 flex min-h-0">
        {/* Sidebar Nav */}
        <aside className="w-64 border-r border-slate-850 bg-[#0F172A] p-4 flex flex-col justify-between flex-shrink-0">
          <div className="space-y-1.5">
            <span className="text-[9px] font-bold text-slate-600 uppercase tracking-widest px-3 mb-2 block">
              Menu Navigation
            </span>
            <button
              onClick={() => setActiveTab('students')}
              className={`w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold transition-all ${
                activeTab === 'students'
                  ? 'bg-amber-500/15 border border-amber-500/25 text-amber-500'
                  : 'text-slate-450 hover:bg-slate-800/40 hover:text-slate-200 border border-transparent'
              }`}
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
              Student Registry
            </button>

            <button
              onClick={() => setActiveTab('payments')}
              className={`w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold transition-all ${
                activeTab === 'payments'
                  ? 'bg-amber-500/15 border border-amber-500/25 text-amber-500'
                  : 'text-slate-450 hover:bg-slate-800/40 hover:text-slate-200 border border-transparent'
              }`}
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.2} d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
              </svg>
              Reconciliation Panel
            </button>

            <button
              onClick={() => setActiveTab('programs')}
              className={`w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold transition-all ${
                activeTab === 'programs'
                  ? 'bg-amber-500/15 border border-amber-500/25 text-amber-500'
                  : 'text-slate-450 hover:bg-slate-800/40 hover:text-slate-200 border border-transparent'
              }`}
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.2} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
              </svg>
              Program Database
            </button>
            {/* Pillar Content Manager button */}
            <button
              onClick={() => setActiveTab('pillars')}
              className={`w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold transition-all ${
                activeTab === 'pillars'
                  ? 'bg-amber-500/15 border border-amber-500/25 text-amber-500'
                  : 'text-slate-450 hover:bg-slate-800/40 hover:text-slate-200 border border-transparent'
              }`}
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.2} d="M12 4v16m8-8H4"/>
              </svg>
              Pillar Content
            </button>
          </div>

          <div className="px-3 text-[10px] text-slate-600 font-mono">
            System Uptime: 2026-06-08
          </div>
        </aside>

        {/* Content Workspace */}
        <main className="flex-1 bg-[#0A0F1C] p-6 overflow-hidden min-w-0 h-full">
          {activeTab === 'students' && (
            <div className="grid grid-cols-1 md:grid-cols-12 gap-6 h-full min-h-0">
              {/* Inbox component on the left */}
              <div className="md:col-span-4 h-full min-h-0">
                <StudentInbox
                  onSelectStudent={setCurrentViewStudent}
                  selectedStudentId={currentViewStudentId}
                />
              </div>

              {/* Thread component on the right */}
              <div className="md:col-span-8 h-full min-h-0">
                {currentViewStudentId ? (
                  <StudentThread studentId={currentViewStudentId} />
                ) : (
                  <div className="flex flex-col items-center justify-center h-full text-slate-500 text-xs border border-dashed border-slate-800 rounded-2xl bg-[#0F172A]/10">
                    <svg className="w-12 h-12 text-slate-800 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    Select a student from the inbox on the left to start communication.
                  </div>
                )}
              </div>
            </div>
          )}

          {activeTab === 'payments' && <PaymentPanel />}

          {activeTab === 'programs' && <ProgramRegistry />}
            {activeTab === 'pillars' && <PillarContentManager />}
        </main>
      </div>
    </div>
  );
};
