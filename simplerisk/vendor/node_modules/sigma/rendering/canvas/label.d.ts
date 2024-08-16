/**
 * Sigma.js Canvas Renderer Label Component
 * =========================================
 *
 * Function used by the canvas renderer to display a single node's label.
 * @module
 */
import { Settings } from "../../settings";
import { NodeDisplayData, PartialButFor } from "../../types";
export default function drawLabel(context: CanvasRenderingContext2D, data: PartialButFor<NodeDisplayData, "x" | "y" | "size" | "label" | "color">, settings: Settings): void;
