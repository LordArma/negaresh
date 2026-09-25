<?php

namespace Negaresh\Tests\Unit;

/**
 * Checks on the shipped PHP files themselves.
 */
class PluginFilesTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public function phpFileProvider(): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(NEGARESH_PLUGIN_DIR, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ('php' === $file->getExtension()) {
                $files[substr($file->getPathname(), strlen(NEGARESH_PLUGIN_DIR) + 1)] = [$file->getPathname()];
            }
        }
        return $files;
    }

    /**
     * B23: 4.0 shipped negaresh-class.php with a newline before `<?php`. That byte was sent on
     * every request, so logins, redirects and headers failed and RSS feeds were invalid XML.
     *
     * @dataProvider phpFileProvider
     */
    public function testB23NoOutputOutsidePhpTags(string $path): void
    {
        $code = self::read($path);

        self::assertStringStartsWith('<?php', $code, 'nothing (not even a BOM or newline) may precede <?php');
        self::assertDoesNotMatchRegularExpression('/\?>\s*$/', $code, 'omit the closing ?> at the end of the file');
    }

    /** @dataProvider phpFileProvider */
    public function testFilesBailOutWhenLoadedDirectly(string $path): void
    {
        $code = self::read($path);

        self::assertMatchesRegularExpression("/defined\\('(ABSPATH|WP_UNINSTALL_PLUGIN)'\\)|^namespace /m", $code);
    }

    /**
     * I7: the release workflow refuses a tag that does not match these, so keep them in step.
     */
    public function testVersionIsTheSameEverywhere(): void
    {
        $main = self::read(NEGARESH_PLUGIN_DIR . '/negaresh.php');
        preg_match('/^ \* Version: (\S+)$/m', $main, $header);
        preg_match("/define\('NEGARESH_VERSION', '([^']+)'\);/", $main, $constant);

        self::assertNotEmpty($header, 'Version: missing from the plugin header');
        self::assertNotEmpty($constant, 'NEGARESH_VERSION missing');
        self::assertSame($header[1], $constant[1], 'plugin header Version and NEGARESH_VERSION differ');

        // I8: readme.txt (wordpress.org) must describe the same release and requirements.
        $readme = self::read(NEGARESH_PLUGIN_DIR . '/readme.txt');
        foreach (['Stable tag' => 'Version', 'Requires at least' => 'Requires at least', 'Requires PHP' => 'Requires PHP', 'Tested up to' => 'Tested up to'] as $readme_key => $header_key) {
            preg_match('/^' . preg_quote($readme_key, '/') . ': (\S+)$/m', $readme, $in_readme);
            preg_match('/^ \* ' . preg_quote($header_key, '/') . ': (\S+)$/m', $main, $in_header);
            self::assertNotEmpty($in_readme, "$readme_key missing from readme.txt");
            self::assertSame($in_header[1] ?? null, $in_readme[1], "readme.txt $readme_key differs from the plugin header");
        }
        self::assertStringContainsString('= ' . $header[1] . ' =', $readme, 'readme.txt changelog has no entry for ' . $header[1]);

        $changelog = self::read(dirname(NEGARESH_PLUGIN_DIR, 3) . '/CHANGELOG.md');
        self::assertStringContainsString('## [' . $header[1] . ']', $changelog, 'CHANGELOG.md has no section for ' . $header[1]);
    }

    /**
     * wordpress.org: the plugin carries its license, and the MIT notice of the bundled Virastar.
     */
    public function testLicensesShipWithThePlugin(): void
    {
        self::assertStringContainsString('GNU GENERAL PUBLIC LICENSE', self::read(NEGARESH_PLUGIN_DIR . '/license.txt'));
        $virastar = self::read(NEGARESH_PLUGIN_DIR . '/includes/Virastar-LICENSE.txt');
        self::assertStringContainsString('Copyright (c) 2022 Alireza Sedghi', $virastar);
        self::assertStringContainsString('Permission is hereby granted', $virastar);
    }
}
