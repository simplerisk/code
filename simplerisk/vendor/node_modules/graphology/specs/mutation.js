"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports["default"] = mutation;
var _assert = _interopRequireDefault(require("assert"));
var _helpers = require("./helpers");
function _interopRequireDefault(e) { return e && e.__esModule ? e : { "default": e }; }
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function ownKeys(e, r) { var t = Object.keys(e); if (Object.getOwnPropertySymbols) { var o = Object.getOwnPropertySymbols(e); r && (o = o.filter(function (r) { return Object.getOwnPropertyDescriptor(e, r).enumerable; })), t.push.apply(t, o); } return t; }
function _objectSpread(e) { for (var r = 1; r < arguments.length; r++) { var t = null != arguments[r] ? arguments[r] : {}; r % 2 ? ownKeys(Object(t), !0).forEach(function (r) { _defineProperty(e, r, t[r]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(e, Object.getOwnPropertyDescriptors(t)) : ownKeys(Object(t)).forEach(function (r) { Object.defineProperty(e, r, Object.getOwnPropertyDescriptor(t, r)); }); } return e; }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); } /**
 * Graphology Mutation Specs
 * ==========================
 *
 * Testing the mutation methods of the graph.
 */
function mutation(Graph, checkers) {
  var invalid = checkers.invalid,
    notFound = checkers.notFound,
    usage = checkers.usage;
  return {
    '#.addNode': {
      'it should throw if given attributes is not an object.': function it_should_throw_if_given_attributes_is_not_an_object() {
        var graph = new Graph();
        _assert["default"]["throws"](function () {
          graph.addNode('test', true);
        }, invalid());
      },
      'it should throw if the given node already exist.': function it_should_throw_if_the_given_node_already_exist() {
        var graph = new Graph();
        graph.addNode('Martha');
        _assert["default"]["throws"](function () {
          graph.addNode('Martha');
        }, usage());
      },
      'it should return the added node.': function it_should_return_the_added_node() {
        var graph = new Graph();
        _assert["default"].strictEqual(graph.addNode('John'), 'John');
      }
    },
    '#.mergeNode': {
      'it should add the node if it does not exist yet.': function it_should_add_the_node_if_it_does_not_exist_yet() {
        var graph = new Graph();
        graph.mergeNode('John');
        _assert["default"].deepStrictEqual(graph.nodes(), ['John']);
      },
      'it should do nothing if the node already exists.': function it_should_do_nothing_if_the_node_already_exists() {
        var graph = new Graph();
        graph.addNode('John');
        graph.mergeNode('John');
        _assert["default"].deepStrictEqual(graph.nodes(), ['John']);
      },
      'it should merge the attributes.': function it_should_merge_the_attributes() {
        var graph = new Graph();
        graph.addNode('John', {
          eyes: 'blue'
        });
        graph.mergeNode('John', {
          age: 15
        });
        _assert["default"].deepStrictEqual(graph.nodes(), ['John']);
        _assert["default"].deepStrictEqual(graph.getNodeAttributes('John'), {
          eyes: 'blue',
          age: 15
        });
      },
      'it should coerce keys to string.': function it_should_coerce_keys_to_string() {
        var graph = new Graph();
        graph.addNode(4);
        _assert["default"].doesNotThrow(function () {
          return graph.mergeNode(4);
        });
      },
      'it should return useful information.': function it_should_return_useful_information() {
        var graph = new Graph();
        var _graph$mergeNode = graph.mergeNode('Jack'),
          key = _graph$mergeNode[0],
          wasAdded = _graph$mergeNode[1];
        _assert["default"].strictEqual(key, 'Jack');
        _assert["default"].strictEqual(wasAdded, true);
        var _graph$mergeNode2 = graph.mergeNode('Jack');
        key = _graph$mergeNode2[0];
        wasAdded = _graph$mergeNode2[1];
        _assert["default"].strictEqual(key, 'Jack');
        _assert["default"].strictEqual(wasAdded, false);
      }
    },
    '#.updateNode': {
      'it should add the node if it does not exist yet.': function it_should_add_the_node_if_it_does_not_exist_yet() {
        var graph = new Graph();
        graph.updateNode('John');
        _assert["default"].deepStrictEqual(graph.nodes(), ['John']);
      },
      'it should do nothing if the node already exists.': function it_should_do_nothing_if_the_node_already_exists() {
        var graph = new Graph();
        graph.addNode('John');
        graph.updateNode('John');
        _assert["default"].deepStrictEqual(graph.nodes(), ['John']);
      },
      'it should update the attributes.': function it_should_update_the_attributes() {
        var graph = new Graph();
        graph.addNode('John', {
          eyes: 'blue',
          count: 1
        });
        graph.updateNode('John', function (attr) {
          return _objectSpread(_objectSpread({}, attr), {}, {
            count: attr.count + 1
          });
        });
        _assert["default"].deepStrictEqual(graph.nodes(), ['John']);
        _assert["default"].deepStrictEqual(graph.getNodeAttributes('John'), {
          eyes: 'blue',
          count: 2
        });
      },
      'it should be possible to start from blank attributes.': function it_should_be_possible_to_start_from_blank_attributes() {
        var graph = new Graph();
        graph.updateNode('John', function () {
          return {
            count: 2
          };
        });
        _assert["default"].deepStrictEqual(graph.getNodeAttributes('John'), {
          count: 2
        });
      },
      'it should coerce keys to string.': function it_should_coerce_keys_to_string() {
        var graph = new Graph();
        graph.addNode(4);
        _assert["default"].doesNotThrow(function () {
          return graph.updateNode(4);
        });
      },
      'it should return useful information.': function it_should_return_useful_information() {
        var graph = new Graph();
        var _graph$updateNode = graph.updateNode('Jack'),
          key = _graph$updateNode[0],
          wasAdded = _graph$updateNode[1];
        _assert["default"].strictEqual(key, 'Jack');
        _assert["default"].strictEqual(wasAdded, true);
        var _graph$updateNode2 = graph.updateNode('Jack');
        key = _graph$updateNode2[0];
        wasAdded = _graph$updateNode2[1];
        _assert["default"].strictEqual(key, 'Jack');
        _assert["default"].strictEqual(wasAdded, false);
      }
    },
    '#.addDirectedEdge': {
      'it should throw if given attributes is not an object.': function it_should_throw_if_given_attributes_is_not_an_object() {
        var graph = new Graph();
        _assert["default"]["throws"](function () {
          graph.addDirectedEdge('source', 'target', true);
        }, invalid());
      },
      'it should throw if the graph is undirected.': function it_should_throw_if_the_graph_is_undirected() {
        var graph = new Graph({
          type: 'undirected'
        });
        _assert["default"]["throws"](function () {
          graph.addDirectedEdge('source', 'target');
        }, usage());
      },
      'it should throw if either the source or the target does not exist.': function it_should_throw_if_either_the_source_or_the_target_does_not_exist() {
        var graph = new Graph();
        graph.addNode('Martha');
        _assert["default"]["throws"](function () {
          graph.addDirectedEdge('Thomas', 'Eric');
        }, notFound());
        _assert["default"]["throws"](function () {
          graph.addDirectedEdge('Martha', 'Eric');
        }, notFound());
      },
      'it should throw if the edge is a loop and the graph does not allow it.': function it_should_throw_if_the_edge_is_a_loop_and_the_graph_does_not_allow_it() {
        var graph = new Graph({
          allowSelfLoops: false
        });
        graph.addNode('Thomas');
        _assert["default"]["throws"](function () {
          graph.addDirectedEdge('Thomas', 'Thomas');
        }, usage());
      },
      'it should be possible to add self loops.': function it_should_be_possible_to_add_self_loops() {
        var graph = new Graph();
        graph.addNode('Thomas');
        var loop = graph.addDirectedEdge('Thomas', 'Thomas');
        _assert["default"].deepStrictEqual(graph.extremities(loop), ['Thomas', 'Thomas']);
      },
      'it should throw if the graph is not multi & we try to add twice the same edge.': function it_should_throw_if_the_graph_is_not_multi__we_try_to_add_twice_the_same_edge() {
        var graph = new Graph();
        graph.addNode('Thomas');
        graph.addNode('Martha');
        graph.addDirectedEdge('Thomas', 'Martha');
        _assert["default"]["throws"](function () {
          graph.addDirectedEdge('Thomas', 'Martha');
        }, usage());
        _assert["default"]["throws"](function () {
          graph.addDirectedEdgeWithKey('T->M', 'Thomas', 'Martha');
        }, usage());
      },
      "it should return the generated edge's key.": function it_should_return_the_generated_edgeS_key() {
        var graph = new Graph();
        graph.addNode('Thomas');
        graph.addNode('Martha');
        var edge = graph.addDirectedEdge('Thomas', 'Martha');
        (0, _assert["default"])(typeof edge === 'string' || typeof edge === 'number');
        (0, _assert["default"])(!(edge instanceof Graph));
      }
    },
    '#.addEdge': {
      'it should add a directed edge if the graph is directed or mixed.': function it_should_add_a_directed_edge_if_the_graph_is_directed_or_mixed() {
        var graph = new Graph(),
          directedGraph = new Graph({
            type: 'directed'
          });
        graph.addNode('John');
        graph.addNode('Martha');
        var mixedEdge = graph.addEdge('John', 'Martha');
        directedGraph.addNode('John');
        directedGraph.addNode('Martha');
        var directedEdge = directedGraph.addEdge('John', 'Martha');
        (0, _assert["default"])(graph.isDirected(mixedEdge));
        (0, _assert["default"])(directedGraph.isDirected(directedEdge));
      },
      'it should add an undirected edge if the graph is undirected.': function it_should_add_an_undirected_edge_if_the_graph_is_undirected() {
        var graph = new Graph({
          type: 'undirected'
        });
        graph.addNode('John');
        graph.addNode('Martha');
        var edge = graph.addEdge('John', 'Martha');
        (0, _assert["default"])(graph.isUndirected(edge));
      }
    },
    '#.addDirectedEdgeWithKey': {
      'it should throw if an edge with the same key already exists.': function it_should_throw_if_an_edge_with_the_same_key_already_exists() {
        var graph = new Graph();
        graph.addNode('Thomas');
        graph.addNode('Martha');
        graph.addDirectedEdgeWithKey('T->M', 'Thomas', 'Martha');
        _assert["default"]["throws"](function () {
          graph.addDirectedEdgeWithKey('T->M', 'Thomas', 'Martha');
        }, usage());
        _assert["default"]["throws"](function () {
          graph.addUndirectedEdgeWithKey('T->M', 'Thomas', 'Martha');
        }, usage());
      }
    },
    '#.addUndirectedEdgeWithKey': {
      'it should throw if an edge with the same key already exists.': function it_should_throw_if_an_edge_with_the_same_key_already_exists() {
        var graph = new Graph();
        graph.addNode('Thomas');
        graph.addNode('Martha');
        graph.addUndirectedEdgeWithKey('T<->M', 'Thomas', 'Martha');
        _assert["default"]["throws"](function () {
          graph.addUndirectedEdgeWithKey('T<->M', 'Thomas', 'Martha');
        }, usage());
        _assert["default"]["throws"](function () {
          graph.addDirectedEdgeWithKey('T<->M', 'Thomas', 'Martha');
        }, usage());
      }
    },
    '#.addEdgeWithKey': {
      'it should add a directed edge if the graph is directed or mixed.': function it_should_add_a_directed_edge_if_the_graph_is_directed_or_mixed() {
        var graph = new Graph(),
          directedGraph = new Graph({
            type: 'directed'
          });
        graph.addNode('John');
        graph.addNode('Martha');
        var mixedEdge = graph.addEdgeWithKey('J->M', 'John', 'Martha');
        directedGraph.addNode('John');
        directedGraph.addNode('Martha');
        var directedEdge = directedGraph.addEdgeWithKey('J->M', 'John', 'Martha');
        (0, _assert["default"])(graph.isDirected(mixedEdge));
        (0, _assert["default"])(directedGraph.isDirected(directedEdge));
      },
      'it should add an undirected edge if the graph is undirected.': function it_should_add_an_undirected_edge_if_the_graph_is_undirected() {
        var graph = new Graph({
          type: 'undirected'
        });
        graph.addNode('John');
        graph.addNode('Martha');
        var edge = graph.addEdgeWithKey('J<->M', 'John', 'Martha');
        (0, _assert["default"])(graph.isUndirected(edge));
      }
    },
    '#.mergeEdge': {
      'it should add the edge if it does not yet exist.': function it_should_add_the_edge_if_it_does_not_yet_exist() {
        var graph = new Graph();
        (0, _helpers.addNodesFrom)(graph, ['John', 'Martha']);
        graph.mergeEdge('John', 'Martha');
        _assert["default"].strictEqual(graph.size, 1);
        _assert["default"].strictEqual(graph.hasEdge('John', 'Martha'), true);
      },
      'it should do nothing if the edge already exists.': function it_should_do_nothing_if_the_edge_already_exists() {
        var graph = new Graph();
        (0, _helpers.addNodesFrom)(graph, ['John', 'Martha']);
        graph.addEdge('John', 'Martha');
        graph.mergeEdge('John', 'Martha');
        _assert["default"].strictEqual(graph.size, 1);
        _assert["default"].strictEqual(graph.hasEdge('John', 'Martha'), true);
      },
      'it should merge existing attributes if any.': function it_should_merge_existing_attributes_if_any() {
        var graph = new Graph();
        (0, _helpers.addNodesFrom)(graph, ['John', 'Martha']);
        graph.addEdge('John', 'Martha', {
          type: 'KNOWS'
        });
        graph.mergeEdge('John', 'Martha', {
          weight: 2
        });
        _assert["default"].strictEqual(graph.size, 1);
        _assert["default"].strictEqual(graph.hasEdge('John', 'Martha'), true);
        _assert["default"].deepStrictEqual(graph.getEdgeAttributes('John', 'Martha'), {
          type: 'KNOWS',
          weight: 2
        });
      },
      'it should add missing nodes in the path.': function it_should_add_missing_nodes_in_the_path() {
        var graph = new Graph();
        graph.mergeEdge('John', 'Martha');
        _assert["default"].strictEqual(graph.order, 2);
        _assert["default"].strictEqual(graph.size, 1);
        _assert["default"].deepStrictEqual(graph.nodes(), ['John', 'Martha']);
      },
      'it should throw in case of inconsistencies.': function it_should_throw_in_case_of_inconsistencies() {
        var graph = new Graph();
        graph.mergeEdgeWithKey('J->M', 'John', 'Martha');
        _assert["default"]["throws"](function () {
          graph.mergeEdgeWithKey('J->M', 'John', 'Thomas');
        }, usage());
      },
      'it should be able to merge undirected edges in both directions.': function it_should_be_able_to_merge_undirected_edges_in_both_directions() {
        _assert["default"].doesNotThrow(function () {
          var graph = new Graph();
          graph.mergeUndirectedEdgeWithKey('J<->M', 'John', 'Martha');
          graph.mergeUndirectedEdgeWithKey('J<->M', 'John', 'Martha');
          graph.mergeUndirectedEdgeWithKey('J<->M', 'Martha', 'John');
        }, usage());
      },
      'it should distinguish between typed edges.': function it_should_distinguish_between_typed_edges() {
        var graph = new Graph();
        graph.mergeEdge('John', 'Martha', {
          type: 'LIKES'
        });
        graph.mergeUndirectedEdge('John', 'Martha', {
          weight: 34
        });
        _assert["default"].strictEqual(graph.size, 2);
      },
      'it should be possible to merge a self loop.': function it_should_be_possible_to_merge_a_self_loop() {
        var graph = new Graph();
        graph.mergeEdge('John', 'John', {
          type: 'IS'
        });
        _assert["default"].strictEqual(graph.order, 1);
        _assert["default"].strictEqual(graph.size, 1);
      },
      'it should return useful information.': function it_should_return_useful_information() {
        var graph = new Graph();
        var info = graph.mergeEdge('John', 'Jack');
        _assert["default"].deepStrictEqual(info, [graph.edge('John', 'Jack'), true, true, true]);
        info = graph.mergeEdge('John', 'Jack');
        _assert["default"].deepStrictEqual(info, [graph.edge('John', 'Jack'), false, false, false]);
        graph.addNode('Mary');
        info = graph.mergeEdge('Mary', 'Sue');
        _assert["default"].deepStrictEqual(info, [graph.edge('Mary', 'Sue'), true, false, true]);
        info = graph.mergeEdge('Gwladys', 'Mary');
        _assert["default"].deepStrictEqual(info, [graph.edge('Gwladys', 'Mary'), true, true, false]);
        graph.addNode('Quintin');
        info = graph.mergeEdge('Quintin', 'Mary');
        _assert["default"].deepStrictEqual(info, [graph.edge('Quintin', 'Mary'), true, false, false]);
      }
    },
    '#.updateEdge': {
      'it should add the edge if it does not yet exist.': function it_should_add_the_edge_if_it_does_not_yet_exist() {
        var graph = new Graph();
        (0, _helpers.addNodesFrom)(graph, ['John', 'Martha']);
        graph.updateEdge('John', 'Martha');
        _assert["default"].strictEqual(graph.size, 1);
        _assert["default"].strictEqual(graph.hasEdge('John', 'Martha'), true);
      },
      'it should do nothing if the edge already exists.': function it_should_do_nothing_if_the_edge_already_exists() {
        var graph = new Graph();
        (0, _helpers.addNodesFrom)(graph, ['John', 'Martha']);
        graph.addEdge('John', 'Martha');
        graph.updateEdge('John', 'Martha');
        _assert["default"].strictEqual(graph.size, 1);
        _assert["default"].strictEqual(graph.hasEdge('John', 'Martha'), true);
      },
      'it should be possible to start from blank attributes.': function it_should_be_possible_to_start_from_blank_attributes() {
        var graph = new Graph();
        (0, _helpers.addNodesFrom)(graph, ['John', 'Martha']);
        graph.updateEdge('John', 'Martha', function (attr) {
          return _objectSpread(_objectSpread({}, attr), {}, {
            weight: 3
          });
        });
        _assert["default"].strictEqual(graph.size, 1);
        _assert["default"].strictEqual(graph.hasEdge('John', 'Martha'), true);
        _assert["default"].deepStrictEqual(graph.getEdgeAttributes('John', 'Martha'), {
          weight: 3
        });
      },
      'it should update existing attributes if any.': function it_should_update_existing_attributes_if_any() {
        var graph = new Graph();
        (0, _helpers.addNodesFrom)(graph, ['John', 'Martha']);
        graph.addEdge('John', 'Martha', {
          type: 'KNOWS'
        });
        graph.updateEdge('John', 'Martha', function (attr) {
          return _objectSpread(_objectSpread({}, attr), {}, {
            weight: 2
          });
        });
        _assert["default"].strictEqual(graph.size, 1);
        _assert["default"].strictEqual(graph.hasEdge('John', 'Martha'), true);
        _assert["default"].deepStrictEqual(graph.getEdgeAttributes('John', 'Martha'), {
          type: 'KNOWS',
          weight: 2
        });
      },
      'it should add missing nodes in the path.': function it_should_add_missing_nodes_in_the_path() {
        var graph = new Graph();
        graph.updateEdge('John', 'Martha');
        _assert["default"].strictEqual(graph.order, 2);
        _assert["default"].strictEqual(graph.size, 1);
        _assert["default"].deepStrictEqual(graph.nodes(), ['John', 'Martha']);
      },
      'it should throw in case of inconsistencies.': function it_should_throw_in_case_of_inconsistencies() {
        var graph = new Graph();
        graph.updateEdgeWithKey('J->M', 'John', 'Martha');
        _assert["default"]["throws"](function () {
          graph.updateEdgeWithKey('J->M', 'John', 'Thomas');
        }, usage());
      },
      'it should distinguish between typed edges.': function it_should_distinguish_between_typed_edges() {
        var graph = new Graph();
        graph.updateEdge('John', 'Martha', function () {
          return {
            type: 'LIKES'
          };
        });
        graph.updateUndirectedEdge('John', 'Martha', function () {
          return {
            weight: 34
          };
        });
        _assert["default"].strictEqual(graph.size, 2);
      },
      'it should be possible to merge a self loop.': function it_should_be_possible_to_merge_a_self_loop() {
        var graph = new Graph();
        graph.updateEdge('John', 'John', function () {
          return {
            type: 'IS'
          };
        });
        _assert["default"].strictEqual(graph.order, 1);
        _assert["default"].strictEqual(graph.size, 1);
      },
      'it should return useful information.': function it_should_return_useful_information() {
        var graph = new Graph();
        var info = graph.updateEdge('John', 'Jack');
        _assert["default"].deepStrictEqual(info, [graph.edge('John', 'Jack'), true, true, true]);
        info = graph.updateEdge('John', 'Jack');
        _assert["default"].deepStrictEqual(info, [graph.edge('John', 'Jack'), false, false, false]);
        graph.addNode('Mary');
        info = graph.updateEdge('Mary', 'Sue');
        _assert["default"].deepStrictEqual(info, [graph.edge('Mary', 'Sue'), true, false, true]);
        info = graph.updateEdge('Gwladys', 'Mary');
        _assert["default"].deepStrictEqual(info, [graph.edge('Gwladys', 'Mary'), true, true, false]);
        graph.addNode('Quintin');
        info = graph.updateEdge('Quintin', 'Mary');
        _assert["default"].deepStrictEqual(info, [graph.edge('Quintin', 'Mary'), true, false, false]);
      }
    },
    '#.dropEdge': {
      'it should throw if the edge or nodes in the path are not found in the graph.': function it_should_throw_if_the_edge_or_nodes_in_the_path_are_not_found_in_the_graph() {
        var graph = new Graph();
        (0, _helpers.addNodesFrom)(graph, ['John', 'Martha']);
        _assert["default"]["throws"](function () {
          graph.dropEdge('Test');
        }, notFound());
        _assert["default"]["throws"](function () {
          graph.dropEdge('Forever', 'Alone');
        }, notFound());
        _assert["default"]["throws"](function () {
          graph.dropEdge('John', 'Test');
        }, notFound());
        _assert["default"]["throws"](function () {
          graph.dropEdge('John', 'Martha');
        }, notFound());
      },
      'it should correctly remove the given edge from the graph.': function it_should_correctly_remove_the_given_edge_from_the_graph() {
        var graph = new Graph();
        (0, _helpers.addNodesFrom)(graph, ['John', 'Margaret']);
        var edge = graph.addEdge('John', 'Margaret');
        graph.dropEdge(edge);
        _assert["default"].strictEqual(graph.order, 2);
        _assert["default"].strictEqual(graph.size, 0);
        _assert["default"].strictEqual(graph.degree('John'), 0);
        _assert["default"].strictEqual(graph.degree('Margaret'), 0);
        _assert["default"].strictEqual(graph.hasEdge(edge), false);
        _assert["default"].strictEqual(graph.hasDirectedEdge('John', 'Margaret'), false);
      },
      'it should be possible to remove an edge using source & target.': function it_should_be_possible_to_remove_an_edge_using_source__target() {
        var graph = new Graph();
        (0, _helpers.addNodesFrom)(graph, ['John', 'Margaret']);
        graph.addEdge('John', 'Margaret');
        graph.dropEdge('John', 'Margaret');
        _assert["default"].strictEqual(graph.order, 2);
        _assert["default"].strictEqual(graph.size, 0);
        _assert["default"].strictEqual(graph.degree('John'), 0);
        _assert["default"].strictEqual(graph.degree('Margaret'), 0);
        _assert["default"].strictEqual(graph.hasEdge('John', 'Margaret'), false);
        _assert["default"].strictEqual(graph.hasDirectedEdge('John', 'Margaret'), false);
      },
      'it should work with self loops.': function it_should_work_with_self_loops() {
        var graph = new Graph();
        graph.mergeEdge('John', 'John');
        graph.dropEdge('John', 'John');
        _assert["default"].deepStrictEqual(graph.edges(), []);
        _assert["default"].deepStrictEqual(graph.edges('John'), []);
        _assert["default"].strictEqual(graph.size, 0);
        var multiGraph = new Graph({
          multi: true
        });
        multiGraph.mergeEdgeWithKey('j', 'John', 'John');
        multiGraph.mergeEdgeWithKey('k', 'John', 'John');
        multiGraph.dropEdge('j');
        _assert["default"].deepStrictEqual(multiGraph.edges(), ['k']);
        _assert["default"].deepStrictEqual(multiGraph.edges('John'), ['k']);
        _assert["default"].strictEqual(multiGraph.size, 1);
      }
    },
    '#.dropNode': {
      'it should throw if the edge is not found in the graph.': function it_should_throw_if_the_edge_is_not_found_in_the_graph() {
        var graph = new Graph();
        _assert["default"]["throws"](function () {
          graph.dropNode('Test');
        }, notFound());
      },
      'it should correctly remove the given node from the graph.': function it_should_correctly_remove_the_given_node_from_the_graph() {
        var graph = new Graph();
        (0, _helpers.addNodesFrom)(graph, ['John', 'Margaret']);
        var edge = graph.addEdge('John', 'Margaret');
        graph.mergeEdge('Jack', 'Trudy');
        graph.dropNode('Margaret');
        _assert["default"].strictEqual(graph.order, 3);
        _assert["default"].strictEqual(graph.size, 1);
        _assert["default"].strictEqual(graph.hasNode('Margaret'), false);
        _assert["default"].strictEqual(graph.hasEdge(edge), false);
        _assert["default"].strictEqual(graph.degree('John'), 0);
        _assert["default"].strictEqual(graph.hasDirectedEdge('John', 'Margaret'), false);
      },
      'it should also work with mixed, multi graphs and self loops.': function it_should_also_work_with_mixed_multi_graphs_and_self_loops() {
        var graph = new Graph({
          multi: true
        });
        graph.mergeEdge('A', 'B');
        graph.mergeEdge('A', 'B');
        graph.mergeEdge('B', 'A');
        graph.mergeEdge('A', 'B');
        graph.mergeEdge('A', 'A');
        graph.mergeUndirectedEdge('A', 'B');
        graph.mergeUndirectedEdge('A', 'B');
        graph.mergeUndirectedEdge('A', 'A');
        var copy = graph.copy();
        graph.dropNode('B');
        _assert["default"].strictEqual(graph.size, 2);
        _assert["default"].strictEqual(graph.directedSelfLoopCount, 1);
        _assert["default"].strictEqual(graph.undirectedSelfLoopCount, 1);
        copy.dropNode('A');
        _assert["default"].strictEqual(copy.size, 0);
        _assert["default"].strictEqual(copy.directedSelfLoopCount, 0);
        _assert["default"].strictEqual(copy.undirectedSelfLoopCount, 0);
      },
      'it should also coerce keys as strings.': function it_should_also_coerce_keys_as_strings() {
        function Key(name) {
          this.name = name;
        }
        Key.prototype.toString = function () {
          return this.name;
        };
        var graph = new Graph();
        var key = new Key('test');
        graph.addNode(key);
        graph.dropNode(key);
        _assert["default"].strictEqual(graph.order, 0);
        _assert["default"].strictEqual(graph.hasNode(key), false);
      }
    },
    '#.dropDirectedEdge': {
      'it should throw if given incorrect arguments.': function it_should_throw_if_given_incorrect_arguments() {
        _assert["default"]["throws"](function () {
          var graph = new Graph({
            multi: true
          });
          graph.mergeEdge('a', 'b');
          graph.dropDirectedEdge('a', 'b');
        }, usage());
        _assert["default"]["throws"](function () {
          var graph = new Graph({
            multi: true
          });
          graph.mergeEdgeWithKey('1', 'a', 'b');
          graph.dropDirectedEdge('1');
        }, usage());
        _assert["default"]["throws"](function () {
          var graph = new Graph();
          graph.dropDirectedEdge('a', 'b');
        }, notFound());
      },
      'it should correctly drop the relevant edge.': function it_should_correctly_drop_the_relevant_edge() {
        var graph = new Graph();
        graph.mergeUndirectedEdge('a', 'b');
        graph.mergeDirectedEdge('a', 'b');
        graph.dropDirectedEdge('a', 'b');
        _assert["default"].strictEqual(graph.directedSize, 0);
        _assert["default"].strictEqual(graph.hasDirectedEdge('a', 'b'), false);
        _assert["default"].strictEqual(graph.hasUndirectedEdge('a', 'b'), true);
      }
    },
    '#.dropUndirectedEdge': {
      'it should throw if given incorrect arguments.': function it_should_throw_if_given_incorrect_arguments() {
        _assert["default"]["throws"](function () {
          var graph = new Graph({
            multi: true,
            type: 'undirected'
          });
          graph.mergeEdge('a', 'b');
          graph.dropUndirectedEdge('a', 'b');
        }, usage());
        _assert["default"]["throws"](function () {
          var graph = new Graph({
            multi: true,
            type: 'undirected'
          });
          graph.mergeEdgeWithKey('1', 'a', 'b');
          graph.dropUndirectedEdge('1');
        }, usage());
        _assert["default"]["throws"](function () {
          var graph = new Graph({
            type: 'undirected'
          });
          graph.dropUndirectedEdge('a', 'b');
        }, notFound());
      },
      'it should correctly drop the relevant edge.': function it_should_correctly_drop_the_relevant_edge() {
        var graph = new Graph();
        graph.mergeUndirectedEdge('a', 'b');
        graph.mergeDirectedEdge('a', 'b');
        graph.dropUndirectedEdge('a', 'b');
        _assert["default"].strictEqual(graph.undirectedSize, 0);
        _assert["default"].strictEqual(graph.hasUndirectedEdge('a', 'b'), false);
        _assert["default"].strictEqual(graph.hasDirectedEdge('a', 'b'), true);
      }
    },
    '#.clear': {
      'it should empty the graph.': function it_should_empty_the_graph() {
        var graph = new Graph();
        (0, _helpers.addNodesFrom)(graph, ['Lindsay', 'Martha']);
        var edge = graph.addEdge('Lindsay', 'Martha');
        graph.clear();
        _assert["default"].strictEqual(graph.order, 0);
        _assert["default"].strictEqual(graph.size, 0);
        _assert["default"].strictEqual(graph.hasNode('Lindsay'), false);
        _assert["default"].strictEqual(graph.hasNode('Martha'), false);
        _assert["default"].strictEqual(graph.hasEdge(edge), false);
      },
      'it should be possible to use the graph normally afterwards.': function it_should_be_possible_to_use_the_graph_normally_afterwards() {
        var graph = new Graph();
        (0, _helpers.addNodesFrom)(graph, ['Lindsay', 'Martha']);
        graph.addEdge('Lindsay', 'Martha');
        graph.clear();
        (0, _helpers.addNodesFrom)(graph, ['Lindsay', 'Martha']);
        var edge = graph.addEdge('Lindsay', 'Martha');
        _assert["default"].strictEqual(graph.order, 2);
        _assert["default"].strictEqual(graph.size, 1);
        _assert["default"].strictEqual(graph.hasNode('Lindsay'), true);
        _assert["default"].strictEqual(graph.hasNode('Martha'), true);
        _assert["default"].strictEqual(graph.hasEdge(edge), true);
      }
    },
    '#.clearEdges': {
      'it should drop every edge from the graph.': function it_should_drop_every_edge_from_the_graph() {
        var graph = new Graph();
        (0, _helpers.addNodesFrom)(graph, ['Lindsay', 'Martha']);
        var edge = graph.addEdge('Lindsay', 'Martha');
        graph.clearEdges();
        _assert["default"].strictEqual(graph.order, 2);
        _assert["default"].strictEqual(graph.size, 0);
        _assert["default"].strictEqual(graph.hasNode('Lindsay'), true);
        _assert["default"].strictEqual(graph.hasNode('Martha'), true);
        _assert["default"].strictEqual(graph.hasEdge(edge), false);
      },
      'it should properly reset instance counters.': function it_should_properly_reset_instance_counters() {
        var graph = new Graph();
        graph.mergeEdge(0, 1);
        _assert["default"].strictEqual(graph.directedSize, 1);
        graph.clearEdges();
        _assert["default"].strictEqual(graph.directedSize, 0);
        graph.mergeEdge(0, 1);
        graph.clear();
        _assert["default"].strictEqual(graph.directedSize, 0);
      },
      'it should properly clear node indices, regarding self loops notably.': function it_should_properly_clear_node_indices_regarding_self_loops_notably() {
        var graph = new Graph();
        graph.mergeEdge(1, 1);
        _assert["default"].strictEqual(graph.degree(1), 2);
        graph.clearEdges();
        _assert["default"].strictEqual(graph.degree(1), 0);
      }
    }
  };
}