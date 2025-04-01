import { Attributes } from "graphology-types";
import { EdgeProgramType } from "../../edge.js";
import { CreateEdgeArrowHeadProgramOptions } from "../edge-arrow-head/index.js";
export declare function createEdgeArrowProgram<N extends Attributes = Attributes, E extends Attributes = Attributes, G extends Attributes = Attributes>(inputOptions?: Partial<Omit<CreateEdgeArrowHeadProgramOptions, "extremity">>): EdgeProgramType<N, E, G>;
declare const EdgeArrowProgram: EdgeProgramType<Attributes, Attributes, Attributes>;
export default EdgeArrowProgram;
