"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports["default"] = serialization;
var _assert = _interopRequireDefault(require("assert"));
var _helpers = require("./helpers");
function _interopRequireDefault(e) { return e && e.__esModule ? e : { "default": e }; }
/**
 * Graphology Serializaton Specs
 * ==============================
 *
 * Testing the serialization methods of the graph.
 */

function serialization(Graph, checkers) {
  var invalid = checkers.invalid,
    notFound = checkers.notFound;
  return {
    '#.export': {
      'it should correctly return the serialized graph.': function it_should_correctly_return_the_serialized_graph() {
        var graph = new Graph({
          multi: true
        });
        graph.setAttribute('name', 'graph');
        (0, _helpers.addNodesFrom)(graph, ['John', 'Jack', 'Martha']);
        graph.setNodeAttribute('John', 'age', 34);
        graph.addEdgeWithKey('J->J•1', 'John', 'Jack');
        graph.addEdgeWithKey('J->J•2', 'John', 'Jack', {
          weight: 2
        });
        graph.addEdgeWithKey('J->J•3', 'John', 'Jack');
        graph.addUndirectedEdgeWithKey('J<->J•1', 'John', 'Jack');
        graph.addUndirectedEdgeWithKey('J<->J•2', 'John', 'Jack', {
          weight: 3
        });
        _assert["default"].deepStrictEqual(graph["export"](), {
          attributes: {
            name: 'graph'
          },
          nodes: [{
            key: 'John',
            attributes: {
              age: 34
            }
          }, {
            key: 'Jack'
          }, {
            key: 'Martha'
          }],
          edges: [{
            key: 'J->J•1',
            source: 'John',
            target: 'Jack'
          }, {
            key: 'J->J•2',
            source: 'John',
            target: 'Jack',
            attributes: {
              weight: 2
            }
          }, {
            key: 'J->J•3',
            source: 'John',
            target: 'Jack'
          }, {
            key: 'J<->J•1',
            source: 'John',
            target: 'Jack',
            undirected: true
          }, {
            key: 'J<->J•2',
            source: 'John',
            target: 'Jack',
            attributes: {
              weight: 3
            },
            undirected: true
          }],
          options: {
            allowSelfLoops: true,
            multi: true,
            type: 'mixed'
          }
        });
      },
      'it should not need to tell whether edges are undirected if the graph is.': function it_should_not_need_to_tell_whether_edges_are_undirected_if_the_graph_is() {
        var graph = new Graph({
          type: 'undirected'
        });
        graph.mergeEdgeWithKey(0, 1, 2);
        _assert["default"].deepStrictEqual(graph["export"](), {
          options: {
            type: 'undirected',
            multi: false,
            allowSelfLoops: true
          },
          attributes: {},
          nodes: [{
            key: '1'
          }, {
            key: '2'
          }],
          edges: [{
            key: '0',
            source: '1',
            target: '2'
          }]
        });
      }
    },
    '#.import': {
      'it should throw if the given data is invalid.': function it_should_throw_if_the_given_data_is_invalid() {
        var graph = new Graph();
        _assert["default"]["throws"](function () {
          graph["import"](true);
        }, invalid());
      },
      'it should be possible to import a graph instance.': function it_should_be_possible_to_import_a_graph_instance() {
        var graph = new Graph();
        (0, _helpers.addNodesFrom)(graph, ['John', 'Thomas']);
        graph.addEdge('John', 'Thomas');
        var other = new Graph();
        other["import"](graph);
        _assert["default"].deepStrictEqual(graph.nodes(), other.nodes());
        _assert["default"].deepStrictEqual(graph.edges(), other.edges());
      },
      'it should be possible to import a serialized graph.': function it_should_be_possible_to_import_a_serialized_graph() {
        var graph = new Graph();
        graph["import"]({
          nodes: [{
            key: 'John'
          }, {
            key: 'Thomas'
          }],
          edges: [{
            source: 'John',
            target: 'Thomas'
          }]
        });
        _assert["default"].deepStrictEqual(graph.nodes(), ['John', 'Thomas']);
        _assert["default"].strictEqual(graph.hasEdge('John', 'Thomas'), true);
      },
      'it should be possible to import only edges when merging.': function it_should_be_possible_to_import_only_edges_when_merging() {
        var graph = new Graph();
        graph["import"]({
          edges: [{
            source: 'John',
            target: 'Thomas'
          }]
        }, true);
        _assert["default"].deepStrictEqual(graph.nodes(), ['John', 'Thomas']);
        _assert["default"].strictEqual(graph.size, 1);
        _assert["default"].strictEqual(graph.hasEdge('John', 'Thomas'), true);
      },
      'it should be possible to import attributes.': function it_should_be_possible_to_import_attributes() {
        var graph = new Graph();
        graph["import"]({
          attributes: {
            name: 'graph'
          }
        });
        _assert["default"].deepStrictEqual(graph.getAttributes(), {
          name: 'graph'
        });
      },
      'it should throw if nodes are absent, edges are present and we merge.': function it_should_throw_if_nodes_are_absent_edges_are_present_and_we_merge() {
        var graph = new Graph();
        _assert["default"]["throws"](function () {
          graph["import"]({
            edges: [{
              source: '1',
              target: '2'
            }]
          });
        }, notFound());
      },
      'it should import undirected graphs properly.': function it_should_import_undirected_graphs_properly() {
        var graph = Graph.from({
          options: {
            type: 'undirected',
            multi: false,
            allowSelfLoops: true
          },
          attributes: {},
          nodes: [{
            key: '1'
          }, {
            key: '2'
          }],
          edges: [{
            key: '0',
            source: '1',
            target: '2'
          }]
        });
        _assert["default"].strictEqual(graph.order, 2);
        _assert["default"].strictEqual(graph.size, 1);
        _assert["default"].strictEqual(graph.hasEdge(2, 1), true);
      }
    }
  };
}