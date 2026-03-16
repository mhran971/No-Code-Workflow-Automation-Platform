import { useState, useCallback, useRef } from 'react';
import type { ExecutionStep, ExecutionStatus, WorkflowExecution } from '@/types/workflow';
import type { Edge, Node } from '@xyflow/react';

// Mock variable data generators per node type
const MOCK_VARIABLES: Record<string, () => Record<string, unknown>> = {
  'webhook-trigger': () => ({
    method: 'POST',
    contentType: 'application/json',
    body: { leadId: 'LD-4521', email: 'jane@acme.com', company: 'Acme Corp', score: 85 },
  }),
  'manual-trigger': () => ({
    triggeredBy: 'admin@company.com',
    triggeredAt: new Date().toISOString(),
    testMode: true,
  }),
  'form-trigger': () => ({
    formId: 'form-001',
    submittedBy: 'user@client.com',
    fields: { name: 'John Doe', email: 'john@example.com', message: 'Interested in demo' },
  }),
  'schedule-trigger': () => ({
    scheduledAt: new Date().toISOString(),
    interval: 'every 30 minutes',
    runCount: 142,
  }),
  'app-event-trigger': () => ({
    eventType: 'ticket.created',
    source: 'support-app',
    payload: { ticketId: 'TK-8823', priority: 'high' },
  }),
  'ai-classifier': () => ({
    classification: { priority: 'high', sentiment: 'positive', confidence: 0.92 },
    documentsUsed: ['sales-policy-v3.pdf'],
    tokensUsed: 1245,
  }),
  'ai-router': () => ({
    route: 'high-value',
    confidence: 0.94,
    reasoning: 'Matches platinum tier criteria',
    documentsUsed: ['routing-rules.pdf'],
  }),
  'ai-summarizer': () => ({
    summary: 'Lead shows strong buying signals with enterprise-level requirements.',
    keyMetrics: { engagement: 'high', budget: 'confirmed' },
    tokensUsed: 890,
  }),
  'ai-custom': () => ({
    response: 'Based on the data, recommend proceeding with enterprise plan.',
    confidence: 0.88,
    tokensUsed: 1560,
  }),
  'ai-extractor': () => ({
    extracted: { customerName: 'Acme Corp', amount: 12500, dueDate: '2026-04-15' },
    confidence: 0.95,
    missingFields: [],
  }),
  'ai-sentiment': () => ({
    sentiment: 0.72,
    emotion: 'excited',
    urgency: 'high',
    intent: 'purchase_ready',
  }),
  'ai-generator': () => ({
    content: 'Dear Jane, we are delighted to present your custom implementation plan...',
    subject: 'Your custom implementation plan',
    tokensUsed: 245,
  }),
  'ai-validator': () => ({
    valid: true,
    issues: [],
    warnings: ['Discount 5% above standard for tier'],
    suggestedFix: 'Apply tier-B discount (12%)',
  }),
  'if-node': () => ({
    condition: 'priority === "high"',
    result: true,
    branch: 'true',
  }),
  'if-else-chain': () => ({
    evaluatedConditions: 3,
    matchedIndex: 1,
    matchedCondition: 'score > 80',
    branch: 'condition-2',
  }),
  'switch-node': () => ({
    expression: 'data.status',
    value: 'approved',
    matchedCase: 'approved',
  }),
  'parallel-split': () => ({
    branchCount: 3,
    startedBranches: ['branch-0', 'branch-1', 'branch-2'],
  }),
  'parallel-join': () => ({
    completedBranches: 3,
    totalBranches: 3,
    mergedKeys: ['resultA', 'resultB', 'resultC'],
  }),
  'wait-until': () => ({
    condition: 'approval.status === "approved"',
    pollCount: 3,
    waitedMs: 45000,
    resolved: true,
  }),
  'loop-node': () => ({
    iterations: 5,
    currentIndex: 4,
    accumulator: [101, 102, 103, 104, 105],
  }),
  'foreach-node': () => ({
    arrayLength: 12,
    processedItems: 12,
    currentItem: { id: 'item-12', name: 'Last Item' },
  }),
  'filter-node': () => ({
    inputCount: 25,
    outputCount: 8,
    condition: 'score > 70',
  }),
  'hubspot-contact': () => ({
    contactId: 'HS-89012',
    email: 'jane@acme.com',
    lifecycleStage: 'opportunity',
    hubspotUrl: 'https://app.hubspot.com/contacts/89012',
  }),
  'hubspot-deal': () => ({
    dealId: 'DEAL-4401',
    dealstage: 'qualifiedtobuy',
    amount: 25000,
    associatedContacts: ['HS-89012'],
  }),
  'gmail-send': () => ({
    emailId: 'msg-7745',
    to: 'sales-team@company.com',
    subject: 'High Priority Lead: Acme Corp',
    sendStatus: 'delivered',
  }),
  'gmail-receive': () => ({
    subject: 'Re: Partnership Proposal',
    from: 'partner@example.com',
    receivedDate: new Date().toISOString(),
  }),
  'send-email': () => ({
    emailId: 'msg-9921',
    to: 'sales-team@company.com',
    subject: 'New Lead Notification',
    sendStatus: 'delivered',
  }),
  'http-request': () => ({
    statusCode: 200,
    responseTime: 342,
    body: { success: true, data: { id: 'rec-001' } },
    headers: { 'content-type': 'application/json' },
  }),
  'external-service': () => ({
    service: 'Jira',
    action: 'createTicket',
    result: { ticketKey: 'PROJ-1234', url: 'https://jira.example.com/PROJ-1234' },
  }),
  'task-node': () => ({
    taskId: 'task-5501',
    assignee: 'reviewer@company.com',
    outcome: 'approved',
    completedAt: new Date().toISOString(),
  }),
  'clickup-task': () => ({
    taskId: 'cu-88231',
    status: 'in progress',
    url: 'https://app.clickup.com/t/cu-88231',
  }),
  'gdrive-file': () => ({
    fileId: 'gdrive-f-001',
    fileName: 'Report_Q1.pdf',
    webViewLink: 'https://drive.google.com/file/d/gdrive-f-001',
  }),
  'gsheets-rows': () => ({
    spreadsheetId: 'sheet-001',
    updatedRange: 'Sheet1!A1:D25',
    rowsAffected: 25,
  }),
  'gdocs-edit': () => ({
    documentId: 'doc-001',
    revisionId: 'rev-14',
    replacements: 3,
  }),
  'set-variables': () => ({
    set: { leadScore: 85, qualifiedAt: new Date().toISOString(), tier: 'enterprise' },
  }),
  'parse-json': () => ({
    parsedFields: 8,
    result: { name: 'Acme Corp', revenue: 1250000 },
  }),
  'format-date': () => ({
    input: '2026-03-15T09:41:00Z',
    output: 'March 15, 2026 at 9:41 AM',
    timezone: 'UTC',
  }),
};

