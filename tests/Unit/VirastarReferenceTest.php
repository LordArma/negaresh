<?php

namespace Negaresh\Tests\Unit;

use Negaresh\Vendor\Virastar\Virastar;

/**
 * I11: Virastar.js's own test suite, run against our PHP Virastar. The cases are extracted from
 * https://github.com/brothersincode/virastar test/virastar.js by
 * tests/tools/extract-virastar-js-cases.js into tests/fixtures/virastar-js-cases.json.
 */
class VirastarReferenceTest extends TestCase
{
    /**
     * Cases where we differ on purpose, by input.
     */
    private const DEVIATIONS = [
        '&quot;گيومه های فارسي&quot;' => 'B2: HTML entities are never decoded (a decoded &lt; would be live markup)',
        '&apos;گيومه های فارسي&apos;' => 'B2: HTML entities are never decoded (a decoded &lt; would be live markup)',
        "```\n(نمایش¬⋅نامه، ۱۳۹۰ شماره یک، ۲۸و۲۹)\n```" => 'Markdown code fences: Negaresh fixes HTML, where pre and code are protected',
    ];

    /**
     * @return array<string, array{string, array<string, bool>, string}>
     */
    public function caseProvider(): array
    {
        $data = json_decode(self::read(NEGARESH_TESTS_DIR . '/fixtures/virastar-js-cases.json'), true);
        self::assertIsArray($data);
        $cases = [];
        foreach ($data['cases'] as $i => $case) {
            $cases[sprintf('#%03d %s', $i, $case['name'])] = [$case['input'], $case['options'], $case['expected']];
        }
        return $cases;
    }

    /**
     * @dataProvider caseProvider
     * @param array<string, bool> $options
     */
    public function testSameResultAsVirastarJs(string $input, array $options, string $expected): void
    {
        if (isset(self::DEVIATIONS[$input])) {
            $this->markTestSkipped('Deliberate deviation: ' . self::DEVIATIONS[$input]);
        }

        self::assertSame($expected, (new Virastar($options))->cleanup($input));
    }

    public function testTheSuiteIsComplete(): void
    {
        self::assertGreaterThanOrEqual(159, count($this->caseProvider()), 'fixture lost cases');
    }
}
