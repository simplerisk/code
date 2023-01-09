import { basename } from 'path'
import minimumNodeVersion from './api'

if (process.argv.length !== 2) {
  console.error(`Usage: ${basename(process.argv[1])}`)
  process.exit(1)
}

minimumNodeVersion()
  .then(version => console.log(version))
  .catch(err => {
    console.error('Caught error:', err.stack)
    process.exit(1)
  })
