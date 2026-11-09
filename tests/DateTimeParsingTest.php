<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WebsiteScreenshotWorld\Exception\WebsiteScreenshotWorldException;
use WebsiteScreenshotWorld\Internal\Fields;

final class DateTimeParsingTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function dates(): iterable
    {
        yield 'milliseconds' => ['2026-09-26T10:30:04.512Z', '2026-09-26T10:30:04.512000+00:00'];
        yield 'no fraction' => ['2026-09-26T10:30:04Z', '2026-09-26T10:30:04.000000+00:00'];
        yield 'one digit' => ['2026-09-26T10:30:04.5Z', '2026-09-26T10:30:04.500000+00:00'];
        yield 'nanoseconds' => ['2026-09-26T10:30:04.123456789Z', '2026-09-26T10:30:04.123456+00:00'];
        yield 'offset' => ['2026-09-26T16:00:04.512+05:30', '2026-09-26T10:30:04.512000+00:00'];
        yield 'offset without colon' => ['2026-09-26T05:30:04.512-0500', '2026-09-26T10:30:04.512000+00:00'];
        yield 'lower case' => ['2026-09-26t10:30:04.512z', '2026-09-26T10:30:04.512000+00:00'];
    }

    #[DataProvider('dates')]
    public function testParsesLeniently(string $input, string $expected): void
    {
        $parsed = Fields::parseDateTime($input);

        self::assertNotNull($parsed);
        self::assertSame($expected, $parsed->format('Y-m-d\TH:i:s.uP'));
        self::assertSame('UTC', $parsed->getTimezone()->getName());
    }

    public function testRefusesWhatIsNotAnIsoDateTime(): void
    {
        self::assertNull(Fields::parseDateTime('tomorrow'));
        self::assertNull(Fields::parseDateTime('2026-09-26'));
        self::assertNull(Fields::parseDateTime('2026-09-26T10:30:04'));
    }

    public function testMissingRequiredDateFails(): void
    {
        $this->expectException(WebsiteScreenshotWorldException::class);
        $this->expectExceptionMessage('"triggered_at"');

        Fields::dateTime(['triggered_at' => 'soon'], 'triggered_at');
    }
}
