import {
  Zap, GitBranch, Brain, Plug, Database, Play,
  MousePointerClick, Webhook, FormInput, Clock, Bell,
  GitMerge, Split, Merge, Timer, Route, Repeat, ListOrdered, Filter as FilterIcon,
  Signpost, Tags, FileText, Bot, Search, Heart, Wand2, ShieldCheck,
  Mail, MailOpen, UserPlus, Handshake, CheckSquare, HardDrive, Sheet, FileEdit,
  Variable, Braces, Calendar,
  Globe, Send, ExternalLink, UserCheck, Workflow,
} from 'lucide-react';

// Icon + color lookup tables shared by the config panel (and any node-type display).
// Keys match the `icon` / `color` strings supplied by node definitions.

const GmailIcon = ({ className }: { className?: string }) => (
  <svg viewBox="0 0 24 24" fill="none" className={className} xmlns="http://www.w3.org/2000/svg">
    <rect x="2" y="5" width="20" height="14" rx="1.5" stroke="currentColor" strokeWidth="1.5"/>
    <path d="M2 8L9 13.5L12 11L15 13.5L22 8" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/>
    <path d="M9 13.5V19M15 13.5V19" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round"/>
  </svg>
);

export const iconMap: Record<string, React.ElementType> = {
  Zap, GitBranch, Brain, Plug, Database, Play,
  MousePointerClick, Webhook, FormInput, Clock, Bell,
  GitMerge, Split, Merge, Timer, Route, Repeat, ListOrdered, Filter: FilterIcon,
  Signpost, Tags, FileText, Bot, Search, Heart, Wand2, ShieldCheck,
  Mail, MailOpen, UserPlus, Handshake, CheckSquare, HardDrive, Sheet, FileEdit,
  Variable, Braces, Calendar,
  Globe, Send, ExternalLink, UserCheck, Workflow,
  Gmail: GmailIcon,
};

export const colorBg: Record<string, string> = {
  amber: 'bg-amber-500/15 text-amber-400',
  indigo: 'bg-indigo-500/15 text-indigo-400',
  violet: 'bg-violet-500/15 text-violet-400',
  blue: 'bg-blue-500/15 text-blue-400',
  emerald: 'bg-emerald-500/15 text-emerald-400',
  teal: 'bg-teal-500/15 text-teal-400',
  rose: 'bg-rose-500/15 text-rose-400',
};

// Fallback icon when a node's `icon` string isn't in iconMap.
export { Zap as DefaultIcon };
