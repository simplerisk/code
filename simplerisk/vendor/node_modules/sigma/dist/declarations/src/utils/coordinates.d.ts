import { CameraState, Dimensions } from "../types.js";
export declare function getCorrectionRatio(viewportDimensions: {
    width: number;
    height: number;
}, graphDimensions: {
    width: number;
    height: number;
}): number;
export declare function matrixFromCamera(state: CameraState, viewportDimensions: {
    width: number;
    height: number;
}, graphDimensions: {
    width: number;
    height: number;
}, padding: number, inverse?: boolean): Float32Array;
export declare function getMatrixImpact(matrix: Float32Array, cameraState: CameraState, viewportDimensions: Dimensions): number;
