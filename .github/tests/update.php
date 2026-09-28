<?php
/** @license GNU General Public License version 3 or later; see LICENSE */

require dirname(__DIR__) . '/scripts/update.php';

$directory = sys_get_temp_dir() . '/mcp-package-feed-' . bin2hex(random_bytes(8));
mkdir($directory);
$manifestPath = $directory . '/pkg_joomengine_mcp.xml';
$feedPath = $directory . '/updates.xml';
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
	if (!$condition)
	{
		throw new RuntimeException($message);
	}
	$checks++;
};
$reject = static function (string $tag) use ($manifestPath, $feedPath, $assert): void {
	$before = file_get_contents($feedPath);
	try
	{
		mcpPackageUpdate($tag, $manifestPath, $feedPath);
	}
	catch (RuntimeException $error)
	{
		$assert(file_get_contents($feedPath) === $before, 'A rejected release changed the feed.');
		return;
	}
	throw new RuntimeException('Accepted invalid release: ' . $tag);
};

try
{
	$manifest = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<extension version="6.1" type="package" method="upgrade">
  <packagename>joomengine_mcp</packagename>
  <version>1.2.3</version>
  <description>Component &amp; plugins</description>
  <author>Llewellyn van der Merwe</author>
  <authorUrl>https://dev.vdm.io/</authorUrl>
  <changelogurl>https://raw.githubusercontent.com/joomengine/mcp_component/main/changelog.xml</changelogurl>
</extension>
XML;
	file_put_contents($manifestPath, $manifest);
	file_put_contents($feedPath, '<?xml version="1.0" encoding="utf-8"?><updates/>');
	mcpPackageUpdate('v1.2.3', $manifestPath, $feedPath);
	$feed = simplexml_load_file($feedPath);
	$entry = $feed->update;
	$assert(count($feed->update) === 1 && (string) $entry->version === '1.2.3', 'First release was not added.');
	$assert((string) $entry->element === 'pkg_joomengine_mcp' && (string) $entry->type === 'package'
		&& (string) $entry->client === 'site', 'The feed must identify the Joomla package and site client.');
	$assert((string) $entry->downloads->downloadurl === 'https://github.com/joomengine/mcp_package/archive/refs/tags/v1.2.3.zip'
		&& (string) $entry->downloads->downloadurl['type'] === 'full'
		&& (string) $entry->downloads->downloadurl['format'] === 'zip', 'The download must be the exact tagged package ZIP.');
	$assert((string) $entry->description === 'Component & plugins'
		&& (string) $entry->changelogurl === (string) simplexml_load_string($manifest)->changelogurl,
		'Tagged manifest values were not preserved or XML escaped.');
	$assert((string) $entry->php_minimum === '8.3.0'
		&& (string) $entry->targetplatform['version'] === '6\.[1-9][0-9]*', 'Package requirements changed.');
	$assert(!isset($entry->sha256) && !isset($entry->sha384) && !isset($entry->sha512), 'Only OctoShoom may add archive hashes.');

	$entry->addChild('sha256', str_repeat('a', 64));
	$entry->addChild('sha384', str_repeat('b', 96));
	$entry->addChild('sha512', str_repeat('c', 128));
	$feed->asXML($feedPath);
	$hashed = file_get_contents($feedPath);
	mcpPackageUpdate('v1.2.3', $manifestPath, $feedPath);
	$assert(file_get_contents($feedPath) === $hashed, 'A rerun rewrote published metadata or hashes.');

	file_put_contents($manifestPath, str_replace(['1.2.3', 'Component &amp; plugins'], ['1.3.0', 'Later package'], $manifest));
	mcpPackageUpdate('v1.3.0', $manifestPath, $feedPath);
	$feed = simplexml_load_file($feedPath);
	$assert(count($feed->update) === 2 && (string) $feed->update[0]->sha256 === str_repeat('a', 64)
		&& (string) $feed->update[0]->sha512 === str_repeat('c', 128), 'A new release damaged the previous entry or hashes.');
	$assert((string) $feed->update[1]->version === '1.3.0' && (string) $feed->update[1]->description === 'Later package'
		&& !isset($feed->update[1]->sha256), 'The later release did not use its own manifest metadata.');
	$reject('v1.4.0');
	$reject('v1.3.0-rc1');
	$reject('1.3.0');

	file_put_contents($manifestPath, $manifest);
	$history = file_get_contents($feedPath);
	mcpPackageUpdate('v1.2.3', $manifestPath, $feedPath);
	$assert(file_get_contents($feedPath) === $history, 'An older release rerun changed the latest feed.');
	file_put_contents($manifestPath, str_replace('type="package"', 'type="component"', $manifest));
	$reject('v1.2.3');
	echo $checks . " package metadata checks passed.\n";
}
finally
{
	unlink($manifestPath);
	unlink($feedPath);
	rmdir($directory);
}
