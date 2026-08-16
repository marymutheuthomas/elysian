// ─── Block Types ─────────────────────────────────────────────────────────────

export type BlockType =
  | 'free_text'
  | 'branching'
  | 'scoring'
  | 'goal'
  | 'result_reveal'
  | 'short_answer'
  | 'dropdown';

export interface ShowIfRule {
  blockId: string;
  operator: 'equals' | 'not_equals' | 'contains';
  value: string | number | boolean;
}

export interface BaseBlock {
  id: string;
  type: BlockType;
  question: string;
  required?: boolean;
  showIf?: ShowIfRule;
  placeholder?: string;
}

export interface FreeTextBlock extends BaseBlock {
  type: 'free_text';
}

export interface GoalBlock extends BaseBlock {
  type: 'goal';
}

export interface ShortAnswerBlock extends BaseBlock {
  type: 'short_answer';
}

export interface ResultRevealBlock extends BaseBlock {
  type: 'result_reveal';
  action_prompt?: string;
}

export interface Option {
  value: string;
  label: string;
}

export interface BranchingBlock extends BaseBlock {
  type: 'branching';
  options: Option[];
}

export interface ScoringOption extends Option {
  hidden_code: string;
}

export interface ScoringBlock extends BaseBlock {
  type: 'scoring';
  options: ScoringOption[];
}

export interface DropdownBlock extends BaseBlock {
  type: 'dropdown';
  options: Option[];
}

export type Block =
  | FreeTextBlock
  | BranchingBlock
  | ScoringBlock
  | GoalBlock
  | ResultRevealBlock
  | ShortAnswerBlock
  | DropdownBlock;

// ─── Program ──────────────────────────────────────────────────────────────────

export interface Pillar {
  id: string;
  title: string;
  description?: string;
  blocks: Block[];
}

export interface Program {
  id: string;
  code: string;
  title: string;
  description: string;
  fee: number;
  outcomes: string[];
  duration: string;
  isActive: boolean;
  pillars: Pillar[];
}

// ─── Student ──────────────────────────────────────────────────────────────────

export type StudentStatus =
  | 'registration'
  | 'program_selection'
  | 'payment_pending'
  | 'active'
  | 'completed';

export interface StudentRecord {
  permanentID: string;
  name: string;
  email: string;
  status: StudentStatus;
  selectedProgramId: string | null;
  activeBlockIndex: number;
  answers: Record<string, string | number | boolean>;
  profileCode: string;
  registeredAt: string;
  ttid?: string;
}

// ─── Chat ─────────────────────────────────────────────────────────────────────

export interface ChatMessage {
  id: string;
  threadId: string; // PermanentID of the student this thread belongs to
  sender: 'student' | 'mentor';
  senderLabel: string;
  content: string;
  timestamp: string;
}

// ─── Payments ─────────────────────────────────────────────────────────────────

export type PaymentStatus = 'pending' | 'verified' | 'rejected';

export interface PaymentRecord {
  id: string;
  studentPermanentID: string;
  programId: string;
  ttid: string;
  amount: number;
  status: PaymentStatus;
  submittedAt: string;
  verifiedAt?: string;
}

// ─── Convenience alias ────────────────────────────────────────────────────────

export type StudentAnswers = Record<string, string | number | boolean>;
