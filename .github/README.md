# Package release automation

OctoJPack runs in [mcp_component](https://github.com/joomengine/mcp_component). It takes the latest tag of the component and both plugins, builds this repository's installable package, and pushes `main` together with its `vX.Y.Z` tag. The package version follows the component version.

That tag push starts **Update package feed with OctoShoom** here. The workflow reads `pkg_joomengine_mcp.xml` from the triggering tag, adds that version to this repository's [update feed](https://raw.githubusercontent.com/joomengine/mcp_package/main/.github/joomengine_mcp_update_server.xml), then calls `octoleo/octoshoom@master` to hash the exact download:

```text
https://github.com/joomengine/mcp_package/archive/refs/tags/vX.Y.Z.zip
```

The feed identifies `pkg_joomengine_mcp` as a Joomla package with the site client. Its update URL belongs to the generated package manifest. The component and each plugin have separate update feeds for their own tagged ZIPs. The package changelog uses the component changelog, since their versions match.

The feed starts empty because no package has been published yet. Released entries and their hashes stay in the feed; rerunning a tag workflow leaves its existing entry intact and lets OctoShoom finish any missing hashes. Metadata and hash commits go to `main`, without changing the release tag. Only tag pushes trigger this workflow, so those commits do not create a loop.

## Setup and first release

Merge the package automation before releasing the component. Add these repository or organization Actions secrets, with access granted to `mcp_package`:

| Secret | Value |
| --- | --- |
| `GPG_KEY` | Git signing private key. |
| `GPG_USER` | Signing key identity. |
| `SSH_KEY` | Private SSH key with push access to this repository. |
| `SSH_PUB` | Matching public SSH key. |
| `GIT_USER` | Commit author name. |
| `GIT_EMAIL` | Commit author email. |

`octoleo/git-user@v2` configures the identity once; OctoShoom reuses it. Allow that identity's signed commits to reach `main` under the repository's branch rules. Enable GitHub Actions in this repository. OctoJPack's SSH push from the component workflow creates the tag event here; no workflow dispatch token or extra repository dispatch is needed.

Release both plugin repositories first so they have tags. Then run the component's release workflow with the intended version. It publishes and hashes the component before OctoJPack publishes this package. The package workflow runs automatically afterward. Check both workflows have succeeded before distributing the package tag ZIP. If the package hash workflow fails, fix the reported issue and rerun it from Actions using the same tag.

## Files maintained here

Keep workflows, scripts, tests, documentation and the package feed inside `.github`. OctoJPack replaces the generated visible files using its existing cleanup operation, which preserves dot directories including `.github`. There is no additional ignore input to configure in the current action. Do not edit its generated root manifest, package ZIP contents, or README by hand.

The metadata helper only creates Joomla update entries; OctoShoom owns hashing and OctoJPack owns packaging. The helper's Joomla 6.1+ and PHP 8.3 requirements match the component's package configuration. Update those requirements together when support changes.

Run the focused metadata checks with `php .github/tests/update.php`. CI also parses the preserved feed and lints the PHP files.
