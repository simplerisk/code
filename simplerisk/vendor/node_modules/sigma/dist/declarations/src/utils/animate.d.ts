import Graph from "graphology-types";
import { PlainObject } from "../types.js";
import { easings } from "./easings.js";
export type Easing = keyof typeof easings | ((k: number) => number);
export interface AnimateOptions {
    easing: Easing;
    duration: number;
}
export declare const ANIMATE_DEFAULTS: {
    easing: string;
    duration: number;
};
export declare function animateNodes(graph: Graph, targets: PlainObject<PlainObject<number>>, opts: Partial<AnimateOptions>, callback?: () => void): () => void;
