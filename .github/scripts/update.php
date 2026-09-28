<?php
/**
 * @package    JoomEngine.Mcp
 * @author     Llewellyn van der Merwe <https://dev.vdm.io>
 * @copyright  Copyright (C) 2026 Vast Development Method. All rights reserved.
 * @license    GNU General Public License version 3 or later; see LICENSE
 */

/** Add the triggering package tag to the preserved Joomla update feed. */
function mcpPackageUpdate(string $tag, string $manifestPath, string $feedPath): void
{
	if (preg_match('/\Av((?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*))\z/D', $tag, $match) !== 1)
	{
		throw new RuntimeException('Expected a stable package tag such as v1.2.3.');
	}

	$manifest = simplexml_load_file($manifestPath, SimpleXMLElement::class, LIBXML_NONET);
	$feed = simplexml_load_file($feedPath, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS);
	$version = $match[1];

	if ($manifest === false || $feed === false || $feed->getName() !== 'updates'
		|| (string) $manifest['type'] !== 'package' || (string) $manifest->packagename !== 'joomengine_mcp'
		|| (string) $manifest->version !== $version)
	{
		throw new RuntimeException('Expected the tagged JoomEngine MCP package manifest to match the release version.');
	}

	$archive = 'https://github.com/joomengine/mcp_package/archive/refs/tags/' . $tag . '.zip';

	foreach ($feed->update as $existing)
	{
		if ((string) $existing->version === $version)
		{
			if ((string) $existing->element !== 'pkg_joomengine_mcp' || (string) $existing->type !== 'package'
				|| (string) $existing->client !== 'site' || (string) $existing->downloads->downloadurl !== $archive)
			{
				throw new RuntimeException('The existing release entry does not describe this package archive.');
			}

			return; // Keep the published metadata and OctoShoom hashes on a rerun.
		}
	}

	$entry = $feed->addChild('update');
	foreach ([
		'name' => 'JoomEngine MCP Package',
		'description' => (string) $manifest->description,
		'element' => 'pkg_joomengine_mcp',
		'type' => 'package',
		'client' => 'site',
		'version' => $version,
		'infourl' => 'https://github.com/joomengine/mcp_package/tree/' . $tag,
		'maintainer' => (string) $manifest->author,
		'maintainerurl' => (string) $manifest->authorUrl,
		'php_minimum' => '8.3.0',
		'changelogurl' => (string) $manifest->changelogurl,
	] as $name => $value)
	{
		$entry->addChild($name);
		$entry->{$name} = $value;
	}

	$entry->infourl->addAttribute('title', 'JoomEngine MCP Package');
	$download = $entry->addChild('downloads')->addChild('downloadurl', $archive);
	$download->addAttribute('type', 'full');
	$download->addAttribute('format', 'zip');
	$entry->addChild('tags')->addChild('tag', 'stable');
	$platform = $entry->addChild('targetplatform');
	$platform->addAttribute('name', 'joomla');
	$platform->addAttribute('version', '6\.[1-9][0-9]*');
	$document = dom_import_simplexml($feed)->ownerDocument;
	$document->formatOutput = true;

	if ($document->save($feedPath) === false)
	{
		throw new RuntimeException('Cannot write the package update feed.');
	}
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__)
{
	try
	{
		mcpPackageUpdate($argv[1] ?? '', $argv[2] ?? '', dirname(__DIR__) . '/joomengine_mcp_update_server.xml');
		echo "Package update metadata ready for OctoShoom.\n";
	}
	catch (Throwable $error)
	{
		fwrite(STDERR, $error->getMessage() . "\n");
		exit(1);
	}
}
