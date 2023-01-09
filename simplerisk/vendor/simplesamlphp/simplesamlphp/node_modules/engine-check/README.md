# Node engine check

This is a drop-in module to enforce the node engine version specified in your
project's package.json.

## Usage

Add an ["engines" section](https://docs.npmjs.com/files/package.json#engines)
to your `package.json`, if you don't have it already.

```json
{
  "name": "my-app",
  ...
  "engines": {
    "node": ">=4.0"
  }
}
```

Add this to the entry point of your node app:

```javascript
// index.js
require('engine-check')()
console.log('Hello node >=4.0!')
```

And then run your app with the correct version of node:

```text
bash$ node -v
v4.3.1

bash$ node index.js
Hello node >=4.0!

bash$ echo $?
0
```

Or with an outdated one:

```text
bash$ node -v
v0.12.10

bash$ node index.js
Detected node version: 0.12.10. Required node version: >=4.0.

bash$ echo $?
1
```

Note: the warning is sent to STDERR. If you'd rather not have any output, you
can set the `silent` option:

```javascript
// index.js
require('engine-check')({ silent: true })
console.log('Hello node >=4.0!')
```

```text
bash$ node -v
v0.12.10

bash$ node index.js

bash$ echo $?
1
```


### Available options

- **`silent`** (default: `false`)
  - when `true`, completely disables all STDERR output
- **`debug`** (default: `false`)
  - when `true`, STDERR output becomes more verbose
- **`searchRoot`** (default: the dirname of the main module)
  - where to start searching for the project's `package.json`
  - **use with caution**

## Caveats

Currently only the `"node"` engine is checked.

## License

Copyright (c) 2016, Peter-Paul van Gemerden.
Distributed under the ISC license (see the `LICENSE` file).
