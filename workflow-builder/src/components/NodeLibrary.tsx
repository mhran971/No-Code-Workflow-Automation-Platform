import type { NodeDefinition } from '../types';
import { groupNodesByCategory, isTriggerDefinition } from '../utils';

interface NodeLibraryProps {
  nodes: NodeDefinition[];
  selectedTriggerType: string | null;
  onSelectTrigger: (node: NodeDefinition) => void;
  onAddNode: (node: NodeDefinition) => void;
}

export function NodeLibrary({
  nodes,
  selectedTriggerType,
  onSelectTrigger,
  onAddNode,
}: NodeLibraryProps) {
  const grouped = groupNodesByCategory(nodes);

  return (
    <section className="panel node-library">
      <h2>Node library</h2>
      <p className="hint">Click a trigger to place it on the canvas. Other nodes are added the same way.</p>

      {Object.entries(grouped).map(([category, categoryNodes]) => (
        <div key={category} className="library-category">
          <h3>{category}</h3>
          <ul>
            {categoryNodes.map((node) => {
              const isTrigger = isTriggerDefinition(node);

              return (
                <li key={node.type}>
                  <button
                    type="button"
                    className={`library-item ${isTrigger && selectedTriggerType === node.type ? 'selected' : ''}`}
                    onClick={() => (isTrigger ? onSelectTrigger(node) : onAddNode(node))}
                  >
                    <strong>{node.label}</strong>
                    <span>{node.type}</span>
                  </button>
                </li>
              );
            })}
          </ul>
        </div>
      ))}
    </section>
  );
}
