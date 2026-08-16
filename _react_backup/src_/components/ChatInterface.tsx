import React, { useState, useRef, useEffect } from 'react';
import { useProgramStore } from '../store/useProgramStore';

interface ChatInterfaceProps {
  mode: 'student' | 'mentor';
  studentId?: string; // required if mode === 'mentor'
}

export const ChatInterface: React.FC<ChatInterfaceProps> = ({ mode, studentId }) => {
  const {
    currentStudentID,
    getStudentMessages,
    sendStudentMessage,
    sendMentorMessage,
    students,
  } = useProgramStore();

  const activeStudentId = mode === 'student' ? currentStudentID : studentId;
  const messages = activeStudentId ? getStudentMessages(activeStudentId) : [];
  const currentStudent = students.find((s) => s.permanentID === activeStudentId);

  const [text, setText] = useState('');
  const chatEndRef = useRef<HTMLDivElement>(null);

  // Scroll to bottom on new message
  useEffect(() => {
    chatEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages.length]);

  const handleSend = (e: React.FormEvent) => {
    e.preventDefault();
    if (!text.trim() || !activeStudentId) return;

    if (mode === 'student') {
      sendStudentMessage(text);
    } else {
      sendMentorMessage(activeStudentId, text);
    }
    setText('');
  };

  if (!activeStudentId) {
    return (
      <div className="flex items-center justify-center h-full text-slate-400 text-sm">
        No active chat session.
      </div>
    );
  }

  return (
    <div className="flex flex-col h-full bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden">
      {/* Header */}
      <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
        <div className="flex items-center gap-3">
          <div className="w-8 h-8 rounded-full bg-amber-50 border border-amber-200 flex items-center justify-center">
            <span className="text-xs font-bold text-amber-600">
              {mode === 'student' ? 'M' : (currentStudent?.name?.[0] || 'S')}
            </span>
          </div>
          <div>
            <h3 className="font-semibold text-sm text-slate-800">
              {mode === 'student' ? 'Elysian Mentor Support' : `Chat with ${currentStudent?.name}`}
            </h3>
            <p className="text-[10px] text-slate-400">
              {mode === 'student' ? 'Ask questions about your diagnostic pillars' : `UIN: ${currentStudent?.permanentID}`}
            </p>
          </div>
        </div>
        <div className="flex items-center gap-1.5">
          <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
          <span className="text-[10px] font-medium text-slate-400">Online</span>
        </div>
      </div>

      {/* Messages List */}
      <div className="flex-1 p-4 overflow-y-auto space-y-3 flex flex-col min-h-0 bg-slate-50/20">
        {messages.length === 0 ? (
          <div className="flex-1 flex flex-col items-center justify-center text-center p-6 text-slate-400">
            <svg
              className="w-10 h-10 text-slate-300 mb-2"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1.5}
                d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
              />
            </svg>
            <p className="text-xs">No messages yet. Send a message to start the conversation.</p>
          </div>
        ) : (
          messages.map((msg) => {
            const isMe =
              (mode === 'student' && msg.sender === 'student') ||
              (mode === 'mentor' && msg.sender === 'mentor');
            return (
              <div
                key={msg.id}
                className={`flex flex-col ${isMe ? 'items-end' : 'items-start'} max-w-full`}
              >
                <span className="text-[10px] text-slate-400 mb-1 px-1">
                  {msg.senderLabel}
                </span>
                <div
                  className={`px-4 py-2.5 rounded-2xl text-sm leading-relaxed ${
                    isMe
                      ? 'bg-slate-900 text-white rounded-br-none'
                      : 'bg-white text-slate-800 border border-slate-200 rounded-bl-none shadow-sm'
                  }`}
                >
                  {msg.content}
                </div>
                <span className="text-[9px] text-slate-400 mt-1 px-1">
                  {new Date(msg.timestamp).toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit',
                  })}
                </span>
              </div>
            );
          })
        )}
        <div ref={chatEndRef} />
      </div>

      {/* Input */}
      <form onSubmit={handleSend} className="p-3 border-t border-slate-100 bg-white">
        <div className="flex gap-2">
          <input
            type="text"
            value={text}
            onChange={(e) => setText(e.target.value)}
            placeholder="Type your message..."
            className="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white transition-all"
          />
          <button
            type="submit"
            disabled={!text.trim()}
            className="bg-amber-500 hover:bg-amber-600 active:scale-95 disabled:opacity-40 disabled:scale-100 text-white p-2 rounded-xl transition-all flex items-center justify-center shadow-sm shadow-amber-500/20"
          >
            <svg
              className="w-5 h-5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M14 5l7 7m0 0l-7 7m7-7H3"
              />
            </svg>
          </button>
        </div>
      </form>
    </div>
  );
};
