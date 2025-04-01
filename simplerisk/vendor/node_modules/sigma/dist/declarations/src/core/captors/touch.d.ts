import { Attributes } from "graphology-types";
import { Settings } from "../../settings.js";
import Sigma from "../../sigma.js";
import { CameraState, Coordinates, Dimensions, TouchCoords } from "../../types.js";
import Captor from "./captor.js";
export declare const TOUCH_SETTINGS_KEYS: readonly ["dragTimeout", "inertiaDuration", "inertiaRatio", "doubleClickTimeout", "doubleClickZoomingRatio", "doubleClickZoomingDuration", "tapMoveTolerance"];
export type TouchSettingKey = (typeof TOUCH_SETTINGS_KEYS)[number];
export type TouchSettings = Pick<Settings, TouchSettingKey>;
export declare const DEFAULT_TOUCH_SETTINGS: TouchSettings;
export type TouchCaptorEventType = "touchdown" | "touchup" | "touchmove" | "touchmovebody" | "tap" | "doubletap";
export type TouchCaptorEvents = Record<TouchCaptorEventType, (coordinates: TouchCoords) => void>;
export default class TouchCaptor<N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes> extends Captor<TouchCaptorEvents, N, E, G> {
    enabled: boolean;
    isMoving: boolean;
    hasMoved: boolean;
    startCameraState?: CameraState;
    touchMode: number;
    movingTimeout?: number;
    startTouchesAngle?: number;
    startTouchesDistance?: number;
    startTouchesPositions: Coordinates[];
    lastTouchesPositions?: Coordinates[];
    lastTouches: Touch[];
    lastTap: null | {
        position: Coordinates;
        time: number;
    };
    settings: TouchSettings;
    constructor(container: HTMLElement, renderer: Sigma<N, E, G>);
    kill(): void;
    getDimensions(): Dimensions;
    handleStart(e: TouchEvent): void;
    handleLeave(e: TouchEvent): void;
    handleMove(e: TouchEvent): void;
    setSettings(settings: TouchSettings): void;
}
