export declare function extend<T>(array: T[], values: Set<T>): void;
export declare function isPlainObject(value: unknown): boolean;
export declare function assign<T>(target: Partial<T> | undefined, ...objects: Array<Partial<T | undefined>>): T;
export declare function assignDeep<T>(target: Partial<T> | undefined, ...objects: Array<Partial<T | undefined>>): T;
