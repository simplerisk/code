var FONT_STYLE_KEYWORDS = new Set(["italic", "oblique"]);
var FONT_WEIGHT_KEYWORDS = new Set(["bold", "bolder", "lighter"]);

/**
 * Parses a CSS-like font string into separate family, weight, and style.
 * Handles strings like "bold Arial, sans-serif" or "italic Georgia, serif".
 * Keywords "normal" are consumed but treated as default.
 */
function parseFontString(font) {
  var weight = "normal";
  var style = "normal";
  var trimmed = font.trim();
  // Split only on spaces before the first comma or end of first word-run,
  // to avoid splitting font family lists like "Arial, sans-serif"
  var parts = trimmed.split(/\s+/);
  var familyStart = 0;
  for (var i = 0; i < parts.length; i++) {
    var lower = parts[i].toLowerCase();
    if (FONT_STYLE_KEYWORDS.has(lower)) {
      style = lower;
      familyStart = i + 1;
    } else if (FONT_WEIGHT_KEYWORDS.has(lower) || /^\d{3}$/.test(lower)) {
      weight = lower;
      familyStart = i + 1;
    } else if (lower === "normal") {
      familyStart = i + 1;
    } else {
      break;
    }
  }
  var family = parts.slice(familyStart).join(" ");
  return {
    family: family || trimmed,
    weight: weight,
    style: style
  };
}

/**
 * Function used to create DOM elements easily.
 */
function createElement(tag, style, attributes) {
  var element = document.createElement(tag);
  if (style) {
    for (var k in style) {
      element.style[k] = style[k];
    }
  }
  if (attributes) {
    for (var _k in attributes) {
      element.setAttribute(_k, attributes[_k]);
    }
  }
  return element;
}

/**
 * Function returning the browser's pixel ratio.
 */
function getPixelRatio() {
  if (typeof window.devicePixelRatio !== "undefined") return window.devicePixelRatio;
  return 1;
}

export { createElement as c, getPixelRatio as g, parseFontString as p };
