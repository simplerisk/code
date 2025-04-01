import { Attributes } from "graphology-types";
import Sigma from "../../sigma.js";
import { Coordinates, EventsMapping, MouseCoords, TouchCoords, TypedEventEmitter, WheelCoords } from "../../types.js";
export declare function getPosition(e: MouseEvent | Touch, dom: HTMLElement): Coordinates;
export declare function getMouseCoords(e: MouseEvent, dom: HTMLElement): MouseCoords;
export declare function cleanMouseCoords(e: MouseCoords | TouchCoords): MouseCoords;
export declare function getWheelCoords(e: WheelEvent, dom: HTMLElement): WheelCoords;
export declare function getTouchesArray(touches: TouchList): Touch[];
export declare function getTouchCoords(e: TouchEvent, previousTouches: Touch[], dom: HTMLElement): TouchCoords;
export declare function getWheelDelta(e: WheelEvent): number;
export default abstract class Captor<Events extends EventsMapping, N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes> extends TypedEventEmitter<Events> {
    container: HTMLElement;
    renderer: Sigma<N, E, G>;
    constructor(container: HTMLElement, renderer: Sigma<N, E, G>);
    abstract kill(): void;
}
