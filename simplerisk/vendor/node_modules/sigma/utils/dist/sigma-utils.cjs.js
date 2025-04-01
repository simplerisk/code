'use strict';

if (process.env.NODE_ENV === "production") {
  module.exports = require("./sigma-utils.cjs.prod.js");
} else {
  module.exports = require("./sigma-utils.cjs.dev.js");
}
