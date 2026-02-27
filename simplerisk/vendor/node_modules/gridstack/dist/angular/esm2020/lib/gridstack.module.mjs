/**
 * gridstack.component.ts 12.4.2
 * Copyright (c) 2022-2024 Alain Dumesny - see GridStack root license
 */
import { NgModule } from "@angular/core";
import { GridstackItemComponent } from "./gridstack-item.component";
import { GridstackComponent } from "./gridstack.component";
import * as i0 from "@angular/core";
/**
 * @deprecated Use GridstackComponent and GridstackItemComponent as standalone components instead.
 *
 * This NgModule is provided for backward compatibility but is no longer the recommended approach.
 * Import components directly in your standalone components or use the new Angular module structure.
 *
 * @example
 * ```typescript
 * // Preferred approach - standalone components
 * @Component({
 *   selector: 'my-app',
 *   imports: [GridstackComponent, GridstackItemComponent],
 *   template: '<gridstack></gridstack>'
 * })
 * export class AppComponent {}
 *
 * // Legacy approach (deprecated)
 * @NgModule({
 *   imports: [GridstackModule]
 * })
 * export class AppModule {}
 * ```
 */
export class GridstackModule {
}
GridstackModule.ɵfac = i0.ɵɵngDeclareFactory({ minVersion: "12.0.0", version: "14.3.0", ngImport: i0, type: GridstackModule, deps: [], target: i0.ɵɵFactoryTarget.NgModule });
GridstackModule.ɵmod = i0.ɵɵngDeclareNgModule({ minVersion: "14.0.0", version: "14.3.0", ngImport: i0, type: GridstackModule, imports: [GridstackItemComponent,
        GridstackComponent], exports: [GridstackItemComponent,
        GridstackComponent] });
GridstackModule.ɵinj = i0.ɵɵngDeclareInjector({ minVersion: "12.0.0", version: "14.3.0", ngImport: i0, type: GridstackModule, imports: [GridstackItemComponent,
        GridstackComponent] });
i0.ɵɵngDeclareClassMetadata({ minVersion: "12.0.0", version: "14.3.0", ngImport: i0, type: GridstackModule, decorators: [{
            type: NgModule,
            args: [{
                    imports: [
                        GridstackItemComponent,
                        GridstackComponent,
                    ],
                    exports: [
                        GridstackItemComponent,
                        GridstackComponent,
                    ],
                }]
        }] });
