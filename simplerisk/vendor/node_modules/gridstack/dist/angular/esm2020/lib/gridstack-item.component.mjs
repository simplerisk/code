/**
 * gridstack-item.component.ts 11.5.1
 * Copyright (c) 2022-2024 Alain Dumesny - see GridStack root license
 */
import { Component, Input, ViewChild, ViewContainerRef } from '@angular/core';
import * as i0 from "@angular/core";
/**
 * HTML Component Wrapper for gridstack items, in combination with GridstackComponent for parent grid
 */
export class GridstackItemComponent {
    constructor(elementRef) {
        this.elementRef = elementRef;
        this.el._gridItemComp = this;
    }
    /** list of options for creating/updating this item */
    set options(val) {
        const grid = this.el.gridstackNode?.grid;
        if (grid) {
            // already built, do an update...
            grid.update(this.el, val);
        }
        else {
            // store our custom element in options so we can update it and not re-create a generic div!
            this._options = { ...val, el: this.el };
        }
    }
    /** return the latest grid options (from GS once built, otherwise initial values) */
    get options() {
        return this.el.gridstackNode || this._options || { el: this.el };
    }
    /** return the native element that contains grid specific fields as well */
    get el() { return this.elementRef.nativeElement; }
    /** clears the initial options now that we've built */
    clearOptions() {
        delete this._options;
    }
    ngOnDestroy() {
        this.clearOptions();
        delete this.childWidget;
        delete this.el._gridItemComp;
        delete this.container;
        delete this.ref;
    }
}
GridstackItemComponent.ɵfac = i0.ɵɵngDeclareFactory({ minVersion: "12.0.0", version: "14.3.0", ngImport: i0, type: GridstackItemComponent, deps: [{ token: i0.ElementRef }], target: i0.ɵɵFactoryTarget.Component });
GridstackItemComponent.ɵcmp = i0.ɵɵngDeclareComponent({ minVersion: "14.0.0", version: "14.3.0", type: GridstackItemComponent, isStandalone: true, selector: "gridstack-item", inputs: { options: "options" }, viewQueries: [{ propertyName: "container", first: true, predicate: ["container"], descendants: true, read: ViewContainerRef, static: true }], ngImport: i0, template: `
    <div class="grid-stack-item-content">
      <!-- where dynamic items go based on component selector (recommended way), or sub-grids, etc...) -->
      <ng-template #container></ng-template>
      <!-- any static (defined in DOM - not recommended) content goes here -->
      <ng-content></ng-content>
      <!-- fallback HTML content from GridStackWidget.content if used instead (not recommended) -->
      {{options.content}}
    </div>`, isInline: true, styles: [":host{display:block}\n"] });
i0.ɵɵngDeclareClassMetadata({ minVersion: "12.0.0", version: "14.3.0", ngImport: i0, type: GridstackItemComponent, decorators: [{
            type: Component,
            args: [{ selector: 'gridstack-item', template: `
    <div class="grid-stack-item-content">
      <!-- where dynamic items go based on component selector (recommended way), or sub-grids, etc...) -->
      <ng-template #container></ng-template>
      <!-- any static (defined in DOM - not recommended) content goes here -->
      <ng-content></ng-content>
      <!-- fallback HTML content from GridStackWidget.content if used instead (not recommended) -->
      {{options.content}}
    </div>`, standalone: true, styles: [":host{display:block}\n"] }]
        }], ctorParameters: function () { return [{ type: i0.ElementRef }]; }, propDecorators: { container: [{
                type: ViewChild,
                args: ['container', { read: ViewContainerRef, static: true }]
            }], options: [{
                type: Input
            }] } });
