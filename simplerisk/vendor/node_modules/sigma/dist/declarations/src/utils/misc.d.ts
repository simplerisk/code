import { Extent, PlainObject } from "../types.js";
export declare function createElement<T extends HTMLElement>(tag: string, style?: Partial<CSSStyleDeclaration>, attributes?: PlainObject<string>): T;
export declare function getPixelRatio(): number;
export declare function zIndexOrdering<T>(_extent: Extent, getter: (e: T) => number, elements: Array<T>): Array<T>;
