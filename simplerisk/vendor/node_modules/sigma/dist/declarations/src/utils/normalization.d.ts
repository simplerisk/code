import { Coordinates, Extent } from "../types.js";
export interface NormalizationFunction {
    (data: Coordinates): Coordinates;
    ratio: number;
    inverse(data: Coordinates): Coordinates;
    applyTo(data: Coordinates): void;
}
export declare function createNormalizationFunction(extent: {
    x: Extent;
    y: Extent;
}): NormalizationFunction;