const MOCK_MESSAGES: Record<string, string> = {
  'webhook-trigger': 'Webhook payload received (245 bytes)',
  'manual-trigger': 'Manually triggered by admin',
  'form-trigger': 'Form submitted by user@client.com',
  'schedule-trigger': 'Scheduled run #142 started',
  'app-event-trigger': 'App event received: ticket.created',
  'ai-classifier': 'Classified as "High Priority" (confidence: 0.92)',
  'ai-router': 'Routed to "high-value" path (confidence: 0.94)',
  'ai-summarizer': 'Generated executive summary (890 tokens)',
  'ai-custom': 'Custom agent completed successfully',
  'ai-extractor': 'Extracted 3 fields with 0.95 confidence',
  'ai-sentiment': 'Sentiment: positive (0.72), Urgency: high',
  'ai-generator': 'Generated email content (245 tokens)',
  'ai-validator': 'Validation passed with 1 warning',
  'if-node': 'Condition evaluated: TRUE → taking priority path',
  'if-else-chain': 'Matched condition #2: score > 80',
  'switch-node': 'Matched case: "approved"',
  'parallel-split': 'Split into 3 parallel branches',
  'parallel-join': 'All 3 branches completed successfully',
  'wait-until': 'Condition met after 3 polls (45s)',
  'loop-node': 'Completed 5 iterations',
  'foreach-node': 'Processed 12 items',
  'filter-node': 'Filtered 25 → 8 items',
  'hubspot-contact': 'Contact created: jane@acme.com (ID: HS-89012)',
  'hubspot-deal': 'Deal created: $25,000 (DEAL-4401)',
  'gmail-send': 'Email delivered to sales-team@company.com',
  'gmail-receive': 'New email from partner@example.com',
  'send-email': 'Email delivered to sales-team@company.com',
  'http-request': 'GET 200 OK (342ms)',
  'external-service': 'Created Jira ticket PROJ-1234',
  'task-node': 'Approved by reviewer@company.com',
  'clickup-task': 'Task created: cu-88231',
  'gdrive-file': 'File uploaded: Report_Q1.pdf',
  'gsheets-rows': 'Updated 25 rows in Sheet1',
  'gdocs-edit': 'Replaced 3 text segments',
  'set-variables': 'Set 3 variables',
  'parse-json': 'Parsed 8 fields from JSON',
  'format-date': 'Formatted date to "March 15, 2026 at 9:41 AM"',
};

