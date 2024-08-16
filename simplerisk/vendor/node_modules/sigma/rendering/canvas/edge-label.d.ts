/**
 * Sigma.js Canvas Renderer Edge Label Component
 * =============================================
 *
 * Function used by the canvas renderer to display a single edge's label.
 * @module
 */
import { Settings } from "../../settings";
import { EdgeDisplayData, NodeDisplayData, PartialButFor } from "../../types";
export default function drawEdgeLabel(context: CanvasRenderingContext2D, edgeData: PartialButFor<EdgeDisplayData, "label" | "color" | "size">, sourceData: PartialButFor<NodeDisplayData, "x" | "y" | "size">, targetData: PartialButFor<NodeDisplayData, "x" | "y" | "size">, settings: Settings): void;
