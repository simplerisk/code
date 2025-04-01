"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports["default"] = knownMethods;
var _assert = _interopRequireDefault(require("assert"));
var _helpers = require("./helpers");
function _interopRequireDefault(e) { return e && e.__esModule ? e : { "default": e }; }
/**
 * Graphology Known Methods Specs
 * ===============================
 *
 * Testing the known methods of the graph.
 */

function knownMethods(Graph) {
  return {
    '#.toJSON': {
      'it should return the serialized graph.': function it_should_return_the_serialized_graph() {
        var graph = new Graph({
          multi: true
        });
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
        _assert["default"].deepStrictEqual(graph.toJSON(), graph["export"]());
      }
    },
    '#.toString': {
      'it should return "[object Graph]".': function it_should_return_Object_Graph() {
        var graph = new Graph();
        _assert["default"].strictEqual(graph.toString(), '[object Graph]');
      }
    }
  };
}