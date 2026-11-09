Description: Phaser is a fast, free and fun HTML5 game framework.
Version: 4.2.1
License: MIT
Source: https://github.com/phaserjs/phaser/releases/tag/v4.2.1
Download: https://cdn.jsdelivr.net/npm/phaser@4.2.1/dist/phaser.min.js

Changes made to the original library:
- None. Using the official minified distribution file.

This is the single copy of Phaser for the Player ecosystem. Games load it through the
local_playergames/phaser AMD module (amd/src/phaser.js), never with their own script tag.

To update this library:
1. Download the new version from https://cdn.jsdelivr.net/npm/phaser@X.X.X/dist/phaser.min.js
2. Replace javascript/phaser.min.js with the downloaded file
3. Update the version number in this file and in thirdpartylibs.xml
4. Stay within the same major version: a new major version breaks every game that depends on
   this plugin at once and has to be coordinated across them.
