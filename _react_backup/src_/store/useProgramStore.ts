// src/store/useProgramStore.ts
import { create } from 'zustand';
import { supabase } from '../lib/supabase';
import type {
  Program,
  StudentRecord,
  ChatMessage,
  PaymentRecord,
  StudentStatus,
  PaymentStatus,
  Block,
  ShowIfRule,
  ScoringOption,
} from '../types/index';
import { defaultPrograms } from '../data/defaultPrograms';

// ─── Helpers ─────────────────────────────────────────────────────────────────
let _counter = 1;
function generatePermanentID(): string {
  const ts = Date.now().toString(36).toUpperCase();
  const rand = Math.random().toString(36).substring(2, 6).toUpperCase();
  return `ES-${ts}-${_counter++}-${rand}`;
}
export function evaluateShowIf(
  rule: ShowIfRule | undefined,
  answers: Record<string, string | number | boolean>
): boolean {
  if (!rule) return true;
  const val = answers[rule.blockId];
  if (val === undefined || val === null || val === '') return false;
  switch (rule.operator) {
    case 'equals':
      return val === rule.value;
    case 'not_equals':
      return val !== rule.value;
    case 'contains':
      if (Array.isArray(val)) return (val as string[]).includes(String(rule.value));
      if (typeof val === 'string') return val.includes(String(rule.value));
      return false;
    default:
      return true;
  }
}

// ─── Store Interface ──────────────────────────────────────────────────────────────
interface ElysianStore {
  // Persisted Data (now fetched from Supabase)
  programs: Program[];
  students: StudentRecord[];
  messages: ChatMessage[];
  payments: PaymentRecord[];

  // Session State
  currentStudentID: string | null;
  mentorLoggedIn: boolean;
  currentViewStudentId: string | null;

  // Loading flag for initial fetch
  loading: boolean;

  // Student Actions
  registerStudent: (name: string, email: string) => string;
  loginStudent: (permanentID: string) => boolean;
  selectProgram: (programId: string) => void;
  submitPayment: (ttid: string) => void;
  setAnswer: (blockId: string, value: string | number | boolean) => void;
  advanceBlock: () => void;
  completeProgram: () => void;
  logoutStudent: () => void;
  sendStudentMessage: (content: string) => void;

  // Mentor Actions
  mentorLogin: (password: string) => boolean;
  mentorLogout: () => void;
  sendMentorMessage: (studentId: string, content: string) => void;
  verifyPayment: (paymentId: string) => void;
  rejectPayment: (paymentId: string) => void;
  setCurrentViewStudent: (studentId: string | null) => void;

  // Program CRUD (async, sync with Supabase)
  addProgram: (program: Program) => Promise<void>;
  updateProgram: (id: string, updates: Partial<Program>) => Promise<void>;
  deleteProgram: (id: string) => Promise<void>;
  toggleProgramActive: (id: string) => Promise<void>;
  setPrograms: (programs: Program[]) => void;

  // Initialise store from Supabase
  initialize: () => Promise<void>;

  // Selectors
  getCurrentStudent: () => StudentRecord | null;
  getCurrentProgram: () => Program | null;
  getStudentProgram: (student: StudentRecord) => Program | null;
  getVisibleBlocks: (student: StudentRecord, program: Program) => Block[];
  getCurrentBlock: () => Block | null;
  getCurrentPillarIndex: () => number;
  getStudentMessages: (permanentID: string) => ChatMessage[];
  getStudentPayment: (permanentID: string) => PaymentRecord | null;
}

