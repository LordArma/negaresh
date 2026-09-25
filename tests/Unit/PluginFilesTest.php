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
}