function getRandomDuration(nodeType: string): number {
  if (nodeType.startsWith('ai-')) return 500 + Math.floor(Math.random() * 1500);
  if (nodeType.includes('hubspot') || nodeType.includes('gmail') || nodeType === 'http-request' || nodeType === 'external-service') return 300 + Math.floor(Math.random() * 1200);
  if (nodeType === 'if-node' || nodeType === 'switch-node' || nodeType === 'set-variables') return 1 + Math.floor(Math.random() * 10);
  return 50 + Math.floor(Math.random() * 400);
}

function buildExecutionOrder(nodes: Node[], edges: Edge[]): Node[] {
  // BFS from trigger nodes (no inputs / 0 inputs)
  const adjacency = new Map<string, string[]>();
  const inDegree = new Map<string, number>();

  nodes.forEach(n => {
    adjacency.set(n.id, []);
    inDegree.set(n.id, 0);
  });

  edges.forEach(e => {
    adjacency.get(e.source)?.push(e.target);
    inDegree.set(e.target, (inDegree.get(e.target) || 0) + 1);
  });

  const queue: string[] = [];
  inDegree.forEach((deg, id) => { if (deg === 0) queue.push(id); });

  const order: string[] = [];
  while (queue.length > 0) {
    const id = queue.shift()!;
    order.push(id);
    for (const next of adjacency.get(id) || []) {
      const newDeg = (inDegree.get(next) || 1) - 1;
      inDegree.set(next, newDeg);
      if (newDeg === 0) queue.push(next);
    }
  }

  // Return nodes in topological order
  const nodeMap = new Map(nodes.map(n => [n.id, n]));
  return order.map(id => nodeMap.get(id)!).filter(Boolean);
}

export type ExecutionMode = 'idle' | 'running' | 'stepping' | 'paused' | 'completed';

export interface UseWorkflowExecutionReturn {
  execution: WorkflowExecution | null;
  mode: ExecutionMode;
  currentStepIndex: number;
  runAll: () => void;
  stepForward: () => void;
  reset: () => void;
  pause: () => void;
  resume: () => void;
  nodeStatuses: Map<string, ExecutionStatus>;
}

