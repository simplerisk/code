'use strict'

var fs = require('fs')
var path = require('path')
var semver = require('semver')

module.exports = function checkVersion (options) {
  options = options || {}
  var searchRoot = options.searchRoot || path.dirname(require.main.filename)
  debug('Search root:', searchRoot)

  var packageJson
  try {
    packageJson = findPackageJson(searchRoot)
  } catch (e) {
    warn(e.message)
    process.exit(1)
  }

  var requiredVersion = packageJson.engines && packageJson.engines.node
  if (!requiredVersion) {
    warn('No engine version requirement in package.json')
    return
  }

  var currentVersion = semver.clean(process.version)
  var versionMessage = (
    'Detected node version: ' + currentVersion + '. ' +
    'Required node version: ' + requiredVersion + '.'
  )
  if (!semver.satisfies(currentVersion, requiredVersion)) {
    warn(versionMessage)
    process.exit(1)
  }

  debug(versionMessage)

  function warn () {
    if (!options.silent) {
      console.error.apply({}, arguments)
    }
  }

  function debug () {
    if (options.debug) {
      console.error.apply({}, arguments)
    }
  }

  function findPackageJson (searchRoot) {
    var packageJsonPath = findPackageJsonPath(searchRoot)
    return require(packageJsonPath)
  }

  function findPackageJsonPath (dirname) {
    var guess = path.resolve(dirname, 'package.json')
    debug('Trying', guess)
    try {
      var stats = fs.statSync(guess)
      if (!stats.isFile()) throw new Error('Not package.json in ' + dirname)
      return guess
    } catch (e) {
      var nextDirname = path.resolve(dirname, '..')
      if (dirname === nextDirname) {
        throw new Error('No package.json found')
      }
      return findPackageJsonPath(nextDirname)
    }
  }
}
