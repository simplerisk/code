import { CameraState, TypedEventEmitter } from "../types.js";
import { AnimateOptions } from "../utils/index.js";
export type CameraEvents = {
    updated(state: CameraState): void;
};
export default class Camera extends TypedEventEmitter<CameraEvents> implements CameraState {
    x: number;
    y: number;
    angle: number;
    ratio: number;
    minRatio: number | null;
    maxRatio: number | null;
    enabledZooming: boolean;
    enabledPanning: boolean;
    enabledRotation: boolean;
    clean: ((state: CameraState) => CameraState) | null;
    private nextFrame;
    private previousState;
    private enabled;
    animationCallback?: () => void;
    constructor();
    static from(state: CameraState): Camera;
    enable(): this;
    disable(): this;
    getState(): CameraState;
    hasState(state: CameraState): boolean;
    getPreviousState(): CameraState | null;
    getBoundedRatio(ratio: number): number;
    validateState(state: Partial<CameraState>): Partial<CameraState>;
    isAnimated(): boolean;
    setState(state: Partial<CameraState>): this;
    updateState(updater: (state: CameraState) => Partial<CameraState>): this;
    animate(state: Partial<CameraState>, opts: Partial<AnimateOptions>, callback: () => void): void;
    animate(state: Partial<CameraState>, opts?: Partial<AnimateOptions>): Promise<void>;
    animatedZoom(factorOrOptions?: number | (Partial<AnimateOptions> & {
        factor?: number;
    })): Promise<void>;
    animatedUnzoom(factorOrOptions?: number | (Partial<AnimateOptions> & {
        factor?: number;
    })): Promise<void>;
    animatedReset(options?: Partial<AnimateOptions>): Promise<void>;
    copy(): Camera;
}
