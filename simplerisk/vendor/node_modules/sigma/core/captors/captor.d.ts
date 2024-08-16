/**
 * Sigma.js Captor Class
 * ======================
 * @module
 */
import { Coordinates, MouseCoords, TouchCoords, WheelCoords, TypedEventEmitter, EventsMapping } from "../../types";
import Sigma from "../../sigma";
/**
 * Captor utils functions
 * ======================
 */
/**
 * Extract the local X and Y coordinates from a mouse event or touch object. If
 * a DOM element is given, it uses this element's offset to compute the position
 * (this allows using events that are not bound to the container itself and
 * still have a proper position).
 *
 * @param  {event}       e - A mouse event or touch object.
 * @param  {HTMLElement} dom - A DOM element to compute offset relatively to.
 * @return {number}      The local Y value of the mouse.
 */
export declare function getPosition(e: MouseEvent | Touch, dom: HTMLElement): Coordinates;
/**
 * Convert mouse coords to sigma coords.
 *
 * @param  {event}       e   - A mouse event or touch object.
 * @param  {HTMLElement} dom - A DOM element to compute offset relatively to.
 * @return {object}
 */
export declare function getMouseCoords(e: MouseEvent, dom: HTMLElement): MouseCoords;
/**
 * Convert mouse wheel event coords to sigma coords.
 *
 * @param  {event}       e   - A wheel mouse event.
 * @param  {HTMLElement} dom - A DOM element to compute offset relatively to.
 * @return {object}
 */
export declare function getWheelCoords(e: WheelEvent, dom: HTMLElement): WheelCoords;
export declare function getTouchesArray(touches: TouchList): Touch[];
/**
 * Convert touch coords to sigma coords.
 *
 * @param  {event}       e   - A touch event.
 * @param  {HTMLElement} dom - A DOM element to compute offset relatively to.
 * @return {object}
 */
export declare function getTouchCoords(e: TouchEvent, dom: HTMLElement): TouchCoords;
/**
 * Extract the wheel delta from a mouse event or touch object.
 *
 * @param  {event}  e - A mouse event or touch object.
 * @return {number}     The wheel delta of the mouse.
 */
export declare function getWheelDelta(e: WheelEvent): number;
/**
 * Abstract class representing a captor like the user's mouse or touch controls.
 */
export default abstract class Captor<Events extends EventsMapping> extends TypedEventEmitter<Events> {
    container: HTMLElement;
    renderer: Sigma;
    constructor(container: HTMLElement, renderer: Sigma);
    abstract kill(): void;
}
