'use strict';

if (process.env.NODE_ENV === "production") {
  module.exports = require("./sigma.cjs.prod.js");
} else {
  module.exports = require("./sigma.cjs.dev.js");
}
