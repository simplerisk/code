import { Attributes } from "graphology-types";
import { Settings } from "../settings.js";
import { NodeDisplayData, PartialButFor } from "../types.js";
export type NodeHoverDrawingFunction<N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes> = (context: CanvasRenderingContext2D, data: PartialButFor<NodeDisplayData, "x" | "y" | "size" | "label" | "color">, settings: Settings<N, E, G>) => void;
export declare function drawDiscNodeHover<N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes>(context: CanvasRenderingContext2D, data: PartialButFor<NodeDisplayData, "x" | "y" | "size" | "label" | "color">, settings: Settings<N, E, G>): void;
