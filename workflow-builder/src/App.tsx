import { useEffect, useMemo, useState } from 'react';
import { ApiError, fetchNodeLibrary, validateWorkflowDefinition } from './api';
import { AuthPanel } from './components/AuthPanel';
import { Canvas } from './components/Canvas';
import { EdgesPanel } from './components/EdgesPanel';
import { NodeLibrary } from './components/NodeLibrary';
import { PropertiesPanel } from './components/PropertiesPanel';
import { ValidationResults } from './components/ValidationResults';
import { DEFAULT_ACCESS_TOKEN, DEFAULT_API_BASE_URL } from './config';
import type { CanvasNode, NodeDefinition, ValidationResult, WorkflowDefinition, WorkflowEdge } from './types';
import {
  TRIGGER_CANVAS,
  TRIGGER_EDGE_SOURCE,
  buildDefinitionPayload,
  createId,
  defaultConfigForNode,
  isTriggerEdge,
  normalizeToken,
  readStoredValue,
} from './utils';
import './App.css';

const STORAGE_KEYS = {
  apiBaseUrl: 'workflow-builder.apiBaseUrl',
  accessToken: 'workflow-builder.accessToken',
};

export default function App() {
  const [apiBaseUrl, setApiBaseUrl] = useState(() =>
    readStoredValue(STORAGE_KEYS.apiBaseUrl, DEFAULT_API_BASE_URL),
  );
  const [accessToken, setAccessToken] = useState(() =>
    readStoredValue(STORAGE_KEYS.accessToken, DEFAULT_ACCESS_TOKEN),
  );
  const [nodeDefinitions, setNodeDefinitions] = useState<NodeDefinition[]>([]);
  const [libraryError, setLibraryError] = useState<string | null>(null);
  const [libraryLoading, setLibraryLoading] = useState(false);

  const [triggerType, setTriggerType] = useState<string | null>(null);
  const [triggerConfig, setTriggerConfig] = useState<Record<string, unknown>>({});
  const [canvasNodes, setCanvasNodes] = useState<CanvasNode[]>([]);
  const [edges, setEdges] = useState<WorkflowEdge[]>([]);
  const [selectedNodeId, setSelectedNodeId] = useState<string | null>(null);
  const [triggerSelected, setTriggerSelected] = useState(false);
  const [pendingConnectionSource, setPendingConnectionSource] = useState<'trigger' | string | null>(
    null,
  );

  const [validationResult, setValidationResult] = useState<ValidationResult | null>(null);
  const [validationError, setValidationError] = useState<string | null>(null);
  const [validationLoading, setValidationLoading] = useState(false);
  const [lastDefinition, setLastDefinition] = useState<WorkflowDefinition | null>(null);

  useEffect(() => {
    window.localStorage.setItem(STORAGE_KEYS.apiBaseUrl, apiBaseUrl);
  }, [apiBaseUrl]);

  useEffect(() => {
    window.localStorage.setItem(STORAGE_KEYS.accessToken, accessToken);
  }, [accessToken]);

  const selectedNode = useMemo(
    () => canvasNodes.find((node) => node.canvasId === selectedNodeId) ?? null,
    [canvasNodes, selectedNodeId],
  );

  const selectedTriggerDefinition = useMemo(
    () => nodeDefinitions.find((node) => node.type === triggerType) ?? null,
    [nodeDefinitions, triggerType],
  );

  const loadNodeLibrary = async () => {
    setLibraryLoading(true);
    setLibraryError(null);

    try {
      const response = await fetchNodeLibrary(apiBaseUrl, accessToken);
      setNodeDefinitions(response.data);

      const defaultTrigger = response.data.find((node) => node.category === 'trigger');
      if (defaultTrigger && !triggerType) {
        setTriggerType(defaultTrigger.type);
        setTriggerConfig(defaultConfigForNode(defaultTrigger));
        setTriggerSelected(true);
        setSelectedNodeId(null);
      }
    } catch (error) {
      const message =
        error instanceof ApiError
          ? error.status > 0
            ? `${error.message} (${error.status}) — ${error.url}`
            : `${error.message} — ${error.url}`
          : error instanceof Error
            ? error.message
            : 'Failed to load node library';
      setLibraryError(message);
    } finally {
      setLibraryLoading(false);
    }
  };

  const handleSelectTrigger = (definition: NodeDefinition) => {
    setTriggerType(definition.type);
    setTriggerConfig(defaultConfigForNode(definition));
    setSelectedNodeId(null);
    setTriggerSelected(true);
    setValidationResult(null);
    setValidationError(null);
  };

  const connectNodes = (source: 'trigger' | string, targetCanvasId: string) => {
    const targetNode = canvasNodes.find((node) => node.canvasId === targetCanvasId);
    if (!targetNode) {
      return;
    }

    if (source === 'trigger') {
      setEdges((current) => [
        ...current.filter((edge) => !isTriggerEdge(edge)),
        {
          id: createId('edge'),
          source_node_key: TRIGGER_EDGE_SOURCE,
          target_node_key: targetNode.id,
          branch_type: 'default',
        },
      ]);

      setCanvasNodes((current) =>
        current.map((node) => ({
          ...node,
          is_entry_point: node.canvasId === targetCanvasId,
        })),
      );
    } else {
      const sourceNode = canvasNodes.find((node) => node.canvasId === source);
      if (!sourceNode || sourceNode.canvasId === targetCanvasId) {
        return;
      }

      const duplicate = edges.some(
        (edge) =>
          !isTriggerEdge(edge) &&
          edge.source_node_key === sourceNode.id &&
          edge.target_node_key === targetNode.id,
      );

      if (duplicate) {
        return;
      }

      setEdges((current) => [
        ...current,
        {
          id: createId('edge'),
          source_node_key: sourceNode.id,
          target_node_key: targetNode.id,
          branch_type: 'default',
        },
      ]);
    }

    setPendingConnectionSource(null);
    setValidationResult(null);
    setValidationError(null);
  };

  const handleAddNode = (definition: NodeDefinition) => {
    const nodeId = createId(definition.type);
    const isFirstNode = canvasNodes.length === 0;
    const canvasId = createId('canvas');

    const autoLinkFromTrigger = Boolean(triggerType && isFirstNode);

    const newNode: CanvasNode = {
      canvasId,
      id: nodeId,
      type: definition.type,
      label: definition.label,
      config: defaultConfigForNode(definition),
      is_entry_point: autoLinkFromTrigger || (isFirstNode && !triggerType),
      is_terminal: false,
      x: TRIGGER_CANVAS.x + TRIGGER_CANVAS.width + 60 + canvasNodes.length * 24,
      y: TRIGGER_CANVAS.y + canvasNodes.length * 24,
    };

    setCanvasNodes((current) => [...current, newNode]);
    setSelectedNodeId(canvasId);
    setTriggerSelected(false);
    setPendingConnectionSource(null);

    if (autoLinkFromTrigger) {
      setEdges((current) => [
        ...current.filter((edge) => !isTriggerEdge(edge)),
        {
          id: createId('edge'),
          source_node_key: TRIGGER_EDGE_SOURCE,
          target_node_key: nodeId,
          branch_type: 'default',
        },
      ]);
    }

    setValidationResult(null);
    setValidationError(null);
  };

  const handleNodeChange = (canvasId: string, patch: Partial<CanvasNode>) => {
    setCanvasNodes((current) =>
      current.map((node) => (node.canvasId === canvasId ? { ...node, ...patch } : node)),
    );
    setValidationResult(null);
    setValidationError(null);
  };

  const handleRemoveNode = (canvasId: string) => {
    const node = canvasNodes.find((item) => item.canvasId === canvasId);
    if (!node) {
      return;
    }

    setCanvasNodes((current) => current.filter((item) => item.canvasId !== canvasId));
    setEdges((current) =>
      current.filter((edge) => {
        if (isTriggerEdge(edge)) {
          return edge.target_node_key !== node.id;
        }

        return edge.source_node_key !== node.id && edge.target_node_key !== node.id;
      }),
    );

    if (selectedNodeId === canvasId) {
      setSelectedNodeId(null);
      setTriggerSelected(Boolean(triggerType));
    }

    setValidationResult(null);
    setValidationError(null);
  };

  const handleVerify = async () => {
    setValidationLoading(true);
    setValidationError(null);
    setValidationResult(null);

    if (!triggerType) {
      setValidationError('Select a trigger type before verifying.');
      setValidationLoading(false);
      return;
    }

    const definition = buildDefinitionPayload(triggerType, triggerConfig, canvasNodes, edges);
    setLastDefinition(definition);

    try {
      const result = await validateWorkflowDefinition(apiBaseUrl, accessToken, definition);
      setValidationResult(result);
    } catch (error) {
      if (error instanceof ApiError) {
        if (error.body && typeof error.body === 'object' && 'is_publishable' in (error.body as object)) {
          setValidationResult(error.body as ValidationResult);
        } else if (error.body && typeof error.body === 'object' && 'message' in (error.body as object)) {
          setValidationError(String((error.body as { message: string }).message));
        } else {
          setValidationError(`${error.message} (${error.status})`);
        }
      } else if (error instanceof Error) {
        setValidationError(error.message);
      } else {
        setValidationError('Verification request failed.');
      }
    } finally {
      setValidationLoading(false);
    }
  };

  return (
    <div className="app">
      <header className="app-header">
        <div>
          <h1>Workflow Builder Tester</h1>
          <p>Build a workflow and verify it against the backend validation service.</p>
        </div>
        <button
          type="button"
          className="verify-button"
          onClick={handleVerify}
          disabled={validationLoading || !normalizeToken(accessToken) || !triggerType}
        >
          {validationLoading ? 'Verifying…' : 'Verify workflow'}
        </button>
      </header>

      <div className="layout">
        <aside className="sidebar">
          <AuthPanel
            apiBaseUrl={apiBaseUrl}
            accessToken={accessToken}
            onApiBaseUrlChange={setApiBaseUrl}
            onAccessTokenChange={setAccessToken}
            onLoadNodes={loadNodeLibrary}
            onClearSavedSettings={() => {
              window.localStorage.removeItem(STORAGE_KEYS.apiBaseUrl);
              window.localStorage.removeItem(STORAGE_KEYS.accessToken);
              setApiBaseUrl(DEFAULT_API_BASE_URL);
              setAccessToken(DEFAULT_ACCESS_TOKEN);
              setLibraryError(null);
            }}
            loading={libraryLoading}
            error={libraryError}
          />

          <NodeLibrary
            nodes={nodeDefinitions}
            selectedTriggerType={triggerType}
            onSelectTrigger={handleSelectTrigger}
            onAddNode={handleAddNode}
          />
        </aside>

        <main className="workspace">
          <Canvas
            trigger={selectedTriggerDefinition}
            triggerSelected={triggerSelected}
            nodes={canvasNodes}
            edges={edges}
            selectedNodeId={selectedNodeId}
            pendingConnectionSource={pendingConnectionSource}
            onSelectTrigger={() => {
              setTriggerSelected(true);
              setSelectedNodeId(null);
            }}
            onSelectNode={(canvasId) => {
              setSelectedNodeId(canvasId);
              setTriggerSelected(false);
            }}
            onMoveNode={(canvasId, x, y) => handleNodeChange(canvasId, { x, y })}
            onRemoveNode={handleRemoveNode}
            onStartConnection={(source) => setPendingConnectionSource(source)}
            onCompleteConnection={(targetCanvasId) => {
              if (pendingConnectionSource) {
                connectNodes(pendingConnectionSource, targetCanvasId);
              }
            }}
            onCancelConnection={() => setPendingConnectionSource(null)}
          />

          <div className="workspace-bottom">
            <PropertiesPanel
              nodeDefinitions={nodeDefinitions}
              triggerType={triggerType}
              triggerConfig={triggerConfig}
              onTriggerConfigChange={(config) => {
                setTriggerConfig(config);
                setValidationResult(null);
                setValidationError(null);
              }}
              selectedNode={selectedNode}
              triggerSelected={triggerSelected}
              onNodeChange={handleNodeChange}
            />

            <EdgesPanel
              triggerLabel={selectedTriggerDefinition?.label ?? null}
              nodes={canvasNodes}
              edges={edges}
              onChange={(nextEdges) => {
                const triggerTargets = new Set(
                  nextEdges.filter(isTriggerEdge).map((edge) => edge.target_node_key),
                );

                setEdges(nextEdges);
                setCanvasNodes((current) =>
                  current.map((node) => ({
                    ...node,
                    is_entry_point: triggerTargets.has(node.id),
                  })),
                );
                setValidationResult(null);
                setValidationError(null);
              }}
            />
          </div>

          <ValidationResults
            definition={lastDefinition}
            result={validationResult}
            error={validationError}
            loading={validationLoading}
          />
        </main>
      </div>
    </div>
  );
}
