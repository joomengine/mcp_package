# Package repository boundaries

- Keep maintained files under `.github`; OctoJPack preserves this directory while regenerating the package repository's visible files. Do not add manual changes to generated root files.
- Packaging belongs exclusively to `joomengine/mcp_component` and its hard-coded `.octojpack` configuration. Its extension sources use latest tags, and the component determines the package version. Do not add a builder or OctoJPack invocation here.
- This repository's `vX.Y.Z` tag push triggers its update workflow. Read release metadata from that exact tagged package manifest, not the current `main` manifest.
- Keep the package-only feed at `.github/joomengine_mcp_update_server.xml`, with `pkg_joomengine_mcp`, type `package`, and client `site` (Joomla client ID 0). Downloads are this repository's automatic GitHub tag ZIPs.
- Preserve released entries and hashes. Update `main` after publication; never move an existing release tag to include its own hash. Keep the initial feed empty until the first published tag.
- Use `octoleo/git-user@v2` once, then `octoleo/octoshoom@master` with the fixed feed location. Inherit the configured Git identity. Do not duplicate their authentication, download or hash implementation.
- The package shares the component's version and changelog. Record package automation changes in the component's `CHANGELOG.md` and `changelog.xml` under `[[[NEXT_VERSION]]]` in a coordinated component PR; do not invent a separate package version or generated changelog here.
- Keep Joomla/PHP compatibility in the metadata helper aligned with the component package configuration. Test first publication, later versions, reruns and invalid tag/manifest combinations with `php .github/tests/update.php` before marking a PR ready.
