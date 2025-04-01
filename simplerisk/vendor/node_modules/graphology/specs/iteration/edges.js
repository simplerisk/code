"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports["default"] = edgesIteration;
var _assert = _interopRequireDefault(require("assert"));
var _utils = require("../../src/utils");
var _helpers = require("../helpers");
function _interopRequireDefault(e) { return e && e.__esModule ? e : { "default": e }; }
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); } /**
 * Graphology Edges Iteration Specs
 * =================================
 *
 * Testing the edges iteration-related methods of the graph.
 */
var METHODS = ['edges', 'inEdges', 'outEdges', 'inboundEdges', 'outboundEdges', 'directedEdges', 'undirectedEdges'];
function edgesIteration(Graph, checkers) {
  var invalid = checkers.invalid,
    notFound = checkers.notFound;
  var graph = new Graph({
    multi: true
  });
  (0, _helpers.addNodesFrom)(graph, ['John', 'Thomas', 'Martha', 'Roger', 'Catherine', 'Alone', 'Forever']);
  graph.replaceNodeAttributes('John', {
    age: 13
  });
  graph.replaceNodeAttributes('Martha', {
    age: 15
  });
  graph.addDirectedEdgeWithKey('J->T', 'John', 'Thomas', {
    weight: 14
  });
  graph.addDirectedEdgeWithKey('J->M', 'John', 'Martha');
  graph.addDirectedEdgeWithKey('C->J', 'Catherine', 'John');
  graph.addUndirectedEdgeWithKey('M<->R', 'Martha', 'Roger');
  graph.addUndirectedEdgeWithKey('M<->J', 'Martha', 'John');
  graph.addUndirectedEdgeWithKey('J<->R', 'John', 'Roger');
  graph.addUndirectedEdgeWithKey('T<->M', 'Thomas', 'Martha');
  var ALL_EDGES = ['J->T', 'J->M', 'C->J', 'M<->R', 'M<->J', 'J<->R', 'T<->M'];
  var ALL_DIRECTED_EDGES = ['J->T', 'J->M', 'C->J'];
  var ALL_UNDIRECTED_EDGES = ['M<->R', 'M<->J', 'J<->R', 'T<->M'];
  var TEST_DATA = {
    edges: {
      all: ALL_EDGES,
      node: {
        key: 'John',
        edges: ['C->J', 'J->T', 'J->M', 'M<->J', 'J<->R']
      },
      path: {
        source: 'John',
        target: 'Martha',
        edges: ['J->M', 'M<->J']
      }
    },
    inEdges: {
      all: ALL_DIRECTED_EDGES,
      node: {
        key: 'John',
        edges: ['C->J']
      },
      path: {
        source: 'John',
        target: 'Martha',
        edges: []
      }
    },
    outEdges: {
      all: ALL_DIRECTED_EDGES,
      node: {
        key: 'John',
        edges: ['J->T', 'J->M']
      },
      path: {
        source: 'John',
        target: 'Martha',
        edges: ['J->M']
      }
    },
    inboundEdges: {
      all: ALL_DIRECTED_EDGES.concat(ALL_UNDIRECTED_EDGES),
      node: {
        key: 'John',
        edges: ['C->J', 'M<->J', 'J<->R']
      },
      path: {
        source: 'John',
        target: 'Martha',
        edges: ['M<->J']
      }
    },
    outboundEdges: {
      all: ALL_DIRECTED_EDGES.concat(ALL_UNDIRECTED_EDGES),
      node: {
        key: 'John',
        edges: ['J->T', 'J->M', 'M<->J', 'J<->R']
      },
      path: {
        source: 'John',
        target: 'Martha',
        edges: ['J->M', 'M<->J']
      }
    },
    directedEdges: {
      all: ALL_DIRECTED_EDGES,
      node: {
        key: 'John',
        edges: ['C->J', 'J->T', 'J->M']
      },
      path: {
        source: 'John',
        target: 'Martha',
        edges: ['J->M']
      }
    },
    undirectedEdges: {
      all: ALL_UNDIRECTED_EDGES,
      node: {
        key: 'John',
        edges: ['M<->J', 'J<->R']
      },
      path: {
        source: 'John',
        target: 'Martha',
        edges: ['M<->J']
      }
    }
  };
  function commonTests(name) {
    return _defineProperty({}, '#.' + name, {
      'it should throw if too many arguments are provided.': function it_should_throw_if_too_many_arguments_are_provided() {
        _assert["default"]["throws"](function () {
          graph[name](1, 2, 3);
        }, invalid());
      },
      'it should throw when the node is not found.': function it_should_throw_when_the_node_is_not_found() {
        _assert["default"]["throws"](function () {
          graph[name]('Test');
        }, notFound());
      },
      'it should throw if either source or target is not found.': function it_should_throw_if_either_source_or_target_is_not_found() {
        _assert["default"]["throws"](function () {
          graph[name]('Test', 'Alone');
        }, notFound());
        _assert["default"]["throws"](function () {
          graph[name]('Alone', 'Test');
        }, notFound());
      }
    });
  }
  function specificTests(name, data) {
    var capitalized = name[0].toUpperCase() + name.slice(1, -1);
    var iteratorName = name.slice(0, -1) + 'Entries';
    var forEachName = 'forEach' + capitalized;
    var findName = 'find' + capitalized;
    var mapName = 'map' + capitalized + 's';
    var filterName = 'filter' + capitalized + 's';
    var reduceName = 'reduce' + capitalized + 's';
    var someName = 'some' + capitalized;
    var everyName = 'every' + capitalized;
    return _defineProperty(_defineProperty(_defineProperty(_defineProperty(_defineProperty(_defineProperty(_defineProperty(_defineProperty(_defineProperty({}, '#.' + name, {
      'it should return all the relevant edges.': function it_should_return_all_the_relevant_edges() {
        var edges = graph[name]().sort();
        _assert["default"].deepStrictEqual(edges, data.all.slice().sort());
      },
      "it should return a node's relevant edges.": function it_should_return_a_nodeS_relevant_edges() {
        var edges = graph[name](data.node.key);
        _assert["default"].deepStrictEqual(edges, data.node.edges);
        _assert["default"].deepStrictEqual(graph[name]('Alone'), []);
      },
      'it should return all the relevant edges between source & target.': function it_should_return_all_the_relevant_edges_between_source__target() {
        var edges = graph[name](data.path.source, data.path.target);
        (0, _assert["default"])((0, _helpers.sameMembers)(edges, data.path.edges));
        _assert["default"].deepStrictEqual(graph[name]('Forever', 'Alone'), []);
      }
    }), '#.' + forEachName, {
      'it should possible to use callback iterators.': function it_should_possible_to_use_callback_iterators() {
        var edges = [];
        graph[forEachName](function (key, attributes, source, target, sA, tA, u) {
          edges.push(key);
          _assert["default"].deepStrictEqual(attributes, key === 'J->T' ? {
            weight: 14
          } : {});
          _assert["default"].strictEqual(source, graph.source(key));
          _assert["default"].strictEqual(target, graph.target(key));
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(source), sA);
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(target), tA);
          _assert["default"].strictEqual(graph.isUndirected(key), u);
        });
        edges.sort();
        _assert["default"].deepStrictEqual(edges, data.all.slice().sort());
      },
      "it should be possible to use callback iterators over a node's relevant edges.": function it_should_be_possible_to_use_callback_iterators_over_a_nodeS_relevant_edges() {
        var edges = [];
        graph[forEachName](data.node.key, function (key, attributes, source, target, sA, tA, u) {
          edges.push(key);
          _assert["default"].deepStrictEqual(attributes, key === 'J->T' ? {
            weight: 14
          } : {});
          _assert["default"].strictEqual(source, graph.source(key));
          _assert["default"].strictEqual(target, graph.target(key));
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(source), sA);
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(target), tA);
          _assert["default"].strictEqual(graph.isUndirected(key), u);
        });
        edges.sort();
        _assert["default"].deepStrictEqual(edges, data.node.edges.slice().sort());
      },
      'it should be possible to use callback iterators over all the relevant edges between source & target.': function it_should_be_possible_to_use_callback_iterators_over_all_the_relevant_edges_between_source__target() {
        var edges = [];
        graph[forEachName](data.path.source, data.path.target, function (key, attributes, source, target, sA, tA, u) {
          edges.push(key);
          _assert["default"].deepStrictEqual(attributes, key === 'J->T' ? {
            weight: 14
          } : {});
          _assert["default"].strictEqual(source, graph.source(key));
          _assert["default"].strictEqual(target, graph.target(key));
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(source), sA);
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(target), tA);
          _assert["default"].strictEqual(graph.isUndirected(key), u);
        });
        (0, _assert["default"])((0, _helpers.sameMembers)(edges, data.path.edges));
      }
    }), '#.' + mapName, {
      'it should possible to map edges.': function it_should_possible_to_map_edges() {
        var result = graph[mapName](function (key) {
          return key;
        });
        result.sort();
        _assert["default"].deepStrictEqual(result, data.all.slice().sort());
      },
      "it should be possible to map a node's relevant edges.": function it_should_be_possible_to_map_a_nodeS_relevant_edges() {
        var result = graph[mapName](data.node.key, function (key) {
          return key;
        });
        result.sort();
        _assert["default"].deepStrictEqual(result, data.node.edges.slice().sort());
      },
      'it should be possible to map the relevant edges between source & target.': function it_should_be_possible_to_map_the_relevant_edges_between_source__target() {
        var result = graph[mapName](data.path.source, data.path.target, function (key) {
          return key;
        });
        result.sort();
        (0, _assert["default"])((0, _helpers.sameMembers)(result, data.path.edges));
      }
    }), '#.' + filterName, {
      'it should possible to filter edges.': function it_should_possible_to_filter_edges() {
        var result = graph[filterName](function (key) {
          return data.all.includes(key);
        });
        result.sort();
        _assert["default"].deepStrictEqual(result, data.all.slice().sort());
      },
      "it should be possible to filter a node's relevant edges.": function it_should_be_possible_to_filter_a_nodeS_relevant_edges() {
        var result = graph[filterName](data.node.key, function (key) {
          return data.all.includes(key);
        });
        result.sort();
        _assert["default"].deepStrictEqual(result, data.node.edges.slice().sort());
      },
      'it should be possible to filter the relevant edges between source & target.': function it_should_be_possible_to_filter_the_relevant_edges_between_source__target() {
        var result = graph[filterName](data.path.source, data.path.target, function (key) {
          return data.all.includes(key);
        });
        result.sort();
        (0, _assert["default"])((0, _helpers.sameMembers)(result, data.path.edges));
      }
    }), '#.' + reduceName, {
      'it should throw when given bad arguments.': function it_should_throw_when_given_bad_arguments() {
        _assert["default"]["throws"](function () {
          graph[reduceName]('test');
        }, invalid());
        _assert["default"]["throws"](function () {
          graph[reduceName](1, 2, 3, 4, 5);
        }, invalid());
        _assert["default"]["throws"](function () {
          graph[reduceName]('notafunction', 0);
        }, TypeError);
        _assert["default"]["throws"](function () {
          graph[reduceName]('test', function () {
            return true;
          });
        }, invalid());
      },
      'it should possible to reduce edges.': function it_should_possible_to_reduce_edges() {
        var result = graph[reduceName](function (x) {
          return x + 1;
        }, 0);
        _assert["default"].strictEqual(result, data.all.length);
      },
      "it should be possible to reduce a node's relevant edges.": function it_should_be_possible_to_reduce_a_nodeS_relevant_edges() {
        var result = graph[reduceName](data.node.key, function (x) {
          return x + 1;
        }, 0);
        _assert["default"].strictEqual(result, data.node.edges.length);
      },
      'it should be possible to reduce the relevant edges between source & target.': function it_should_be_possible_to_reduce_the_relevant_edges_between_source__target() {
        var result = graph[reduceName](data.path.source, data.path.target, function (x) {
          return x + 1;
        }, 0);
        _assert["default"].strictEqual(result, data.path.edges.length);
      }
    }), '#.' + findName, {
      'it should possible to find an edge.': function it_should_possible_to_find_an_edge() {
        var edges = [];
        var found = graph[findName](function (key, attributes, source, target, sA, tA, u) {
          edges.push(key);
          _assert["default"].deepStrictEqual(attributes, key === 'J->T' ? {
            weight: 14
          } : {});
          _assert["default"].strictEqual(source, graph.source(key));
          _assert["default"].strictEqual(target, graph.target(key));
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(source), sA);
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(target), tA);
          _assert["default"].strictEqual(graph.isUndirected(key), u);
          return true;
        });
        _assert["default"].strictEqual(found, edges[0]);
        _assert["default"].strictEqual(edges.length, 1);
        found = graph[findName](function () {
          return false;
        });
        _assert["default"].strictEqual(found, undefined);
      },
      "it should be possible to find a node's edge.": function it_should_be_possible_to_find_a_nodeS_edge() {
        var edges = [];
        var found = graph[findName](data.node.key, function (key, attributes, source, target, sA, tA, u) {
          edges.push(key);
          _assert["default"].deepStrictEqual(attributes, key === 'J->T' ? {
            weight: 14
          } : {});
          _assert["default"].strictEqual(source, graph.source(key));
          _assert["default"].strictEqual(target, graph.target(key));
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(source), sA);
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(target), tA);
          _assert["default"].strictEqual(graph.isUndirected(key), u);
          return true;
        });
        _assert["default"].strictEqual(found, edges[0]);
        _assert["default"].strictEqual(edges.length, 1);
        found = graph[findName](data.node.key, function () {
          return false;
        });
        _assert["default"].strictEqual(found, undefined);
      },
      'it should be possible to find an edge between source & target.': function it_should_be_possible_to_find_an_edge_between_source__target() {
        var edges = [];
        var found = graph[findName](data.path.source, data.path.target, function (key, attributes, source, target, sA, tA, u) {
          edges.push(key);
          _assert["default"].deepStrictEqual(attributes, key === 'J->T' ? {
            weight: 14
          } : {});
          _assert["default"].strictEqual(source, graph.source(key));
          _assert["default"].strictEqual(target, graph.target(key));
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(source), sA);
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(target), tA);
          _assert["default"].strictEqual(graph.isUndirected(key), u);
          return true;
        });
        _assert["default"].strictEqual(found, edges[0]);
        _assert["default"].strictEqual(edges.length, graph[name](data.path.source, data.path.target).length ? 1 : 0);
        found = graph[findName](data.path.source, data.path.target, function () {
          return false;
        });
        _assert["default"].strictEqual(found, undefined);
      }
    }), '#.' + someName, {
      'it should possible to assert whether any edge matches a predicate.': function it_should_possible_to_assert_whether_any_edge_matches_a_predicate() {
        var edges = [];
        var found = graph[someName](function (key, attributes, source, target, sA, tA, u) {
          edges.push(key);
          _assert["default"].deepStrictEqual(attributes, key === 'J->T' ? {
            weight: 14
          } : {});
          _assert["default"].strictEqual(source, graph.source(key));
          _assert["default"].strictEqual(target, graph.target(key));
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(source), sA);
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(target), tA);
          _assert["default"].strictEqual(graph.isUndirected(key), u);
          return true;
        });
        _assert["default"].strictEqual(found, true);
        _assert["default"].strictEqual(edges.length, 1);
        found = graph[someName](function () {
          return false;
        });
        _assert["default"].strictEqual(found, false);
      },
      "it should possible to assert whether any node's edge matches a predicate.": function it_should_possible_to_assert_whether_any_nodeS_edge_matches_a_predicate() {
        var edges = [];
        var found = graph[someName](data.node.key, function (key, attributes, source, target, sA, tA, u) {
          edges.push(key);
          _assert["default"].deepStrictEqual(attributes, key === 'J->T' ? {
            weight: 14
          } : {});
          _assert["default"].strictEqual(source, graph.source(key));
          _assert["default"].strictEqual(target, graph.target(key));
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(source), sA);
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(target), tA);
          _assert["default"].strictEqual(graph.isUndirected(key), u);
          return true;
        });
        _assert["default"].strictEqual(found, true);
        _assert["default"].strictEqual(edges.length, 1);
        found = graph[someName](data.node.key, function () {
          return false;
        });
        _assert["default"].strictEqual(found, false);
      },
      'it should possible to assert whether any edge between source & target matches a predicate.': function it_should_possible_to_assert_whether_any_edge_between_source__target_matches_a_predicate() {
        var edges = [];
        var found = graph[someName](data.path.source, data.path.target, function (key, attributes, source, target, sA, tA, u) {
          edges.push(key);
          _assert["default"].deepStrictEqual(attributes, key === 'J->T' ? {
            weight: 14
          } : {});
          _assert["default"].strictEqual(source, graph.source(key));
          _assert["default"].strictEqual(target, graph.target(key));
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(source), sA);
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(target), tA);
          _assert["default"].strictEqual(graph.isUndirected(key), u);
          return true;
        });
        _assert["default"].strictEqual(found, graph[name](data.path.source, data.path.target).length !== 0);
        _assert["default"].strictEqual(edges.length, graph[name](data.path.source, data.path.target).length ? 1 : 0);
        found = graph[someName](data.path.source, data.path.target, function () {
          return false;
        });
        _assert["default"].strictEqual(found, false);
      },
      'it should always return false on empty sets.': function it_should_always_return_false_on_empty_sets() {
        var empty = new Graph();
        _assert["default"].strictEqual(empty[someName](function () {
          return true;
        }), false);
      }
    }), '#.' + everyName, {
      'it should possible to assert whether all edges matches a predicate.': function it_should_possible_to_assert_whether_all_edges_matches_a_predicate() {
        var edges = [];
        var found = graph[everyName](function (key, attributes, source, target, sA, tA, u) {
          edges.push(key);
          _assert["default"].deepStrictEqual(attributes, key === 'J->T' ? {
            weight: 14
          } : {});
          _assert["default"].strictEqual(source, graph.source(key));
          _assert["default"].strictEqual(target, graph.target(key));
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(source), sA);
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(target), tA);
          _assert["default"].strictEqual(graph.isUndirected(key), u);
          return true;
        });
        _assert["default"].strictEqual(found, true);
        found = graph[everyName](function () {
          return false;
        });
        _assert["default"].strictEqual(found, false);
      },
      "it should possible to assert whether all of a node's edges matches a predicate.": function it_should_possible_to_assert_whether_all_of_a_nodeS_edges_matches_a_predicate() {
        var edges = [];
        var found = graph[everyName](data.node.key, function (key, attributes, source, target, sA, tA, u) {
          edges.push(key);
          _assert["default"].deepStrictEqual(attributes, key === 'J->T' ? {
            weight: 14
          } : {});
          _assert["default"].strictEqual(source, graph.source(key));
          _assert["default"].strictEqual(target, graph.target(key));
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(source), sA);
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(target), tA);
          _assert["default"].strictEqual(graph.isUndirected(key), u);
          return true;
        });
        _assert["default"].strictEqual(found, true);
        found = graph[everyName](data.node.key, function () {
          return false;
        });
        _assert["default"].strictEqual(found, false);
      },
      'it should possible to assert whether all edges between source & target matches a predicate.': function it_should_possible_to_assert_whether_all_edges_between_source__target_matches_a_predicate() {
        var edges = [];
        var found = graph[everyName](data.path.source, data.path.target, function (key, attributes, source, target, sA, tA, u) {
          edges.push(key);
          _assert["default"].deepStrictEqual(attributes, key === 'J->T' ? {
            weight: 14
          } : {});
          _assert["default"].strictEqual(source, graph.source(key));
          _assert["default"].strictEqual(target, graph.target(key));
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(source), sA);
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(target), tA);
          _assert["default"].strictEqual(graph.isUndirected(key), u);
          return true;
        });
        var isEmpty = graph[name](data.path.source, data.path.target).length === 0;
        _assert["default"].strictEqual(found, true);
        found = graph[everyName](data.path.source, data.path.target, function () {
          return false;
        });
        _assert["default"].strictEqual(found, isEmpty ? true : false);
      },
      'it should always return true on empty sets.': function it_should_always_return_true_on_empty_sets() {
        var empty = new Graph();
        _assert["default"].strictEqual(empty[everyName](function () {
          return true;
        }), true);
      }
    }), '#.' + iteratorName, {
      'it should be possible to return an iterator over the relevant edges.': function it_should_be_possible_to_return_an_iterator_over_the_relevant_edges() {
        var iterator = graph[iteratorName]();
        _assert["default"].deepStrictEqual(Array.from(iterator), data.all.map(function (edge) {
          var _graph$extremities = graph.extremities(edge),
            source = _graph$extremities[0],
            target = _graph$extremities[1];
          return {
            edge: edge,
            attributes: graph.getEdgeAttributes(edge),
            source: source,
            target: target,
            sourceAttributes: graph.getNodeAttributes(source),
            targetAttributes: graph.getNodeAttributes(target),
            undirected: graph.isUndirected(edge)
          };
        }));
      },
      "it should be possible to return an iterator over a node's relevant edges.": function it_should_be_possible_to_return_an_iterator_over_a_nodeS_relevant_edges() {
        var iterator = graph[iteratorName](data.node.key);
        _assert["default"].deepStrictEqual(Array.from(iterator), data.node.edges.map(function (edge) {
          var _graph$extremities2 = graph.extremities(edge),
            source = _graph$extremities2[0],
            target = _graph$extremities2[1];
          return {
            edge: edge,
            attributes: graph.getEdgeAttributes(edge),
            source: source,
            target: target,
            sourceAttributes: graph.getNodeAttributes(source),
            targetAttributes: graph.getNodeAttributes(target),
            undirected: graph.isUndirected(edge)
          };
        }));
      },
      'it should be possible to return an iterator over relevant edges between source & target.': function it_should_be_possible_to_return_an_iterator_over_relevant_edges_between_source__target() {
        var iterator = graph[iteratorName](data.path.source, data.path.target);
        _assert["default"].deepStrictEqual(Array.from(iterator), data.path.edges.map(function (edge) {
          var _graph$extremities3 = graph.extremities(edge),
            source = _graph$extremities3[0],
            target = _graph$extremities3[1];
          return {
            edge: edge,
            attributes: graph.getEdgeAttributes(edge),
            source: source,
            target: target,
            sourceAttributes: graph.getNodeAttributes(source),
            targetAttributes: graph.getNodeAttributes(target),
            undirected: graph.isUndirected(edge)
          };
        }));
      }
    });
  }
  var tests = {
    Miscellaneous: {
      'simple graph indices should work.': function simple_graph_indices_should_work() {
        var simpleGraph = new Graph();
        (0, _helpers.addNodesFrom)(simpleGraph, [1, 2, 3, 4]);
        simpleGraph.addEdgeWithKey('1->2', 1, 2);
        simpleGraph.addEdgeWithKey('1->3', 1, 3);
        simpleGraph.addEdgeWithKey('1->4', 1, 4);
        _assert["default"].deepStrictEqual(simpleGraph.edges(1), ['1->2', '1->3', '1->4']);
      },
      'it should also work with typed graphs.': function it_should_also_work_with_typed_graphs() {
        var undirected = new Graph({
            type: 'undirected'
          }),
          directed = new Graph({
            type: 'directed'
          });
        undirected.mergeEdgeWithKey('1--2', 1, 2);
        directed.mergeEdgeWithKey('1->2', 1, 2);
        _assert["default"].deepStrictEqual(undirected.edges(1, 2), ['1--2']);
        _assert["default"].deepStrictEqual(directed.edges(1, 2), ['1->2']);
      },
      'self loops should appear when using #.inEdges and should appear only once with #.edges.': function self_loops_should_appear_when_using_InEdges_and_should_appear_only_once_with_Edges() {
        var directed = new Graph({
          type: 'directed'
        });
        directed.addNode('Lucy');
        directed.addEdgeWithKey('Lucy', 'Lucy', 'Lucy');
        _assert["default"].deepStrictEqual(directed.inEdges('Lucy'), ['Lucy']);
        _assert["default"].deepStrictEqual(Array.from(directed.inEdgeEntries('Lucy')).map(function (x) {
          return x.edge;
        }), ['Lucy']);
        var edges = [];
        directed.forEachInEdge('Lucy', function (edge) {
          edges.push(edge);
        });
        _assert["default"].deepStrictEqual(edges, ['Lucy']);
        _assert["default"].deepStrictEqual(directed.edges('Lucy'), ['Lucy']);
        edges = [];
        directed.forEachEdge('Lucy', function (edge) {
          edges.push(edge);
        });
        _assert["default"].deepStrictEqual(edges, ['Lucy']);
        _assert["default"].deepStrictEqual(Array.from(directed.edgeEntries('Lucy')).map(function (x) {
          return x.edge;
        }), ['Lucy']);
      },
      'it should be possible to retrieve self loops.': function it_should_be_possible_to_retrieve_self_loops() {
        var loopy = new Graph();
        loopy.addNode('John');
        loopy.addEdgeWithKey('d', 'John', 'John');
        loopy.addUndirectedEdgeWithKey('u', 'John', 'John');
        _assert["default"].deepStrictEqual(new Set(loopy.edges('John', 'John')), new Set(['d', 'u']));
        _assert["default"].deepStrictEqual(loopy.directedEdges('John', 'John'), ['d']);
        _assert["default"].deepStrictEqual(loopy.undirectedEdges('John', 'John'), ['u']);
        var edges = [];
        loopy.forEachDirectedEdge('John', 'John', function (edge) {
          edges.push(edge);
        });
        _assert["default"].deepStrictEqual(edges, ['d']);
        edges = [];
        loopy.forEachUndirectedEdge('John', 'John', function (edge) {
          edges.push(edge);
        });
        _assert["default"].deepStrictEqual(edges, ['u']);
      },
      'self loops in multi graphs should work properly (#352).': function self_loops_in_multi_graphs_should_work_properly_352() {
        var loopy = new Graph({
          multi: true
        });
        loopy.addNode('n');
        loopy.addEdgeWithKey('e1', 'n', 'n');
        loopy.addEdgeWithKey('e2', 'n', 'n');
        loopy.addUndirectedEdgeWithKey('e3', 'n', 'n');

        // Arrays
        _assert["default"].deepStrictEqual(loopy.edges('n'), ['e2', 'e1', 'e3']);
        _assert["default"].deepStrictEqual(loopy.outboundEdges('n'), ['e2', 'e1', 'e3']);
        _assert["default"].deepStrictEqual(loopy.inboundEdges('n'), ['e2', 'e1', 'e3']);
        _assert["default"].deepStrictEqual(loopy.outEdges('n'), ['e2', 'e1']);
        _assert["default"].deepStrictEqual(loopy.inEdges('n'), ['e2', 'e1']);
        _assert["default"].deepStrictEqual(loopy.undirectedEdges('n'), ['e3']);
        _assert["default"].deepStrictEqual(loopy.directedEdges('n'), ['e2', 'e1']);
        _assert["default"].deepStrictEqual(loopy.edges('n', 'n'), ['e2', 'e1', 'e3']);
        _assert["default"].deepStrictEqual(loopy.outboundEdges('n', 'n'), ['e2', 'e1', 'e3']);
        _assert["default"].deepStrictEqual(loopy.inboundEdges('n', 'n'), ['e2', 'e1', 'e3']);
        _assert["default"].deepStrictEqual(loopy.outEdges('n', 'n'), ['e2', 'e1']);
        _assert["default"].deepStrictEqual(loopy.inEdges('n', 'n'), ['e2', 'e1']);
        _assert["default"].deepStrictEqual(loopy.undirectedEdges('n', 'n'), ['e3']);
        _assert["default"].deepStrictEqual(loopy.directedEdges('n', 'n'), ['e2', 'e1']);

        // Iterators
        var mapKeys = function mapKeys(it) {
          return Array.from((0, _utils.map)(it, function (e) {
            return e.edge;
          }));
        };
        _assert["default"].deepStrictEqual(mapKeys(loopy.edgeEntries('n')), ['e2', 'e1', 'e3']);
        _assert["default"].deepStrictEqual(mapKeys(loopy.outboundEdgeEntries('n')), ['e2', 'e1', 'e3']);
        _assert["default"].deepStrictEqual(mapKeys(loopy.inboundEdgeEntries('n')), ['e2', 'e1', 'e3']);
        _assert["default"].deepStrictEqual(mapKeys(loopy.outEdgeEntries('n')), ['e2', 'e1']);
        _assert["default"].deepStrictEqual(mapKeys(loopy.inEdgeEntries('n')), ['e2', 'e1']);
        _assert["default"].deepStrictEqual(mapKeys(loopy.undirectedEdgeEntries('n')), ['e3']);
        _assert["default"].deepStrictEqual(mapKeys(loopy.directedEdgeEntries('n')), ['e2', 'e1']);
        _assert["default"].deepStrictEqual(mapKeys(loopy.edgeEntries('n', 'n')), ['e2', 'e1', 'e3']);
        _assert["default"].deepStrictEqual(mapKeys(loopy.outboundEdgeEntries('n', 'n')), ['e2', 'e1', 'e3']);
        _assert["default"].deepStrictEqual(mapKeys(loopy.inboundEdgeEntries('n', 'n')), ['e2', 'e1', 'e3']);
        _assert["default"].deepStrictEqual(mapKeys(loopy.outEdgeEntries('n', 'n')), ['e2', 'e1']);
        _assert["default"].deepStrictEqual(mapKeys(loopy.inEdgeEntries('n', 'n')), ['e2', 'e1']);
        _assert["default"].deepStrictEqual(mapKeys(loopy.undirectedEdgeEntries('n', 'n')), ['e3']);
        _assert["default"].deepStrictEqual(mapKeys(loopy.directedEdgeEntries('n', 'n')), ['e2', 'e1']);
      },
      'findOutboundEdge should work on multigraphs (#319).': function findOutboundEdge_should_work_on_multigraphs_319() {
        var loopy = new Graph({
          multi: true
        });
        loopy.mergeEdgeWithKey('e1', 'n', 'm');
        loopy.mergeEdgeWithKey('e2', 'n', 'n');
        _assert["default"].strictEqual(loopy.findOutboundEdge(function (_e, _a, s, t) {
          return s === t;
        }), 'e2');
        _assert["default"].strictEqual(loopy.findOutboundEdge('n', function (_e, _a, s, t) {
          return s === t;
        }), 'e2');
        _assert["default"].strictEqual(loopy.findOutboundEdge('n', 'n', function (_e, _a, s, t) {
          return s === t;
        }), 'e2');
      }
    }
  };

  // Common tests
  METHODS.forEach(function (name) {
    return (0, _helpers.deepMerge)(tests, commonTests(name));
  });

  // Specific tests
  for (var name in TEST_DATA) (0, _helpers.deepMerge)(tests, specificTests(name, TEST_DATA[name]));
  return tests;
}