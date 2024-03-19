# Entry Versions

**WARNING: Experimental and unsupported. This is just a prototype idea.**

Cache a version of an entry each time it is saved for a full edit history. Recover an entry and re-publish it, and view full content history in Data Source XML.

## Installation
* extract the `entry_versions` folder to your `/extensions` directory
* enable the Entry Versions row in the Extensions page
* add the Entry Versions field to a section

## Bump Version via frontend event

		<!-- Bump Version -->
		<input type="hidden" name="fields[entry-versions]" value="yes"/>