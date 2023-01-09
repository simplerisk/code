// originally form of this was located at
// https://github.com/nodejs/nodejs.org/blob/master/scripts/helpers/downloads.js

const semver = require('semver')

const downloads = [
  {
    title:       'Windows 32-bit Installer',
    templateUrl: 'https://%host%.org/download/release/v%version%/%bin%-v%version%-x86.msi',
    validFor:    '>= 0.7.11'
  },
  {
    title:       'Windows 64-bit Installer',
    templateUrl: 'https://%host%.org/download/release/v%version%/x64/%bin%-v%version%-x64.msi',
    validFor:    '>= 0.7.11 < 1'
  },
  {
    title:       'Windows 64-bit Installer',
    templateUrl: 'https://%host%.org/download/release/v%version%/%bin%-v%version%-x64.msi',
    validFor:    '>= 1'
  },
  {
    title:       'Windows 32-bit Binary',
    templateUrl: 'https://%host%.org/download/release/v%version%/%bin%.exe',
    validFor:    '>= 0.5.1 < 1'
  },
  {
    title:       'Windows 32-bit Binary',
    templateUrl: 'https://%host%.org/download/release/v%version%/win-x86/%bin%.exe',
    validFor:    '>= 1'
  },
  {
    title:       'Windows 64-bit Binary',
    templateUrl: 'https://%host%.org/download/release/v%version%/x64/%bin%.exe',
    validFor:    '>= 0.6.13 < 1'
  },
  {
    title:       'Windows 64-bit Binary',
    templateUrl: 'https://%host%.org/download/release/v%version%/win-x64/%bin%.exe',
    validFor:    '>= 1'
  },
  {
    title:       'macOS Universal Installer',
    templateUrl: 'https://%host%.org/download/release/v%version%/%bin%-v%version%.pkg',
    validFor:    '>= 0.6.1 < 1'
  },
  {
    title:       'macOS 64-bit Installer',
    templateUrl: 'https://%host%.org/download/release/v%version%/%bin%-v%version%.pkg',
    validFor:    '>= 1'
  },
  {
    title:       'macOS 32-bit Binary',
    templateUrl: 'https://%host%.org/download/release/v%version%/%bin%-v%version%-darwin-x86.tar.gz',
    validFor:    '>= 0.8.10 < 1'
  },
  {
    title:       'macOS 64-bit Binary',
    templateUrl: 'https://%host%.org/download/release/v%version%/%bin%-v%version%-darwin-x64.tar.gz',
    validFor:    '>= 0.8.6 < 1.8.3'
  },
  {
    title:       'macOS 64-bit Binary',
    templateUrl: 'https://%host%.org/download/release/v%version%/%bin%-v%version%-darwin-x64.tar.xz',
    validFor:    '>= 1.8.3'
  },
  {
    title:       'Linux 32-bit Binary',
    templateUrl: 'https://%host%.org/download/release/v%version%/%bin%-v%version%-linux-x86.tar.gz',
    validFor:    '>= 0.8.6 < 10'
  },
  {
    title:       'Linux 64-bit Binary',
    templateUrl: 'https://%host%.org/download/release/v%version%/%bin%-v%version%-linux-x64.tar.gz',
    validFor:    '>= 0.8.6 < 1.8.3'
  },
  {
    title:       'Linux 64-bit Binary',
    templateUrl: 'https://%host%.org/download/release/v%version%/%bin%-v%version%-linux-x64.tar.xz',
    validFor:    '>= 1.8.3'
  },
  {
    title:       'Linux PPC LE 64-bit Binary',
    templateUrl: 'https://%host%.org/download/release/v%version%/%bin%-v%version%-linux-ppc64le.tar.xz',
    validFor:    '>= 4.3.2'
  },
  {
    title:       'Linux PPC BE 64-bit Binary',
    templateUrl: 'https://%host%.org/download/release/v%version%/%bin%-v%version%-linux-ppc64.tar.xz',
    validFor:    '4.4.5 - 8'
  },
  {
    title:       'Linux s390x 64-bit Binary',
    templateUrl: 'https://%host%.org/download/release/v%version%/%bin%-v%version%-linux-s390x.tar.xz',
    validFor:    '>= 6.6.0'
  },
  {
    title:       'AIX 64-bit Binary',
    templateUrl: 'https://%host%.org/download/release/v%version%/%bin%-v%version%-aix-ppc64.tar.gz',
    validFor:    '>= 6.7.0'
  },
  {
    title:       'SunOS 32-bit Binary',
    templateUrl: 'https://%host%.org/download/release/v%version%/%bin%-v%version%-sunos-x86.tar.gz',
    validFor:    '>= 0.8.6 < 1'
  },
  {
    title:       'SunOS 32-bit Binary',
    templateUrl: 'https://%host%.org/download/release/v%version%/%bin%-v%version%-sunos-x86.tar.xz',
    validFor:    '>= 3.3.1 < 10'
  },
  {
    title:       'SunOS 64-bit Binary',
    templateUrl: 'https://%host%.org/download/release/v%version%/%bin%-v%version%-sunos-x64.tar.gz',
    validFor:    '>= 0.8.6 < 1'
  },
  {
    title:       'SunOS 64-bit Binary',
    templateUrl: 'https://%host%.org/download/release/v%version%/%bin%-v%version%-sunos-x64.tar.xz',
    validFor:    '>= 3.3.1'
  },
  {
    title:       'ARMv6 32-bit Binary',
    templateUrl: 'https://%host%.org/download/release/v%version%/%bin%-v%version%-linux-armv6l.tar.xz',
    validFor:    '>= 1.2.0'
  },
  {
    title:       'ARMv7 32-bit Binary',
    templateUrl: 'https://%host%.org/download/release/v%version%/%bin%-v%version%-linux-armv7l.tar.xz',
    validFor:    '>= 1'
  },
  {
    title:       'ARMv8 64-bit Binary',
    templateUrl: 'https://%host%.org/download/release/v%version%/%bin%-v%version%-linux-arm64.tar.xz',
    validFor:    '>= 3.3.1'
  },
  {
    title:       'Source Code',
    templateUrl: 'https://%host%.org/download/release/v%version%/%bin%-v%version%.tar.gz',
    validFor:    '>= 0.1.14 < 1'
  },
  {
    title:       'Source Code',
    templateUrl: 'https://%host%.org/download/release/v%version%/%bin%-v%version%.tar.xz',
    validFor:    '>= 1'
  }
]

function isIojs (version) {
  return semver.satisfies(version, '>= 1 < 4')
}

function resolveUrl (item, version) {
  const iojs = isIojs(version)
  const url = item.templateUrl
    .replace(/%host%/g, iojs ? 'iojs' : 'nodejs')
    .replace(/%bin%/g, iojs ? 'iojs' : 'node')
    .replace(/%version%/g, version)
  return { title: item.title, url }
}

function nodeDownloads (version) {
  return downloads
    .filter((item) => semver.satisfies(version, item.validFor))
    .map((item) => resolveUrl(item, version))
}

module.exports = nodeDownloads
