import { Attributes } from "graphology-types";
import { EdgeLabelDrawingFunction, EdgeProgramType, NodeHoverDrawingFunction, NodeLabelDrawingFunction, NodeProgramType } from "./rendering/index.js";
import { AtLeastOne, EdgeDisplayData, NodeDisplayData } from "./types.js";
export interface Settings<N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes> {
    hideEdgesOnMove: boolean;
    hideLabelsOnMove: boolean;
    renderLabels: boolean;
    renderEdgeLabels: boolean;
    enableEdgeEvents: boolean;
    defaultNodeColor: string;
    defaultNodeType: string;
    defaultEdgeColor: string;
    defaultEdgeType: string;
    labelFont: string;
    labelSize: number;
    labelWeight: string;
    labelColor: {
        attribute: string;
        color?: string;
    } | {
        color: string;
        attribute?: undefined;
    };
    edgeLabelFont: string;
    edgeLabelSize: number;
    edgeLabelWeight: string;
    edgeLabelColor: {
        attribute: string;
        color?: string;
    } | {
        color: string;
        attribute?: undefined;
    };
    stagePadding: number;
    defaultDrawEdgeLabel: EdgeLabelDrawingFunction<N, E, G>;
    defaultDrawNodeLabel: NodeLabelDrawingFunction<N, E, G>;
    defaultDrawNodeHover: NodeHoverDrawingFunction<N, E, G>;
    minEdgeThickness: number;
    antiAliasingFeather: number;
    dragTimeout: number;
    draggedEventsTolerance: number;
    inertiaDuration: number;
    inertiaRatio: number;
    zoomDuration: number;
    zoomingRatio: number;
    doubleClickTimeout: number;
    doubleClickZoomingRatio: number;
    doubleClickZoomingDuration: number;
    tapMoveTolerance: number;
    zoomToSizeRatioFunction: (ratio: number) => number;
    itemSizesReference: "screen" | "positions";
    autoRescale: boolean;
    autoCenter: boolean;
    labelDensity: number;
    labelGridCellSize: number;
    labelRenderedSizeThreshold: number;
    nodeReducer: null | ((node: string, data: N) => Partial<NodeDisplayData>);
    edgeReducer: null | ((edge: string, data: E) => Partial<EdgeDisplayData>);
    zIndex: boolean;
    minCameraRatio: null | number;
    maxCameraRatio: null | number;
    enableCameraZooming: boolean;
    enableCameraPanning: boolean;
    enableCameraRotation: boolean;
    cameraPanBoundaries: null | true | AtLeastOne<{
        tolerance: number;
        boundaries: {
            x: [number, number];
            y: [number, number];
        };
    }>;
    allowInvalidContainer: boolean;
    nodeProgramClasses: {
        [type: string]: NodeProgramType<N, E, G>;
    };
    nodeHoverProgramClasses: {
        [type: string]: NodeProgramType<N, E, G>;
    };
    edgeProgramClasses: {
        [type: string]: EdgeProgramType<N, E, G>;
    };
}
export declare const DEFAULT_SETTINGS: Settings<Attributes, Attributes, Attributes>;
export declare const DEFAULT_NODE_PROGRAM_CLASSES: Record<string, NodeProgramType>;
export declare const DEFAULT_EDGE_PROGRAM_CLASSES: Record<string, EdgeProgramType>;
export declare function validateSettings<N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes>(settings: Settings<N, E, G>): void;
export declare function resolveSettings<N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes>(settings: Partial<Settings<N, E, G>>): Settings<N, E, G>;
