/**
 * gridstack-item.component.ts 11.5.1
 * Copyright (c) 2022-2024 Alain Dumesny - see GridStack root license
 */
/**
 * Base interface that all widgets need to implement in order for GridstackItemComponent to correctly save/load/delete/..
 */
import { Injectable } from '@angular/core';
import * as i0 from "@angular/core";
export class BaseWidget {
    /**
     * REDEFINE to return an object representing the data needed to re-create yourself, other than `selector` already handled.
     * This should map directly to the @Input() fields of this objects on create, so a simple apply can be used on read
     */
    serialize() { return; }
    /**
     * REDEFINE this if your widget needs to read from saved data and transform it to create itself - you do this for
     * things that are not mapped directly into @Input() fields for example.
     */
    deserialize(w) {
        // save full description for meta data
        this.widgetItem = w;
        if (!w)
            return;
        if (w.input)
            Object.assign(this, w.input);
    }
}
BaseWidget.ɵfac = i0.ɵɵngDeclareFactory({ minVersion: "12.0.0", version: "14.3.0", ngImport: i0, type: BaseWidget, deps: [], target: i0.ɵɵFactoryTarget.Injectable });
BaseWidget.ɵprov = i0.ɵɵngDeclareInjectable({ minVersion: "12.0.0", version: "14.3.0", ngImport: i0, type: BaseWidget });
i0.ɵɵngDeclareClassMetadata({ minVersion: "12.0.0", version: "14.3.0", ngImport: i0, type: BaseWidget, decorators: [{
            type: Injectable
        }] });
