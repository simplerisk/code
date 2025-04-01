'use strict';

if (process.env.NODE_ENV === "production") {
  module.exports = require("./sigma-rendering.cjs.prod.js");
} else {
  module.exports = require("./sigma-rendering.cjs.dev.js");
}
