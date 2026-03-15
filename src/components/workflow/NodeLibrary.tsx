import { useState } from 'react';
import { NODE_TYPES, NODE_CATEGORIES, type NodeCategory, type NodeTypeDefinition } from '@/types/workflow';
import {
  Zap, GitBranch, Brain, Plug, Database, Play,
  MousePointerClick, Webhook, FormInput, Clock, Bell,
  GitMerge, Split, Merge, Timer, Route, Repeat, ListOrdered, Filter as FilterIcon,
  Signpost, Tags, FileText, Bot, Search, Heart, Wand2, ShieldCheck,
  Mail, MailOpen, UserPlus, Handshake, CheckSquare, HardDrive, Sheet, FileEdit,
  Variable, Braces, Calendar,
  Globe, Send, ExternalLink, UserCheck,
  ChevronDown, SearchIcon
} from 'lucide-react';

const iconMap: Record<string, React.ElementType> = {
  Zap, GitBranch, Brain, Plug, Database, Play,
  MousePointerClick, Webhook, FormInput, Clock, Bell,
  GitMerge, Split, Merge, Timer, Route, Repeat, ListOrdered, Filter: FilterIcon,
  Signpost, Tags, FileText, Bot, Search, Heart, Wand2, ShieldCheck,
  Mail, MailOpen, UserPlus, Handshake, CheckSquare, HardDrive, Sheet, FileEdit,
  Variable, Braces, Calendar,
  Globe, Send, ExternalLink, UserCheck,
};

const categoryColorMap: Record<string, string> = {
  amber: 'bg-amber-500/15 text-amber-400 border-amber-500/20',
  indigo: 'bg-indigo-500/15 text-indigo-400 border-indigo-500/20',
  violet: 'bg-violet-500/15 text-violet-400 border-violet-500/20',
  blue: 'bg-blue-500/15 text-blue-400 border-blue-500/20',
  emerald: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/20',
  rose: 'bg-rose-500/15 text-rose-400 border-rose-500/20',
};

const dotColorMap: Record<string, string> = {
  amber: 'bg-amber-400',
  indigo: 'bg-indigo-400',
  violet: 'bg-violet-400',
  blue: 'bg-blue-400',
  emerald: 'bg-emerald-400',
  rose: 'bg-rose-400',
};

export function NodeLibrary() {
  const [search, setSearch] = useState('');
  const [expandedCategories, setExpandedCategories] = useState<Set<NodeCategory>>(
    new Set(['triggers', 'logic', 'ai', 'integrations', 'data', 'actions'])
  );

  const toggleCategory = (cat: NodeCategory) => {
    setExpandedCategories(prev => {
      const next = new Set(prev);
      if (next.has(cat)) next.delete(cat);
      else next.add(cat);
      return next;
    });
  };

  const filteredNodes = search
    ? NODE_TYPES.filter(n => n.label.toLowerCase().includes(search.toLowerCase()) || n.description.toLowerCase().includes(search.toLowerCase()))
    : NODE_TYPES;

  const groupedNodes = (Object.keys(NODE_CATEGORIES) as NodeCategory[]).map(cat => ({
    category: cat,
    ...NODE_CATEGORIES[cat],
    nodes: filteredNodes.filter(n => n.category === cat),
  })).filter(g => g.nodes.length > 0);

  const onDragStart = (event: React.DragEvent, nodeType: NodeTypeDefinition) => {
    event.dataTransfer.setData('application/reactflow', JSON.stringify(nodeType));
    event.dataTransfer.effectAllowed = 'move';
  };

  return (
    <div className="w-[280px] h-full bg-background border-r border-border flex flex-col">
      <div className="p-4 border-b border-border">
        <h2 className="text-sm font-semibold text-foreground mb-3">Node Library</h2>
        <div className="relative">
          <SearchIcon className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground" />
          <input
            type="text"
            placeholder="Search nodes..."
            value={search}
            onChange={e => setSearch(e.target.value)}
            className="w-full h-8 pl-8 pr-3 text-xs bg-muted border border-border rounded-md text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-primary"
          />
        </div>
      </div>

      <div className="flex-1 overflow-y-auto p-2 space-y-1">
        {groupedNodes.map(group => (
          <div key={group.category}>
            <button
              onClick={() => toggleCategory(group.category)}
              className="w-full flex items-center gap-2 px-2 py-1.5 text-xs font-medium text-muted-foreground hover:text-foreground transition-colors rounded"
            >
              <div className={`w-1.5 h-1.5 rounded-full ${dotColorMap[group.color]}`} />
              <span className="flex-1 text-left">{group.label}</span>
              <span className="text-[10px] text-muted-foreground/60">{group.nodes.length}</span>
              <ChevronDown className={`h-3 w-3 transition-transform ${expandedCategories.has(group.category) ? '' : '-rotate-90'}`} />
            </button>

            {expandedCategories.has(group.category) && (
              <div className="ml-1 space-y-0.5 mt-0.5">
                {group.nodes.map(node => {
                  const Icon = iconMap[node.icon] || Zap;
                  return (
                    <div
                      key={node.type}
                      draggable
                      onDragStart={e => onDragStart(e, node)}
                      className="flex items-center gap-2.5 px-2.5 py-2 rounded-md cursor-grab active:cursor-grabbing hover:bg-muted/60 transition-colors group"
                    >
                      <div className={`w-7 h-7 rounded-md flex items-center justify-center border ${categoryColorMap[node.color]}`}>
                        <Icon className="h-3.5 w-3.5" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="text-xs font-medium text-foreground/90 truncate">{node.label}</div>
                        <div className="text-[10px] text-muted-foreground truncate">{node.description}</div>
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}
