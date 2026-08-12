import Graph, {NodeKey, Attributes} from 'graphology-types';

type SubgraphPredicateFunction = (key: string, attributes: Attributes) => boolean;

type SubgraphNodes = Array<NodeKey> | Set<NodeKey> | SubgraphPredicateFunction;

export default function subgraph(graph: Graph, nodes: SubgraphNodes): Graph;
