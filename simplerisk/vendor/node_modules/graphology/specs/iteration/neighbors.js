"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports["default"] = neighborsIteration;
var _assert = _interopRequireDefault(require("assert"));
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
var METHODS = ['neighbors', 'inNeighbors', 'outNeighbors', 'inboundNeighbors', 'outboundNeighbors', 'directedNeighbors', 'undirectedNeighbors'];
function neighborsIteration(Graph, checkers) {
  var notFound = checkers.notFound,
    invalid = checkers.invalid;
  var graph = new Graph({
    multi: true
  });
  (0, _helpers.addNodesFrom)(graph, ['John', 'Thomas', 'Martha', 'Roger', 'Catherine', 'Alone', 'Forever']);
  graph.replaceNodeAttributes('John', {
    age: 34
  });
  graph.replaceNodeAttributes('Martha', {
    age: 35
  });
  graph.addDirectedEdgeWithKey('J->T', 'John', 'Thomas');
  graph.addDirectedEdgeWithKey('J->M', 'John', 'Martha');
  graph.addDirectedEdgeWithKey('C->J', 'Catherine', 'John');
  graph.addUndirectedEdgeWithKey('M<->R', 'Martha', 'Roger');
  graph.addUndirectedEdgeWithKey('M<->J', 'Martha', 'John');
  graph.addUndirectedEdgeWithKey('J<->R', 'John', 'Roger');
  graph.addUndirectedEdgeWithKey('T<->M', 'Thomas', 'Martha');
  var TEST_DATA = {
    neighbors: {
      node: {
        key: 'John',
        neighbors: ['Catherine', 'Thomas', 'Martha', 'Roger']
      }
    },
    inNeighbors: {
      node: {
        key: 'John',
        neighbors: ['Catherine']
      }
    },
    outNeighbors: {
      node: {
        key: 'John',
        neighbors: ['Thomas', 'Martha']
      }
    },
    inboundNeighbors: {
      node: {
        key: 'John',
        neighbors: ['Catherine', 'Martha', 'Roger']
      }
    },
    outboundNeighbors: {
      node: {
        key: 'John',
        neighbors: ['Thomas', 'Martha', 'Roger']
      }
    },
    directedNeighbors: {
      node: {
        key: 'John',
        neighbors: ['Catherine', 'Thomas', 'Martha']
      }
    },
    undirectedNeighbors: {
      node: {
        key: 'John',
        neighbors: ['Martha', 'Roger']
      }
    }
  };
  function commonTests(name) {
    return _defineProperty({}, '#.' + name, {
      'it should throw when the node is not found.': function it_should_throw_when_the_node_is_not_found() {
        _assert["default"]["throws"](function () {
          graph[name]('Test');
        }, notFound());
        if (~name.indexOf('count')) return;
        _assert["default"]["throws"](function () {
          graph[name]('Test', 'SecondTest');
        }, notFound());
      }
    });
  }
  function specificTests(name, data) {
    var capitalized = name[0].toUpperCase() + name.slice(1, -1);
    var forEachName = 'forEach' + capitalized;
    var findName = 'find' + capitalized;
    var iteratorName = name.slice(0, -1) + 'Entries';
    var areName = 'are' + capitalized + 's';
    var mapName = 'map' + capitalized + 's';
    var filterName = 'filter' + capitalized + 's';
    var reduceName = 'reduce' + capitalized + 's';
    var someName = 'some' + capitalized;
    var everyName = 'every' + capitalized;
    return _defineProperty(_defineProperty(_defineProperty(_defineProperty(_defineProperty(_defineProperty(_defineProperty(_defineProperty(_defineProperty({}, '#.' + name, {
      'it should return the correct neighbors array.': function it_should_return_the_correct_neighbors_array() {
        var neighbors = graph[name](data.node.key);
        _assert["default"].deepStrictEqual(neighbors, data.node.neighbors);
        _assert["default"].deepStrictEqual(graph[name]('Alone'), []);
      }
    }), '#.' + forEachName, {
      'it should be possible to iterate over neighbors using a callback.': function it_should_be_possible_to_iterate_over_neighbors_using_a_callback() {
        var neighbors = [];
        graph[forEachName](data.node.key, function (target, attrs) {
          neighbors.push(target);
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(target), attrs);
          _assert["default"].strictEqual(graph[areName](data.node.key, target), true);
        });
        _assert["default"].deepStrictEqual(neighbors, data.node.neighbors);
      }
    }), '#.' + mapName, {
      'it should be possible to map neighbors using a callback.': function it_should_be_possible_to_map_neighbors_using_a_callback() {
        var result = graph[mapName](data.node.key, function (target) {
          return target;
        });
        _assert["default"].deepStrictEqual(result, data.node.neighbors);
      }
    }), '#.' + filterName, {
      'it should be possible to filter neighbors using a callback.': function it_should_be_possible_to_filter_neighbors_using_a_callback() {
        var result = graph[filterName](data.node.key, function () {
          return true;
        });
        _assert["default"].deepStrictEqual(result, data.node.neighbors);
        result = graph[filterName](data.node.key, function () {
          return false;
        });
        _assert["default"].deepStrictEqual(result, []);
      }
    }), '#.' + reduceName, {
      'it sould throw if not given an initial value.': function it_sould_throw_if_not_given_an_initial_value() {
        _assert["default"]["throws"](function () {
          graph[reduceName]('node', function () {
            return true;
          });
        }, invalid());
      },
      'it should be possible to reduce neighbors using a callback.': function it_should_be_possible_to_reduce_neighbors_using_a_callback() {
        var result = graph[reduceName](data.node.key, function (acc, key) {
          return acc.concat(key);
        }, []);
        _assert["default"].deepStrictEqual(result, data.node.neighbors);
      }
    }), '#.' + findName, {
      'it should be possible to find neighbors.': function it_should_be_possible_to_find_neighbors() {
        var neighbors = [];
        var found = graph[findName](data.node.key, function (target, attrs) {
          neighbors.push(target);
          _assert["default"].deepStrictEqual(graph.getNodeAttributes(target), attrs);
          _assert["default"].strictEqual(graph[areName](data.node.key, target), true);
          return true;
        });
        _assert["default"].strictEqual(found, neighbors[0]);
        _assert["default"].deepStrictEqual(neighbors, data.node.neighbors.slice(0, 1));
        found = graph[findName](data.node.key, function () {
          return false;
        });
        _assert["default"].strictEqual(found, undefined);
      }
    }), '#.' + someName, {
      'it should always return false on empty set.': function it_should_always_return_false_on_empty_set() {
        var loneGraph = new Graph();
        loneGraph.addNode('alone');
        _assert["default"].strictEqual(loneGraph[someName]('alone', function () {
          return true;
        }), false);
      },
      'it should be possible to assert whether any neighbor matches a predicate.': function it_should_be_possible_to_assert_whether_any_neighbor_matches_a_predicate() {
        _assert["default"].strictEqual(graph[someName](data.node.key, function () {
          return true;
        }), data.node.neighbors.length > 0);
      }
    }), '#.' + everyName, {
      'it should always return true on empty set.': function it_should_always_return_true_on_empty_set() {
        var loneGraph = new Graph();
        loneGraph.addNode('alone');
        _assert["default"].strictEqual(loneGraph[everyName]('alone', function () {
          return true;
        }), true);
      },
      'it should be possible to assert whether any neighbor matches a predicate.': function it_should_be_possible_to_assert_whether_any_neighbor_matches_a_predicate() {
        _assert["default"].strictEqual(graph[everyName](data.node.key, function () {
          return true;
        }), data.node.neighbors.length > 0);
      }
    }), '#.' + iteratorName, {
      'it should be possible to create an iterator over neighbors.': function it_should_be_possible_to_create_an_iterator_over_neighbors() {
        var iterator = graph[iteratorName](data.node.key);
        _assert["default"].deepStrictEqual(Array.from(iterator), data.node.neighbors.map(function (neighbor) {
          return {
            neighbor: neighbor,
            attributes: graph.getNodeAttributes(neighbor)
          };
        }));
      }
    });
  }
  var tests = {
    Miscellaneous: {
      'self loops should appear when using #.inNeighbors and should appear only once with #.neighbors.': function self_loops_should_appear_when_using_InNeighbors_and_should_appear_only_once_with_Neighbors() {
        var directed = new Graph({
          type: 'directed'
        });
        directed.addNode('Lucy');
        directed.addEdgeWithKey('test', 'Lucy', 'Lucy');
        _assert["default"].deepStrictEqual(directed.inNeighbors('Lucy'), ['Lucy']);
        _assert["default"].deepStrictEqual(Array.from(directed.inNeighborEntries('Lucy')).map(function (x) {
          return x.neighbor;
        }), ['Lucy']);
        var neighbors = [];
        directed.forEachInNeighbor('Lucy', function (neighbor) {
          neighbors.push(neighbor);
        });
        _assert["default"].deepStrictEqual(neighbors, ['Lucy']);
        _assert["default"].deepStrictEqual(directed.neighbors('Lucy'), ['Lucy']);
        neighbors = [];
        directed.forEachNeighbor('Lucy', function (neighbor) {
          neighbors.push(neighbor);
        });
        _assert["default"].deepStrictEqual(neighbors, ['Lucy']);
        _assert["default"].deepStrictEqual(Array.from(directed.neighborEntries('Lucy')).map(function (x) {
          return x.neighbor;
        }), ['Lucy']);
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