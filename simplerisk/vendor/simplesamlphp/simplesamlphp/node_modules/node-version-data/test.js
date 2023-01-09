const assert = require('assert')
    , nvd    = require('./')


nvd(function (err, data) {
  assert.ifError(err)

  assert.deepEqual(
      {
        version: 'v0.1.14',
        date: '2011-08-26',
        files: [ 'src' ],
        v8: '1.3.15.0',
        lts: false,
        name: 'Node.js',
        url: 'https://nodejs.org/download/release/v0.1.14/',
        security: false
      }
    , data[data.length - 1]
  )

  assert.deepEqual(
      {
        version: 'v4.2.1',
        date: '2015-10-13',
        files:
         [ 'headers',
           'linux-arm64',
           'linux-armv6l',
           'linux-armv7l',
           'linux-x64',
           'linux-x86',
           'osx-x64-pkg',
           'osx-x64-tar',
           'src',
           'sunos-x64',
           'sunos-x86',
           'win-x64-exe',
           'win-x64-msi',
           'win-x86-exe',
           'win-x86-msi' ],
        npm: '2.14.7',
        v8: '4.5.103.35',
        uv: '1.7.5',
        zlib: '1.2.8',
        openssl: '1.0.2d',
        modules: '46',
        lts: 'Argon',
        name: 'Node.js',
        url: 'https://nodejs.org/download/release/v4.2.1/',
        security: false
      }
    , data.filter(function (d) { return d.version == 'v4.2.1' })[0]
  )

  assert.deepEqual(
      {
        version: 'v1.0.1',
        date: '2015-01-14',
        files:
         [ 'linux-armv7l',
           'linux-x64',
           'linux-x86',
           'osx-x64-pkg',
           'osx-x64-tar',
           'src',
           'win-x64-exe',
           'win-x64-msi',
           'win-x86-exe',
           'win-x86-msi' ],
        npm: '2.1.18',
        v8: '3.31.74.1',
        uv: '1.2.0',
        zlib: '1.2.8',
        openssl: '1.0.1k',
        modules: '42',
        name: 'io.js',
        url: 'https://iojs.org/download/release/v1.0.1/'
      }
    , data.filter(function (d) { return d.version == 'v1.0.1' })[0]
  )
})


const expectedDownloads110 = [
  { title: 'Windows 32-bit Installer', url: 'https://iojs.org/download/release/v1.1.0/iojs-v1.1.0-x86.msi' },
  { title: 'Windows 64-bit Installer', url: 'https://iojs.org/download/release/v1.1.0/iojs-v1.1.0-x64.msi' },
  { title: 'Windows 32-bit Binary', url: 'https://iojs.org/download/release/v1.1.0/win-x86/iojs.exe' },
  { title: 'Windows 64-bit Binary', url: 'https://iojs.org/download/release/v1.1.0/win-x64/iojs.exe' },
  { title: 'macOS 64-bit Installer', url: 'https://iojs.org/download/release/v1.1.0/iojs-v1.1.0.pkg' },
  { title: 'macOS 64-bit Binary', url: 'https://iojs.org/download/release/v1.1.0/iojs-v1.1.0-darwin-x64.tar.gz' },
  { title: 'Linux 32-bit Binary', url: 'https://iojs.org/download/release/v1.1.0/iojs-v1.1.0-linux-x86.tar.gz' },
  { title: 'Linux 64-bit Binary', url: 'https://iojs.org/download/release/v1.1.0/iojs-v1.1.0-linux-x64.tar.gz' },
  { title: 'ARMv7 32-bit Binary', url: 'https://iojs.org/download/release/v1.1.0/iojs-v1.1.0-linux-armv7l.tar.xz' },
  { title: 'Source Code', url:  'https://iojs.org/download/release/v1.1.0/iojs-v1.1.0.tar.xz' }
]
