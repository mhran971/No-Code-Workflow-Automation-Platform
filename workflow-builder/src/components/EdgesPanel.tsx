import type { CanvasNode, WorkflowEdge } from '../types';
import { TRIGGER_EDGE_SOURCE, createId, isTriggerEdge } from '../utils';

interface EdgesPanelProps {
  triggerLabel: string | null;
  nodes: CanvasNode[];
  edges: WorkflowEdge[];
  onChange: (edges: WorkflowEdge[]) => void;
}

const emptyEdge = (nodes: CanvasNode[]): WorkflowEdge => ({
  id: createId('edge'),
  source_node_key: nodes[0]?.id ?? '',
  target_node_key: nodes[1]?.id ?? nodes[0]?.id ?? '',
  branch_type: 'default',
});

export function EdgesPanel({ triggerLabel, nodes, edges, onChange }: EdgesPanelProps) {
  const updateEdge = (edgeId: string, patch: Partial<WorkflowEdge>) => {
    onChange(edges.map((edge) => (edge.id === edgeId ? { ...edge, ...patch } : edge)));
  };

  const removeEdge = (edgeId: string) => {
    onChange(edges.filter((edge) => edge.id !== edgeId));
  };

  const sourceOptions = [
    ...(triggerLabel
      ? [{ value: TRIGGER_EDGE_SOURCE, label: `Trigger (${triggerLabel})` }]
      : []),
    ...nodes.map((node) => ({
      value: node.id,
      label: `${node.label} (${node.id})`,
    })),
  ];

  const targetOptions = nodes.map((node) => ({
    value: node.id,
    label: `${node.label} (${node.id})`,
  }));

  return (
    <section className="panel edges-panel">
      <div className="panel-heading">
        <h2>Edges</h2>
        <button
          type="button"
          onClick={() => onChange([...edges, emptyEdge(nodes)])}
          disabled={nodes.length < 1}
        >
          Add edge
        </button>
      </div>

      <p className="hint">
        Trigger edges are visual only — the API marks the target as the entry node. Node-to-node
        edges are sent to the backend.
      </p>

      {edges.length === 0 ? (
        <p className="hint">Use the canvas ports or add an edge manually.</p>
      ) : null}

      {edges.map((edge) => (
        <div key={edge.id} className={`edge-card ${isTriggerEdge(edge) ? 'edge-card-trigger' : ''}`}>
          <div className="edge-card-header">
            <strong>{edge.id}</strong>
            {isTriggerEdge(edge) ? <span className="badge badge-trigger-link">trigger link</span> : null}
            <button type="button" className="icon-button" onClick={() => removeEdge(edge.id)}>
              ×
            </button>
          </div>

          <label>
            Source
            <select
              value={edge.source_node_key}
              onChange={(event) => updateEdge(edge.id, { source_node_key: event.target.value })}
            >
              {sourceOptions.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </label>

          <label>
            Target
            <select
              value={edge.target_node_key}
              onChange={(event) => updateEdge(edge.id, { target_node_key: event.target.value })}
            >
              {targetOptions.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </label>

          {!isTriggerEdge(edge) ? (
            <>
              <label>
                Branch type
                <select
                  value={edge.branch_type ?? 'default'}
                  onChange={(event) => updateEdge(edge.id, { branch_type: event.target.value })}
                >
                  <option value="default">default</option>
                  <option value="conditional">conditional</option>
                  <option value="parallel">parallel</option>
                </select>
              </label>

              {edge.branch_type === 'conditional' ? (
                <label>
                  Condition expression
                  <input
                    type="text"
                    value={edge.condition_expression ?? ''}
                    onChange={(event) =>
                      updateEdge(edge.id, { condition_expression: event.target.value })
                    }
                    placeholder='status == "approved"'
                  />
                </label>
              ) : null}

              {edge.branch_type === 'conditional' ? (
                <label className="checkbox-row">
                  <input
                    type="checkbox"
                    checked={Boolean(edge.is_default_branch)}
                    onChange={(event) =>
                      updateEdge(edge.id, { is_default_branch: event.target.checked })
                    }
                  />
                  <span>Default branch</span>
                </label>
              ) : null}

              {edge.branch_type === 'parallel' ? (
                <>
                  <label>
                    Parallel strategy
                    <select
                      value={edge.parallel_strategy ?? ''}
                      onChange={(event) =>
                        updateEdge(edge.id, { parallel_strategy: event.target.value || null })
                      }
                    >
                      <option value="">None</option>
                      <option value="fork_join">fork_join</option>
                      <option value="fire_and_forget">fire_and_forget</option>
                    </select>
                  </label>

                  <label>
                    Join node key
                    <select
                      value={edge.join_node_key ?? ''}
                      onChange={(event) =>
                        updateEdge(edge.id, { join_node_key: event.target.value || null })
                      }
                    >
                      <option value="">None</option>
                      {nodes.map((node) => (
                        <option key={node.id} value={node.id}>
                          {node.label} ({node.id})
                        </option>
                      ))}
                    </select>
                  </label>
                </>
              ) : null}
            </>
          ) : null}
        </div>
      ))}
    </section>
  );
}
