import type { CanvasNode, NodeDefinition } from '../types';
import { ConfigFieldsForm } from './ConfigFieldsForm';

interface PropertiesPanelProps {
  nodeDefinitions: NodeDefinition[];
  triggerType: string | null;
  triggerConfig: Record<string, unknown>;
  onTriggerConfigChange: (config: Record<string, unknown>) => void;
  selectedNode: CanvasNode | null;
  triggerSelected: boolean;
  onNodeChange: (canvasId: string, patch: Partial<CanvasNode>) => void;
}

export function PropertiesPanel({
  nodeDefinitions,
  triggerType,
  triggerConfig,
  onTriggerConfigChange,
  selectedNode,
  triggerSelected,
  onNodeChange,
}: PropertiesPanelProps) {
  const triggerDefinition = nodeDefinitions.find((node) => node.type === triggerType) ?? null;

  if (triggerSelected || !selectedNode) {
    return (
      <section className="panel properties-panel">
        <h2>Trigger configuration</h2>
        {triggerDefinition ? (
          <>
            <p className="hint">
              Trigger: <strong>{triggerDefinition.label}</strong> (<code>{triggerDefinition.type}</code>)
            </p>
            <ConfigFieldsForm
              fields={triggerDefinition.config_fields}
              values={triggerConfig}
              onChange={onTriggerConfigChange}
            />
          </>
        ) : (
          <p className="hint">Select a trigger from the node library.</p>
        )}
      </section>
    );
  }

  const nodeDefinition =
    nodeDefinitions.find((definition) => definition.type === selectedNode.type) ?? null;

  return (
    <section className="panel properties-panel">
      <h2>Node configuration</h2>

      <label>
        Node ID
        <input
          type="text"
          value={selectedNode.id}
          onChange={(event) => onNodeChange(selectedNode.canvasId, { id: event.target.value })}
        />
      </label>

      <label>
        Label
        <input
          type="text"
          value={selectedNode.label}
          onChange={(event) => onNodeChange(selectedNode.canvasId, { label: event.target.value })}
        />
      </label>

      <div className="checkbox-group">
        <label className="checkbox-row">
          <input
            type="checkbox"
            checked={selectedNode.is_entry_point}
            onChange={(event) =>
              onNodeChange(selectedNode.canvasId, { is_entry_point: event.target.checked })
            }
          />
          <span>Entry point</span>
        </label>

        <label className="checkbox-row">
          <input
            type="checkbox"
            checked={selectedNode.is_terminal}
            onChange={(event) =>
              onNodeChange(selectedNode.canvasId, { is_terminal: event.target.checked })
            }
          />
          <span>Terminal</span>
        </label>
      </div>

      {nodeDefinition ? (
        <ConfigFieldsForm
          fields={nodeDefinition.config_fields}
          values={selectedNode.config}
          onChange={(config) => onNodeChange(selectedNode.canvasId, { config })}
        />
      ) : (
        <p className="hint">Unknown node type: {selectedNode.type}</p>
      )}
    </section>
  );
}
