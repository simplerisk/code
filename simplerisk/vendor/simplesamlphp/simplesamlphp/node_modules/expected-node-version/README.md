# expected-node-version

## API
var version = require('expected-node-version')([directory])

## Load order
The module tries to load the version using the fastest mechanisms.
It falls pack to loading the package.json content using `require`. So that content should will be cached.

1. fetch version from package.json environment variable, which gets set by `npm start`
2. fetch version from .nvmrc file
3. fetch version from package.json value `engines.node`
