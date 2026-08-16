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
  value: any;
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

export interface Option {
  value: string;
  label: string;
}

export interface BranchingBlock extends BaseBlock {
  type: 'branching';
  options: Option[];
}

export interface ScoringOption extends Option {
  hidden_code: string; // e.g., 'E', 'S', 'T', 'P'
}

export interface ScoringBlock extends BaseBlock {
  type: 'scoring';
  options: ScoringOption[];
}

export interface ResultRevealBlock extends BaseBlock {
  type: 'result_reveal';
  action_prompt?: string;
}

export interface ShortAnswerBlock extends BaseBlock {
  type: 'short_answer';
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

export interface Pillar {
  id: string;
  title: string;
  description?: string;
  blocks: Block[];
}

export interface Program {
  id: string;
  title: string;
  description?: string;
  pillars: Pillar[];
}

export type StudentAnswers = Record<string, any>;
