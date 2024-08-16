/**
 * Sigma.js Labels Heuristics
 * ===========================
 *
 * Miscelleneous heuristics related to label display.
 * @module
 */
import Graph from "graphology-types";
import { Dimensions, Coordinates } from "../types";
/**
 * Class representing a single candidate for the label grid selection.
 *
 * It also describes a deterministic way to compare two candidates to assess
 * which one is better.
 */
declare class LabelCandidate {
    key: string;
    size: number;
    constructor(key: string, size: number);
    static compare(first: LabelCandidate, second: LabelCandidate): number;
}
/**
 * Class representing a 2D spatial grid divided into constant-size cells.
 */
export declare class LabelGrid {
    width: number;
    height: number;
    cellSize: number;
    columns: number;
    rows: number;
    cells: Record<number, Array<LabelCandidate>>;
    resizeAndClear(dimensions: Dimensions, cellSize: number): void;
    private getIndex;
    add(key: string, size: number, pos: Coordinates): void;
    organize(): void;
    getLabelsToDisplay(ratio: number, density: number): Array<string>;
}
/**
 * Label heuristic selecting edge labels to display, based on displayed node
 * labels
 *
 * @param  {object} params                 - Parameters:
 * @param  {Set}      displayedNodeLabels  - Currently displayed node labels.
 * @param  {Set}      highlightedNodes     - Highlighted nodes.
 * @param  {Graph}    graph                - The rendered graph.
 * @param  {string}   hoveredNode          - Hovered node (optional)
 * @return {Array}                         - The selected labels.
 */
export declare function edgeLabelsToDisplayFromNodes(params: {
    displayedNodeLabels: Set<string>;
    highlightedNodes: Set<string>;
    graph: Graph;
    hoveredNode: string | null;
}): Array<string>;
export {};
