/**
 * Sigma.js Camera Class
 * ======================
 *
 * Class designed to store camera information & used to update it.
 * @module
 */
import { AnimateOptions } from "../utils/animate";
import { CameraState, TypedEventEmitter } from "../types";
/**
 * Event types.
 */
export declare type CameraEvents = {
    updated(state: CameraState): void;
};
/**
 * Camera class
 *
 * @constructor
 */
export default class Camera extends TypedEventEmitter<CameraEvents> implements CameraState {
    x: number;
    y: number;
    angle: number;
    ratio: number;
    minRatio: number | null;
    maxRatio: number | null;
    private nextFrame;
    private previousState;
    private enabled;
    animationCallback?: () => void;
    constructor();
    /**
     * Static method used to create a Camera object with a given state.
     *
     * @param state
     * @return {Camera}
     */
    static from(state: CameraState): Camera;
    /**
     * Method used to enable the camera.
     *
     * @return {Camera}
     */
    enable(): this;
    /**
     * Method used to disable the camera.
     *
     * @return {Camera}
     */
    disable(): this;
    /**
     * Method used to retrieve the camera's current state.
     *
     * @return {object}
     */
    getState(): CameraState;
    /**
     * Method used to check whether the camera has the given state.
     *
     * @return {object}
     */
    hasState(state: CameraState): boolean;
    /**
     * Method used to retrieve the camera's previous state.
     *
     * @return {object}
     */
    getPreviousState(): CameraState | null;
    /**
     * Method used to check minRatio and maxRatio values.
     *
     * @param ratio
     * @return {number}
     */
    getBoundedRatio(ratio: number): number;
    /**
     * Method used to check various things to return a legit state candidate.
     *
     * @param state
     * @return {object}
     */
    validateState(state: Partial<CameraState>): Partial<CameraState>;
    /**
     * Method used to check whether the camera is currently being animated.
     *
     * @return {boolean}
     */
    isAnimated(): boolean;
    /**
     * Method used to set the camera's state.
     *
     * @param  {object} state - New state.
     * @return {Camera}
     */
    setState(state: Partial<CameraState>): this;
    /**
     * Method used to update the camera's state using a function.
     *
     * @param  {function} updater - Updated function taking current state and
     *                              returning next state.
     * @return {Camera}
     */
    updateState(updater: (state: CameraState) => Partial<CameraState>): this;
    /**
     * Method used to animate the camera.
     *
     * @param  {object}                    state      - State to reach eventually.
     * @param  {object}                    opts       - Options:
     * @param  {number}                      duration - Duration of the animation.
     * @param  {string | number => number}   easing   - Easing function or name of an existing one
     * @param  {function}                  callback   - Callback
     */
    animate(state: Partial<CameraState>, opts?: Partial<AnimateOptions>, callback?: () => void): void;
    /**
     * Method used to zoom the camera.
     *
     * @param  {number|object} factorOrOptions - Factor or options.
     * @return {function}
     */
    animatedZoom(factorOrOptions?: number | (Partial<AnimateOptions> & {
        factor?: number;
    })): void;
    /**
     * Method used to unzoom the camera.
     *
     * @param  {number|object} factorOrOptions - Factor or options.
     */
    animatedUnzoom(factorOrOptions?: number | (Partial<AnimateOptions> & {
        factor?: number;
    })): void;
    /**
     * Method used to reset the camera.
     *
     * @param  {object} options - Options.
     */
    animatedReset(options?: Partial<AnimateOptions>): void;
    /**
     * Returns a new Camera instance, with the same state as the current camera.
     *
     * @return {Camera}
     */
    copy(): Camera;
}
