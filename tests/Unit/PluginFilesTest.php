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

        $changelog = self::read(dirname(NEGARESH_PLUGIN_DIR, 3) . '/CHANGELOG.md');
        self::assertStringContainsString('## [' . $header[1] . ']', $changelog, 'CHANGELOG.md has no section for ' . $header[1]);
    }
}
