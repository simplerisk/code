/**
 * gridstack.component.ts 12.4.2
 * Copyright (c) 2022-2024 Alain Dumesny - see GridStack root license
 */
import { Component, ContentChildren, EventEmitter, Input, Output, ViewChild, ViewContainerRef, reflectComponentType } from '@angular/core';
import { NgIf } from '@angular/common';
import { GridStack } from 'gridstack';
import { GridstackItemComponent } from './gridstack-item.component';
import * as i0 from "@angular/core";
/**
 * Angular component wrapper for GridStack.
 *
 * This component provides Angular integration for GridStack grids, handling:
 * - Grid initialization and lifecycle
 * - Dynamic component creation and management
 * - Event binding and emission
 * - Integration with Angular change detection
 *
 * Use in combination with GridstackItemComponent for individual grid items.
 *
 * @example
 * ```html
 * <gridstack [options]="gridOptions" (change)="onGridChange($event)">
 *   <div empty-content>Drag widgets here</div>
 * </gridstack>
 * ```
 */
export class GridstackComponent {
    constructor(elementRef) {
        this.elementRef = elementRef;
        /**
         * GridStack event emitters for Angular integration.
         *
         * These provide Angular-style event handling for GridStack events.
         * Alternatively, use `this.grid.on('event1 event2', callback)` for multiple events.
         *
         * Note: 'CB' suffix prevents conflicts with native DOM events.
         *
         * @example
         * ```html
         * <gridstack (changeCB)="onGridChange($event)" (droppedCB)="onItemDropped($event)">
         * </gridstack>
         * ```
         */
        /** Emitted when widgets are added to the grid */
        this.addedCB = new EventEmitter();
        /** Emitted when grid layout changes */
        this.changeCB = new EventEmitter();
        /** Emitted when grid is disabled */
        this.disableCB = new EventEmitter();
        /** Emitted during widget drag operations */
        this.dragCB = new EventEmitter();
        /** Emitted when widget drag starts */
        this.dragStartCB = new EventEmitter();
        /** Emitted when widget drag stops */
        this.dragStopCB = new EventEmitter();
        /** Emitted when widget is dropped */
        this.droppedCB = new EventEmitter();
        /** Emitted when grid is enabled */
        this.enableCB = new EventEmitter();
        /** Emitted when widgets are removed from the grid */
        this.removedCB = new EventEmitter();
        /** Emitted during widget resize operations */
        this.resizeCB = new EventEmitter();
        /** Emitted when widget resize starts */
        this.resizeStartCB = new EventEmitter();
        /** Emitted when widget resize stops */
        this.resizeStopCB = new EventEmitter();
        // set globally our method to create the right widget type
        if (!GridStack.addRemoveCB) {
            GridStack.addRemoveCB = gsCreateNgComponents;
        }
        if (!GridStack.saveCB) {
            GridStack.saveCB = gsSaveAdditionalNgInfo;
        }
        if (!GridStack.updateCB) {
            GridStack.updateCB = gsUpdateNgComponents;
        }
        this.el._gridComp = this;
    }
    /**
     * Grid configuration options.
     * Can be set before grid initialization or updated after grid is created.
     *
     * @example
     * ```typescript
     * gridOptions: GridStackOptions = {
     *   column: 12,
     *   cellHeight: 'auto',
     *   animate: true
     * };
     * ```
     */
    set options(o) {
        if (this._grid) {
            this._grid.updateOptions(o);
        }
        else {
            this._options = o;
        }
    }
    /** Get the current running grid options */
    get options() { return this._grid?.opts || this._options || {}; }
    /**
     * Get the native DOM element that contains grid-specific fields.
     * This element has GridStack properties attached to it.
     */
    get el() { return this.elementRef.nativeElement; }
    /**
     * Get the underlying GridStack instance.
     * Use this to access GridStack API methods directly.
     *
     * @example
     * ```typescript
     * this.gridComponent.grid.addWidget({x: 0, y: 0, w: 2, h: 1});
     * ```
     */
    get grid() { return this._grid; }
    /**
     * Register a list of Angular components for dynamic creation.
     *
     * @param typeList Array of component types to register
     *
     * @example
     * ```typescript
     * GridstackComponent.addComponentToSelectorType([
     *   MyWidgetComponent,
     *   AnotherWidgetComponent
     * ]);
     * ```
     */
    static addComponentToSelectorType(typeList) {
        typeList.forEach(type => GridstackComponent.selectorToType[GridstackComponent.getSelector(type)] = type);
    }
    /**
     * Extract the selector string from an Angular component type.
     *
     * @param type The component type to get selector from
     * @returns The component's selector string
     */
    static getSelector(type) {
        // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
        return reflectComponentType(type).selector;
    }
    ngOnInit() {
        // init ourself before any template children are created since we track them below anyway - no need to double create+update widgets
        this.loaded = !!this.options?.children?.length;
        this._grid = GridStack.init(this._options, this.el);
        delete this._options; // GS has it now
        this.checkEmpty();
    }
    /** wait until after all DOM is ready to init gridstack children (after angular ngFor and sub-components run first) */
    ngAfterContentInit() {
        // track whenever the children list changes and update the layout...
        this._sub = this.gridstackItems?.changes.subscribe(() => this.updateAll());
        // ...and do this once at least unless we loaded children already
        if (!this.loaded)
            this.updateAll();
        this.hookEvents(this.grid);
    }
    ngOnDestroy() {
        this.unhookEvents(this._grid);
        this._sub?.unsubscribe();
        this._grid?.destroy();
        delete this._grid;
        delete this.el._gridComp;
        delete this.container;
        delete this.ref;
    }
    /**
     * called when the TEMPLATE (not recommended) list of items changes - get a list of nodes and
     * update the layout accordingly (which will take care of adding/removing items changed by Angular)
     */
    updateAll() {
        if (!this.grid)
            return;
        const layout = [];
        this.gridstackItems?.forEach(item => {
            layout.push(item.options);
            item.clearOptions();
        });
        this.grid.load(layout); // efficient that does diffs only
    }
    /** check if the grid is empty, if so show alternative content */
    checkEmpty() {
        if (!this.grid)
            return;
        this.isEmpty = !this.grid.engine.nodes.length;
    }
    /** get all known events as easy to use Outputs for convenience */
    hookEvents(grid) {
        if (!grid)
            return;
        // nested grids don't have events in v12.1+ so skip
        if (grid.parentGridNode)
            return;
        grid
            .on('added', (event, nodes) => {
            const gridComp = nodes[0].grid?.el._gridComp || this;
            gridComp.checkEmpty();
            this.addedCB.emit({ event, nodes });
        })
            .on('change', (event, nodes) => this.changeCB.emit({ event, nodes }))
            .on('disable', (event) => this.disableCB.emit({ event }))
            .on('drag', (event, el) => this.dragCB.emit({ event, el }))
            .on('dragstart', (event, el) => this.dragStartCB.emit({ event, el }))
            .on('dragstop', (event, el) => this.dragStopCB.emit({ event, el }))
            .on('dropped', (event, previousNode, newNode) => this.droppedCB.emit({ event, previousNode, newNode }))
            .on('enable', (event) => this.enableCB.emit({ event }))
            .on('removed', (event, nodes) => {
            const gridComp = nodes[0].grid?.el._gridComp || this;
            gridComp.checkEmpty();
            this.removedCB.emit({ event, nodes });
        })
            .on('resize', (event, el) => this.resizeCB.emit({ event, el }))
            .on('resizestart', (event, el) => this.resizeStartCB.emit({ event, el }))
            .on('resizestop', (event, el) => this.resizeStopCB.emit({ event, el }));
    }
    unhookEvents(grid) {
        if (!grid)
            return;
        // nested grids don't have events in v12.1+ so skip
        if (grid.parentGridNode)
            return;
        grid.off('added change disable drag dragstart dragstop dropped enable removed resize resizestart resizestop');
    }
}
/**
 * Mapping of component selectors to their types for dynamic creation.
 *
 * This enables dynamic component instantiation from string selectors.
 * Angular doesn't provide public access to this mapping, so we maintain our own.
 *
 * @example
 * ```typescript
 * GridstackComponent.addComponentToSelectorType([MyWidgetComponent]);
 * ```
 */
GridstackComponent.selectorToType = {};
GridstackComponent.ɵfac = i0.ɵɵngDeclareFactory({ minVersion: "12.0.0", version: "14.3.0", ngImport: i0, type: GridstackComponent, deps: [{ token: i0.ElementRef }], target: i0.ɵɵFactoryTarget.Component });
GridstackComponent.ɵcmp = i0.ɵɵngDeclareComponent({ minVersion: "14.0.0", version: "14.3.0", type: GridstackComponent, isStandalone: true, selector: "gridstack", inputs: { options: "options", isEmpty: "isEmpty" }, outputs: { addedCB: "addedCB", changeCB: "changeCB", disableCB: "disableCB", dragCB: "dragCB", dragStartCB: "dragStartCB", dragStopCB: "dragStopCB", droppedCB: "droppedCB", enableCB: "enableCB", removedCB: "removedCB", resizeCB: "resizeCB", resizeStartCB: "resizeStartCB", resizeStopCB: "resizeStopCB" }, queries: [{ propertyName: "gridstackItems", predicate: GridstackItemComponent }], viewQueries: [{ propertyName: "container", first: true, predicate: ["container"], descendants: true, read: ViewContainerRef, static: true }], ngImport: i0, template: `
    <!-- content to show when when grid is empty, like instructions on how to add widgets -->
    <ng-content select="[empty-content]" *ngIf="isEmpty"></ng-content>
    <!-- where dynamic items go -->
    <ng-template #container></ng-template>
    <!-- where template items go -->
    <ng-content></ng-content>
  `, isInline: true, styles: [":host{display:block}\n"], dependencies: [{ kind: "directive", type: NgIf, selector: "[ngIf]", inputs: ["ngIf", "ngIfThen", "ngIfElse"] }] });
