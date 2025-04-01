"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports["default"] = properties;
var _assert = _interopRequireDefault(require("assert"));
function _interopRequireDefault(e) { return e && e.__esModule ? e : { "default": e }; }
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); } /**
 * Graphology Properties Specs
 * ============================
 *
 * Testing the properties of the graph.
 */
var PROPERTIES = ['order', 'size', 'directedSize', 'undirectedSize', 'type', 'multi', 'allowSelfLoops', 'implementation', 'selfLoopCount', 'directedSelfLoopCount', 'undirectedSelfLoopCount'];
function properties(Graph) {
  return {
    /**
     * Regarding all properties.
     */
    misc: {
      'all expected properties should be set.': function all_expected_properties_should_be_set() {
        var graph = new Graph();
        PROPERTIES.forEach(function (property) {
          (0, _assert["default"])(property in graph, property);
        });
      },
      'properties should be read-only.': function properties_should_be_readOnly() {
        var graph = new Graph();

        // Attempting to mutate the properties
        PROPERTIES.forEach(function (property) {
          _assert["default"]["throws"](function () {
            graph[property] = 'test';
          }, TypeError);
        });
      }
    },
    /**
     * Order.
     */
    '#.order': {
      'it should be 0 if the graph is empty.': function it_should_be_0_if_the_graph_is_empty() {
        var graph = new Graph();
        _assert["default"].strictEqual(graph.order, 0);
      },
      'adding nodes should increase order.': function adding_nodes_should_increase_order() {
        var graph = new Graph();
        graph.addNode('John');
        graph.addNode('Jack');
        _assert["default"].strictEqual(graph.order, 2);
      }
    },
    /**
     * Size.
     */
    '#.size': {
      'it should be 0 if the graph is empty.': function it_should_be_0_if_the_graph_is_empty() {
        var graph = new Graph();
        _assert["default"].strictEqual(graph.size, 0);
      },
      'adding & dropping edges should affect size.': function adding__dropping_edges_should_affect_size() {
        var graph = new Graph();
        graph.addNode('John');
        graph.addNode('Jack');
        graph.addDirectedEdge('John', 'Jack');
        _assert["default"].strictEqual(graph.size, 1);
        graph.dropEdge('John', 'Jack');
        _assert["default"].strictEqual(graph.size, 0);
      }
    },
    /**
     * Directed Size.
     */
    '#.directedSize': {
      'it should be 0 if the graph is empty.': function it_should_be_0_if_the_graph_is_empty() {
        var graph = new Graph();
        _assert["default"].strictEqual(graph.directedSize, 0);
      },
      'adding & dropping edges should affect directed size.': function adding__dropping_edges_should_affect_directed_size() {
        var graph = new Graph();
        graph.addNode('John');
        graph.addNode('Jack');
        var directedEdge = graph.addDirectedEdge('John', 'Jack');
        _assert["default"].strictEqual(graph.directedSize, 1);
        var undirectedEdge = graph.addUndirectedEdge('John', 'Jack');
        _assert["default"].strictEqual(graph.directedSize, 1);
        graph.dropEdge(directedEdge);
        _assert["default"].strictEqual(graph.directedSize, 0);
        graph.dropEdge(undirectedEdge);
        _assert["default"].strictEqual(graph.directedSize, 0);
      }
    },
    /**
     * Undirected Size.
     */
    '#.undirectedSize': {
      'it should be 0 if the graph is empty.': function it_should_be_0_if_the_graph_is_empty() {
        var graph = new Graph();
        _assert["default"].strictEqual(graph.undirectedSize, 0);
      },
      'adding & dropping edges should affect undirected size.': function adding__dropping_edges_should_affect_undirected_size() {
        var graph = new Graph();
        graph.addNode('John');
        graph.addNode('Jack');
        var directedEdge = graph.addDirectedEdge('John', 'Jack');
        _assert["default"].strictEqual(graph.undirectedSize, 0);
        var undirectedEdge = graph.addUndirectedEdge('John', 'Jack');
        _assert["default"].strictEqual(graph.undirectedSize, 1);
        graph.dropEdge(directedEdge);
        _assert["default"].strictEqual(graph.undirectedSize, 1);
        graph.dropEdge(undirectedEdge);
        _assert["default"].strictEqual(graph.undirectedSize, 0);
      }
    },
    /**
     * Multi.
     */
    '#.multi': {
      'it should be false by default.': function it_should_be_false_by_default() {
        var graph = new Graph();
        _assert["default"].strictEqual(graph.multi, false);
      }
    },
    /**
     * Type.
     */
    '#.type': {
      'it should be "mixed" by default.': function it_should_be_Mixed_by_default() {
        var graph = new Graph();
        _assert["default"].strictEqual(graph.type, 'mixed');
      }
    },
    /**
     * Self loops.
     */
    '#.allowSelfLoops': {
      'it should be true by default.': function it_should_be_true_by_default() {
        var graph = new Graph();
        _assert["default"].strictEqual(graph.allowSelfLoops, true);
      }
    },
    /**
     * Implementation.
     */
    '#.implementation': {
      'it should exist and be a string.': function it_should_exist_and_be_a_string() {
        var graph = new Graph();
        _assert["default"].strictEqual(_typeof(graph.implementation), 'string');
      }
    },
    /**
     * Self Loop Count.
     */
    '#.selfLoopCount': {
      'it should exist and be correct.': function it_should_exist_and_be_correct() {
        var graph = new Graph();
        graph.mergeDirectedEdge('John', 'John');
        graph.mergeDirectedEdge('Lucy', 'Lucy');
        graph.mergeUndirectedEdge('Joana', 'Joana');
        _assert["default"].strictEqual(graph.selfLoopCount, 3);
        _assert["default"].strictEqual(graph.directedSelfLoopCount, 2);
        _assert["default"].strictEqual(graph.undirectedSelfLoopCount, 1);
        graph.forEachEdge(function (edge) {
          return graph.dropEdge(edge);
        });
        _assert["default"].strictEqual(graph.selfLoopCount, 0);
        _assert["default"].strictEqual(graph.directedSelfLoopCount, 0);
        _assert["default"].strictEqual(graph.undirectedSelfLoopCount, 0);
      }
    }
  };
}