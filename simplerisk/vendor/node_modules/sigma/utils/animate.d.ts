/**
 * Sigma.js Animation Helpers
 * ===========================
 *
 * Handy helper functions dealing with nodes & edges attributes animation.
 * @module
 */
import Graph from "graphology-types";
import { PlainObject } from "../types";
import easings from "./easings";
/**
 * Defaults.
 */
export declare type Easing = keyof typeof easings | ((k: number) => number);
export interface AnimateOptions {
    easing: Easing;
    duration: number;
}
export declare const ANIMATE_DEFAULTS: {
    easing: string;
    duration: number;
};
/**
 * Function used to animate the nodes.
 */
export declare function animateNodes(graph: Graph, targets: PlainObject<PlainObject<number>>, opts: Partial<AnimateOptions>, callback?: () => void): () => void;