//# sourceMappingURL=data:application/json;base64,eyJ2ZXJzaW9uIjozLCJmaWxlIjoiZ3JpZHN0YWNrLWl0ZW0uY29tcG9uZW50LmpzIiwic291cmNlUm9vdCI6IiIsInNvdXJjZXMiOlsiLi4vLi4vLi4vLi4vYW5ndWxhci9wcm9qZWN0cy9saWIvc3JjL2xpYi9ncmlkc3RhY2staXRlbS5jb21wb25lbnQudHMiXSwibmFtZXMiOltdLCJtYXBwaW5ncyI6IkFBQUE7OztHQUdHO0FBRUgsT0FBTyxFQUFFLFNBQVMsRUFBYyxLQUFLLEVBQUUsU0FBUyxFQUFFLGdCQUFnQixFQUEyQixNQUFNLGVBQWUsQ0FBQzs7QUFTbkg7O0dBRUc7QUFrQkgsTUFBTSxPQUFPLHNCQUFzQjtJQXFDakMsWUFBK0IsVUFBK0M7UUFBL0MsZUFBVSxHQUFWLFVBQVUsQ0FBcUM7UUFDNUUsSUFBSSxDQUFDLEVBQUUsQ0FBQyxhQUFhLEdBQUcsSUFBSSxDQUFDO0lBQy9CLENBQUM7SUE1QkQsc0RBQXNEO0lBQ3RELElBQW9CLE9BQU8sQ0FBQyxHQUFrQjtRQUM1QyxNQUFNLElBQUksR0FBRyxJQUFJLENBQUMsRUFBRSxDQUFDLGFBQWEsRUFBRSxJQUFJLENBQUM7UUFDekMsSUFBSSxJQUFJLEVBQUU7WUFDUixpQ0FBaUM7WUFDakMsSUFBSSxDQUFDLE1BQU0sQ0FBQyxJQUFJLENBQUMsRUFBRSxFQUFFLEdBQUcsQ0FBQyxDQUFDO1NBQzNCO2FBQU07WUFDTCwyRkFBMkY7WUFDM0YsSUFBSSxDQUFDLFFBQVEsR0FBRyxFQUFDLEdBQUcsR0FBRyxFQUFFLEVBQUUsRUFBRSxJQUFJLENBQUMsRUFBRSxFQUFDLENBQUM7U0FDdkM7SUFDSCxDQUFDO0lBQ0Qsb0ZBQW9GO0lBQ3BGLElBQVcsT0FBTztRQUNoQixPQUFPLElBQUksQ0FBQyxFQUFFLENBQUMsYUFBYSxJQUFJLElBQUksQ0FBQyxRQUFRLElBQUksRUFBQyxFQUFFLEVBQUUsSUFBSSxDQUFDLEVBQUUsRUFBQyxDQUFDO0lBQ2pFLENBQUM7SUFJRCwyRUFBMkU7SUFDM0UsSUFBVyxFQUFFLEtBQThCLE9BQU8sSUFBSSxDQUFDLFVBQVUsQ0FBQyxhQUFhLENBQUMsQ0FBQyxDQUFDO0lBRWxGLHNEQUFzRDtJQUMvQyxZQUFZO1FBQ2pCLE9BQU8sSUFBSSxDQUFDLFFBQVEsQ0FBQztJQUN2QixDQUFDO0lBTU0sV0FBVztRQUNoQixJQUFJLENBQUMsWUFBWSxFQUFFLENBQUM7UUFDcEIsT0FBTyxJQUFJLENBQUMsV0FBVyxDQUFBO1FBQ3ZCLE9BQU8sSUFBSSxDQUFDLEVBQUUsQ0FBQyxhQUFhLENBQUM7UUFDN0IsT0FBTyxJQUFJLENBQUMsU0FBUyxDQUFDO1FBQ3RCLE9BQU8sSUFBSSxDQUFDLEdBQUcsQ0FBQztJQUNsQixDQUFDOzttSEEvQ1Usc0JBQXNCO3VHQUF0QixzQkFBc0IsNkxBR0QsZ0JBQWdCLDJDQWxCdEM7Ozs7Ozs7O1dBUUQ7MkZBT0Usc0JBQXNCO2tCQWpCbEMsU0FBUzsrQkFDRSxnQkFBZ0IsWUFDaEI7Ozs7Ozs7O1dBUUQsY0FJRyxJQUFJO2lHQU13RCxTQUFTO3NCQUFoRixTQUFTO3VCQUFDLFdBQVcsRUFBRSxFQUFFLElBQUksRUFBRSxnQkFBZ0IsRUFBRSxNQUFNLEVBQUUsSUFBSSxFQUFDO2dCQVMzQyxPQUFPO3NCQUExQixLQUFLIiwic291cmNlc0NvbnRlbnQiOlsiLyoqXG4gKiBncmlkc3RhY2staXRlbS5jb21wb25lbnQudHMgMTEuNS4xXG4gKiBDb3B5cmlnaHQgKGMpIDIwMjItMjAyNCBBbGFpbiBEdW1lc255IC0gc2VlIEdyaWRTdGFjayByb290IGxpY2Vuc2VcbiAqL1xuXG5pbXBvcnQgeyBDb21wb25lbnQsIEVsZW1lbnRSZWYsIElucHV0LCBWaWV3Q2hpbGQsIFZpZXdDb250YWluZXJSZWYsIE9uRGVzdHJveSwgQ29tcG9uZW50UmVmIH0gZnJvbSAnQGFuZ3VsYXIvY29yZSc7XG5pbXBvcnQgeyBHcmlkSXRlbUhUTUxFbGVtZW50LCBHcmlkU3RhY2tOb2RlIH0gZnJvbSAnZ3JpZHN0YWNrJztcbmltcG9ydCB7IEJhc2VXaWRnZXQgfSBmcm9tICcuL2Jhc2Utd2lkZ2V0JztcblxuLyoqIHN0b3JlIGVsZW1lbnQgdG8gTmcgQ2xhc3MgcG9pbnRlciBiYWNrICovXG5leHBvcnQgaW50ZXJmYWNlIEdyaWRJdGVtQ29tcEhUTUxFbGVtZW50IGV4dGVuZHMgR3JpZEl0ZW1IVE1MRWxlbWVudCB7XG4gIF9ncmlkSXRlbUNvbXA/OiBHcmlkc3RhY2tJdGVtQ29tcG9uZW50O1xufVxuXG4vKipcbiAqIEhUTUwgQ29tcG9uZW50IFdyYXBwZXIgZm9yIGdyaWRzdGFjayBpdGVtcywgaW4gY29tYmluYXRpb24gd2l0aCBHcmlkc3RhY2tDb21wb25lbnQgZm9yIHBhcmVudCBncmlkXG4gKi9cbkBDb21wb25lbnQoe1xuICBzZWxlY3RvcjogJ2dyaWRzdGFjay1pdGVtJyxcbiAgdGVtcGxhdGU6IGBcbiAgICA8ZGl2IGNsYXNzPVwiZ3JpZC1zdGFjay1pdGVtLWNvbnRlbnRcIj5cbiAgICAgIDwhLS0gd2hlcmUgZHluYW1pYyBpdGVtcyBnbyBiYXNlZCBvbiBjb21wb25lbnQgc2VsZWN0b3IgKHJlY29tbWVuZGVkIHdheSksIG9yIHN1Yi1ncmlkcywgZXRjLi4uKSAtLT5cbiAgICAgIDxuZy10ZW1wbGF0ZSAjY29udGFpbmVyPjwvbmctdGVtcGxhdGU+XG4gICAgICA8IS0tIGFueSBzdGF0aWMgKGRlZmluZWQgaW4gRE9NIC0gbm90IHJlY29tbWVuZGVkKSBjb250ZW50IGdvZXMgaGVyZSAtLT5cbiAgICAgIDxuZy1jb250ZW50PjwvbmctY29udGVudD5cbiAgICAgIDwhLS0gZmFsbGJhY2sgSFRNTCBjb250ZW50IGZyb20gR3JpZFN0YWNrV2lkZ2V0LmNvbnRlbnQgaWYgdXNlZCBpbnN0ZWFkIChub3QgcmVjb21tZW5kZWQpIC0tPlxuICAgICAge3tvcHRpb25zLmNvbnRlbnR9fVxuICAgIDwvZGl2PmAsXG4gIHN0eWxlczogW2BcbiAgICA6aG9zdCB7IGRpc3BsYXk6IGJsb2NrOyB9XG4gIGBdLFxuICBzdGFuZGFsb25lOiB0cnVlLFxuICAvLyBjaGFuZ2VEZXRlY3Rpb246IENoYW5nZURldGVjdGlvblN0cmF0ZWd5Lk9uUHVzaCwgLy8gSUZGIHlvdSB3YW50IHRvIG9wdGltaXplIGFuZCBjb250cm9sIHdoZW4gQ2hhbmdlRGV0ZWN0aW9uIG5lZWRzIHRvIGhhcHBlbi4uLlxufSlcbmV4cG9ydCBjbGFzcyBHcmlkc3RhY2tJdGVtQ29tcG9uZW50IGltcGxlbWVudHMgT25EZXN0cm95IHtcblxuICAvKiogY29udGFpbmVyIHRvIGFwcGVuZCBpdGVtcyBkeW5hbWljYWxseSAqL1xuICBAVmlld0NoaWxkKCdjb250YWluZXInLCB7IHJlYWQ6IFZpZXdDb250YWluZXJSZWYsIHN0YXRpYzogdHJ1ZX0pIHB1YmxpYyBjb250YWluZXI/OiBWaWV3Q29udGFpbmVyUmVmO1xuXG4gIC8qKiBDb21wb25lbnRSZWYgb2Ygb3Vyc2VsZiAtIHVzZWQgYnkgZHluYW1pYyBvYmplY3QgdG8gY29ycmVjdGx5IGdldCByZW1vdmVkICovXG4gIHB1YmxpYyByZWY6IENvbXBvbmVudFJlZjxHcmlkc3RhY2tJdGVtQ29tcG9uZW50PiB8IHVuZGVmaW5lZDtcblxuICAvKiogY2hpbGQgY29tcG9uZW50IHNvIHdlIGNhbiBzYXZlL3Jlc3RvcmUgYWRkaXRpb25hbCBkYXRhIHRvIGJlIHNhdmVkIGFsb25nICovXG4gIHB1YmxpYyBjaGlsZFdpZGdldDogQmFzZVdpZGdldCB8IHVuZGVmaW5lZDtcblxuICAvKiogbGlzdCBvZiBvcHRpb25zIGZvciBjcmVhdGluZy91cGRhdGluZyB0aGlzIGl0ZW0gKi9cbiAgQElucHV0KCkgcHVibGljIHNldCBvcHRpb25zKHZhbDogR3JpZFN0YWNrTm9kZSkge1xuICAgIGNvbnN0IGdyaWQgPSB0aGlzLmVsLmdyaWRzdGFja05vZGU/LmdyaWQ7XG4gICAgaWYgKGdyaWQpIHtcbiAgICAgIC8vIGFscmVhZHkgYnVpbHQsIGRvIGFuIHVwZGF0ZS4uLlxuICAgICAgZ3JpZC51cGRhdGUodGhpcy5lbCwgdmFsKTtcbiAgICB9IGVsc2Uge1xuICAgICAgLy8gc3RvcmUgb3VyIGN1c3RvbSBlbGVtZW50IGluIG9wdGlvbnMgc28gd2UgY2FuIHVwZGF0ZSBpdCBhbmQgbm90IHJlLWNyZWF0ZSBhIGdlbmVyaWMgZGl2IVxuICAgICAgdGhpcy5fb3B0aW9ucyA9IHsuLi52YWwsIGVsOiB0aGlzLmVsfTtcbiAgICB9XG4gIH1cbiAgLyoqIHJldHVybiB0aGUgbGF0ZXN0IGdyaWQgb3B0aW9ucyAoZnJvbSBHUyBvbmNlIGJ1aWx0LCBvdGhlcndpc2UgaW5pdGlhbCB2YWx1ZXMpICovXG4gIHB1YmxpYyBnZXQgb3B0aW9ucygpOiBHcmlkU3RhY2tOb2RlIHtcbiAgICByZXR1cm4gdGhpcy5lbC5ncmlkc3RhY2tOb2RlIHx8IHRoaXMuX29wdGlvbnMgfHwge2VsOiB0aGlzLmVsfTtcbiAgfVxuXG4gIHByb3RlY3RlZCBfb3B0aW9ucz86IEdyaWRTdGFja05vZGU7XG5cbiAgLyoqIHJldHVybiB0aGUgbmF0aXZlIGVsZW1lbnQgdGhhdCBjb250YWlucyBncmlkIHNwZWNpZmljIGZpZWxkcyBhcyB3ZWxsICovXG4gIHB1YmxpYyBnZXQgZWwoKTogR3JpZEl0ZW1Db21wSFRNTEVsZW1lbnQgeyByZXR1cm4gdGhpcy5lbGVtZW50UmVmLm5hdGl2ZUVsZW1lbnQ7IH1cblxuICAvKiogY2xlYXJzIHRoZSBpbml0aWFsIG9wdGlvbnMgbm93IHRoYXQgd2UndmUgYnVpbHQgKi9cbiAgcHVibGljIGNsZWFyT3B0aW9ucygpIHtcbiAgICBkZWxldGUgdGhpcy5fb3B0aW9ucztcbiAgfVxuXG4gIGNvbnN0cnVjdG9yKHByb3RlY3RlZCByZWFkb25seSBlbGVtZW50UmVmOiBFbGVtZW50UmVmPEdyaWRJdGVtQ29tcEhUTUxFbGVtZW50Pikge1xuICAgIHRoaXMuZWwuX2dyaWRJdGVtQ29tcCA9IHRoaXM7XG4gIH1cblxuICBwdWJsaWMgbmdPbkRlc3Ryb3koKTogdm9pZCB7XG4gICAgdGhpcy5jbGVhck9wdGlvbnMoKTtcbiAgICBkZWxldGUgdGhpcy5jaGlsZFdpZGdldFxuICAgIGRlbGV0ZSB0aGlzLmVsLl9ncmlkSXRlbUNvbXA7XG4gICAgZGVsZXRlIHRoaXMuY29udGFpbmVyO1xuICAgIGRlbGV0ZSB0aGlzLnJlZjtcbiAgfVxufVxuIl19