i0.ɵɵngDeclareClassMetadata({ minVersion: "12.0.0", version: "14.3.0", ngImport: i0, type: GridstackComponent, decorators: [{
            type: Component,
            args: [{ selector: 'gridstack', template: `
    <!-- content to show when when grid is empty, like instructions on how to add widgets -->
    <ng-content select="[empty-content]" *ngIf="isEmpty"></ng-content>
    <!-- where dynamic items go -->
    <ng-template #container></ng-template>
    <!-- where template items go -->
    <ng-content></ng-content>
  `, standalone: true, imports: [NgIf], styles: [":host{display:block}\n"] }]
        }], ctorParameters: function () { return [{ type: i0.ElementRef }]; }, propDecorators: { gridstackItems: [{
                type: ContentChildren,
                args: [GridstackItemComponent]
            }], container: [{
                type: ViewChild,
                args: ['container', { read: ViewContainerRef, static: true }]
            }], options: [{
                type: Input
            }], isEmpty: [{
                type: Input
            }], addedCB: [{
                type: Output
            }], changeCB: [{
                type: Output
            }], disableCB: [{
                type: Output
            }], dragCB: [{
                type: Output
            }], dragStartCB: [{
                type: Output
            }], dragStopCB: [{
                type: Output
            }], droppedCB: [{
                type: Output
            }], enableCB: [{
                type: Output
            }], removedCB: [{
                type: Output
            }], resizeCB: [{
                type: Output
            }], resizeStartCB: [{
                type: Output
            }], resizeStopCB: [{
                type: Output
            }] } });
/**
 * can be used when a new item needs to be created, which we do as a Angular component, or deleted (skip)
 **/
export function gsCreateNgComponents(host, n, add, isGrid) {
    if (add) {
        //
        // create the component dynamically - see https://angular.io/docs/ts/latest/cookbook/dynamic-component-loader.html
        //
        if (!host)
            return;
        if (isGrid) {
            // TODO: figure out how to create ng component inside regular Div. need to access app injectors...
            // if (!container) {
            //   const hostElement: Element = host;
            //   const environmentInjector: EnvironmentInjector;
            //   grid = createComponent(GridstackComponent, {environmentInjector, hostElement})?.instance;
            // }
            const gridItemComp = host.parentElement?._gridItemComp;
            if (!gridItemComp)
                return;
            // check if gridItem has a child component with 'container' exposed to create under..
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            const container = gridItemComp.childWidget?.container || gridItemComp.container;
            const gridRef = container?.createComponent(GridstackComponent);
            const grid = gridRef?.instance;
            if (!grid)
                return;
            grid.ref = gridRef;
            grid.options = n;
            return grid.el;
        }
        else {
            const gridComp = host._gridComp;
            const gridItemRef = gridComp?.container?.createComponent(GridstackItemComponent);
            const gridItem = gridItemRef?.instance;
            if (!gridItem)
                return;
            gridItem.ref = gridItemRef;
            // define what type of component to create as child, OR you can do it GridstackItemComponent template, but this is more generic
            const selector = n.selector;
            const type = selector ? GridstackComponent.selectorToType[selector] : undefined;
            if (type) {
                // shared code to create our selector component
                const createComp = () => {
                    const childWidget = gridItem.container?.createComponent(type)?.instance;
                    // if proper BaseWidget subclass, save it and load additional data
                    if (childWidget && typeof childWidget.serialize === 'function' && typeof childWidget.deserialize === 'function') {
                        gridItem.childWidget = childWidget;
                        childWidget.deserialize(n);
                    }
                };
                const lazyLoad = n.lazyLoad || n.grid?.opts?.lazyLoad && n.lazyLoad !== false;
                if (lazyLoad) {
                    if (!n.visibleObservable) {
                        n.visibleObservable = new IntersectionObserver(([entry]) => {
                            if (entry.isIntersecting) {
                                n.visibleObservable?.disconnect();
                                delete n.visibleObservable;
                                createComp();
                            }
                        });
                        window.setTimeout(() => n.visibleObservable?.observe(gridItem.el)); // wait until callee sets position attributes
                    }
                }
                else
                    createComp();
            }
            return gridItem.el;
        }
    }
    else {
        //
        // REMOVE - have to call ComponentRef:destroy() for dynamic objects to correctly remove themselves
        // Note: this will destroy all children dynamic components as well: gridItem -> childWidget
        //
        if (isGrid) {
            const grid = n.el?._gridComp;
            if (grid?.ref)
                grid.ref.destroy();
            else
                grid?.ngOnDestroy();
        }
        else {
            const gridItem = n.el?._gridItemComp;
            if (gridItem?.ref)
                gridItem.ref.destroy();
            else
                gridItem?.ngOnDestroy();
        }
    }
    return;
}
/**
 * called for each item in the grid - check if additional information needs to be saved.
 * Note: since this is options minus gridstack protected members using Utils.removeInternalForSave(),
 * this typically doesn't need to do anything. However your custom Component @Input() are now supported
 * using BaseWidget.serialize()
 */
export function gsSaveAdditionalNgInfo(n, w) {
    const gridItem = n.el?._gridItemComp;
    if (gridItem) {
        const input = gridItem.childWidget?.serialize();
        if (input) {
            w.input = input;
        }
        return;
    }
    // else check if Grid
    const grid = n.el?._gridComp;
    if (grid) {
        //.... save any custom data
    }
}
/**
 * track when widgeta re updated (rather than created) to make sure we de-serialize them as well
 */
