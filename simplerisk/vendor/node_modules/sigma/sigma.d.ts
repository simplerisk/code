/**
 * Sigma.js
 * ========
 * @module
 */
import Graph from "graphology-types";
import Camera from "./core/camera";
import MouseCaptor from "./core/captors/mouse";
import { CameraState, Coordinates, Dimensions, EdgeDisplayData, Extent, MouseCoords, NodeDisplayData, PlainObject, CoordinateConversionOverride, TypedEventEmitter, MouseInteraction } from "./types";
import { Settings } from "./settings";
import TouchCaptor from "./core/captors/touch";
/**
 * Event types.
 */
export interface SigmaEventPayload {
    event: MouseCoords;
    preventSigmaDefault(): void;
}
export interface SigmaStageEventPayload extends SigmaEventPayload {
}
export interface SigmaNodeEventPayload extends SigmaEventPayload {
    node: string;
}
export interface SigmaEdgeEventPayload extends SigmaEventPayload {
    edge: string;
}
export declare type SigmaStageEvents = {
    [E in MouseInteraction as `${E}Stage`]: (payload: SigmaStageEventPayload) => void;
};
export declare type SigmaNodeEvents = {
    [E in MouseInteraction as `${E}Node`]: (payload: SigmaNodeEventPayload) => void;
};
export declare type SigmaEdgeEvents = {
    [E in MouseInteraction as `${E}Edge`]: (payload: SigmaEdgeEventPayload) => void;
};
export declare type SigmaAdditionalEvents = {
    beforeRender(): void;
    afterRender(): void;
    resize(): void;
    kill(): void;
    enterNode(payload: SigmaNodeEventPayload): void;
    leaveNode(payload: SigmaNodeEventPayload): void;
    enterEdge(payload: SigmaEdgeEventPayload): void;
    leaveEdge(payload: SigmaEdgeEventPayload): void;
};
export declare type SigmaEvents = SigmaStageEvents & SigmaNodeEvents & SigmaEdgeEvents & SigmaAdditionalEvents;
/**
 * Main class.
 *
 * @constructor
 * @param {Graph}       graph     - Graph to render.
 * @param {HTMLElement} container - DOM container in which to render.
 * @param {object}      settings  - Optional settings.
 */
