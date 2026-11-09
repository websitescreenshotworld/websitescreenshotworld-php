# Releasing

1. Move the "Unreleased" section of `CHANGELOG.md` under the new version and date.
2. Set the version: `CURRENT` in `src/Version.php`.
3. Commit, then tag that commit `v<version>`, such as `v0.1.1`, and push the tag.
4. Packagist picks the tag up from this repository through its GitHub hook and publishes
   `websitescreenshotworld/websitescreenshotworld-php`; the tag's workflow only runs the tests.

The libraries for the other languages share the version number, so a change made in all of them is released at
the same version in each.
