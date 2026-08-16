import type { Program } from '../types/index';

// ─── 12-Pillar Flagship Program ───────────────────────────────────────────────

const elysianAccelerator: Program = {
  id: 'esa-001',
  code: 'ESA-001',
  title: 'Elysian Success Accelerator',
  description:
    'The flagship 12-pillar transformation program designed for ambitious professionals ready to scale their impact, revenue, and leadership depth.',
  fee: 4500,
  outcomes: [
    'Define your irreversible strategic vision',
    'Build high-performance leadership identity',
    'Install scalable client acquisition systems',
    'Achieve operational excellence',
    'Master financial intelligence',
  ],
  duration: '12 Weeks',
  isActive: true,
  pillars: [
    {
      id: 'p1',
      title: 'Pillar 1: Vision & Strategic Positioning',
      description: 'Establish your core direction and set actionable growth milestones.',
      blocks: [
        {
          id: 'p1_b1',
          type: 'free_text',
          question: 'What is your primary professional vision for the next 12 months?',
          placeholder: 'Detail your long-term goals, target revenue, or impact area...',
          required: true,
        },
        {
          id: 'p1_b2',
          type: 'branching',
          question: 'Identify the primary scaling constraint in your business today:',
          options: [
            { value: 'client_acquisition', label: 'Client Acquisition & Branding' },
            { value: 'operational_efficiency', label: 'Operational Systems & Delegation' },
            { value: 'leadership_capacity', label: 'Leadership Alignment & Team Culture' },
          ],
          required: true,
        },
        {
          id: 'p1_b3',
          type: 'scoring',
          question: 'Rate the strength of your standard operating procedures (SOPs):',
          options: [
            { value: 'ad_hoc', label: 'Ad-hoc / Mostly undocumented', hidden_code: 'ESTP' },
            { value: 'hybrid', label: 'Hybrid / Some departments documented', hidden_code: 'ISTJ' },
            { value: 'mature', label: 'Mature / Fully documented and accessible', hidden_code: 'INTJ' },
          ],
          required: true,
          showIf: { blockId: 'p1_b2', operator: 'equals', value: 'operational_efficiency' },
        },
        {
          id: 'p1_b4',
          type: 'goal',
          question: 'State the key target metric you want to achieve by Q4:',
          placeholder: 'Example: Reach $100k MRR or hire 3 senior managers...',
          required: true,
        },
      ],
    },
    {
      id: 'p2',
      title: 'Pillar 2: Leadership Identity',
      description: 'Define who you are as a leader and what principles guide your decisions.',
      blocks: [
        {
          id: 'p2_b1',
          type: 'short_answer',
          question: 'Describe your leadership style in three words:',
          placeholder: 'e.g., Bold, Empathetic, Decisive',
          required: true,
        },
        {
          id: 'p2_b2',
          type: 'scoring',
          question: 'How consistently do you delegate high-impact tasks?',
          options: [
            { value: 'rarely', label: 'Rarely — I do most things myself', hidden_code: 'ENTJ' },
            { value: 'sometimes', label: 'Sometimes — only routine tasks', hidden_code: 'ENFP' },
            { value: 'often', label: 'Often — I have a full delegation framework', hidden_code: 'INFJ' },
          ],
          required: true,
        },
        {
          id: 'p2_b3',
          type: 'free_text',
          question: 'What is the one leadership decision you are most proud of this year?',
          placeholder: 'Describe the context, decision, and outcome...',
          required: true,
        },
      ],
    },
    {
      id: 'p3',
      title: 'Pillar 3: Client Acquisition Systems',
      description: 'Build a repeatable, scalable engine for attracting high-value clients.',
      blocks: [
        {
          id: 'p3_b1',
          type: 'dropdown',
          question: 'What is your primary client acquisition channel today?',
          options: [
            { value: 'referrals', label: 'Referrals & Word of Mouth' },
            { value: 'linkedin', label: 'LinkedIn Outreach' },
            { value: 'paid_ads', label: 'Paid Advertising' },
            { value: 'content', label: 'Content Marketing & SEO' },
            { value: 'events', label: 'Events & Speaking' },
            { value: 'partnerships', label: 'Strategic Partnerships' },
          ],
          required: true,
        },
        {
          id: 'p3_b2',
          type: 'scoring',
          question: 'How would you rate the predictability of your lead flow?',
          options: [
            { value: 'unpredictable', label: 'Unpredictable — feast or famine', hidden_code: 'ISFP' },
            { value: 'moderate', label: 'Moderate — some consistency', hidden_code: 'ESTP' },
            { value: 'consistent', label: 'Consistent — fully systematized', hidden_code: 'ISTJ' },
          ],
          required: true,
        },
        {
          id: 'p3_b3',
          type: 'goal',
          question: 'Set a specific client acquisition goal for this program period:',
          placeholder: 'e.g., Sign 5 new retainer clients at $5k/month each...',
          required: true,
        },
      ],
    },
    {
      id: 'p4',
      title: 'Pillar 4: Operational Excellence',
      description: 'Systematize your operations to scale without adding chaos.',
      blocks: [
        {
          id: 'p4_b1',
          type: 'branching',
          question: 'Which area of operations is your biggest bottleneck?',
          options: [
            { value: 'documentation', label: 'Documentation & SOPs' },
            { value: 'automation', label: 'Automation & Tech Stack' },
            { value: 'team_handoffs', label: 'Team Handoffs & Communication' },
          ],
          required: true,
        },
        {
          id: 'p4_b2',
          type: 'free_text',
          question: 'Describe the one process that wastes the most time in your business:',
          placeholder: 'Be specific about the task, frequency, and time cost...',
          required: true,
        },
        {
          id: 'p4_b3',
          type: 'short_answer',
          question: 'Name the tool or system you would most like to implement or improve:',
          placeholder: 'e.g., CRM, project management, invoicing...',
          required: true,
        },
      ],
    },
    {
      id: 'p5',
      title: 'Pillar 5: Financial Intelligence',
      description: 'Command your numbers and build a profitable, sustainable business.',
      blocks: [
        {
          id: 'p5_b1',
          type: 'scoring',
          question: 'How clearly do you understand your monthly profit margins?',
          options: [
            { value: 'unclear', label: 'Not clear — I rely on my accountant', hidden_code: 'ISFP' },
            { value: 'partial', label: 'Partially — I know revenue but not margins', hidden_code: 'ENFP' },
            { value: 'clear', label: 'Very clear — I track weekly P&L', hidden_code: 'INTJ' },
          ],
          required: true,
        },
        {
          id: 'p5_b2',
          type: 'free_text',
          question: 'What is your target monthly revenue, and what is blocking you from reaching it?',
          placeholder: 'Be honest about the gap and the root cause...',
          required: true,
        },
        {
          id: 'p5_b3',
          type: 'goal',
          question: 'Define one financial goal you will achieve during this program:',
          placeholder: 'e.g., Reduce operational costs by 20% or hit $50k revenue in one month...',
          required: true,
        },
      ],
    },
    {
      id: 'p6',
      title: 'Pillar 6: Team Building & Culture',
      description: 'Recruit, retain, and align A-players who execute your vision.',
      blocks: [
        {
          id: 'p6_b1',
          type: 'short_answer',
          question: 'How many direct reports do you currently have?',
          placeholder: 'Enter a number (e.g., 3)',
          required: true,
        },
        {
          id: 'p6_b2',
          type: 'branching',
          question: 'What is your biggest team challenge right now?',
          options: [
            { value: 'retention', label: 'Retention & Employee Satisfaction' },
            { value: 'performance', label: 'Accountability & Performance Management' },
            { value: 'hiring', label: 'Hiring the Right Talent' },
          ],
          required: true,
        },
        {
          id: 'p6_b3',
          type: 'free_text',
          question: 'Describe your current onboarding process for new team members:',
          placeholder: 'Walk me through the first 30 days for a new hire...',
          required: true,
        },
      ],
    },
    {
      id: 'p7',
      title: 'Pillar 7: Marketing & Brand Authority',
      description: 'Build a brand that attracts, repels, and converts the right people.',
      blocks: [
        {
          id: 'p7_b1',
          type: 'short_answer',
          question: 'Write your brand positioning statement in one sentence:',
          placeholder: 'We help [WHO] achieve [WHAT] by [HOW]...',
          required: true,
        },
        {
          id: 'p7_b2',
          type: 'scoring',
          question: 'How consistent is your brand voice across all platforms?',
          options: [
            { value: 'inconsistent', label: 'Inconsistent — no clear guidelines', hidden_code: 'ISFP' },
            { value: 'developing', label: 'Developing — rough guidelines exist', hidden_code: 'ENFP' },
            { value: 'strong', label: 'Strong — documented and enforced', hidden_code: 'ENTJ' },
          ],
          required: true,
        },
        {
          id: 'p7_b3',
          type: 'goal',
          question: 'Set one brand authority goal for this quarter:',
          placeholder: 'e.g., Publish 2 thought leadership articles per week or grow LinkedIn to 10k...',
          required: true,
        },
      ],
    },
    {
      id: 'p8',
      title: 'Pillar 8: Sales Architecture',
      description: 'Design a closing system that converts the right prospects reliably.',
      blocks: [
        {
          id: 'p8_b1',
          type: 'dropdown',
          question: 'What best describes your current sales process?',
          options: [
            { value: 'no_process', label: 'No formal process — wing it' },
            { value: 'informal', label: 'Informal — consistent steps but undocumented' },
            { value: 'documented', label: 'Documented — script/playbook exists' },
            { value: 'automated', label: 'Automated — CRM-driven with triggers' },
          ],
          required: true,
        },
        {
          id: 'p8_b2',
          type: 'free_text',
          question: 'Where do prospects most commonly drop off in your sales funnel?',
          placeholder: 'Describe the stage and the most common objection or friction...',
          required: true,
        },
        {
          id: 'p8_b3',
          type: 'goal',
          question: 'What is your sales conversion rate target for this program period?',
          placeholder: 'e.g., Increase close rate from 20% to 40% on discovery calls...',
          required: true,
        },
      ],
    },
    {
      id: 'p9',
      title: 'Pillar 9: Technology & Innovation',
      description: 'Leverage the right tools and emerging technology to gain a competitive edge.',
      blocks: [
        {
          id: 'p9_b1',
          type: 'branching',
          question: 'How do you currently use AI or automation in your business?',
          options: [
            { value: 'not_using', label: 'Not using it yet' },
            { value: 'experimenting', label: 'Experimenting with a few tools' },
            { value: 'integrated', label: 'Fully integrated into core workflows' },
          ],
          required: true,
        },
        {
          id: 'p9_b2',
          type: 'free_text',
          question: 'What technology gap is costing you the most time or money?',
          placeholder: 'Describe the manual task or bottleneck you wish were automated...',
          required: true,
        },
        {
          id: 'p9_b3',
          type: 'short_answer',
          question: 'Name one technology investment you plan to make in the next 90 days:',
          placeholder: 'e.g., AI scheduling tool, new CRM, data analytics platform...',
          required: true,
        },
      ],
    },
    {
      id: 'p10',
      title: 'Pillar 10: Personal Productivity',
      description: 'Protect your peak energy and design a high-performance operating rhythm.',
      blocks: [
        {
          id: 'p10_b1',
          type: 'scoring',
          question: 'How would you rate your current daily planning discipline?',
          options: [
            { value: 'reactive', label: 'Reactive — I respond to whatever happens', hidden_code: 'ESTP' },
            { value: 'partial', label: 'Partial — I plan loosely each morning', hidden_code: 'ENFP' },
            { value: 'structured', label: 'Structured — time-blocked and protected', hidden_code: 'INTJ' },
          ],
          required: true,
        },
        {
          id: 'p10_b2',
          type: 'free_text',
          question: 'What is the biggest productivity killer in your current routine?',
          placeholder: 'Meetings, context switching, email, social media...',
          required: true,
        },
        {
          id: 'p10_b3',
          type: 'goal',
          question: 'Design one new daily habit that will dramatically increase your output:',
          placeholder: 'e.g., 90-minute deep work block from 6–7:30am before any meetings...',
          required: true,
        },
      ],
    },
    {
      id: 'p11',
      title: 'Pillar 11: Resilience & Mindset',
      description: 'Build the mental fortitude to operate at elite levels under pressure.',
      blocks: [
        {
          id: 'p11_b1',
          type: 'branching',
          question: 'How do you typically respond when facing a major business setback?',
          options: [
            { value: 'overwhelmed', label: 'I feel overwhelmed and lose momentum' },
            { value: 'pause_reflect', label: 'I pause, reflect, then regroup' },
            { value: 'immediate_action', label: 'I immediately identify solutions and act' },
          ],
          required: true,
        },
        {
          id: 'p11_b2',
          type: 'free_text',
          question: 'Describe the most difficult professional challenge you have overcome:',
          placeholder: 'What happened, how you handled it, and what you learned...',
          required: true,
        },
        {
          id: 'p11_b3',
          type: 'short_answer',
          question: 'What is the one mindset shift that would most change your results?',
          placeholder: 'Be specific about the limiting belief and its replacement...',
          required: true,
        },
      ],
    },
    {
      id: 'p12',
      title: 'Pillar 12: Legacy & Strategic Impact',
      description: 'Define the lasting contribution you want your work to make in the world.',
      blocks: [
        {
          id: 'p12_b1',
          type: 'free_text',
          question: 'How do you want to be remembered in your industry in 10 years?',
          placeholder: 'Think about the problems you will have solved and the people you will have impacted...',
          required: true,
        },
        {
          id: 'p12_b2',
          type: 'scoring',
          question: 'How aligned is your current work with your long-term legacy?',
          options: [
            { value: 'misaligned', label: 'Misaligned — daily work feels disconnected', hidden_code: 'ISFP' },
            { value: 'partially', label: 'Partially aligned — some meaningful work', hidden_code: 'ENFP' },
            { value: 'fully', label: 'Fully aligned — every decision serves the mission', hidden_code: 'INFJ' },
          ],
          required: true,
        },
        {
          id: 'p12_b3',
          type: 'goal',
          question: 'Write your Elysian Legacy Statement — your irreversible commitment:',
          placeholder: 'State what you will build, who you will serve, and the impact you will create...',
          required: true,
        },
        {
          id: 'p12_b4',
          type: 'result_reveal',
          question: 'Your Elysian Success Profile',
          action_prompt:
            'These SMART goals are tailored to your personality type. Rewrite or add goals that reflect your unique journey.',
          required: false,
        },
      ],
    },
  ],
};

