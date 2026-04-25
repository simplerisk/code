/**
 * dd-draggable.ts 12.6.0
 * Copyright (c) 2021-2025  Alain Dumesny - see GridStack root license
 */
import { DDBaseImplement, HTMLElementExtendOpt } from './dd-base-impl';
import { GridItemHTMLElement, DDDragOpt } from './types';
type DDDragEvent = 'drag' | 'dragstart' | 'dragstop';
export declare class DDDraggable extends DDBaseImplement implements HTMLElementExtendOpt<DDDragOpt> {
    el: GridItemHTMLElement;
    option: DDDragOpt;
    helper: HTMLElement;
    protected _autoScrollContainer?: HTMLElement;
    protected _autoScrollMaxSpeed?: number;
    constructor(el: GridItemHTMLElement, option?: DDDragOpt);
    /** return all handles omitting other nested `.grid-stack-item` children (in case node.subGrid isn't set for some reason) */
    protected getAllHandles(): HTMLElement[];
    on(event: DDDragEvent, callback: (event: DragEvent) => void): void;
    off(event: DDDragEvent): void;
    enable(): void;
    disable(forDestroy?: boolean): void;
    destroy(): void;
    updateOption(opts: DDDragOpt): DDDraggable;
}
export {};