//# sourceMappingURL=data:application/json;base64,eyJ2ZXJzaW9uIjozLCJmaWxlIjoiZ3JpZHN0YWNrLm1vZHVsZS5qcyIsInNvdXJjZVJvb3QiOiIiLCJzb3VyY2VzIjpbIi4uLy4uLy4uLy4uL2FuZ3VsYXIvcHJvamVjdHMvbGliL3NyYy9saWIvZ3JpZHN0YWNrLm1vZHVsZS50cyJdLCJuYW1lcyI6W10sIm1hcHBpbmdzIjoiQUFBQTs7O0dBR0c7QUFFSCxPQUFPLEVBQUUsUUFBUSxFQUFFLE1BQU0sZUFBZSxDQUFDO0FBRXpDLE9BQU8sRUFBRSxzQkFBc0IsRUFBRSxNQUFNLDRCQUE0QixDQUFDO0FBQ3BFLE9BQU8sRUFBRSxrQkFBa0IsRUFBRSxNQUFNLHVCQUF1QixDQUFDOztBQUUzRDs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7OztHQXNCRztBQVdILE1BQU0sT0FBTyxlQUFlOzs0R0FBZixlQUFlOzZHQUFmLGVBQWUsWUFSeEIsc0JBQXNCO1FBQ3RCLGtCQUFrQixhQUdsQixzQkFBc0I7UUFDdEIsa0JBQWtCOzZHQUdULGVBQWUsWUFSeEIsc0JBQXNCO1FBQ3RCLGtCQUFrQjsyRkFPVCxlQUFlO2tCQVYzQixRQUFRO21CQUFDO29CQUNSLE9BQU8sRUFBRTt3QkFDUCxzQkFBc0I7d0JBQ3RCLGtCQUFrQjtxQkFDbkI7b0JBQ0QsT0FBTyxFQUFFO3dCQUNQLHNCQUFzQjt3QkFDdEIsa0JBQWtCO3FCQUNuQjtpQkFDRiIsInNvdXJjZXNDb250ZW50IjpbIi8qKlxyXG4gKiBncmlkc3RhY2suY29tcG9uZW50LnRzIDEyLjQuMlxyXG4gKiBDb3B5cmlnaHQgKGMpIDIwMjItMjAyNCBBbGFpbiBEdW1lc255IC0gc2VlIEdyaWRTdGFjayByb290IGxpY2Vuc2VcclxuICovXHJcblxyXG5pbXBvcnQgeyBOZ01vZHVsZSB9IGZyb20gXCJAYW5ndWxhci9jb3JlXCI7XHJcblxyXG5pbXBvcnQgeyBHcmlkc3RhY2tJdGVtQ29tcG9uZW50IH0gZnJvbSBcIi4vZ3JpZHN0YWNrLWl0ZW0uY29tcG9uZW50XCI7XHJcbmltcG9ydCB7IEdyaWRzdGFja0NvbXBvbmVudCB9IGZyb20gXCIuL2dyaWRzdGFjay5jb21wb25lbnRcIjtcclxuXHJcbi8qKlxyXG4gKiBAZGVwcmVjYXRlZCBVc2UgR3JpZHN0YWNrQ29tcG9uZW50IGFuZCBHcmlkc3RhY2tJdGVtQ29tcG9uZW50IGFzIHN0YW5kYWxvbmUgY29tcG9uZW50cyBpbnN0ZWFkLlxyXG4gKlxyXG4gKiBUaGlzIE5nTW9kdWxlIGlzIHByb3ZpZGVkIGZvciBiYWNrd2FyZCBjb21wYXRpYmlsaXR5IGJ1dCBpcyBubyBsb25nZXIgdGhlIHJlY29tbWVuZGVkIGFwcHJvYWNoLlxyXG4gKiBJbXBvcnQgY29tcG9uZW50cyBkaXJlY3RseSBpbiB5b3VyIHN0YW5kYWxvbmUgY29tcG9uZW50cyBvciB1c2UgdGhlIG5ldyBBbmd1bGFyIG1vZHVsZSBzdHJ1Y3R1cmUuXHJcbiAqXHJcbiAqIEBleGFtcGxlXHJcbiAqIGBgYHR5cGVzY3JpcHRcclxuICogLy8gUHJlZmVycmVkIGFwcHJvYWNoIC0gc3RhbmRhbG9uZSBjb21wb25lbnRzXHJcbiAqIEBDb21wb25lbnQoe1xyXG4gKiAgIHNlbGVjdG9yOiAnbXktYXBwJyxcclxuICogICBpbXBvcnRzOiBbR3JpZHN0YWNrQ29tcG9uZW50LCBHcmlkc3RhY2tJdGVtQ29tcG9uZW50XSxcclxuICogICB0ZW1wbGF0ZTogJzxncmlkc3RhY2s+PC9ncmlkc3RhY2s+J1xyXG4gKiB9KVxyXG4gKiBleHBvcnQgY2xhc3MgQXBwQ29tcG9uZW50IHt9XHJcbiAqXHJcbiAqIC8vIExlZ2FjeSBhcHByb2FjaCAoZGVwcmVjYXRlZClcclxuICogQE5nTW9kdWxlKHtcclxuICogICBpbXBvcnRzOiBbR3JpZHN0YWNrTW9kdWxlXVxyXG4gKiB9KVxyXG4gKiBleHBvcnQgY2xhc3MgQXBwTW9kdWxlIHt9XHJcbiAqIGBgYFxyXG4gKi9cclxuQE5nTW9kdWxlKHtcclxuICBpbXBvcnRzOiBbXHJcbiAgICBHcmlkc3RhY2tJdGVtQ29tcG9uZW50LFxyXG4gICAgR3JpZHN0YWNrQ29tcG9uZW50LFxyXG4gIF0sXHJcbiAgZXhwb3J0czogW1xyXG4gICAgR3JpZHN0YWNrSXRlbUNvbXBvbmVudCxcclxuICAgIEdyaWRzdGFja0NvbXBvbmVudCxcclxuICBdLFxyXG59KVxyXG5leHBvcnQgY2xhc3MgR3JpZHN0YWNrTW9kdWxlIHt9XHJcbiJdfQ==