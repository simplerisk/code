module.exports = function expectedNodeVersion (cwd) {
  var version = process.env.npm_package_engines_node
  if (version !== undefined) return version

  cwd = cwd || process.cwd()
  var path = require('path')
  var fs = require('fs')
  try {
    var nvmRcPath = path.join(cwd, '.nvmrc')
    var file = fs.readFileSync(nvmRcPath, 'utf8')
    if (file && (file = file.trim())) return file
  } catch (err) {
    if (err.code !== 'ENOENT') throw err
  }

  var packageJsonPath = path.join(cwd, 'package.json')
  try {
    var json = require(packageJsonPath)
  } catch (e) {}
  return json && json.engines && json.engines.node
}