//# sourceMappingURL=data:application/json;base64,eyJ2ZXJzaW9uIjozLCJmaWxlIjoiYmFzZS13aWRnZXQuanMiLCJzb3VyY2VSb290IjoiIiwic291cmNlcyI6WyIuLi8uLi8uLi8uLi9hbmd1bGFyL3Byb2plY3RzL2xpYi9zcmMvbGliL2Jhc2Utd2lkZ2V0LnRzIl0sIm5hbWVzIjpbXSwibWFwcGluZ3MiOiJBQUFBOzs7R0FHRztBQUVIOztHQUVHO0FBRUgsT0FBTyxFQUFFLFVBQVUsRUFBRSxNQUFNLGVBQWUsQ0FBQzs7QUFJMUMsTUFBTSxPQUFnQixVQUFVO0lBSy9COzs7T0FHRztJQUNJLFNBQVMsS0FBZ0MsT0FBTyxDQUFDLENBQUM7SUFFekQ7OztPQUdHO0lBQ0ksV0FBVyxDQUFDLENBQW9CO1FBQ3JDLHNDQUFzQztRQUN0QyxJQUFJLENBQUMsVUFBVSxHQUFHLENBQUMsQ0FBQztRQUNwQixJQUFJLENBQUMsQ0FBQztZQUFFLE9BQU87UUFFZixJQUFJLENBQUMsQ0FBQyxLQUFLO1lBQUcsTUFBTSxDQUFDLE1BQU0sQ0FBQyxJQUFJLEVBQUUsQ0FBQyxDQUFDLEtBQUssQ0FBQyxDQUFDO0lBQzdDLENBQUM7O3VHQXJCb0IsVUFBVTsyR0FBVixVQUFVOzJGQUFWLFVBQVU7a0JBRC9CLFVBQVUiLCJzb3VyY2VzQ29udGVudCI6WyIvKipcclxuICogZ3JpZHN0YWNrLWl0ZW0uY29tcG9uZW50LnRzIDExLjUuMVxyXG4gKiBDb3B5cmlnaHQgKGMpIDIwMjItMjAyNCBBbGFpbiBEdW1lc255IC0gc2VlIEdyaWRTdGFjayByb290IGxpY2Vuc2VcclxuICovXHJcblxyXG4vKipcclxuICogQmFzZSBpbnRlcmZhY2UgdGhhdCBhbGwgd2lkZ2V0cyBuZWVkIHRvIGltcGxlbWVudCBpbiBvcmRlciBmb3IgR3JpZHN0YWNrSXRlbUNvbXBvbmVudCB0byBjb3JyZWN0bHkgc2F2ZS9sb2FkL2RlbGV0ZS8uLlxyXG4gKi9cclxuXHJcbmltcG9ydCB7IEluamVjdGFibGUgfSBmcm9tICdAYW5ndWxhci9jb3JlJztcclxuaW1wb3J0IHsgTmdDb21wSW5wdXRzLCBOZ0dyaWRTdGFja1dpZGdldCB9IGZyb20gJy4vdHlwZXMnO1xyXG5cclxuIEBJbmplY3RhYmxlKClcclxuIGV4cG9ydCBhYnN0cmFjdCBjbGFzcyBCYXNlV2lkZ2V0IHtcclxuXHJcbiAgLyoqIHZhcmlhYmxlIHRoYXQgaG9sZHMgdGhlIGNvbXBsZXRlIGRlZmluaXRpb24gb2YgdGhpcyB3aWRnZXRzICh3aXRoIHNlbGVjdG9yLHgseSx3LGgpICovXHJcbiAgcHVibGljIHdpZGdldEl0ZW0/OiBOZ0dyaWRTdGFja1dpZGdldDtcclxuXHJcbiAgLyoqXHJcbiAgICogUkVERUZJTkUgdG8gcmV0dXJuIGFuIG9iamVjdCByZXByZXNlbnRpbmcgdGhlIGRhdGEgbmVlZGVkIHRvIHJlLWNyZWF0ZSB5b3Vyc2VsZiwgb3RoZXIgdGhhbiBgc2VsZWN0b3JgIGFscmVhZHkgaGFuZGxlZC5cclxuICAgKiBUaGlzIHNob3VsZCBtYXAgZGlyZWN0bHkgdG8gdGhlIEBJbnB1dCgpIGZpZWxkcyBvZiB0aGlzIG9iamVjdHMgb24gY3JlYXRlLCBzbyBhIHNpbXBsZSBhcHBseSBjYW4gYmUgdXNlZCBvbiByZWFkXHJcbiAgICovXHJcbiAgcHVibGljIHNlcmlhbGl6ZSgpOiBOZ0NvbXBJbnB1dHMgfCB1bmRlZmluZWQgIHsgcmV0dXJuOyB9XHJcblxyXG4gIC8qKlxyXG4gICAqIFJFREVGSU5FIHRoaXMgaWYgeW91ciB3aWRnZXQgbmVlZHMgdG8gcmVhZCBmcm9tIHNhdmVkIGRhdGEgYW5kIHRyYW5zZm9ybSBpdCB0byBjcmVhdGUgaXRzZWxmIC0geW91IGRvIHRoaXMgZm9yXHJcbiAgICogdGhpbmdzIHRoYXQgYXJlIG5vdCBtYXBwZWQgZGlyZWN0bHkgaW50byBASW5wdXQoKSBmaWVsZHMgZm9yIGV4YW1wbGUuXHJcbiAgICovXHJcbiAgcHVibGljIGRlc2VyaWFsaXplKHc6IE5nR3JpZFN0YWNrV2lkZ2V0KSAge1xyXG4gICAgLy8gc2F2ZSBmdWxsIGRlc2NyaXB0aW9uIGZvciBtZXRhIGRhdGFcclxuICAgIHRoaXMud2lkZ2V0SXRlbSA9IHc7XHJcbiAgICBpZiAoIXcpIHJldHVybjtcclxuXHJcbiAgICBpZiAody5pbnB1dCkgIE9iamVjdC5hc3NpZ24odGhpcywgdy5pbnB1dCk7XHJcbiAgfVxyXG4gfVxyXG4iXX0=