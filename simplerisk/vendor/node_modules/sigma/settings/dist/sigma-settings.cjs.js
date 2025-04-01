'use strict';

if (process.env.NODE_ENV === "production") {
  module.exports = require("./sigma-settings.cjs.prod.js");
} else {
  module.exports = require("./sigma-settings.cjs.dev.js");
}
