import Graph from "graphology-types";
import { Extent } from "../types.js";
export declare function graphExtent(graph: Graph): {
    x: Extent;
    y: Extent;
};
export declare function validateGraph(graph: Graph): void;
