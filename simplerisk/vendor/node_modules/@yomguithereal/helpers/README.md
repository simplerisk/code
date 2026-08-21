[![Build Status](https://travis-ci.org/Yomguithereal/helpers.svg)](https://travis-ci.org/Yomguithereal/helpers)

# Yomguithereal's helpers

Miscellaneous helper functions.

## Installation

```
npm install @yomguithereal/helpers
```

## Usage

* [#.extend](#extend)

## #.extend

Pushes multiple values to the given array.

It is faster than doing `array.push.apply(array, values)` and works by first mutating the array's length and then setting the new indices (benchmark proved it is faster than a loop of pushes).

```js
import extend from '@yomguithereal/helpers/extend';

const a = [1, 2, 3];
extend(a, [4, 5, 6]);

a
>>> [1, 2, 3, 4, 5, 6]
```


