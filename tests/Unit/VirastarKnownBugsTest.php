<?php

namespace Negaresh\Tests\Unit;

use Alirezasedghi\Virastar\Virastar;

/**
 * Open bugs in the vendored Virastar, written as real assertions and marked
 * incomplete. When fixing one, delete its markTestIncomplete() line and move
 * the test next to the related tests. See docs/dev/BUGFIX-PLAN.md.
 */
class VirastarKnownBugsTest extends TestCase
{
    public function testB20QuestionMarkBecomesPersianQuestionMark(): void
    {
        $this->markTestIncomplete('B20: replacement strings contain a literal \x{061F}');

        $out = (new Virastar(['decode_html_entities' => false]))->cleanup('خوبید ?');
        self::assertSame('خوبید؟', $out);
    }

    public function testB20CleanupZwnjDoesNotInsertEscapeText(): void
    {
        $this->markTestIncomplete('B20: cleanupZWNJ replaces soft hyphen and ZWNJ runs with a literal \x{200c}');

        $out = (new Virastar(['decode_html_entities' => false]))->cleanup("می\u{200c}\u{200c}روم");
        self::assertNoPreserverLeftovers($out);
        self::assertSame("می\u{200c}روم", $out);
    }

    public function testB21FrontMatterIsPreserved(): void
    {
        $this->markTestIncomplete('B21: front matter regex expects a leading space the PHP port never adds');

        $out = (new Virastar(['decode_html_entities' => false]))->cleanup("---\ntitle: یک , دو\n---\nمتن اصلی .");
        self::assertStringContainsString('title: یک , دو', $out);
    }
}