export function gsUpdateNgComponents(n) {
    const w = n;
    const gridItem = n.el?._gridItemComp;
    if (gridItem?.childWidget && w.input)
        gridItem.childWidget.deserialize(w);
}
//# sourceMappingURL=data:application/json;base64,eyJ2ZXJzaW9uIjozLCJmaWxlIjoiZ3JpZHN0YWNrLmNvbXBvbmVudC5qcyIsInNvdXJjZVJvb3QiOiIiLCJzb3VyY2VzIjpbIi4uLy4uLy4uLy4uL2FuZ3VsYXIvcHJvamVjdHMvbGliL3NyYy9saWIvZ3JpZHN0YWNrLmNvbXBvbmVudC50cyJdLCJuYW1lcyI6W10sIm1hcHBpbmdzIjoiQUFBQTs7O0dBR0c7QUFFSCxPQUFPLEVBQ2EsU0FBUyxFQUFFLGVBQWUsRUFBYyxZQUFZLEVBQUUsS0FBSyxFQUMxRCxNQUFNLEVBQW1CLFNBQVMsRUFBRSxnQkFBZ0IsRUFBRSxvQkFBb0IsRUFDOUYsTUFBTSxlQUFlLENBQUM7QUFDdkIsT0FBTyxFQUFFLElBQUksRUFBRSxNQUFNLGlCQUFpQixDQUFDO0FBRXZDLE9BQU8sRUFBd0MsU0FBUyxFQUFvRCxNQUFNLFdBQVcsQ0FBQztBQUk5SCxPQUFPLEVBQTJCLHNCQUFzQixFQUFFLE1BQU0sNEJBQTRCLENBQUM7O0FBa0M3Rjs7Ozs7Ozs7Ozs7Ozs7Ozs7R0FpQkc7QUFrQkgsTUFBTSxPQUFPLGtCQUFrQjtJQXdLN0IsWUFBK0IsVUFBMkM7UUFBM0MsZUFBVSxHQUFWLFVBQVUsQ0FBaUM7UUF0SDFFOzs7Ozs7Ozs7Ozs7O1dBYUc7UUFFSCxpREFBaUQ7UUFDaEMsWUFBTyxHQUFHLElBQUksWUFBWSxFQUFXLENBQUM7UUFFdkQsdUNBQXVDO1FBQ3RCLGFBQVEsR0FBRyxJQUFJLFlBQVksRUFBVyxDQUFDO1FBRXhELG9DQUFvQztRQUNuQixjQUFTLEdBQUcsSUFBSSxZQUFZLEVBQVcsQ0FBQztRQUV6RCw0Q0FBNEM7UUFDM0IsV0FBTSxHQUFHLElBQUksWUFBWSxFQUFhLENBQUM7UUFFeEQsc0NBQXNDO1FBQ3JCLGdCQUFXLEdBQUcsSUFBSSxZQUFZLEVBQWEsQ0FBQztRQUU3RCxxQ0FBcUM7UUFDcEIsZUFBVSxHQUFHLElBQUksWUFBWSxFQUFhLENBQUM7UUFFNUQscUNBQXFDO1FBQ3BCLGNBQVMsR0FBRyxJQUFJLFlBQVksRUFBYSxDQUFDO1FBRTNELG1DQUFt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RSxTQUFTLENBQUM7SUFDdEQsSUFBSSxJQUFJLEVBQUU7UUFDUiwyQkFBMkI7S0FDNUI7QUFDSCxDQUFDO0FBRUQ7O0dBRUc7QUFDSCxNQUFNLFVBQVUsb0JBQW9CLENBQUMsQ0FBa0I7SUFDckQsTUFBTSxDQUFDLEdBQXNCLENBQUMsQ0FBQztJQUMvQixNQUFNLFFBQVEsR0FBSSxDQUFDLENBQUMsRUFBOEIsRUFBRSxhQUFhLENBQUM7SUFDbEUsSUFBSSxRQUFRLEVBQUUsV0FBVyxJQUFJLENBQUMsQ0FBQyxLQUFLO1FBQUUsUUFBUSxDQUFDLFdBQVcsQ0FBQyxXQUFXLENBQUMsQ0FBQyxDQUFDLENBQUM7QUFDNUUsQ0FBQyIsInNvdXJjZXNDb250ZW50IjpbIi8qKlxuICogZ3JpZHN0YWNrLmNvbXBvbmVudC50cyAxMi40LjJcbiAqIENvcHlyaWdodCAoYykgMjAyMi0yMDI0IEFsYWluIER1bWVzbnkgLSBzZWUgR3JpZFN0YWNrIHJvb3QgbGljZW5zZVxuICovXG5cbmltcG9ydCB7XG4gIEFmdGVyQ29udGVudEluaXQsIENvbXBvbmVudCwgQ29udGVudENoaWxkcmVuLCBFbGVtZW50UmVmLCBFdmVudEVtaXR0ZXIsIElucHV0LFxuICBPbkRlc3Ryb3ksIE9uSW5pdCwgT3V0cHV0LCBRdWVyeUxpc3QsIFR5cGUsIFZpZXdDaGlsZCwgVmlld0NvbnRhaW5lclJlZiwgcmVmbGVjdENvbXBvbmVudFR5cGUsIENvbXBvbmVudFJlZlxufSBmcm9tICdAYW5ndWxhci9jb3JlJztcbmltcG9ydCB7IE5nSWYgfSBmcm9tICdAYW5ndWxhci9jb21tb24nO1xuaW1wb3J0IHsgU3Vic2NyaXB0aW9uIH0gZnJvbSAncnhqcyc7XG5pbXBvcnQgeyBHcmlkSFRNTEVsZW1lbnQsIEdyaWRJdGVtSFRNTEVsZW1lbnQsIEdyaWRTdGFjaywgR3JpZFN0YWNrTm9kZSwgR3JpZFN0YWNrT3B0aW9ucywgR3JpZFN0YWNrV2lkZ2V0IH0gZnJvbSAnZ3JpZHN0YWNrJztcblxuaW1wb3J0IHsgTmdHcmlkU3RhY2tOb2RlLCBOZ0dyaWRTdGFja1dpZGdldCB9IGZyb20gJy4vdHlwZXMnO1xuaW1wb3J0IHsgQmFzZVdpZGdldCB9IGZyb20gJy4vYmFzZS13aWRnZXQnO1xuaW1wb3J0IHsgR3JpZEl0ZW1Db21wSFRNTEVsZW1lbnQsIEdyaWRzdGFja0l0ZW1Db21wb25lbnQgfSBmcm9tICcuL2dyaWRzdGFjay1pdGVtLmNvbXBvbmVudCc7XG5cbi8qKlxuICogRXZlbnQgaGFuZGxlciBjYWxsYmFjayBzaWduYXR1cmVzIGZvciBkaWZmZXJlbnQgR3JpZFN0YWNrIGV2ZW50cy5cbiAqIFRoZXNlIHR5cGVzIGRlZmluZSB0aGUgc3RydWN0dXJlIG9mIGRhdGEgcGFzc2VkIHRvIEFuZ3VsYXIgZXZlbnQgZW1pdHRlcnMuXG4gKi9cblxuLyoqIENhbGxiYWNrIGZvciBnZW5lcmFsIGV2ZW50cyAoZW5hYmxlLCBkaXNhYmxlLCBldGMuKSAqL1xuZXhwb3J0IHR5cGUgZXZlbnRDQiA9IHtldmVudDogRXZlbnR9O1xuXG4vKiogQ2FsbGJhY2sgZm9yIGVsZW1lbnQtc3BlY2lmaWMgZXZlbnRzIChyZXNpemUsIGRyYWcsIGV0Yy4pICovXG5leHBvcnQgdHlwZSBlbGVtZW50Q0IgPSB7ZXZlbnQ6IEV2ZW50LCBlbDogR3JpZEl0ZW1IVE1MRWxlbWVudH07XG5cbi8qKiBDYWxsYmFjayBmb3IgZXZlbnRzIGFmZmVjdGluZyBtdWx0aXBsZSBub2RlcyAoY2hhbmdlLCBldGMuKSAqL1xuZXhwb3J0IHR5cGUgbm9kZXNDQiA9IHtldmVudDogRXZlbnQsIG5vZGVzOiBHcmlkU3RhY2tOb2RlW119O1xuXG4vKiogQ2FsbGJhY2sgZm9yIGRyb3AgZXZlbnRzIHdpdGggYmVmb3JlL2FmdGVyIG5vZGUgc3RhdGUgKi9cbmV4cG9ydCB0eXBlIGRyb3BwZWRDQiA9IHtldmVudDogRXZlbnQsIHByZXZpb3VzTm9kZTogR3JpZFN0YWNrTm9kZSwgbmV3Tm9kZTogR3JpZFN0YWNrTm9kZX07XG5cbi8qKlxuICogRXh0ZW5kZWQgSFRNTEVsZW1lbnQgaW50ZXJmYWNlIGZvciB0aGUgZ3JpZCBjb250YWluZXIuXG4gKiBTdG9yZXMgYSBiYWNrLXJlZmVyZW5jZSB0byB0aGUgQW5ndWxhciBjb21wb25lbnQgZm9yIGludGVncmF0aW9uIHB1cnBvc2VzLlxuICovXG5leHBvcnQgaW50ZXJmYWNlIEdyaWRDb21wSFRNTEVsZW1lbnQgZXh0ZW5kcyBHcmlkSFRNTEVsZW1lbnQge1xuICAvKiogQmFjay1yZWZlcmVuY2UgdG8gdGhlIEFuZ3VsYXIgR3JpZFN0YWNrIGNvbXBvbmVudCAqL1xuICBfZ3JpZENvbXA/OiBHcmlkc3RhY2tDb21wb25lbnQ7XG59XG5cbi8qKlxuICogTWFwcGluZyBvZiBzZWxlY3RvciBzdHJpbmdzIHRvIEFuZ3VsYXIgY29tcG9uZW50IHR5cGVzLlxuICogVXNlZCBmb3IgZHluYW1pYyBjb21wb25lbnQgY3JlYXRpb24gYmFzZWQgb24gd2lkZ2V0IHNlbGVjdG9ycy5cbiAqL1xuZXhwb3J0IHR5cGUgU2VsZWN0b3JUb1R5cGUgPSB7W2tleTogc3RyaW5nXTogVHlwZTxvYmplY3Q+fTtcblxuLyoqXG4gKiBBbmd1bGFyIGNvbXBvbmVudCB3cmFwcGVyIGZvciBHcmlkU3RhY2suXG4gKlxuICogVGhpcyBjb21wb25lbnQgcHJvdmlkZXMgQW5ndWxhciBpbnRlZ3JhdGlvbiBmb3IgR3JpZFN0YWNrIGdyaWRzLCBoYW5kbGluZzpcbiAqIC0gR3JpZCBpbml0aWFsaXphdGlvbiBhbmQgbGlmZWN5Y2xlXG4gKiAtIER5bmFtaWMgY29tcG9uZW50IGNyZWF0aW9uIGFuZCBtYW5hZ2VtZW50XG4gKiAtIEV2ZW50IGJpbmRpbmcgYW5kIGVtaXNzaW9uXG4gKiAtIEludGVncmF0aW9uIHdpdGggQW5ndWxhciBjaGFuZ2UgZGV0ZWN0aW9uXG4gKlxuICogVXNlIGluIGNvbWJpbmF0aW9uIHdpdGggR3JpZHN0YWNrSXRlbUNvbXBvbmVudCBmb3IgaW5kaXZpZHVhbCBncmlkIGl0ZW1zLlxuICpcbiAqIEBleGFtcGxlXG4gKiBgYGBodG1sXG4gKiA8Z3JpZHN0YWNrIFtvcHRpb25zXT1cImdyaWRPcHRpb25zXCIgKGNoYW5nZSk9XCJvbkdyaWRDaGFuZ2UoJGV2ZW50KVwiPlxuICogICA8ZGl2IGVtcHR5LWNvbnRlbnQ+RHJhZyB3aWRnZXRzIGhlcmU8L2Rpdj5cbiAqIDwvZ3JpZHN0YWNrPlxuICogYGBgXG4gKi9cbkBDb21wb25lbnQoe1xuICBzZWxlY3RvcjogJ2dyaWRzdGFjaycsXG4gIHRlbXBsYXRlOiBgXG4gICAgPCEtLSBjb250ZW50IHRvIHNob3cgd2hlbiB3aGVuIGdyaWQgaXMgZW1wdHksIGxpa2UgaW5zdHJ1Y3Rpb25zIG9uIGhvdyB0byBhZGQgd2lkZ2V0cyAtLT5cbiAgICA8bmctY29udGVudCBzZWxlY3Q9XCJbZW1wdHktY29udGVudF1cIiAqbmdJZj1cImlzRW1wdHlcIj48L25nLWNvbnRlbnQ+XG4gICAgPCEtLSB3aGVyZSBkeW5hbWljIGl0ZW1zIGdvIC0tPlxuICAgIDxuZy10ZW1wbGF0ZSAjY29udGFpbmVyPjwvbmctdGVtcGxhdGU+XG4gICAgPCEtLSB3aGVyZSB0ZW1wbGF0ZSBpdGVtcyBnbyAtLT5cbiAgICA8bmctY29udGVudD48L25nLWNvbnRlbnQ+XG4gIGAsXG4gIHN0eWxlczogW2BcbiAgICA6aG9zdCB7IGRpc3BsYXk6IGJsb2NrOyB9XG4gIGBdLFxuICBzdGFuZGFsb25lOiB0cnVlLFxuICBpbXBvcnRzOiBbTmdJZl1cbiAgLy8gY2hhbmdlRGV0ZWN0aW9uOiBDaGFuZ2VEZXRlY3Rpb25TdHJhdGVneS5PblB1c2gsIC8vIElGRiB5b3Ugd2FudCB0byBvcHRpbWl6ZSBhbmQgY29udHJvbCB3aGVuIENoYW5nZURldGVjdGlvbiBuZWVkcyB0byBoYXBwZW4uLi5cbn0pXG5leHBvcnQgY2xhc3MgR3JpZHN0YWNrQ29tcG9uZW50IGltcGxlbWVudHMgT25Jbml0LCBBZnRlckNvbnRlbnRJbml0LCBPbkRlc3Ryb3kge1xuXG4gIC8qKlxuICAgKiBMaXN0IG9mIHRlbXBsYXRlLWJhc2VkIGdyaWQgaXRlbXMgKG5vdCByZWNvbW1lbmRlZCBhcHByb2FjaCkuXG4gICAqIFVzZWQgdG8gc3luYyBiZXR3ZWVuIERPTSBhbmQgR3JpZFN0YWNrIGludGVybmFscyB3aGVuIGl0ZW1zIGFyZSBkZWZpbmVkIGluIHRlbXBsYXRlcy5cbiAgICogUHJlZmVyIGR5bmFtaWMgY29tcG9uZW50IGNyZWF0aW9uIGluc3RlYWQuXG4gICAqL1xuICBAQ29udGVudENoaWxkcmVuKEdyaWRzdGFja0l0ZW1Db21wb25lbnQpIHB1YmxpYyBncmlkc3RhY2tJdGVtcz86IFF1ZXJ5TGlzdDxHcmlkc3RhY2tJdGVtQ29tcG9uZW50PjtcbiAgLyoqXG4gICAqIENvbnRhaW5lciBmb3IgZHluYW1pYyBjb21wb25lbnQgY3JlYXRpb24gKHJlY29tbWVuZGVkIGFwcHJvYWNoKS5cbiAgICogVXNlZCB0byBhcHBlbmQgZ3JpZCBpdGVtcyBwcm9ncmFtbWF0aWNhbGx5IGF0IHJ1bnRpbWUuXG4gICAqL1xuICBAVmlld0NoaWxkKCdjb250YWluZXInLCB7IHJlYWQ6IFZpZXdDb250YWluZXJSZWYsIHN0YXRpYzogdHJ1ZX0pIHB1YmxpYyBjb250YWluZXI/OiBWaWV3Q29udGFpbmVyUmVmO1xuXG4gIC8qKlxuICAgKiBHcmlkIGNvbmZpZ3VyYXRpb24gb3B0aW9ucy5cbiAgICogQ2FuIGJlIHNldCBiZWZvcmUgZ3JpZCBpbml0aWFsaXphdGlvbiBvciB1cGRhdGVkIGFmdGVyIGdyaWQgaXMgY3JlYXRlZC5cbiAgICpcbiAgICogQGV4YW1wbGVcbiAgICogYGBgdHlwZXNjcmlwdFxuICAgKiBncmlkT3B0aW9uczogR3JpZFN0YWNrT3B0aW9ucyA9IHtcbiAgICogICBjb2x1bW46IDEyLFxuICAgKiAgIGNlbGxIZWlnaHQ6ICdhdXRvJyxcbiAgICogICBhbmltYXRlOiB0cnVlXG4gICAqIH07XG4gICAqIGBgYFxuICAgKi9cbiAgQElucHV0KCkgcHVibGljIHNldCBvcHRpb25zKG86IEdyaWRTdGFja09wdGlvbnMpIHtcbiAgICBpZiAodGhpcy5fZ3JpZCkge1xuICAgICAgdGhpcy5fZ3JpZC51cGRhdGVPcHRpb25zKG8pO1xuICAgIH0gZWxzZSB7XG4gICAgICB0aGlzLl9vcHRpb25zID0gbztcbiAgICB9XG4gIH1cbiAgLyoqIEdldCB0aGUgY3VycmVudCBydW5uaW5nIGdyaWQgb3B0aW9ucyAqL1xuICBwdWJsaWMgZ2V0IG9wdGlvbnMoKTogR3JpZFN0YWNrT3B0aW9ucyB7IHJldHVybiB0aGlzLl9ncmlkPy5vcHRzIHx8IHRoaXMuX29wdGlvbnMgfHwge307IH1cblxuICAvKipcbiAgICogQ29udHJvbHMgd2hldGhlciBlbXB0eSBjb250ZW50IHNob3VsZCBiZSBkaXNwbGF5ZWQuXG4gICAqIFNldCB0byB0cnVlIHRvIHNob3cgbmctY29udGVudCB3aXRoICdlbXB0eS1jb250ZW50JyBzZWxlY3RvciB3aGVuIGdyaWQgaGFzIG5vIGl0ZW1zLlxuICAgKlxuICAgKiBAZXhhbXBsZVxuICAgKiBgYGBodG1sXG4gICAqIDxncmlkc3RhY2sgW2lzRW1wdHldPVwiZ3JpZEl0ZW1zLmxlbmd0aCA9PT0gMFwiPlxuICAgKiAgIDxkaXYgZW1wdHktY29udGVudD5EcmFnIHdpZGdldHMgaGVyZSB0byBnZXQgc3RhcnRlZDwvZGl2PlxuICAgKiA8L2dyaWRzdGFjaz5cbiAgICogYGBgXG4gICAqL1xuICBASW5wdXQoKSBwdWJsaWMgaXNFbXB0eT86IGJvb2xlYW47XG5cbiAgLyoqXG4gICAqIEdyaWRTdGFjayBldmVudCBlbWl0dGVycyBmb3IgQW5ndWxhciBpbnRlZ3JhdGlvbi5cbiAgICpcbiAgICogVGhlc2UgcHJvdmlkZSBBbmd1bGFyLXN0eWxlIGV2ZW50IGhhbmRsaW5nIGZvciBHcmlkU3RhY2sgZXZlbnRzLlxuICAgKiBBbHRlcm5hdGl2ZWx5LCB1c2UgYHRoaXMuZ3JpZC5vbignZXZlbnQxIGV2ZW50MicsIGNhbGxiYWNrKWAgZm9yIG11bHRpcGxlIGV2ZW50cy5cbiAgICpcbiAgICogTm90ZTogJ0NCJyBzdWZmaXggcHJldmVudHMgY29uZmxpY3RzIHdpdGggbmF0aXZlIERPTSBldmVudHMuXG4gICAqXG4gICAqIEBleGFtcGxlXG4gICAqIGBgYGh0bWxcbiAgICogPGdyaWRzdGFjayAoY2hhbmdlQ0IpPVwib25HcmlkQ2hhbmdlKCRldmVudClcIiAoZHJvcHBlZENCKT1cIm9uSXRlbURyb3BwZWQoJGV2ZW50KVwiPlxuICAgKiA8L2dyaWRzdGFjaz5cbiAgICogYGBgXG4gICAqL1xuXG4gIC8qKiBFbWl0dGVkIHdoZW4gd2lkZ2V0cyBhcmUgYWRkZWQgdG8gdGhlIGdyaWQgKi9cbiAgQE91dHB1dCgpIHB1YmxpYyBhZGRlZENCID0gbmV3IEV2ZW50RW1pdHRlcjxub2Rlc0NCPigpO1xuXG4gIC8qKiBFbWl0dGVkIHdoZW4gZ3JpZCBsYXlvdXQgY2hhbmdlcyAqL1xuICBAT3V0cHV0KCkgcHVibGljIGNoYW5nZUNCID0gbmV3IEV2ZW50RW1pdHRlcjxub2Rlc0NCPigpO1xuXG4gIC8qKiBFbWl0dGVkIHdoZW4gZ3JpZCBpcyBkaXNhYmxlZCAqL1xuICBAT3V0cHV0KCkgcHVibGljIGRpc2FibGVDQiA9IG5ldyBFdmVudEVtaXR0ZXI8ZXZlbnRDQj4oKTtcblxuICAvKiogRW1pdHRlZCBkdXJpbmcgd2lkZ2V0IGRyYWcgb3BlcmF0aW9ucyAqL1xuICBAT3V0cHV0KCkgcHVibGljIGRyYWdDQiA9IG5ldyBFdmVudEVtaXR0ZXI8ZWxlbWVudENCPigpO1xuXG4gIC8qKiBFbWl0dGVkIHdoZW4gd2lkZ2V0IGRyYWcgc3RhcnRzICovXG4gIEBPdXRwdXQoKSBwdWJsaWMgZHJhZ1N0YXJ0Q0IgPSBuZXcgRXZlbnRFbWl0dGVyPGVsZW1lbnRDQj4oKTtcblxuICAvKiogRW1pdHRlZCB3aGVuIHdpZGdldCBkcmFnIHN0b3BzICovXG4gIEBPdXRwdXQoKSBwdWJsaWMgZHJhZ1N0b3BDQiA9IG5ldyBFdmVudEVtaXR0ZXI8ZWxlbWVudENCPigpO1xuXG4gIC8qKiBFbWl0dGVkIHdoZW4gd2lkZ2V0IGlzIGRyb3BwZWQgKi9cbiAgQE91dHB1dCgpIHB1YmxpYyBkcm9wcGVkQ0IgPSBuZXcgRXZlbnRFbWl0dGVyPGRyb3BwZWRDQj4oKTtcblxuICAvKiogRW1pdHRlZCB3aGVuIGdyaWQgaXMgZW5hYmxlZCAqL1xuICBAT3V0cHV0KCkgcHVibGljIGVuYWJsZUNCID0gbmV3IEV2ZW50RW1pdHRlcjxldmVudENCPigpO1xuXG4gIC8qKiBFbWl0dGVkIHdoZW4gd2lkZ2V0cyBhcmUgcmVtb3ZlZCBmcm9tIHRoZSBncmlkICovXG4gIEBPdXRwdXQoKSBwdWJsaWMgcmVtb3ZlZENCID0gbmV3IEV2ZW50RW1pdHRlcjxub2Rlc0NCPigpO1xuXG4gIC8qKiBFbWl0dGVkIGR1cmluZyB3aWRnZXQgcmVzaXplIG9wZXJhdGlvbnMgKi9cbiAgQE91dHB1dCgpIHB1YmxpYyByZXNpemVDQiA9IG5ldyBFdmVudEVtaXR0ZXI8ZWxlbWVudENCPigpO1xuXG4gIC8qKiBFbWl0dGVkIHdoZW4gd2lkZ2V0IHJlc2l6ZSBzdGFydHMgKi9cbiAgQE91dHB1dCgpIHB1YmxpYyByZXNpemVTdGFydENCID0gbmV3IEV2ZW50RW1pdHRlcjxlbGVtZW50Q0I+KCk7XG5cbiAgLyoqIEVtaXR0ZWQgd2hlbiB3aWRnZXQgcmVzaXplIHN0b3BzICovXG4gIEBPdXRwdXQoKSBwdWJsaWMgcmVzaXplU3RvcENCID0gbmV3IEV2ZW50RW1pdHRlcjxlbGVtZW50Q0I+KCk7XG5cbiAgLyoqXG4gICAqIEdldCB0aGUgbmF0aXZlIERPTSBlbGVtZW50IHRoYXQgY29udGFpbnMgZ3JpZC1zcGVjaWZpYyBmaWVsZHMuXG4gICAqIFRoaXMgZWxlbWVudCBoYXMgR3JpZFN0YWNrIHByb3BlcnRpZXMgYXR0YWNoZWQgdG8gaXQuXG4gICAqL1xuICBwdWJsaWMgZ2V0IGVsKCk6IEdyaWRDb21wSFRNTEVsZW1lbnQgeyByZXR1cm4gdGhpcy5lbGVtZW50UmVmLm5hdGl2ZUVsZW1lbnQ7IH1cblxuICAvKipcbiAgICogR2V0IHRoZSB1bmRlcmx5aW5nIEdyaWRTdGFjayBpbnN0YW5jZS5cbiAgICogVXNlIHRoaXMgdG8gYWNjZXNzIEdyaWRTdGFjayBBUEkgbWV0aG9kcyBkaXJlY3RseS5cbiAgICpcbiAgICogQGV4YW1wbGVcbiAgICogYGBgdHlwZXNjcmlwdFxuICAgKiB0aGlzLmdyaWRDb21wb25lbnQuZ3JpZC5hZGRXaWRnZXQoe3g6IDAsIHk6IDAsIHc6IDIsIGg6IDF9KTtcbiAgICogYGBgXG4gICAqL1xuICBwdWJsaWMgZ2V0IGdyaWQoKTogR3JpZFN0YWNrIHwgdW5kZWZpbmVkIHsgcmV0dXJuIHRoaXMuX2dyaWQ7IH1cblxuICAvKipcbiAgICogQ29tcG9uZW50IHJlZmVyZW5jZSBmb3IgZHluYW1pYyBjb21wb25lbnQgcmVtb3ZhbC5cbiAgICogVXNlZCBpbnRlcm5hbGx5IHdoZW4gdGhpcyBjb21wb25lbnQgaXMgY3JlYXRlZCBkeW5hbWljYWxseS5cbiAgICovXG4gIHB1YmxpYyByZWY6IENvbXBvbmVudFJlZjxHcmlkc3RhY2tDb21wb25lbnQ+IHwgdW5kZWZpbmVkO1xuXG4gIC8qKlxuICAgKiBNYXBwaW5nIG9mIGNvbXBvbmVudCBzZWxlY3RvcnMgdG8gdGhlaXIgdHlwZXMgZm9yIGR5bmFtaWMgY3JlYXRpb24uXG4gICAqXG4gICAqIFRoaXMgZW5hYmxlcyBkeW5hbWljIGNvbXBvbmVudCBpbnN0YW50aWF0aW9uIGZyb20gc3RyaW5nIHNlbGVjdG9ycy5cbiAgICogQW5ndWxhciBkb2Vzbid0IHByb3ZpZGUgcHVibGljIGFjY2VzcyB0byB0aGlzIG1hcHBpbmcsIHNvIHdlIG1haW50YWluIG91ciBvd24uXG4gICAqXG4gICAqIEBleGFtcGxlXG4gICAqIGBgYHR5cGVzY3JpcHRcbiAgICogR3JpZHN0YWNrQ29tcG9uZW50LmFkZENvbXBvbmVudFRvU2VsZWN0b3JUeXBlKFtNeVdpZGdldENvbXBvbmVudF0pO1xuICAgKiBgYGBcbiAgICovXG4gIHB1YmxpYyBzdGF0aWMgc2VsZWN0b3JUb1R5cGU6IFNlbGVjdG9yVG9UeXBlID0ge307XG4gIC8qKlxuICAgKiBSZWdpc3RlciBhIGxpc3Qgb2YgQW5ndWxhciBjb21wb25lbnRzIGZvciBkeW5hbWljIGNyZWF0aW9uLlxuICAgKlxuICAgKiBAcGFyYW0gdHlwZUxpc3QgQXJyYXkgb2YgY29tcG9uZW50IHR5cGVzIHRvIHJlZ2lzdGVyXG4gICAqXG4gICAqIEBleGFtcGxlXG4gICAqIGBgYHR5cGVzY3JpcHRcbiAgICogR3JpZHN0YWNrQ29tcG9uZW50LmFkZENvbXBvbmVudFRvU2VsZWN0b3JUeXBlKFtcbiAgICogICBNeVdpZGdldENvbXBvbmVudCxcbiAgICogICBBbm90aGVyV2lkZ2V0Q29tcG9uZW50XG4gICAqIF0pO1xuICAgKiBgYGBcbiAgICovXG4gIHB1YmxpYyBzdGF0aWMgYWRkQ29tcG9uZW50VG9TZWxlY3RvclR5cGUodHlwZUxpc3Q6IEFycmF5PFR5cGU8b2JqZWN0Pj4pIHtcbiAgICB0eXBlTGlzdC5mb3JFYWNoKHR5cGUgPT4gR3JpZHN0YWNrQ29tcG9uZW50LnNlbGVjdG9yVG9UeXBlWyBHcmlkc3RhY2tDb21wb25lbnQuZ2V0U2VsZWN0b3IodHlwZSkgXSA9IHR5cGUpO1xuICB9XG4gIC8qKlxuICAgKiBFeHRyYWN0IHRoZSBzZWxlY3RvciBzdHJpbmcgZnJvbSBhbiBBbmd1bGFyIGNvbXBvbmVudCB0eXBlLlxuICAgKlxuICAgKiBAcGFyYW0gdHlwZSBUaGUgY29tcG9uZW50IHR5cGUgdG8gZ2V0IHNlbGVjdG9yIGZyb21cbiAgICogQHJldHVybnMgVGhlIGNvbXBvbmVudCdzIHNlbGVjdG9yIHN0cmluZ1xuICAgKi9cbiAgcHVibGljIHN0YXRpYyBnZXRTZWxlY3Rvcih0eXBlOiBUeXBlPG9iamVjdD4pOiBzdHJpbmcge1xuICAgIC8vIGVzbGludC1kaXNhYmxlLW5leHQtbGluZSBAdHlwZXNjcmlwdC1lc2xpbnQvbm8tbm9uLW51bGwtYXNzZXJ0aW9uXG4gICAgcmV0dXJuIHJlZmxlY3RDb21wb25lbnRUeXBlKHR5cGUpIS5zZWxlY3RvcjtcbiAgfVxuXG4gIHByb3RlY3RlZCBfb3B0aW9ucz86IEdyaWRTdGFja09wdGlvbnM7XG4gIHByb3RlY3RlZCBfZ3JpZD86IEdyaWRTdGFjaztcbiAgcHJvdGVjdGVkIF9zdWI6IFN1YnNjcmlwdGlvbiB8IHVuZGVmaW5lZDtcbiAgcHJvdGVjdGVkIGxvYWRlZD86IGJvb2xlYW47XG5cbiAgY29uc3RydWN0b3IocHJvdGVjdGVkIHJlYWRvbmx5IGVsZW1lbnRSZWY6IEVsZW1lbnRSZWY8R3JpZENvbXBIVE1MRWxlbWVudD4pIHtcbiAgICAvLyBzZXQgZ2xvYmFsbHkgb3VyIG1ldGhvZCB0byBjcmVhdGUgdGhlIHJpZ2h0IHdpZGdldCB0eXBlXG4gICAgaWYgKCFHcmlkU3RhY2suYWRkUmVtb3ZlQ0IpIHtcbiAgICAgIEdyaWRTdGFjay5hZGRSZW1vdmVDQiA9IGdzQ3JlYXRlTmdDb21wb25lbnRzO1xuICAgIH1cbiAgICBpZiAoIUdyaWRTdGFjay5zYXZlQ0IpIHtcbiAgICAgIEdyaWRTdGFjay5zYXZlQ0IgPSBnc1NhdmVBZGRpdGlvbmFsTmdJbmZvO1xuICAgIH1cbiAgICBpZiAoIUdyaWRTdGFjay51cGRhdGVDQikge1xuICAgICAgR3JpZFN0YWNrLnVwZGF0ZUNCID0gZ3NVcGRhdGVOZ0NvbXBvbmVudHM7XG4gICAgfVxuICAgIHRoaXMuZWwuX2dyaWRDb21wID0gdGhpcztcbiAgfVxuXG4gIHB1YmxpYyBuZ09uSW5pdCgpOiB2b2lkIHtcbiAgICAvLyBpbml0IG91cnNlbGYgYmVmb3JlIGFueSB0ZW1wbGF0ZSBjaGlsZHJlbiBhcmUgY3JlYXRlZCBzaW5jZSB3ZSB0cmFjayB0aGVtIGJlbG93IGFueXdheSAtIG5vIG5lZWQgdG8gZG91YmxlIGNyZWF0ZSt1cGRhdGUgd2lkZ2V0c1xuICAgIHRoaXMubG9hZGVkID0gISF0aGlzLm9wdGlvbnM/LmNoaWxkcmVuPy5sZW5ndGg7XG4gICAgdGhpcy5fZ3JpZCA9IEdyaWRTdGFjay5pbml0KHRoaXMuX29wdGlvbnMsIHRoaXMuZWwpO1xuICAgIGRlbGV0ZSB0aGlzLl9vcHRpb25zOyAvLyBHUyBoYXMgaXQgbm93XG5cbiAgICB0aGlzLmNoZWNrRW1wdHkoKTtcbiAgfVxuXG4gIC8qKiB3YWl0IHVudGlsIGFmdGVyIGFsbCBET00gaXMgcmVhZHkgdG8gaW5pdCBncmlkc3RhY2sgY2hpbGRyZW4gKGFmdGVyIGFuZ3VsYXIgbmdGb3IgYW5kIHN1Yi1jb21wb25lbnRzIHJ1biBmaXJzdCkgKi9cbiAgcHVibGljIG5nQWZ0ZXJDb250ZW50SW5pdCgpOiB2b2lkIHtcbiAgICAvLyB0cmFjayB3aGVuZXZlciB0aGUgY2hpbGRyZW4gbGlzdCBjaGFuZ2VzIGFuZCB1cGRhdGUgdGhlIGxheW91dC4uLlxuICAgIHRoaXMuX3N1YiA9IHRoaXMuZ3JpZHN0YWNrSXRlbXM/LmNoYW5nZXMuc3Vic2NyaWJlKCgpID0+IHRoaXMudXBkYXRlQWxsKCkpO1xuICAgIC8vIC4uLmFuZCBkbyB0aGlzIG9uY2UgYXQgbGVhc3QgdW5sZXNzIHdlIGxvYWRlZCBjaGlsZHJlbiBhbHJlYWR5XG4gICAgaWYgKCF0aGlzLmxvYWRlZCkgdGhpcy51cGRhdGVBbGwoKTtcbiAgICB0aGlzLmhvb2tFdmVudHModGhpcy5ncmlkKTtcbiAgfVxuXG4gIHB1YmxpYyBuZ09uRGVzdHJveSgpOiB2b2lkIHtcbiAgICB0aGlzLnVuaG9va0V2ZW50cyh0aGlzLl9ncmlkKTtcbiAgICB0aGlzLl9zdWI/LnVuc3Vic2NyaWJlKCk7XG4gICAgdGhpcy5fZ3JpZD8uZGVzdHJveSgpO1xuICAgIGRlbGV0ZSB0aGlzLl9ncmlkO1xuICAgIGRlbGV0ZSB0aGlzLmVsLl9ncmlkQ29tcDtcbiAgICBkZWxldGUgdGhpcy5jb250YWluZXI7XG4gICAgZGVsZXRlIHRoaXMucmVmO1xuICB9XG5cbiAgLyoqXG4gICAqIGNhbGxlZCB3aGVuIHRoZSBURU1QTEFURSAobm90IHJlY29tbWVuZGVkKSBsaXN0IG9mIGl0ZW1zIGNoYW5nZXMgLSBnZXQgYSBsaXN0IG9mIG5vZGVzIGFuZFxuICAgKiB1cGRhdGUgdGhlIGxheW91dCBhY2NvcmRpbmdseSAod2hpY2ggd2lsbCB0YWtlIGNhcmUgb2YgYWRkaW5nL3JlbW92aW5nIGl0ZW1zIGNoYW5nZWQgYnkgQW5ndWxhcilcbiAgICovXG4gIHB1YmxpYyB1cGRhdGVBbGwoKSB7XG4gICAgaWYgKCF0aGlzLmdyaWQpIHJldHVybjtcbiAgICBjb25zdCBsYXlvdXQ6IEdyaWRTdGFja1dpZGdldFtdID0gW107XG4gICAgdGhpcy5ncmlkc3RhY2tJdGVtcz8uZm9yRWFjaChpdGVtID0+IHtcbiAgICAgIGxheW91dC5wdXNoKGl0ZW0ub3B0aW9ucyk7XG4gICAgICBpdGVtLmNsZWFyT3B0aW9ucygpO1xuICAgIH0pO1xuICAgIHRoaXMuZ3JpZC5sb2FkKGxheW91dCk7IC8vIGVmZmljaWVudCB0aGF0IGRvZXMgZGlmZnMgb25seVxuICB9XG5cbiAgLyoqIGNoZWNrIGlmIHRoZSBncmlkIGlzIGVtcHR5LCBpZiBzbyBzaG93IGFsdGVybmF0aXZlIGNvbnRlbnQgKi9cbiAgcHVibGljIGNoZWNrRW1wdHkoKSB7XG4gICAgaWYgKCF0aGlzLmdyaWQpIHJldHVybjtcbiAgICB0aGlzLmlzRW1wdHkgPSAhdGhpcy5ncmlkLmVuZ2luZS5ub2Rlcy5sZW5ndGg7XG4gIH1cblxuICAvKiogZ2V0IGFsbCBrbm93biBldmVudHMgYXMgZWFzeSB0byB1c2UgT3V0cHV0cyBmb3IgY29udmVuaWVuY2UgKi9cbiAgcHJvdGVjdGVkIGhvb2tFdmVudHMoZ3JpZD86IEdyaWRTdGFjaykge1xuICAgIGlmICghZ3JpZCkgcmV0dXJuO1xuICAgIC8vIG5lc3RlZCBncmlkcyBkb24ndCBoYXZlIGV2ZW50cyBpbiB2MTIuMSsgc28gc2tpcFxuICAgIGlmIChncmlkLnBhcmVudEdyaWROb2RlKSByZXR1cm47XG4gICAgZ3JpZFxuICAgICAgLm9uKCdhZGRlZCcsIChldmVudDogRXZlbnQsIG5vZGVzOiBHcmlkU3RhY2tOb2RlW10pID0+IHtcbiAgICAgICAgY29uc3QgZ3JpZENvbXAgPSAobm9kZXNbMF0uZ3JpZD8uZWwgYXMgR3JpZENvbXBIVE1MRWxlbWVudCkuX2dyaWRDb21wIHx8IHRoaXM7XG4gICAgICAgIGdyaWRDb21wLmNoZWNrRW1wdHkoKTtcbiAgICAgICAgdGhpcy5hZGRlZENCLmVtaXQoe2V2ZW50LCBub2Rlc30pO1xuICAgICAgfSlcbiAgICAgIC5vbignY2hhbmdlJywgKGV2ZW50OiBFdmVudCwgbm9kZXM6IEdyaWRTdGFja05vZGVbXSkgPT4gdGhpcy5jaGFuZ2VDQi5lbWl0KHtldmVudCwgbm9kZXN9KSlcbiAgICAgIC5vbignZGlzYWJsZScsIChldmVudDogRXZlbnQpID0+IHRoaXMuZGlzYWJsZUNCLmVtaXQoe2V2ZW50fSkpXG4gICAgICAub24oJ2RyYWcnLCAoZXZlbnQ6IEV2ZW50LCBlbDogR3JpZEl0ZW1IVE1MRWxlbWVudCkgPT4gdGhpcy5kcmFnQ0IuZW1pdCh7ZXZlbnQsIGVsfSkpXG4gICAgICAub24oJ2RyYWdzdGFydCcsIChldmVudDogRXZlbnQsIGVsOiBHcmlkSXRlbUhUTUxFbGVtZW50KSA9PiB0aGlzLmRyYWdTdGFydENCLmVtaXQoe2V2ZW50LCBlbH0pKVxuICAgICAgLm9uKCdkcmFnc3RvcCcsIChldmVudDogRXZlbnQsIGVsOiBHcmlkSXRlbUhUTUxFbGVtZW50KSA9PiB0aGlzLmRyYWdTdG9wQ0IuZW1pdCh7ZXZlbnQsIGVsfSkpXG4gICAgICAub24oJ2Ryb3BwZWQnLCAoZXZlbnQ6IEV2ZW50LCBwcmV2aW91c05vZGU6IEdyaWRTdGFja05vZGUsIG5ld05vZGU6IEdyaWRTdGFja05vZGUpID0+IHRoaXMuZHJvcHBlZENCLmVtaXQoe2V2ZW50LCBwcmV2aW91c05vZGUsIG5ld05vZGV9KSlcbiAgICAgIC5vbignZW5hYmxlJywgKGV2ZW50OiBFdmVudCkgPT4gdGhpcy5lbmFibGVDQi5lbWl0KHtldmVudH0pKVxuICAgICAgLm9uKCdyZW1vdmVkJywgKGV2ZW50OiBFdmVudCwgbm9kZXM6IEdyaWRTdGFja05vZGVbXSkgPT4ge1xuICAgICAgICBjb25zdCBncmlkQ29tcCA9IChub2Rlc1swXS5ncmlkPy5lbCBhcyBHcmlkQ29tcEhUTUxFbGVtZW50KS5fZ3JpZENvbXAgfHwgdGhpcztcbiAgICAgICAgZ3JpZENvbXAuY2hlY2tFbXB0eSgpO1xuICAgICAgICB0aGlzLnJlbW92ZWRDQi5lbWl0KHtldmVudCwgbm9kZXN9KTtcbiAgICAgIH0pXG4gICAgICAub24oJ3Jlc2l6ZScsIChldmVudDogRXZlbnQsIGVsOiBHcmlkSXRlbUhUTUxFbGVtZW50KSA9PiB0aGlzLnJlc2l6ZUNCLmVtaXQoe2V2ZW50LCBlbH0pKVxuICAgICAgLm9uKCdyZXNpemVzdGFydCcsIChldmVudDogRXZlbnQsIGVsOiBHcmlkSXRlbUhUTUxFbGVtZW50KSA9PiB0aGlzLnJlc2l6ZVN0YXJ0Q0IuZW1pdCh7ZXZlbnQsIGVsfSkpXG4gICAgICAub24oJ3Jlc2l6ZXN0b3AnLCAoZXZlbnQ6IEV2ZW50LCBlbDogR3JpZEl0ZW1IVE1MRWxlbWVudCkgPT4gdGhpcy5yZXNpemVTdG9wQ0IuZW1pdCh7ZXZlbnQsIGVsfSkpXG4gIH1cblxuICBwcm90ZWN0ZWQgdW5ob29rRXZlbnRzKGdyaWQ/OiBHcmlkU3RhY2spIHtcbiAgICBpZiAoIWdyaWQpIHJldHVybjtcbiAgICAvLyBuZXN0ZWQgZ3JpZHMgZG9uJ3QgaGF2ZSBldmVudHMgaW4gdjEyLjErIHNvIHNraXBcbiAgICBpZiAoZ3JpZC5wYXJlbnRHcmlkTm9kZSkgcmV0dXJuO1xuICAgIGdyaWQub2ZmKCdhZGRlZCBjaGFuZ2UgZGlzYWJsZSBkcmFnIGRyYWdzdGFydCBkcmFnc3RvcCBkcm9wcGVkIGVuYWJsZSByZW1vdmVkIHJlc2l6ZSByZXNpemVzdGFydCByZXNpemVzdG9wJyk7XG4gIH1cbn1cblxuLyoqXG4gKiBjYW4gYmUgdXNlZCB3aGVuIGEgbmV3IGl0ZW0gbmVlZHMgdG8gYmUgY3JlYXRlZCwgd2hpY2ggd2UgZG8gYXMgYSBBbmd1bGFyIGNvbXBvbmVudCwgb3IgZGVsZXRlZCAoc2tpcClcbiAqKi9cbmV4cG9ydCBmdW5jdGlvbiBnc0NyZWF0ZU5nQ29tcG9uZW50cyhob3N0OiBHcmlkQ29tcEhUTUxFbGVtZW50IHwgSFRNTEVsZW1lbnQsIG46IE5nR3JpZFN0YWNrTm9kZSwgYWRkOiBib29sZWFuLCBpc0dyaWQ6IGJvb2xlYW4pOiBIVE1MRWxlbWVudCB8IHVuZGVmaW5lZCB7XG4gIGlmIChhZGQpIHtcbiAgICAvL1xuICAgIC8vIGNyZWF0ZSB0aGUgY29tcG9uZW50IGR5bmFtaWNhbGx5IC0gc2VlIGh0dHBzOi8vYW5ndWxhci5pby9kb2NzL3RzL2xhdGVzdC9jb29rYm9vay9keW5hbWljLWNvbXBvbmVudC1sb2FkZXIuaHRtbFxuICAgIC8vXG4gICAgaWYgKCFob3N0KSByZXR1cm47XG4gICAgaWYgKGlzR3JpZCkge1xuICAgICAgLy8gVE9ETzogZmlndXJlIG91dCBob3cgdG8gY3JlYXRlIG5nIGNvbXBvbmVudCBpbnNpZGUgcmVndWxhciBEaXYuIG5lZWQgdG8gYWNjZXNzIGFwcCBpbmplY3RvcnMuLi5cbiAgICAgIC8vIGlmICghY29udGFpbmVyKSB7XG4gICAgICAvLyAgIGNvbnN0IGhvc3RFbGVtZW50OiBFbGVtZW50ID0gaG9zdDtcbiAgICAgIC8vICAgY29uc3QgZW52aXJvbm1lbnRJbmplY3RvcjogRW52aXJvbm1lbnRJbmplY3RvcjtcbiAgICAgIC8vICAgZ3JpZCA9IGNyZWF0ZUNvbXBvbmVudChHcmlkc3RhY2tDb21wb25lbnQsIHtlbnZpcm9ubWVudEluamVjdG9yLCBob3N0RWxlbWVudH0pPy5pbnN0YW5jZTtcbiAgICAgIC8vIH1cblxuICAgICAgY29uc3QgZ3JpZEl0ZW1Db21wID0gKGhvc3QucGFyZW50RWxlbWVudCBhcyBHcmlkSXRlbUNvbXBIVE1MRWxlbWVudCk/Ll9ncmlkSXRlbUNvbXA7XG4gICAgICBpZiAoIWdyaWRJdGVtQ29tcCkgcmV0dXJuO1xuICAgICAgLy8gY2hlY2sgaWYgZ3JpZEl0ZW0gaGFzIGEgY2hpbGQgY29tcG9uZW50IHdpdGggJ2NvbnRhaW5lcicgZXhwb3NlZCB0byBjcmVhdGUgdW5kZXIuLlxuICAgICAgLy8gZXNsaW50LWRpc2FibGUtbmV4dC1saW5lIEB0eXBlc2NyaXB0LWVzbGludC9uby1leHBsaWNpdC1hbnlcbiAgICAgIGNvbnN0IGNvbnRhaW5lciA9IChncmlkSXRlbUNvbXAuY2hpbGRXaWRnZXQgYXMgYW55KT8uY29udGFpbmVyIHx8IGdyaWRJdGVtQ29tcC5jb250YWluZXI7XG4gICAgICBjb25zdCBncmlkUmVmID0gY29udGFpbmVyPy5jcmVhdGVDb21wb25lbnQoR3JpZHN0YWNrQ29tcG9uZW50KTtcbiAgICAgIGNvbnN0IGdyaWQgPSBncmlkUmVmPy5pbnN0YW5jZTtcbiAgICAgIGlmICghZ3JpZCkgcmV0dXJuO1xuICAgICAgZ3JpZC5yZWYgPSBncmlkUmVmO1xuICAgICAgZ3JpZC5vcHRpb25zID0gbjtcbiAgICAgIHJldHVybiBncmlkLmVsO1xuICAgIH0gZWxzZSB7XG4gICAgICBjb25zdCBncmlkQ29tcCA9IChob3N0IGFzIEdyaWRDb21wSFRNTEVsZW1lbnQpLl9ncmlkQ29tcDtcbiAgICAgIGNvbnN0IGdyaWRJdGVtUmVmID0gZ3JpZENvbXA/LmNvbnRhaW5lcj8uY3JlYXRlQ29tcG9uZW50KEdyaWRzdGFja0l0ZW1Db21wb25lbnQpO1xuICAgICAgY29uc3QgZ3JpZEl0ZW0gPSBncmlkSXRlbVJlZj8uaW5zdGFuY2U7XG4gICAgICBpZiAoIWdyaWRJdGVtKSByZXR1cm47XG4gICAgICBncmlkSXRlbS5yZWYgPSBncmlkSXRlbVJlZlxuXG4gICAgICAvLyBkZWZpbmUgd2hhdCB0eXBlIG9mIGNvbXBvbmVudCB0byBjcmVhdGUgYXMgY2hpbGQsIE9SIHlvdSBjYW4gZG8gaXQgR3JpZHN0YWNrSXRlbUNvbXBvbmVudCB0ZW1wbGF0ZSwgYnV0IHRoaXMgaXMgbW9yZSBnZW5lcmljXG4gICAgICBjb25zdCBzZWxlY3RvciA9IG4uc2VsZWN0b3I7XG4gICAgICBjb25zdCB0eXBlID0gc2VsZWN0b3IgPyBHcmlkc3RhY2tDb21wb25lbnQuc2VsZWN0b3JUb1R5cGVbc2VsZWN0b3JdIDogdW5kZWZpbmVkO1xuICAgICAgaWYgKHR5cGUpIHtcbiAgICAgICAgLy8gc2hhcmVkIGNvZGUgdG8gY3JlYXRlIG91ciBzZWxlY3RvciBjb21wb25lbnRcbiAgICAgICAgY29uc3QgY3JlYXRlQ29tcCA9ICgpID0+IHtcbiAgICAgICAgICBjb25zdCBjaGlsZFdpZGdldCA9IGdyaWRJdGVtLmNvbnRhaW5lcj8uY3JlYXRlQ29tcG9uZW50KHR5cGUpPy5pbnN0YW5jZSBhcyBCYXNlV2lkZ2V0O1xuICAgICAgICAgIC8vIGlmIHByb3BlciBCYXNlV2lkZ2V0IHN1YmNsYXNzLCBzYXZlIGl0IGFuZCBsb2FkIGFkZGl0aW9uYWwgZGF0YVxuICAgICAgICAgIGlmIChjaGlsZFdpZGdldCAmJiB0eXBlb2YgY2hpbGRXaWRnZXQuc2VyaWFsaXplID09PSAnZnVuY3Rpb24nICYmIHR5cGVvZiBjaGlsZFdpZGdldC5kZXNlcmlhbGl6ZSA9PT0gJ2Z1bmN0aW9uJykge1xuICAgICAgICAgICAgZ3JpZEl0ZW0uY2hpbGRXaWRnZXQgPSBjaGlsZFdpZGdldDtcbiAgICAgICAgICAgIGNoaWxkV2lkZ2V0LmRlc2VyaWFsaXplKG4pO1xuICAgICAgICAgIH1cbiAgICAgICAgfVxuXG4gICAgICAgIGNvbnN0IGxhenlMb2FkID0gbi5sYXp5TG9hZCB8fCBuLmdyaWQ/Lm9wdHM/LmxhenlMb2FkICYmIG4ubGF6eUxvYWQgIT09IGZhbHNlO1xuICAgICAgICBpZiAobGF6eUxvYWQpIHtcbiAgICAgICAgICBpZiAoIW4udmlzaWJsZU9ic2VydmFibGUpIHtcbiAgICAgICAgICAgIG4udmlzaWJsZU9ic2VydmFibGUgPSBuZXcgSW50ZXJzZWN0aW9uT2JzZXJ2ZXIoKFtlbnRyeV0pID0+IHsgaWYgKGVudHJ5LmlzSW50ZXJzZWN0aW5nKSB7XG4gICAgICAgICAgICAgIG4udmlzaWJsZU9ic2VydmFibGU/LmRpc2Nvbm5lY3QoKTtcbiAgICAgICAgICAgICAgZGVsZXRlIG4udmlzaWJsZU9ic2VydmFibGU7XG4gICAgICAgICAgICAgIGNyZWF0ZUNvbXAoKTtcbiAgICAgICAgICAgIH19KTtcbiAgICAgICAgICAgIHdpbmRvdy5zZXRUaW1lb3V0KCgpID0+IG4udmlzaWJsZU9ic2VydmFibGU/Lm9ic2VydmUoZ3JpZEl0ZW0uZWwpKTsgLy8gd2FpdCB1bnRpbCBjYWxsZWUgc2V0cyBwb3NpdGlvbiBhdHRyaWJ1dGVzXG4gICAgICAgICAgfVxuICAgICAgICB9IGVsc2UgY3JlYXRlQ29tcCgpO1xuICAgICAgfVxuXG4gICAgICByZXR1cm4gZ3JpZEl0ZW0uZWw7XG4gICAgfVxuICB9IGVsc2Uge1xuICAgIC8vXG4gICAgLy8gUkVNT1ZFIC0gaGF2ZSB0byBjYWxsIENvbXBvbmVudFJlZjpkZXN0cm95KCkgZm9yIGR5bmFtaWMgb2JqZWN0cyB0byBjb3JyZWN0bHkgcmVtb3ZlIHRoZW1zZWx2ZXNcbiAgICAvLyBOb3RlOiB0aGlzIHdpbGwgZGVzdHJveSBhbGwgY2hpbGRyZW4gZHluYW1pYyBjb21wb25lbnRzIGFzIHdlbGw6IGdyaWRJdGVtIC0+IGNoaWxkV2lkZ2V0XG4gICAgLy9cbiAgICBpZiAoaXNHcmlkKSB7XG4gICAgICBjb25zdCBncmlkID0gKG4uZWwgYXMgR3JpZENvbXBIVE1MRWxlbWVudCk/Ll9ncmlkQ29tcDtcbiAgICAgIGlmIChncmlkPy5yZWYpIGdyaWQucmVmLmRlc3Ryb3koKTtcbiAgICAgIGVsc2UgZ3JpZD8ubmdPbkRlc3Ryb3koKTtcbiAgICB9IGVsc2Uge1xuICAgICAgY29uc3QgZ3JpZEl0ZW0gPSAobi5lbCBhcyBHcmlkSXRlbUNvbXBIVE1MRWxlbWVudCk/Ll9ncmlkSXRlbUNvbXA7XG4gICAgICBpZiAoZ3JpZEl0ZW0/LnJlZikgZ3JpZEl0ZW0ucmVmLmRlc3Ryb3koKTtcbiAgICAgIGVsc2UgZ3JpZEl0ZW0/Lm5nT25EZXN0cm95KCk7XG4gICAgfVxuICB9XG4gIHJldHVybjtcbn1cblxuLyoqXG4gKiBjYWxsZWQgZm9yIGVhY2ggaXRlbSBpbiB0aGUgZ3JpZCAtIGNoZWNrIGlmIGFkZGl0aW9uYWwgaW5mb3JtYXRpb24gbmVlZHMgdG8gYmUgc2F2ZWQuXG4gKiBOb3RlOiBzaW5jZSB0aGlzIGlzIG9wdGlvbnMgbWludXMgZ3JpZHN0YWNrIHByb3RlY3RlZCBtZW1iZXJzIHVzaW5nIFV0aWxzLnJlbW92ZUludGVybmFsRm9yU2F2ZSgpLFxuICogdGhpcyB0eXBpY2FsbHkgZG9lc24ndCBuZWVkIHRvIGRvIGFueXRoaW5nLiBIb3dldmVyIHlvdXIgY3VzdG9tIENvbXBvbmVudCBASW5wdXQoKSBhcmUgbm93IHN1cHBvcnRlZFxuICogdXNpbmcgQmFzZVdpZGdldC5zZXJpYWxpemUoKVxuICovXG5leHBvcnQgZnVuY3Rpb24gZ3NTYXZlQWRkaXRpb25hbE5nSW5mbyhuOiBOZ0dyaWRTdGFja05vZGUsIHc6IE5nR3JpZFN0YWNrV2lkZ2V0KSB7XG4gIGNvbnN0IGdyaWRJdGVtID0gKG4uZWwgYXMgR3JpZEl0ZW1Db21wSFRNTEVsZW1lbnQpPy5fZ3JpZEl0ZW1Db21wO1xuICBpZiAoZ3JpZEl0ZW0pIHtcbiAgICBjb25zdCBpbnB1dCA9IGdyaWRJdGVtLmNoaWxkV2lkZ2V0Py5zZXJpYWxpemUoKTtcbiAgICBpZiAoaW5wdXQpIHtcbiAgICAgIHcuaW5wdXQgPSBpbnB1dDtcbiAgICB9XG4gICAgcmV0dXJuO1xuICB9XG4gIC8vIGVsc2UgY2hlY2sgaWYgR3JpZFxuICBjb25zdCBncmlkID0gKG4uZWwgYXMgR3JpZENvbXBIVE1MRWxlbWVudCk/Ll9ncmlkQ29tcDtcbiAgaWYgKGdyaWQpIHtcbiAgICAvLy4uLi4gc2F2ZSBhbnkgY3VzdG9tIGRhdGFcbiAgfVxufVxuXG4vKipcbiAqIHRyYWNrIHdoZW4gd2lkZ2V0YSByZSB1cGRhdGVkIChyYXRoZXIgdGhhbiBjcmVhdGVkKSB0byBtYWtlIHN1cmUgd2UgZGUtc2VyaWFsaXplIHRoZW0gYXMgd2VsbFxuICovXG5leHBvcnQgZnVuY3Rpb24gZ3NVcGRhdGVOZ0NvbXBvbmVudHMobjogTmdHcmlkU3RhY2tOb2RlKSB7XG4gIGNvbnN0IHc6IE5nR3JpZFN0YWNrV2lkZ2V0ID0gbjtcbiAgY29uc3QgZ3JpZEl0ZW0gPSAobi5lbCBhcyBHcmlkSXRlbUNvbXBIVE1MRWxlbWVudCk/Ll9ncmlkSXRlbUNvbXA7XG4gIGlmIChncmlkSXRlbT8uY2hpbGRXaWRnZXQgJiYgdy5pbnB1dCkgZ3JpZEl0ZW0uY2hpbGRXaWRnZXQuZGVzZXJpYWxpemUodyk7XG59Il19