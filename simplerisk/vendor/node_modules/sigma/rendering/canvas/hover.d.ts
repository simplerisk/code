/**
 * Sigma.js Canvas Renderer Hover Component
 * =========================================
 *
 * Function used by the canvas renderer to display a single node's hovered
 * state.
 * @module
 */
import { Settings } from "../../settings";
import { NodeDisplayData, PartialButFor } from "../../types";
/**
 * Draw an hovered node.
 * - if there is no label => display a shadow on the node
 * - if the label box is bigger than node size => display a label box that contains the node with a shadow
 * - else node with shadow and the label box
 */
export default function drawHover(context: CanvasRenderingContext2D, data: PartialButFor<NodeDisplayData, "x" | "y" | "size" | "label" | "color">, settings: Settings): void;