export function useWorkflowExecution(nodes: Node[], edges: Edge[]): UseWorkflowExecutionReturn {
  const [execution, setExecution] = useState<WorkflowExecution | null>(null);
  const [mode, setMode] = useState<ExecutionMode>('idle');
  const [currentStepIndex, setCurrentStepIndex] = useState(-1);
  const [nodeStatuses, setNodeStatuses] = useState<Map<string, ExecutionStatus>>(new Map());

  const executionOrderRef = useRef<Node[]>([]);
  const stepsRef = useRef<ExecutionStep[]>([]);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const modeRef = useRef<ExecutionMode>('idle');

  const clearTimer = useCallback(() => {
    if (timerRef.current) {
      clearTimeout(timerRef.current);
      timerRef.current = null;
    }
  }, []);

  const buildSteps = useCallback(() => {
    const order = buildExecutionOrder(nodes, edges);
    executionOrderRef.current = order;

    const now = new Date();
    let timeOffset = 0;
    const steps: ExecutionStep[] = order.map((node, i) => {
      const d = node.data as Record<string, unknown>;
      const nodeType = (d.nodeType as string) || 'unknown';
      const duration = getRandomDuration(nodeType);
      timeOffset += duration;
      const ts = new Date(now.getTime() + timeOffset);
      const timestamp = `${ts.getHours().toString().padStart(2, '0')}:${ts.getMinutes().toString().padStart(2, '0')}:${ts.getSeconds().toString().padStart(2, '0')}`;

      const varGen = MOCK_VARIABLES[nodeType];
      return {
        id: `step-${i}`,
        nodeId: node.id,
        nodeLabel: (d.label as string) || 'Unknown',
        nodeType,
        status: 'idle' as ExecutionStatus,
        timestamp,
        duration,
        variables: varGen ? varGen() : { result: 'ok' },
        message: MOCK_MESSAGES[nodeType] || `Executed ${d.label}`,
      };
    });

    stepsRef.current = steps;
    return steps;
  }, [nodes, edges]);

  const executeStep = useCallback((stepIndex: number) => {
    const steps = stepsRef.current;
    if (stepIndex >= steps.length) {
      // Execution complete
      setMode('completed');
      modeRef.current = 'completed';
      setExecution(prev => prev ? { ...prev, status: 'success', completedAt: new Date().toISOString() } : prev);
      return;
    }

    // Mark current step as running
    steps[stepIndex] = { ...steps[stepIndex], status: 'running' };
    setExecution(prev => prev ? { ...prev, steps: [...steps] } : prev);
    setCurrentStepIndex(stepIndex);

    // Update node status
    setNodeStatuses(prev => {
      const next = new Map(prev);
      next.set(steps[stepIndex].nodeId, 'running');
      return next;
    });

    // After simulated duration, mark as success
    const duration = Math.min(steps[stepIndex].duration || 500, 1500);
    const simulatedDelay = Math.max(300, Math.min(duration, 1200));

    timerRef.current = setTimeout(() => {
      steps[stepIndex] = { ...steps[stepIndex], status: 'success' };
      setExecution(prev => prev ? { ...prev, steps: [...steps] } : prev);

      setNodeStatuses(prev => {
        const next = new Map(prev);
        next.set(steps[stepIndex].nodeId, 'success');
        return next;
      });

      const currentMode = modeRef.current;
      if (currentMode === 'running') {
        // Auto-advance
        executeStep(stepIndex + 1);
      } else if (currentMode === 'stepping') {
        // Wait for next stepForward call
        setMode('paused');
        modeRef.current = 'paused';
        setCurrentStepIndex(stepIndex);
        // Check if that was the last step
        if (stepIndex + 1 >= steps.length) {
          setMode('completed');
          modeRef.current = 'completed';
          setExecution(prev => prev ? { ...prev, status: 'success', completedAt: new Date().toISOString() } : prev);
        }
      }
    }, simulatedDelay);
  }, []);

  const initExecution = useCallback(() => {
    clearTimer();
    const steps = buildSteps();

    const ex: WorkflowExecution = {
      id: `exec-${Date.now().toString(36)}`,
      status: 'running',
      startedAt: new Date().toISOString(),
      steps: [...steps],
    };

    setExecution(ex);
    setCurrentStepIndex(-1);

    // Reset all node statuses
    const statuses = new Map<string, ExecutionStatus>();
    nodes.forEach(n => statuses.set(n.id, 'idle'));
    setNodeStatuses(statuses);

    return steps;
  }, [buildSteps, clearTimer, nodes]);

  const runAll = useCallback(() => {
    initExecution();
    setMode('running');
    modeRef.current = 'running';
    // Start from step 0 after a brief delay
    setTimeout(() => executeStep(0), 200);
  }, [initExecution, executeStep]);

  const stepForward = useCallback(() => {
    if (mode === 'idle' || mode === 'completed') {
      // Fresh start in step mode
      initExecution();
      setMode('stepping');
      modeRef.current = 'stepping';
      setTimeout(() => executeStep(0), 200);
    } else if (mode === 'paused') {
      // Continue to next step
      setMode('stepping');
      modeRef.current = 'stepping';
      const nextIndex = currentStepIndex + 1;
      if (nextIndex < stepsRef.current.length) {
        executeStep(nextIndex);
      }
    }
  }, [mode, currentStepIndex, initExecution, executeStep]);

  const pause = useCallback(() => {
    clearTimer();
    setMode('paused');
    modeRef.current = 'paused';
  }, [clearTimer]);

  const resume = useCallback(() => {
    setMode('running');
    modeRef.current = 'running';
    const nextIndex = currentStepIndex + 1;
    if (nextIndex < stepsRef.current.length) {
      executeStep(nextIndex);
    }
  }, [currentStepIndex, executeStep]);

  const reset = useCallback(() => {
    clearTimer();
    setExecution(null);
    setMode('idle');
    modeRef.current = 'idle';
    setCurrentStepIndex(-1);
    setNodeStatuses(new Map());
  }, [clearTimer]);

  return {
    execution,
    mode,
    currentStepIndex,
    runAll,
    stepForward,
    reset,
    pause,
    resume,
    nodeStatuses,
  };
}
