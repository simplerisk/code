import { clean, minSatisfying } from 'semver'

import versionData from './version-data'
import expectedNodeVersion from 'expected-node-version'

export default () => {
  const expected = expectedNodeVersion()

  return versionData().then(records => {
    const versions = records
      .filter(record => record.name === 'Node.js')
      .map(record => record.version)
      .map(version => clean(version))

    return minSatisfying(versions, expected)
  })
}
