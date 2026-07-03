import type { Edge, Node } from '@xyflow/react';

// Topologically order canvas nodes for execution: BFS (Kahn's algorithm) starting
// from trigger nodes (in-degree 0). Returns nodes in the order they should run.
export function buildExecutionOrder(nodes: Node[], edges: Edge[]): Node[] {
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

  const nodeMap = new Map(nodes.map(n => [n.id, n]));
  return order.map(id => nodeMap.get(id)!).filter(Boolean);
}
