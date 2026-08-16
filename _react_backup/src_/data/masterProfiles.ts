export interface ProfileData {
  title: string;
  strengths: string[];
  weaknesses: string[];
  suggested_goals: string[];
}

export const master_profiles: Record<string, ProfileData> = {
  ESTP: {
    title: 'ESTP (The Entrepreneur)',
    strengths: ['Energetic', 'Bold', 'Adaptable'],
    weaknesses: ['Impulsive', 'Short-term focus'],
    suggested_goals: ['Lead one group activity monthly.', 'Draft a 3-month project plan.'],
  },
  ISTJ: {
    title: 'ISTJ (The Inspector)',
    strengths: ['Responsible', 'Detail-oriented', 'Logical'],
    weaknesses: ['Stubborn', 'Judgmental'],
    suggested_goals: ['Establish a weekly review routine.', 'Document three key standard processes.'],
  },
  ENFP: {
    title: 'ENFP (The Campaigner)',
    strengths: ['Imaginative', 'Enthusiastic', 'Out-going'],
    weaknesses: ['Easily distracted', 'Overthinker'],
    suggested_goals: ['Focus on one key business metric this month.', 'Delegate routine tasks to team members.'],
  },
  INTJ: {
    title: 'INTJ (The Architect)',
    strengths: ['Strategic', 'Analytical', 'Independent'],
    weaknesses: ['Perfectionist', 'Overly critical'],
    suggested_goals: ['Design a scalable business system map.', 'Set clear KPIs for every team member.'],
  },
  ENTJ: {
    title: 'ENTJ (The Commander)',
    strengths: ['Efficient', 'Strong-willed', 'Strategic thinker'],
    weaknesses: ['Stubborn', 'Impatient'],
    suggested_goals: ['Conduct monthly alignment audits.', 'Empower direct reports through structured delegation.'],
  },
  INFJ: {
    title: 'INFJ (The Advocate)',
    strengths: ['Creative', 'Insightful', 'Principled'],
    weaknesses: ['Sensitive to criticism', 'Prone to burnout'],
    suggested_goals: ['Align core values with marketing messaging.', 'Implement a weekly boundaries audit.'],
  },
  ISFP: {
    title: 'ISFP (The Adventurer)',
    strengths: ['Artistic', 'Sensitive', 'Imaginative'],
    weaknesses: ['Unpredictable', 'Easily stressed'],
    suggested_goals: ['Inject unique brand aesthetics into UX.', 'Focus on incremental daily process execution.'],
  }
};
