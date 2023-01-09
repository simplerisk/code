'use strict'

const jsonist = require('jsonist')
    , after   = require('after')
    , semver  = require('semver')

    , nodeurl = 'https://nodejs.org/download/release/index.json'
    , iojsurl = 'https://iojs.org/download/release/index.json'


function versionData (callback) {
  var done = after(2, afterFetch)
    , nodeData
    , iojsData

  jsonist.get(nodeurl, function (err, data) {
    if (err)
      return done(err)
    if (!Array.isArray(data))
      return done(new Error('Could not fetch Node.js version data from nodejs.org'))

    nodeData = data
    nodeData.forEach(function (d) {
      d.name = 'Node.js'
      d.url ='https://nodejs.org/download/release/' + d.version + '/'
    })
    done()
  })

  jsonist.get(iojsurl, function (err, data) {
    if (err)
      return done(err)
    if (!Array.isArray(data))
      return done(new Error('Could not fetch io.js version data from iojs.org'))

    iojsData = data
    iojsData.forEach(function (d) {
      d.name = 'io.js'
      d.url ='https://iojs.org/download/release/' + d.version + '/'
    })
    done()
  })

  function afterFetch (err) {
    if (err)
      return callback(err)

    var data = nodeData.concat(iojsData)

    data.sort(function (a, b) {
      return semver.compare(b.version, a.version)
    })

    callback(null, data)
  }
}


module.exports = versionData
module.exports.downloads = require('./node-downloads')
