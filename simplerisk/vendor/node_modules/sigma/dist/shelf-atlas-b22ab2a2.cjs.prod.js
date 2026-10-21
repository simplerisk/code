'use strict';

var colors = require('./colors-2b1720c5.cjs.prod.js');

/**
 * Sigma.js Shelf-Packing Atlas Utility
 * =====================================
 *
 * Generic shelf-packing algorithm for arranging rectangular items into
 * an atlas texture. Used by label attachments to pack user-rendered
 * canvases into a WebGL texture.
 *
 * @module
 */

var MARGIN = 2;

/**
 * Cursor tracking the current packing position within the atlas.
 */

/**
 * Location of a packed item within the atlas.
 */

/**
 * An item to be packed into the atlas.
 */

/**
 * Packs as many items as possible into a single canvas using a shelf (row)
 * algorithm. Items that don't fit are returned in `remaining`.
 */
function packItemsOnPage(items, ctx, cursor) {
  var margin = arguments.length > 3 && arguments[3] !== undefined ? arguments[3] : MARGIN;
  var _ctx$canvas = ctx.canvas,
    canvasW = _ctx$canvas.width,
    canvasH = _ctx$canvas.height;
  var x = cursor.x,
    y = cursor.y,
    rowHeight = cursor.rowHeight,
    maxRowWidth = cursor.maxRowWidth;
  var atlas = {};
  var remaining = [];
  var _iterator = colors._createForOfIteratorHelper(items),
    _step;
  try {
    for (_iterator.s(); !(_step = _iterator.n()).done;) {
      var item = _step.value;
      var w = item.width + margin;
      var h = item.height + margin;

      // Skip items that can never fit this page
      if (w > canvasW || h > canvasH || x + w > canvasW && y + rowHeight + h > canvasH) {
        remaining.push(item);
        continue;
      }

      // Move to next row if current item doesn't fit horizontally
      if (x + w > canvasW) {
        maxRowWidth = Math.max(maxRowWidth, x);
        x = 0;
        y += rowHeight;
        rowHeight = h;
      }
      item.draw(ctx, x, y);
      atlas[item.key] = {
        x: x,
        y: y,
        width: item.width,
        height: item.height
      };
      x += w;
      rowHeight = Math.max(rowHeight, h);
    }
  } catch (err) {
    _iterator.e(err);
  } finally {
    _iterator.f();
  }
  maxRowWidth = Math.max(maxRowWidth, x);
  return {
    atlas: atlas,
    cursor: {
      x: x,
      y: y,
      rowHeight: rowHeight,
      maxRowWidth: maxRowWidth
    },
    remaining: remaining
  };
}

exports.packItemsOnPage = packItemsOnPage;
