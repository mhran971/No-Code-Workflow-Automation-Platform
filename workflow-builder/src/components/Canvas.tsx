import type { CanvasNode, NodeDefinition, WorkflowEdge } from '../types';
import {
  TRIGGER_CANVAS,
  isTriggerEdge,
  nodeAnchorPoints,
  triggerAnchorPoints,
} from '../utils';

interface CanvasProps {
  trigger: NodeDefinition | null;
  triggerSelected: boolean;
  nodes: CanvasNode[];
  edges: WorkflowEdge[];
  selectedNodeId: string | null;
  pendingConnectionSource: 'trigger' | string | null;
  onSelectTrigger: () => void;
  onSelectNode: (canvasId: string) => void;
  onMoveNode: (canvasId: string, x: number, y: number) => void;
  onRemoveNode: (canvasId: string) => void;
  onStartConnection: (source: 'trigger' | string) => void;
  onCompleteConnection: (targetCanvasId: string) => void;
  onCancelConnection: () => void;
}

export function Canvas({
  trigger,
  triggerSelected,
  nodes,
  edges,
  selectedNodeId,
  pendingConnectionSource,
  onSelectTrigger,
  onSelectNode,
  onMoveNode,
  onRemoveNode,
  onStartConnection,
  onCompleteConnection,
  onCancelConnection,
}: CanvasProps) {
  const nodeById = new Map(nodes.map((node) => [node.id, node]));
  const canvasIdByNodeId = new Map(nodes.map((node) => [node.id, node.canvasId]));

  const renderEdge = (edge: WorkflowEdge) => {
    if (isTriggerEdge(edge)) {
      const target = nodeById.get(edge.target_node_key);
      if (!trigger || !target) {
        return null;
      }

      const source = triggerAnchorPoints().out;
      const targetPoint = nodeAnchorPoints(target).in;

      return (
        <line
          key={edge.id}
          x1={source.x}
          y1={source.y}
          x2={targetPoint.x}
          y2={targetPoint.y}
          stroke="#0f766e"
          strokeWidth="2"
          strokeDasharray="6 4"
          markerEnd="url(#arrow-trigger)"
        />
      );
    }

    const source = nodeById.get(edge.source_node_key);
    const target = nodeById.get(edge.target_node_key);

    if (!source || !target) {
      return null;
    }

    const sourcePoint = nodeAnchorPoints(source).out;
    const targetPoint = nodeAnchorPoints(target).in;

    return (
      <line
        key={edge.id}
        x1={sourcePoint.x}
        y1={sourcePoint.y}
        x2={targetPoint.x}
        y2={targetPoint.y}
        stroke="#64748b"
        strokeWidth="2"
        markerEnd="url(#arrow)"
      />
    );
  };

  return (
    <section className="panel canvas-panel">
      <div className="canvas-header">
        <h2>Canvas</h2>
        <span className="hint">
          Click the <span className="port-inline">●</span> port on a node, then the target port to
          draw an edge. Triggers connect to the workflow entry node.
        </span>
      </div>

      {pendingConnectionSource ? (
        <div className="connection-banner">
          Select a target input port <button type="button" onClick={onCancelConnection}>Cancel</button>
        </div>
      ) : null}

      <div
        className="canvas"
        onMouseDown={(event) => {
          if (event.target === event.currentTarget) {
            onCancelConnection();
          }
        }}
      >
        <svg className="canvas-edges" aria-hidden="true">
          {edges.map(renderEdge)}
          <defs>
            <marker id="arrow" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto">
              <path d="M0,0 L6,3 L0,6 Z" fill="#64748b" />
            </marker>
            <marker
              id="arrow-trigger"
              markerWidth="8"
              markerHeight="8"
              refX="6"
              refY="3"
              orient="auto"
            >
              <path d="M0,0 L6,3 L0,6 Z" fill="#0f766e" />
            </marker>
          </defs>
        </svg>

        {!trigger && nodes.length === 0 ? (
          <div className="canvas-empty">
            Select a trigger or add nodes from the library to build your workflow.
          </div>
        ) : null}

        {trigger ? (
          <div
            className={`canvas-node canvas-trigger ${triggerSelected ? 'selected' : ''}`}
            style={{ left: TRIGGER_CANVAS.x, top: TRIGGER_CANVAS.y }}
            onMouseDown={(event) => {
              if ((event.target as HTMLElement).closest('.connection-port')) {
                return;
              }

              event.preventDefault();
              onSelectTrigger();
            }}
          >
            <div className="canvas-node-header">
              <strong>{trigger.label}</strong>
              <span className="badge badge-trigger">trigger</span>
            </div>
            <div className="canvas-node-meta">
              <code>{trigger.type}</code>
            </div>
            <button
              type="button"
              className={`connection-port port-out ${pendingConnectionSource === 'trigger' ? 'active' : ''}`}
              title="Draw edge from trigger"
              onClick={(event) => {
                event.stopPropagation();
                onStartConnection('trigger');
              }}
            >
              ●
            </button>
          </div>
        ) : null}

        {nodes.map((node) => (
          <div
            key={node.canvasId}
            className={`canvas-node ${selectedNodeId === node.canvasId ? 'selected' : ''}`}
            style={{ left: node.x, top: node.y }}
            onMouseDown={(event) => {
              if (
                (event.target as HTMLElement).closest('button') &&
                !(event.target as HTMLElement).closest('.connection-port')
              ) {
                return;
              }

              if ((event.target as HTMLElement).closest('.connection-port')) {
                return;
              }

              event.preventDefault();
              onSelectNode(node.canvasId);

              const startX = event.clientX;
              const startY = event.clientY;
              const originX = node.x;
              const originY = node.y;

              const onMouseMove = (moveEvent: MouseEvent) => {
                onMoveNode(
                  node.canvasId,
                  originX + moveEvent.clientX - startX,
                  originY + moveEvent.clientY - startY,
                );
              };

              const onMouseUp = () => {
                window.removeEventListener('mousemove', onMouseMove);
                window.removeEventListener('mouseup', onMouseUp);
              };

              window.addEventListener('mousemove', onMouseMove);
              window.addEventListener('mouseup', onMouseUp);
            }}
          >
            <button
              type="button"
              className="connection-port port-in"
              title="Connect to this node"
              onClick={(event) => {
                event.stopPropagation();
                if (pendingConnectionSource) {
                  onCompleteConnection(node.canvasId);
                }
              }}
            >
              ●
            </button>

            <div className="canvas-node-header">
              <strong>{node.label}</strong>
              <button
                type="button"
                className="icon-button"
                onClick={(event) => {
                  event.stopPropagation();
                  onRemoveNode(node.canvasId);
                }}
                aria-label={`Remove ${node.label}`}
              >
                ×
              </button>
            </div>
            <div className="canvas-node-meta">
              <code>{node.type}</code>
              <span>{node.id}</span>
            </div>
            <div className="canvas-node-flags">
              {node.is_entry_point ? <span className="badge">entry</span> : null}
              {edges.some(
                (edge) =>
                  isTriggerEdge(edge) && canvasIdByNodeId.get(edge.target_node_key) === node.canvasId,
              ) ? (
                <span className="badge badge-trigger-link">from trigger</span>
              ) : null}
              {node.is_terminal ? <span className="badge">terminal</span> : null}
            </div>

            <button
              type="button"
              className={`connection-port port-out ${
                pendingConnectionSource === node.canvasId ? 'active' : ''
              }`}
              title="Draw edge from this node"
              onClick={(event) => {
                event.stopPropagation();
                onStartConnection(node.canvasId);
              }}
            >
              ●
            </button>
          </div>
        ))}
      </div>
    </section>
  );
}
