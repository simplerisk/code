/**
 * Sigma.js Mouse Captor
 * ======================
 *
 * Sigma's captor dealing with the user's mouse.
 * @module
 */
import { CameraState, MouseCoords, WheelCoords } from "../../types";
import Sigma from "../../sigma";
import Captor from "./captor";
/**
 * Event types.
 */
export declare type MouseCaptorEvents = {
    click(coordinates: MouseCoords): void;
    rightClick(coordinates: MouseCoords): void;
    doubleClick(coordinates: MouseCoords): void;
    mouseup(coordinates: MouseCoords): void;
    mousedown(coordinates: MouseCoords): void;
    mousemove(coordinates: MouseCoords): void;
    mousemovebody(coordinates: MouseCoords): void;
    wheel(coordinates: WheelCoords): void;
};
/**
 * Mouse captor class.
 *
 * @constructor
 */
export default class MouseCaptor extends Captor<MouseCaptorEvents> {
    enabled: boolean;
    draggedEvents: number;
    downStartTime: number | null;
    lastMouseX: number | null;
    lastMouseY: number | null;
    isMouseDown: boolean;
    isMoving: boolean;
    movingTimeout: number | null;
    startCameraState: CameraState | null;
    clicks: number;
    doubleClickTimeout: number | null;
    currentWheelDirection: -1 | 0 | 1;
    lastWheelTriggerTime?: number;
    constructor(container: HTMLElement, renderer: Sigma);
    kill(): void;
    handleClick(e: MouseEvent): void;
    handleRightClick(e: MouseEvent): void;
    handleDoubleClick(e: MouseEvent): void;
    handleDown(e: MouseEvent): void;
    handleUp(e: MouseEvent): void;
    handleMove(e: MouseEvent): void;
    handleWheel(e: WheelEvent): void;
    handleOut(): void;
}