// ─── Store ─────────────────────────────────────────────────────────────────────
export const useProgramStore = create<ElysianStore>()((set, get) => ({
  // Initial state – empty; will be populated by initialize()
  programs: [],
  students: [],
  messages: [],
  payments: [],
  currentStudentID: null,
  mentorLoggedIn: false,
  currentViewStudentId: null,
  loading: true,

  // ── Initialise ────────────────────────────────────────────────────────
  initialize: async () => {
    set({ loading: true });
    try {
      // Fetch programs with nested pillars and their components (blocks)
      const { data, error } = await supabase
        .from('programs')
        .select('*, pillars(*, components(*))');
      if (error) throw error;
      // Cast to Program[] assuming shape matches type definitions
      set({ programs: data as Program[], loading: false });
    } catch (e) {
      console.error('Failed to fetch programs from Supabase', e);
      // Fallback to default static data
      set({ programs: defaultPrograms, loading: false });
    }
  },

  // ── Register ────────────────────────────────────────────────────────
  registerStudent: (name, email) => {
    const permanentID = generatePermanentID();
    const newStudent: StudentRecord = {
      permanentID,
      name,
      email,
      status: 'program_selection',
      selectedProgramId: null,
      activeBlockIndex: 0,
      answers: {},
      profileCode: '',
      registeredAt: new Date().toISOString(),
    };
    set((s) => ({
      students: [...s.students, newStudent],
      currentStudentID: permanentID,
    }));
    return permanentID;
  },

  // ── Login ─────────────────────────────────────────────────────────────
  loginStudent: (permanentID) => {
    const found = get().students.find((s) => s.permanentID === permanentID);
    if (!found) return false;
    set({ currentStudentID: permanentID });
    return true;
  },

  // ── Select Program ───────────────────────────────────────────────────
  selectProgram: (programId) => {
    const { currentStudentID } = get();
    if (!currentStudentID) return;
    set((s) => ({
      students: s.students.map((st) =>
        st.permanentID === currentStudentID
          ? { ...st, selectedProgramId: programId, status: 'payment_pending' as StudentStatus }
          : st
      ),
    }));
  },

  // ── Submit Payment ───────────────────────────────────────────────────
  submitPayment: (ttid) => {
    const { currentStudentID, students, programs } = get();
    if (!currentStudentID) return;
    const student = students.find((s) => s.permanentID === currentStudentID);
    if (!student?.selectedProgramId) return;
    const program = programs.find((p) => p.id === student.selectedProgramId);
    const payment: PaymentRecord = {
      id: `PAY-${Date.now()}`,
      studentPermanentID: currentStudentID,
      programId: student.selectedProgramId,
      ttid,
      amount: program?.fee ?? 0,
      status: 'pending',
      submittedAt: new Date().toISOString(),
    };
    set((s) => ({
      payments: [...s.payments, payment],
      students: s.students.map((st) => (st.permanentID === currentStudentID ? { ...st, ttid } : st)),
    }));
  },

  // ── Set Answer ────────────────────────────────────────────────────────
  setAnswer: (blockId, value) => {
    const { currentStudentID, students, programs } = get();
    if (!currentStudentID) return;
    const student = students.find((s) => s.permanentID === currentStudentID);
    if (!student) return;
    const program = programs.find((p) => p.id === student.selectedProgramId);
    let profileCodeAddition = '';
    if (program) {
      const block = program.pillars
        .flatMap((p) => p.blocks)
        .find((b) => b.id === blockId);
      if (block?.type === 'scoring') {
        const scoringBlock = block as { type: 'scoring'; options: ScoringOption[] };
        const opt = scoringBlock.options.find((o) => o.value === value);
        if (opt?.hidden_code) profileCodeAddition = opt.hidden_code;
      }
    }
    set((s) => ({
      students: s.students.map((st) =>
        st.permanentID === currentStudentID
          ? {
              ...st,
              answers: { ...st.answers, [blockId]: value },
              profileCode: profileCodeAddition ? st.profileCode + profileCodeAddition : st.profileCode,
            }
          : st
      ),
    }));
  },

  // ── Advance Block ─────────────────────────────────────────────────────
  advanceBlock: () => {
    const student = get().getCurrentStudent();
    const program = get().getCurrentProgram();
    if (!student || !program) return;
    const visibleBlocks = get().getVisibleBlocks(student, program);
    const nextIndex = student.activeBlockIndex + 1;
    if (nextIndex >= visibleBlocks.length) {
      get().completeProgram();
    } else {
      set((s) => ({
        students: s.students.map((st) =>
          st.permanentID === student.permanentID ? { ...st, activeBlockIndex: nextIndex } : st
        ),
      }));
    }
  },

  // ── Complete Program ─────────────────────────────────────────────────
  completeProgram: () => {
    const { currentStudentID } = get();
    if (!currentStudentID) return;
    set((s) => ({
      students: s.students.map((st) =>
        st.permanentID === currentStudentID ? { ...st, status: 'completed' as StudentStatus } : st
      ),
    }));
  },

  // ── Logout Student ───────────────────────────────────────────────────
  logoutStudent: () => set({ currentStudentID: null }),

  // ── Student Sends Message ─────────────────────────────────────────────
  sendStudentMessage: (content) => {
    const { currentStudentID } = get();
    if (!currentStudentID || !content.trim()) return;
    const student = get().students.find((s) => s.permanentID === currentStudentID);
    const msg: ChatMessage = {
      id: `MSG-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`,
      threadId: currentStudentID,
      sender: 'student',
      senderLabel: student?.name ?? currentStudentID,
      content: content.trim(),
      timestamp: new Date().toISOString(),
    };
    set((s) => ({ messages: [...s.messages, msg] }));
  },

  // ── Mentor Login ───────────────────────────────────────────────────────
  mentorLogin: (password) => {
    if (password === 'elysian2026') {
      set({ mentorLoggedIn: true });
      return true;
    }
    return false;
  },

  mentorLogout: () => set({ mentorLoggedIn: false, currentViewStudentId: null }),

  // ── Mentor Sends Message ───────────────────────────────────────────────
  sendMentorMessage: (studentId, content) => {
    if (!content.trim()) return;
    const msg: ChatMessage = {
      id: `MSG-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`,
      threadId: studentId,
      sender: 'mentor',
      senderLabel: 'Elysian Mentor',
      content: content.trim(),
      timestamp: new Date().toISOString(),
    };
    set((s) => ({ messages: [...s.messages, msg] }));
  },

  // ── Verify / Reject Payment ─────────────────────────────────────────────
  verifyPayment: (paymentId) => {
    const payment = get().payments.find((p) => p.id === paymentId);
    if (!payment) return;
    set((s) => ({
      payments: s.payments.map((p) =>
        p.id === paymentId ? { ...p, status: 'verified' as PaymentStatus, verifiedAt: new Date().toISOString() } : p
      ),
      students: s.students.map((st) =>
        st.permanentID === payment.studentPermanentID ? { ...st, status: 'active' as StudentStatus } : st
      ),
    }));
  },

  rejectPayment: (paymentId) => {
    set((s) => ({
      payments: s.payments.map((p) => (p.id === paymentId ? { ...p, status: 'rejected' as PaymentStatus } : p)),
    }));
  },

  setCurrentViewStudent: (studentId) => set({ currentViewStudentId: studentId }),

  addProgram: async (program: Program) => {
  try {
    // Insert program record
    const { data, error } = await supabase
      .from('programs')
      .insert({
        code: program.title, // map title to code
        name: program.title, // also store title in name column for legacy compatibility
        description: program.description,
        fee: program.fee,
        duration: program.duration,
        is_active: program.isActive,
        // outcomes column does not exist in DB; ignore or store in description if needed
      })
      .select();
    if (error) throw error;
    const insertedProgram = data[0] as Program;
    const programId = insertedProgram.id;

    // Insert pillars linked to program
    const pillarsToInsert = program.pillars.map((p) => ({
      id: p.id,
      title: p.title,
      description: p.description,
      program_id: programId,
    }));
    const { data: pillarData, error: pillarError } = await supabase
      .from('pillars')
      .insert(pillarsToInsert)
      .select();
    if (pillarError) throw pillarError;

    // Insert components (blocks) linked to pillars
    const componentsToInsert: any[] = [];
    program.pillars.forEach((pillar) => {
      const insertedPillar = (pillarData as any[]).find((p) => p.id === pillar.id);
      const pillarId = insertedPillar?.id ?? pillar.id;
      pillar.blocks.forEach((block) => {
        componentsToInsert.push({
          id: block.id,
          type: block.type,
          question: block.question,
          required: block.required,
          showIf: block.showIf,
          placeholder: block.placeholder,
          pillar_id: pillarId,
          data: block,
        });
      });
    });
      const { error: compError } = await supabase.from('components').insert(componentsToInsert);
      if (compError) throw compError;

      // Update local store state with new program
      set((s) => ({ programs: [...s.programs, insertedProgram] }));
    } catch (error) {
      console.error('Failed to add program', error);
    }
  },

  setPrograms: (programs) => set(() => ({ programs })),

  updateProgram: async (id, updates) => {
    try {
      // Map the front‑end fields to the actual DB columns
      const dbUpdates: any = {};
      if (updates.title !== undefined) dbUpdates.code = updates.title;
// legacy name column update removed
      if (updates.description !== undefined) dbUpdates.description = updates.description;
      if (updates.fee !== undefined) dbUpdates.fee = updates.fee;
      if (updates.duration !== undefined) dbUpdates.duration = updates.duration;
      if (updates.isActive !== undefined) dbUpdates.is_active = updates.isActive;
      // outcomes column does not exist – ignore it

      const { error } = await supabase.from('programs').update(dbUpdates).eq('id', id);
      if (error) throw error;
      // Update local store using the original shape so UI stays consistent
      set((s) => ({
        programs: s.programs.map((p) => (p.id === id ? { ...p, ...updates } : p)),
      }));
    } catch (error) {
      console.error('API Error Details: updateProgram', error);
    }
  },


deleteProgram: async (id) => {
  try {
    console.log('API Request Data: deleteProgram', id);
    const { error } = await supabase.from('programs').delete().eq('id', id);
    if (error) throw error;
    set((s) => ({ programs: s.programs.filter((p) => p.id !== id) }));
  } catch (error) {
    console.error('API Error Details: deleteProgram', error);
  }
},

toggleProgramActive: async (id) => {
  try {
    console.log('API Request Data: toggleProgramActive', id);
    const program = get().programs.find((p) => p.id === id);
    if (!program) return;
    const { error } = await supabase
      .from('programs')
      .update({ isActive: !program.isActive })
      .eq('id', id);
    if (error) throw error;
    set((s) => ({
      programs: s.programs.map((p) => (p.id === id ? { ...p, isActive: !p.isActive } : p)),
    }));
  } catch (error) {
    console.error('API Error Details: toggleProgramActive', error);
  }
},

  // ── Selectors ----------------------------------------------------------
  getCurrentStudent: () => {
    const { currentStudentID, students } = get();
    if (!currentStudentID) return null;
    return students.find((s) => s.permanentID === currentStudentID) ?? null;
  },

  getCurrentProgram: () => {
    const student = get().getCurrentStudent();
    if (!student?.selectedProgramId) return null;
    return get().programs.find((p) => p.id === student.selectedProgramId) ?? null;
  },

  getStudentProgram: (student) => {
    if (!student.selectedProgramId) return null;
    return get().programs.find((p) => p.id === student.selectedProgramId) ?? null;
  },

  getVisibleBlocks: (student, program) => {
    const allBlocks = program.pillars.flatMap((p) => p.blocks);
    return allBlocks.filter((block) => {
      if (!evaluateShowIf(block.showIf, student.answers)) return false;
      let rule: ShowIfRule | undefined = block.showIf;
      while (rule) {
        let parentBlock: Block | undefined;
        for (const pillar of program.pillars) {
          parentBlock = pillar.blocks.find((b) => b.id === rule?.blockId);
          if (parentBlock) break;
        }
        if (!parentBlock) break;
        if (!evaluateShowIf(parentBlock.showIf, student.answers)) return false;
        rule = parentBlock.showIf;
      }
      return true;
    });
  },

  getCurrentBlock: () => {
    const student = get().getCurrentStudent();
    const program = get().getCurrentProgram();
    if (!student || !program) return null;
    const visible = get().getVisibleBlocks(student, program);
    return visible[student.activeBlockIndex] ?? null;
  },

  getCurrentPillarIndex: () => {
    const student = get().getCurrentStudent();
    const program = get().getCurrentProgram();
    if (!student || !program) return 0;
    const visible = get().getVisibleBlocks(student, program);
    const currentBlock = visible[student.activeBlockIndex];
    if (!currentBlock) return 0;
    for (let i = 0; i < program.pillars.length; i++) {
      if (program.pillars[i].blocks.some((b) => b.id === currentBlock.id)) return i;
    }
    return 0;
  },

  getStudentMessages: (permanentID) => get().messages.filter((m) => m.threadId === permanentID),

  getStudentPayment: (permanentID) =>
    get().payments.find((p) => p.studentPermanentID === permanentID) ?? null,
}));