// ─── Supporting Programs ──────────────────────────────────────────────────────

function mkProgram(
  id: string,
  code: string,
  title: string,
  description: string,
  fee: number,
  outcomes: string[],
  duration: string,
  pillars: Program['pillars']
): Program {
  return { id, code, title, description, fee, outcomes, duration, isActive: true, pillars };
}

export const defaultPrograms: Program[] = [
  elysianAccelerator,

  mkProgram(
    'fin-002', 'FIN-002', 'Financial Freedom Blueprint',
    'A focused program that rewires your relationship with money, builds cash flow literacy, and installs wealth-building habits.',
    1800,
    ['Build a personal P&L dashboard', 'Master cash flow forecasting', 'Create a 12-month wealth plan'],
    '4 Weeks',
    [{
      id: 'fin2_p1', title: 'Pillar 1: Money Mindset Reset', blocks: [
        { id: 'fin2_b1', type: 'scoring', question: 'How do you feel about your current financial position?', options: [{ value: 'stressed', label: 'Stressed and uncertain', hidden_code: 'ISFP' }, { value: 'neutral', label: 'Neutral — managing but not growing', hidden_code: 'ISTJ' }, { value: 'confident', label: 'Confident and growing', hidden_code: 'INTJ' }], required: true },
        { id: 'fin2_b2', type: 'goal', question: 'State your financial freedom goal for the next 12 months:', placeholder: 'e.g., Save $50k, eliminate debt, reach $10k passive income...', required: true },
      ]
    }]
  ),

  mkProgram(
    'mkt-003', 'MKT-003', 'Marketing Mastery Intensive',
    'Transform your marketing from guesswork into a data-driven growth engine that attracts premium clients.',
    2200,
    ['Build a content authority flywheel', 'Master paid and organic acquisition', 'Create a 90-day marketing calendar'],
    '6 Weeks',
    [{
      id: 'mkt3_p1', title: 'Pillar 1: Brand Authority Audit', blocks: [
        { id: 'mkt3_b1', type: 'dropdown', question: 'Which marketing channel drives the most revenue today?', options: [{ value: 'organic', label: 'Organic Search / SEO' }, { value: 'social', label: 'Social Media' }, { value: 'email', label: 'Email Marketing' }, { value: 'ads', label: 'Paid Ads' }, { value: 'referral', label: 'Referral / Word of Mouth' }], required: true },
        { id: 'mkt3_b2', type: 'free_text', question: 'Describe your ideal client avatar in detail:', placeholder: 'Age, role, pain points, goals, where they spend time online...', required: true },
        { id: 'mkt3_b3', type: 'goal', question: 'Set your marketing growth target for this program:', placeholder: 'e.g., Grow to 5,000 email subscribers or double LinkedIn reach...', required: true },
      ]
    }]
  ),

  mkProgram(
    'ldr-004', 'LDR-004', 'Leadership Excellence Program',
    'Develop the executive presence, decision-making frameworks, and team alignment skills of world-class leaders.',
    2800,
    ['Master executive communication', 'Build high-trust team culture', 'Design your leadership operating system'],
    '8 Weeks',
    [{
      id: 'ldr4_p1', title: 'Pillar 1: Leadership Self-Audit', blocks: [
        { id: 'ldr4_b1', type: 'branching', question: 'Which leadership skill do you most need to develop?', options: [{ value: 'communication', label: 'Executive Communication' }, { value: 'delegation', label: 'Delegation & Trust' }, { value: 'strategy', label: 'Strategic Thinking' }], required: true },
        { id: 'ldr4_b2', type: 'free_text', question: 'Describe a recent leadership decision you would handle differently today:', placeholder: 'Context, decision made, what you would change and why...', required: true },
      ]
    }]
  ),

  mkProgram(
    'sal-005', 'SAL-005', 'Sales Transformation System',
    'Install a proven, repeatable sales process that converts premium prospects without feeling pushy.',
    1900,
    ['Design a 7-step closing framework', 'Master objection neutralization', 'Build a consistent $50k/month sales engine'],
    '5 Weeks',
    [{
      id: 'sal5_p1', title: 'Pillar 1: Sales DNA Diagnostic', blocks: [
        { id: 'sal5_b1', type: 'scoring', question: 'Rate your confidence in closing high-ticket sales:', options: [{ value: 'low', label: 'Low — I often discount or apologize', hidden_code: 'ISFP' }, { value: 'medium', label: 'Medium — I close some but miss many', hidden_code: 'ENFP' }, { value: 'high', label: 'High — I have a reliable system', hidden_code: 'ENTJ' }], required: true },
        { id: 'sal5_b2', type: 'goal', question: 'Set your sales revenue target for this program period:', placeholder: 'e.g., Close $75k in new contracts within 60 days...', required: true },
      ]
    }]
  ),

  mkProgram(
    'ops-006', 'OPS-006', 'Operational Efficiency Bootcamp',
    'Systematize your business so it runs without you, enabling true freedom and scale.',
    1500,
    ['Document all core SOPs', 'Implement automation for 3 key processes', 'Free 10+ hours per week'],
    '3 Weeks',
    [{
      id: 'ops6_p1', title: 'Pillar 1: Operations Audit', blocks: [
        { id: 'ops6_b1', type: 'free_text', question: 'List the 5 most repetitive tasks in your business:', placeholder: 'Be specific — what are you doing daily/weekly that should be systematized...', required: true },
        { id: 'ops6_b2', type: 'goal', question: 'What operational goal will prove this program was worth it?', placeholder: 'e.g., Delegate all client onboarding to my VA within 30 days...', required: true },
      ]
    }]
  ),

  mkProgram(
    'ent-007', 'ENT-007', 'Entrepreneur Foundation Track',
    'The essential starting point for new entrepreneurs to build solid business foundations from day one.',
    800,
    ['Validate your business model', 'Build your first offer', 'Land your first 3 paying clients'],
    '2 Weeks',
    [{
      id: 'ent7_p1', title: 'Pillar 1: Business Model Clarity', blocks: [
        { id: 'ent7_b1', type: 'short_answer', question: 'In one sentence, what problem does your business solve?', placeholder: 'We help [WHO] solve [WHAT] by [HOW]...', required: true },
        { id: 'ent7_b2', type: 'goal', question: 'What does success look like at the end of this program?', placeholder: 'e.g., 3 paying clients, first $10k revenue, validated offer...', required: true },
      ]
    }]
  ),

  mkProgram(
    'tec-008', 'TEC-008', 'Tech Business Accelerator',
    'For technology founders and product leaders who want to scale faster with smart systems and strategy.',
    3200,
    ['Design a scalable product roadmap', 'Build investor-ready metrics', 'Install growth loops'],
    '10 Weeks',
    [{
      id: 'tec8_p1', title: 'Pillar 1: Product-Market Fit Audit', blocks: [
        { id: 'tec8_b1', type: 'branching', question: 'What stage best describes your product today?', options: [{ value: 'idea', label: 'Idea / Pre-MVP' }, { value: 'mvp', label: 'MVP / Early Customers' }, { value: 'scaling', label: 'Scaling / Series A+' }], required: true },
        { id: 'tec8_b2', type: 'free_text', question: 'What is your single biggest growth blocker right now?', placeholder: 'Be specific about the constraint — technical, market, team, or capital...', required: true },
      ]
    }]
  ),

  mkProgram(
    'mnd-009', 'MND-009', 'Mindset & Peak Performance',
    'Reprogram limiting beliefs, install elite mental habits, and operate at the highest level consistently.',
    1200,
    ['Eliminate top 3 limiting beliefs', 'Build a peak performance morning protocol', 'Design a stress-proof decision framework'],
    '3 Weeks',
    [{
      id: 'mnd9_p1', title: 'Pillar 1: Mindset Baseline', blocks: [
        { id: 'mnd9_b1', type: 'free_text', question: 'What is the #1 limiting belief that holds you back most?', placeholder: "Be radically honest — what story do you tell yourself when you fail or hesitate?", required: true },
        { id: 'mnd9_b2', type: 'goal', question: 'What does your peak performance state look like in practice?', placeholder: "Describe your ideal daily energy, clarity, and execution rhythm...", required: true },
      ]
    }]
  ),

  mkProgram(
    'brd-010', 'BRD-010', 'Brand Architecture Workshop',
    'Build a premium brand from the inside out — values, voice, visual identity, and market positioning.',
    1600,
    ['Define core brand values', 'Develop signature visual identity', 'Create a brand style guide'],
    '4 Weeks',
    [{
      id: 'brd10_p1', title: 'Pillar 1: Brand Identity Audit', blocks: [
        { id: 'brd10_b1', type: 'short_answer', question: 'What 3 words should your brand instantly evoke?', placeholder: "e.g., Premium, Bold, Trustworthy", required: true },
        { id: 'brd10_b2', type: 'goal', question: 'What brand milestone will signal program success?', placeholder: "e.g., Launch new website, finalize brand guidelines, rebrand all socials...", required: true },
      ]
    }]
  ),

  mkProgram(
    'clt-011', 'CLT-011', 'Client Acquisition Intensive',
    'A hyper-focused sprint to fill your pipeline with qualified, high-value clients within 30 days.',
    1100,
    ['Generate 20+ qualified leads', 'Book 10 discovery calls', 'Close 3 new clients'],
    '30 Days',
    [{
      id: 'clt11_p1', title: 'Pillar 1: Pipeline Audit', blocks: [
        { id: 'clt11_b1', type: 'dropdown', question: 'What is your average client deal value?', options: [{ value: 'under1k', label: 'Under $1,000' }, { value: '1k_5k', label: '$1,000 – $5,000' }, { value: '5k_15k', label: '$5,000 – $15,000' }, { value: 'over15k', label: 'Over $15,000' }], required: true },
        { id: 'clt11_b2', type: 'goal', question: 'Set a specific pipeline goal for this sprint:', placeholder: "e.g., Book 15 discovery calls and close 4 at $5k each...", required: true },
      ]
    }]
  ),

  mkProgram(
    'tem-012', 'TEM-012', 'Team Building Mastery',
    'Hire, develop, and retain A-players who elevate your culture and execute your vision without constant oversight.',
    2000,
    ['Design a world-class hiring process', 'Build a performance review framework', 'Install a team culture playbook'],
    '6 Weeks',
    [{
      id: 'tem12_p1', title: 'Pillar 1: Team Capacity Audit', blocks: [
        { id: 'tem12_b1', type: 'scoring', question: 'How would you rate your current team performance overall?', options: [{ value: 'underperforming', label: 'Underperforming — constant fire-fighting', hidden_code: 'ESTP' }, { value: 'average', label: 'Average — meets minimum expectations', hidden_code: 'ISTJ' }, { value: 'excellent', label: 'Excellent — proactive and autonomous', hidden_code: 'ENTJ' }], required: true },
        { id: 'tem12_b2', type: 'free_text', question: 'Describe your ideal team culture in detail:', placeholder: "Values, behaviors, communication norms, performance standards...", required: true },
      ]
    }]
  ),

  mkProgram(
    'rev-013', 'REV-013', 'Revenue Optimization Lab',
    'Identify and unlock hidden revenue opportunities within your existing client base and offer structure.',
    1700,
    ['Increase average client LTV by 40%', 'Design premium upsell pathways', 'Build a referral flywheel'],
    '4 Weeks',
    [{
      id: 'rev13_p1', title: 'Pillar 1: Revenue Audit', blocks: [
        { id: 'rev13_b1', type: 'free_text', question: 'What additional value could you offer your current clients that they are not yet paying for?', placeholder: "Think about expertise, access, time savings, network...", required: true },
        { id: 'rev13_b2', type: 'goal', question: 'Set a specific LTV growth goal for this program:', placeholder: "e.g., Increase average client value from $3k to $5k per year...", required: true },
      ]
    }]
  ),

  mkProgram(
    'str-014', 'STR-014', 'Strategic Planning Sprint',
    'A condensed but comprehensive planning session that gives your business a clear, executable 90-day roadmap.',
    900,
    ['Complete a full SWOT analysis', 'Set 3 OKRs for the next quarter', 'Build a weekly execution cadence'],
    '2 Weeks',
    [{
      id: 'str14_p1', title: 'Pillar 1: Strategic Clarity', blocks: [
        { id: 'str14_b1', type: 'branching', question: 'What is the primary focus of your next 90 days?', options: [{ value: 'growth', label: 'Aggressive Growth' }, { value: 'stability', label: 'Stabilize & Optimize' }, { value: 'pivot', label: 'Strategic Pivot or Repositioning' }], required: true },
        { id: 'str14_b2', type: 'goal', question: 'State your #1 OKR for the next quarter:', placeholder: "e.g., Objective: Dominate local market. KR: 30 new clients by Sep 30...", required: true },
      ]
    }]
  ),

  mkProgram(
    'exe-015', 'EXE-015', 'Executive Presence Training',
    'Master the art of commanding rooms, speaking with authority, and being perceived as the leader you truly are.',
    2400,
    ['Develop signature storytelling style', 'Master high-stakes presentation skills', 'Build unshakeable confidence under pressure'],
    '5 Weeks',
    [{
      id: 'exe15_p1', title: 'Pillar 1: Presence Baseline', blocks: [
        { id: 'exe15_b1', type: 'scoring', question: 'How confident are you presenting to a room of 50+ executives?', options: [{ value: 'nervous', label: 'Nervous — I avoid it when possible', hidden_code: 'ISFP' }, { value: 'capable', label: 'Capable — I manage but feel limited', hidden_code: 'ENFP' }, { value: 'commanding', label: 'Commanding — I own the room', hidden_code: 'ENTJ' }], required: true },
        { id: 'exe15_b2', type: 'free_text', question: 'Describe the last time you felt most powerful as a communicator:', placeholder: "What were you speaking about, who was the audience, what made it work...", required: true },
      ]
    }]
  ),
];
