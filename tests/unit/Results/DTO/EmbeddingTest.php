<?php

declare(strict_types=1);

namespace WordPress\AiClient\Tests\unit\Results\DTO;

use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Results\DTO\Embedding;

/**
 * @covers \WordPress\AiClient\Results\DTO\Embedding
 */
class EmbeddingTest extends TestCase
{
    public function testGettersAndArrayConversion(): void
    {
        $embedding = new Embedding([0.1, 1, 0.3], 3);

        $this->assertSame([0.1, 1, 0.3], $embedding->getValues());
        $this->assertSame(3, $embedding->getDimensions());
        $this->assertSame(3, count($embedding));
        $this->assertSame([0.1, 1, 0.3], $embedding->toArray());
        $this->assertSame([0.1, 1, 0.3], $embedding->jsonSerialize());
    }

    public function testIsIterable(): void
    {
        $values = [];

        foreach (new Embedding([0.1, 0.2, 0.3], 3) as $value) {
            $values[] = $value;
        }

        $this->assertSame([0.1, 0.2, 0.3], $values);
    }

    public function testFromArray(): void
    {
        $embedding = Embedding::fromArray([0.1, 0.2, 0.3], 3);

        $this->assertSame([0.1, 0.2, 0.3], $embedding->getValues());
    }

    public function testDimensionsMustBeGreaterThanZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Embedding dimensions must be greater than zero');

        new Embedding([], 0);
    }

    public function testValuesMustBeAList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Embedding values must be a list array.');

        new Embedding([1 => 0.1], 1);
    }

    public function testValuesMustMatchDimensions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Embedding vector length must match dimensions.');

        new Embedding([0.1, 0.2], 3);
    }

    public function testValuesMustBeNumeric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Embedding values must be integers or floats.');

        new Embedding([0.1, '0.2'], 2);
    }

    /**
     * @dataProvider nonFiniteValueProvider
     *
     * @param list<float> $values
     */
    public function testValuesMustBeFinite(array $values, int $index): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches(
            '/^Embedding values must be finite numbers\. Non-finite value found at index ' . $index . '\.$/'
        );

        new Embedding($values, count($values));
    }

    /**
     * @return array<string, array{list<float>, int}>
     */
    public static function nonFiniteValueProvider(): array
    {
        return [
            'NAN first'      => [[NAN, 0.2, 0.3], 0],
            'NAN middle'     => [[0.1, NAN, 0.3], 1],
            'NAN last'       => [[0.1, 0.2, NAN], 2],
            'INF first'      => [[INF, 0.2, 0.3], 0],
            'INF middle'     => [[0.1, INF, 0.3], 1],
            'INF last'       => [[0.1, 0.2, INF], 2],
            '-INF first'     => [[-INF, 0.2, 0.3], 0],
            '-INF middle'    => [[0.1, -INF, 0.3], 1],
            '-INF last'      => [[0.1, 0.2, -INF], 2],
            'INF before NAN' => [[0.1, INF, NAN], 1],
        ];
    }

    public function testNonFiniteValueMessageKeepsGenericPrefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Embedding values must be finite numbers.');

        new Embedding([0.1, NAN], 2);
    }

    public function testNonNumericValueTakesPrecedenceOverNonFiniteValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Embedding values must be integers or floats.');

        new Embedding([INF, '0.2'], 2);
    }

    public function testListCheckTakesPrecedenceOverNonFiniteValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Embedding values must be a list array.');

        new Embedding([1 => INF], 1);
    }

    /**
     * @dataProvider finiteValueProvider
     *
     * @param list<float|int> $values
     */
    public function testAcceptsFiniteValues(array $values): void
    {
        $embedding = new Embedding($values, count($values));

        $this->assertSame($values, $embedding->getValues());
    }

    /**
     * @return array<string, array{list<float|int>}>
     */
    public static function finiteValueProvider(): array
    {
        return [
            'largest floats'           => [[PHP_FLOAT_MAX, -PHP_FLOAT_MAX]],
            'signed zeros'             => [[0.0, -0.0]],
            'integers'                 => [[1, 0, -1]],
            'integers and tiny floats' => [[1, PHP_FLOAT_MIN, 5e-324, -2]],
        ];
    }

    public function testPreservesNegativeZero(): void
    {
        $embedding = new Embedding([0.0, -0.0], 2);

        // -0.0 === 0.0 in PHP, so compare string forms to observe the sign.
        $this->assertSame('-0', (string) $embedding->getValues()[1]);
    }
}
