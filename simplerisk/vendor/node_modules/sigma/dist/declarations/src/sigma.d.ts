import Graph, { Attributes } from "graphology-types";
import Camera from "./core/camera.js";
import MouseCaptor from "./core/captors/mouse.js";
import TouchCaptor from "./core/captors/touch.js";
import { Settings } from "./settings.js";
import { CameraState, CoordinateConversionOverride, Coordinates, Dimensions, EdgeDisplayData, Extent, NodeDisplayData, PlainObject, RenderParams, SigmaEvents, TypedEventEmitter } from "./types.js";
export default class Sigma<N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes> extends TypedEventEmitter<SigmaEvents> {
    private settings;
    private graph;
    private mouseCaptor;
    private touchCaptor;
    private container;
    private elements;
    private canvasContexts;
    private webGLContexts;
    private pickingLayers;
    private textures;
    private frameBuffers;
    private activeListeners;
    private labelGrid;
    private nodeDataCache;
    private edgeDataCache;
    private nodeProgramIndex;
    private edgeProgramIndex;
    private nodesWithForcedLabels;
    private edgesWithForcedLabels;
    private nodeExtent;
    private nodeZExtent;
    private edgeZExtent;
    private matrix;
    private invMatrix;
    private correctionRatio;
    private customBBox;
    private normalizationFunction;
    private graphToViewportRatio;
    private itemIDsIndex;
    private nodeIndices;
    private edgeIndices;
    private width;
    private height;
    private pixelRatio;
    private pickingDownSizingRatio;
    private displayedNodeLabels;
    private displayedEdgeLabels;
    private highlightedNodes;
    private hoveredNode;
    private hoveredEdge;
    private renderFrame;
    private renderHighlightedNodesFrame;
    private needToProcess;
    private checkEdgesEventsFrame;
    private nodePrograms;
    private nodeHoverPrograms;
    private edgePrograms;
    private camera;
    constructor(graph: Graph<N, E, G>, container: HTMLElement, settings?: Partial<Settings<N, E, G>>);
    private registerNodeProgram;
    private registerEdgeProgram;
    private unregisterNodeProgram;
    private unregisterEdgeProgram;
    private resetWebGLTexture;
    private bindCameraHandlers;
    private unbindCameraHandlers;
    private getNodeAtPosition;
    private bindEventHandlers;
    private bindGraphHandlers;
    private unbindGraphHandlers;
    private getEdgeAtPoint;
    private process;
    private handleSettingsUpdate;
    private cleanCameraState;
    private renderLabels;
    private renderEdgeLabels;
    private renderHighlightedNodes;
    private scheduleHighlightedNodesRender;
    private render;
    private addNode;
    private updateNode;
    private removeNode;
    private addEdge;
    private updateEdge;
    private removeEdge;
    private clearNodeIndices;
    private clearEdgeIndices;
    private clearIndices;
    private clearNodeState;
    private clearEdgeState;
    private clearState;
    private addNodeToProgram;
    private addEdgeToProgram;
    getRenderParams(): RenderParams;
    getStagePadding(): number;
    createLayer<T extends HTMLElement>(id: string, tag: string, options?: {
        style?: Partial<CSSStyleDeclaration>;
    } & ({
        beforeLayer?: string;
    } | {
        afterLayer?: string;
    })): T;
    createCanvas(id: string, options?: {
        style?: Partial<CSSStyleDeclaration>;
    } & ({
        beforeLayer?: string;
    } | {
        afterLayer?: string;
    })): HTMLCanvasElement;
    createCanvasContext(id: string, options?: {
        style?: Partial<CSSStyleDeclaration>;
    }): this;
    createWebGLContext(id: string, options?: {
        preserveDrawingBuffer?: boolean;
        antialias?: boolean;
        hidden?: boolean;
        picking?: boolean;
    } & ({
        canvas?: HTMLCanvasElement;
        style?: undefined;
    } | {
        style?: CSSStyleDeclaration;
        canvas?: undefined;
    })): WebGLRenderingContext;
    killLayer(id: string): this;
    getCamera(): Camera;
    setCamera(camera: Camera): void;
    getContainer(): HTMLElement;
    getGraph(): Graph<N, E, G>;
    setGraph(graph: Graph<N, E, G>): void;
    getMouseCaptor(): MouseCaptor<N, E, G>;
    getTouchCaptor(): TouchCaptor<N, E, G>;
    getDimensions(): Dimensions;
    getGraphDimensions(): Dimensions;
    getNodeDisplayData(key: unknown): NodeDisplayData | undefined;
    getEdgeDisplayData(key: unknown): EdgeDisplayData | undefined;
    getNodeDisplayedLabels(): Set<string>;
    getEdgeDisplayedLabels(): Set<string>;
    getSettings(): Settings<N, E, G>;
    getSetting<K extends keyof Settings<N, E, G>>(key: K): Settings<N, E, G>[K];
    setSetting<K extends keyof Settings<N, E, G>>(key: K, value: Settings<N, E, G>[K]): this;
    updateSetting<K extends keyof Settings<N, E, G>>(key: K, updater: (value: Settings<N, E, G>[K]) => Settings<N, E, G>[K]): this;
    setSettings(settings: Partial<Settings<N, E, G>>): this;
    resize(force?: boolean): this;
    clear(): this;
    refresh(opts?: {
        partialGraph?: {
            nodes?: string[];
            edges?: string[];
        };
        schedule?: boolean;
        skipIndexation?: boolean;
    }): this;
    scheduleRender(): this;
    scheduleRefresh(opts?: {
        partialGraph?: {
            nodes?: string[];
            edges?: string[];
        };
        layoutUnchange?: boolean;
    }): this;
    getViewportZoomedState(viewportTarget: Coordinates, newRatio: number): CameraState;
    viewRectangle(): {
        x1: number;
        y1: number;
        x2: number;
        y2: number;
        height: number;
    };
    framedGraphToViewport(coordinates: Coordinates, override?: CoordinateConversionOverride): Coordinates;
    viewportToFramedGraph(coordinates: Coordinates, override?: CoordinateConversionOverride): Coordinates;
    viewportToGraph(viewportPoint: Coordinates, override?: CoordinateConversionOverride): Coordinates;
    graphToViewport(graphPoint: Coordinates, override?: CoordinateConversionOverride): Coordinates;
    getGraphToViewportRatio(): number;
    getBBox(): {
        x: Extent;
        y: Extent;
    };
    getCustomBBox(): {
        x: Extent;
        y: Extent;
    } | null;
    setCustomBBox(customBBox: {
        x: Extent;
        y: Extent;
    } | null): this;
    kill(): void;
    scaleSize(size?: number, cameraRatio?: number): number;
    getCanvases(): PlainObject<HTMLCanvasElement>;
}
