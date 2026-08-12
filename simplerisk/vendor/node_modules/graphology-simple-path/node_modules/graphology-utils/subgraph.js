/**
 * Graphology Sub Graph
 * =====================
 *
 * Function returning the subgraph composed of the nodes passed as parameters.
 */

/**
 * Returning the subgraph composed of the nodes passed as parameters.
 *
 * @param  {Graph} graph - Graph containing the subgraph.
 * @param  {array} nodes - Array, set or function defining the nodes wanted in the subgraph.
 */

module.exports = function subGraph(graph, nodes) {
  var nodesSet;
  var subGraphResult = graph.nullCopy();

  if (Array.isArray(nodes)) {
    nodesSet = new Set(nodes);
  }
  else if (nodes instanceof Set) {
    nodesSet = nodes;
  }
  else if (typeof nodes === 'function') {
    nodesSet = new Set();
    graph.forEachNode(function(key, attrs) {
      if (nodes(key, attrs)) {
        nodesSet.add(key);
      }
    });
  }
  else {
    throw new Error(
      'The argument "nodes" is neither an array, nor a set, nor a function.'
    );
  }

  if (nodesSet.size === 0) return subGraphResult;

  var insertedSelfloops = new Set(); // Useful to check if a selfloop has already been inserted or not

  nodesSet.forEach(function(node) {
    // Nodes addition
    if (!graph.hasNode(node))
      throw new Error('graphology-utils/subgraph: the "' + node + '" node is not present in the graph.');
    subGraphResult.addNode(node, graph.getNodeAttributes(node));
  });

  nodesSet.forEach(function(node) {
    // Edges addition
    graph.forEachOutEdge(node, function(edge, attributes, source, target) {
      if (nodesSet.has(target)) {
        subGraphResult.importEdge(graph.exportEdge(edge));
      }
    });
    graph.forEachUndirectedEdge(node, function(
      edge,
      attributes,
      source,
      target
    ) {
      if (source !== node) {
        var tmp = source;
        source = target;
        target = tmp;
      }
      if (nodesSet.has(target)) {
        if (source === target && !insertedSelfloops.has(edge)) {
          subGraphResult.importEdge(graph.exportEdge(edge));
          insertedSelfloops.add(edge);
        }
        else if (source > target) {
          subGraphResult.importEdge(graph.exportEdge(edge));
        }
      }
    });
  });

  return subGraphResult;
};
