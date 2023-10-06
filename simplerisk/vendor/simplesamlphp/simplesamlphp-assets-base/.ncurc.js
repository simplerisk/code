module.exports = {
  /** Filter out non-major version updates.
    @param {string} packageName               The name of the dependency.
    @param {string} currentVersion            Current version declaration (may be range).
    @param {SemVer[]} currentVersionSemver    Current version declaration in semantic versioning format (may be range).
    @param {string} upgradedVersion           Upgraded version.
    @param {SemVer} upgradedVersionSemver     Upgraded version in semantic versioning format.
    @returns {boolean}                        Return true if the upgrade should be kept, otherwise it will be ignored.
  */
  filterResults: (packageName, { currentVersion, currentVersionSemver, upgradedVersion, upgradedVersionSemver }) => {
    const currentMajorVersion = parseInt(currentVersionSemver?.[0]?.major, 10)
    const upgradedMajorVersion = parseInt(upgradedVersionSemver?.major, 10)
    if (upgradedMajorVersion > currentMajorVersion) {
      console.warn("Skipping major release for " + packageName + " from " + currentMajorVersion + " > " + upgradedMajorVersion + ".")
      return false
    }
    return true
  }
}