export default class Sigma<GraphType extends Graph = Graph> extends TypedEventEmitter<SigmaEvents> {
    private settings;
    private graph;
    private mouseCaptor;
    private touchCaptor;
    private container;
    private elements;
    private canvasContexts;
    private webGLContexts;
    private activeListeners;
    private quadtree;
    private labelGrid;
    private nodeDataCache;
    private edgeDataCache;
    private nodesWithForcedLabels;
    private edgesWithForcedLabels;
    private nodeExtent;
    private matrix;
    private invMatrix;
    private correctionRatio;
    private customBBox;
    private normalizationFunction;
    private cameraSizeRatio;
    private width;
    private height;
    private pixelRatio;
    private displayedLabels;
    private highlightedNodes;
    private hoveredNode;
    private hoveredEdge;
    private renderFrame;
    private renderHighlightedNodesFrame;
    private needToProcess;
    private needToSoftProcess;
    private checkEdgesEventsFrame;
    private nodePrograms;
    private nodeHoverPrograms;
    private edgePrograms;
    private camera;
    constructor(graph: GraphType, container: HTMLElement, settings?: Partial<Settings>);
    /**---------------------------------------------------------------------------
     * Internal methods.
     **---------------------------------------------------------------------------
     */
    /**
     * Internal function used to create a canvas element.
     * @param  {string} id - Context's id.
     * @return {Sigma}
     */
    private createCanvas;
    /**
     * Internal function used to create a canvas context and add the relevant
     * DOM elements.
     *
     * @param  {string} id - Context's id.
     * @return {Sigma}
     */
    private createCanvasContext;
    /**
     * Internal function used to create a canvas context and add the relevant
     * DOM elements.
     *
     * @param  {string}  id      - Context's id.
     * @param  {object?} options - #getContext params to override (optional)
     * @return {Sigma}
     */
    private createWebGLContext;
    /**
     * Method binding camera handlers.
     *
     * @return {Sigma}
     */
    private bindCameraHandlers;
    /**
     * Method that checks whether or not a node collides with a given position.
     */
    private mouseIsOnNode;
    /**
     * Method that returns all nodes in quad at a given position.
     */
    private getQuadNodes;
    /**
     * Method that returns the closest node to a given position.
     */
    private getNodeAtPosition;
    /**
     * Method binding event handlers.
     *
     * @return {Sigma}
     */
    private bindEventHandlers;
    /**
     * Method binding graph handlers
     *
     * @return {Sigma}
     */
    private bindGraphHandlers;
    /**
     * Method used to unbind handlers from the graph.
     *
     * @return {undefined}
     */
    private unbindGraphHandlers;
    /**
     * Method dealing with "leaveEdge" and "enterEdge" events.
     *
     * @return {Sigma}
     */
    private checkEdgeHoverEvents;
    /**
     * Method looking for an edge colliding with a given point at (x, y). Returns
     * the key of the edge if any, or null else.
     */
    private getEdgeAtPoint;
    /**
     * Method used to process the whole graph's data.
     *
     * @return {Sigma}
     */
    private process;
    /**
     * Method that backports potential settings updates where it's needed.
     * @private
     */
    private handleSettingsUpdate;
    /**
     * Method that decides whether to reprocess graph or not, and then render the
     * graph.
     *
     * @return {Sigma}
     */
    private _refresh;
    /**
     * Method that schedules a `_refresh` call if none has been scheduled yet. It
     * will then be processed next available frame.
     *
     * @return {Sigma}
     */
    private _scheduleRefresh;
    /**
     * Method used to render labels.
     *
     * @return {Sigma}
     */
    private renderLabels;
    /**
     * Method used to render edge labels, based on which node labels were
     * rendered.
     *
     * @return {Sigma}
     */
    private renderEdgeLabels;
    /**
     * Method used to render the highlighted nodes.
     *
     * @return {Sigma}
     */
    private renderHighlightedNodes;
    /**
     * Method used to schedule a hover render.
     *
     */
    private scheduleHighlightedNodesRender;
    /**
     * Method used to render.
     *
     * @return {Sigma}
     */
    private render;
    /**
     * Internal method used to update expensive and therefore cached values
     * each time the camera state is updated.
     */
    private updateCachedValues;
    /**---------------------------------------------------------------------------
     * Public API.
     **---------------------------------------------------------------------------
     */
    /**
     * Method returning the renderer's camera.
     *
     * @return {Camera}
     */
    getCamera(): Camera;
    /**
     * Method returning the container DOM element.
     *
     * @return {HTMLElement}
     */
    getContainer(): HTMLElement;
    /**
     * Method returning the renderer's graph.
     *
     * @return {Graph}
     */
    getGraph(): GraphType;
    /**
     * Method used to set the renderer's graph.
     *
     * @return {Graph}
     */
    setGraph(graph: GraphType): void;
    /**
     * Method returning the mouse captor.
     *
     * @return {MouseCaptor}
     */
    getMouseCaptor(): MouseCaptor;
    /**
     * Method returning the touch captor.
     *
     * @return {TouchCaptor}
     */
    getTouchCaptor(): TouchCaptor;
    /**
     * Method returning the current renderer's dimensions.
     *
     * @return {Dimensions}
     */
    getDimensions(): Dimensions;
    /**
     * Method returning the current graph's dimensions.
     *
     * @return {Dimensions}
     */
    getGraphDimensions(): Dimensions;
    /**
     * Method used to get all the sigma node attributes.
     * It's usefull for example to get the position of a node
     * and to get values that are set by the nodeReducer
     *
     * @param  {string} key - The node's key.
     * @return {NodeDisplayData | undefined} A copy of the desired node's attribute or undefined if not found
     */
    getNodeDisplayData(key: unknown): NodeDisplayData | undefined;
    /**
     * Method used to get all the sigma edge attributes.
     * It's usefull for example to get values that are set by the edgeReducer.
     *
     * @param  {string} key - The edge's key.
     * @return {EdgeDisplayData | undefined} A copy of the desired edge's attribute or undefined if not found
     */
    getEdgeDisplayData(key: unknown): EdgeDisplayData | undefined;
    /**
     * Method returning a copy of the settings collection.
     *
     * @return {Settings} A copy of the settings collection.
     */
    getSettings(): Settings;
    /**
     * Method returning the current value for a given setting key.
     *
     * @param  {string} key - The setting key to get.
     * @return {any} The value attached to this setting key or undefined if not found
     */
    getSetting<K extends keyof Settings>(key: K): Settings[K] | undefined;
    /**
     * Method setting the value of a given setting key. Note that this will schedule
     * a new render next frame.
     *
     * @param  {string} key - The setting key to set.
     * @param  {any}    value - The value to set.
     * @return {Sigma}
     */
    setSetting<K extends keyof Settings>(key: K, value: Settings[K]): this;
    /**
     * Method updating the value of a given setting key using the provided function.
     * Note that this will schedule a new render next frame.
     *
     * @param  {string}   key     - The setting key to set.
     * @param  {function} updater - The update function.
     * @return {Sigma}
     */
    updateSetting<K extends keyof Settings>(key: K, updater: (value: Settings[K]) => Settings[K]): this;
    /**
     * Method used to resize the renderer.
     *
     * @return {Sigma}
     */
    resize(): this;
    /**
     * Method used to clear all the canvases.
     *
     * @return {Sigma}
     */
    clear(): this;
    /**
     * Method used to refresh all computed data.
     *
     * @return {Sigma}
     */
    refresh(): this;
    /**
     * Method used to refresh all computed data, at the next available frame.
     * If this method has already been called this frame, then it will only render once at the next available frame.
     *
     * @return {Sigma}
     */
    scheduleRefresh(): this;
    /**
     * Method used to (un)zoom, while preserving the position of a viewport point.
     * Used for instance to zoom "on the mouse cursor".
     *
     * @param viewportTarget
     * @param newRatio
     * @return {CameraState}
     */
    getViewportZoomedState(viewportTarget: Coordinates, newRatio: number): CameraState;
    /**
     * Method returning the abstract rectangle containing the graph according
     * to the camera's state.
     *
     * @return {object} - The view's rectangle.
     */
    viewRectangle(): {
        x1: number;
        y1: number;
        x2: number;
        y2: number;
        height: number;
    };
    /**
     * Method returning the coordinates of a point from the framed graph system to the viewport system. It allows
     * overriding anything that is used to get the translation matrix, or even the matrix itself.
     *
     * Be careful if overriding dimensions, padding or cameraState, as the computation of the matrix is not the lightest
     * of computations.
     */
    framedGraphToViewport(coordinates: Coordinates, override?: CoordinateConversionOverride): Coordinates;
    /**
     * Method returning the coordinates of a point from the viewport system to the framed graph system. It allows
     * overriding anything that is used to get the translation matrix, or even the matrix itself.
     *
     * Be careful if overriding dimensions, padding or cameraState, as the computation of the matrix is not the lightest
     * of computations.
     */
    viewportToFramedGraph(coordinates: Coordinates, override?: CoordinateConversionOverride): Coordinates;
    /**
     * Method used to translate a point's coordinates from the viewport system (pixel distance from the top-left of the
     * stage) to the graph system (the reference system of data as they are in the given graph instance).
     *
     * This method accepts an optional camera which can be useful if you need to translate coordinates
     * based on a different view than the one being currently being displayed on screen.
     *
     * @param {Coordinates}                  viewportPoint
     * @param {CoordinateConversionOverride} override
     */
    viewportToGraph(viewportPoint: Coordinates, override?: CoordinateConversionOverride): Coordinates;
    /**
     * Method used to translate a point's coordinates from the graph system (the reference system of data as they are in
     * the given graph instance) to the viewport system (pixel distance from the top-left of the stage).
     *
     * This method accepts an optional camera which can be useful if you need to translate coordinates
     * based on a different view than the one being currently being displayed on screen.
     *
     * @param {Coordinates}                  graphPoint
     * @param {CoordinateConversionOverride} override
     */
    graphToViewport(graphPoint: Coordinates, override?: CoordinateConversionOverride): Coordinates;
    /**
     * Method returning the graph's bounding box.
     *
     * @return {{ x: Extent, y: Extent }}
     */
    getBBox(): {
        x: Extent;
        y: Extent;
    };
    /**
     * Method returning the graph's custom bounding box, if any.
     *
     * @return {{ x: Extent, y: Extent } | null}
     */
    getCustomBBox(): {
        x: Extent;
        y: Extent;
    } | null;
    /**
     * Method used to override the graph's bounding box with a custom one. Give `null` as the argument to stop overriding.
     *
     * @return {Sigma}
     */
    setCustomBBox(customBBox: {
        x: Extent;
        y: Extent;
    } | null): this;
    /**
     * Method used to shut the container & release event listeners.
     *
     * @return {undefined}
     */
    kill(): void;
    /**
     * Method used to scale the given size according to the camera's ratio, i.e.
     * zooming state.
     *
     * @param  {number} size - The size to scale (node size, edge thickness etc.).
     * @return {number}      - The scaled size.
     */
    scaleSize(size: number): number;
    /**
     * Method that returns the collection of all used canvases.
     * At the moment, the instantiated canvases are the following, and in the
     * following order in the DOM:
     * - `edges`
     * - `nodes`
     * - `edgeLabels`
     * - `labels`
     * - `hovers`
     * - `hoverNodes`
     * - `mouse`
     *
     * @return {PlainObject<HTMLCanvasElement>} - The collection of canvases.
     */
    getCanvases(): PlainObject<HTMLCanvasElement>;
}
