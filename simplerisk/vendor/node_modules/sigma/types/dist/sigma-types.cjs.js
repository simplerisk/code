'use strict';

if (process.env.NODE_ENV === "production") {
  module.exports = require("./sigma-types.cjs.prod.js");
} else {
  module.exports = require("./sigma-types.cjs.dev.js");
}
