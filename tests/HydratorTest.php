<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Model\Column;
use Reflexive\Model\Hydrator;
use Reflexive\Model\Model;
use Reflexive\Model\ModelId;
use Reflexive\Model\Property;
use Reflexive\Model\SCRUDInterface;
use Reflexive\Model\Table;

#[Table('hydrator_test_records')]
final class HydratorTestRecord extends Model
{
	use ModelId;

	#[Property]
	#[Column('name')]
	protected string $name = '';

	#[Property]
	#[Column('payload', type: 'json')]
	protected array $payload = [];
}

#[Table('hydrator_test_states')]
enum HydratorTestState implements SCRUDInterface
{
	use \Reflexive\Model\ModelEnum;

	case Ready;
	case Done;
}

final class HydratorTest extends TestCase
{
	public function testGetHydratorReturnsSameInstancePerModelClass(): void
	{
		// Verifies hydrators are cached per model class.
		$this->assertSame(
			Hydrator::getHydrator(HydratorTestRecord::class),
			Hydrator::getHydrator(HydratorTestRecord::class),
		);
	}

	public function testFetchHydratesModelColumnsAndCachesById(): void
	{
		// Verifies rows hydrate typed model properties and reuse cached identities.
		$hydrator = Hydrator::getHydrator(HydratorTestRecord::class);
		$row = (object) [
			'id' => 14,
			'name' => 'Hydrated',
			'payload' => '{"a":1}',
		];

		[$key, $model] = $hydrator->fetch($row, null);
		[, $cached] = $hydrator->fetch($row, null);

		$this->assertSame('14', $key);
		$this->assertInstanceOf(HydratorTestRecord::class, $model);
		$this->assertSame('Hydrated', $model->getName());
		$this->assertSame(['a' => 1], $model->getPayload());
		$this->assertSame($model, $cached);
	}

	public function testFetchHydratesEnumCase(): void
	{
		// Verifies enum-backed models hydrate from their id column.
		[$key, $case] = Hydrator::getHydrator(HydratorTestState::class)->fetch((object) ['id' => 'Done'], null);

		$this->assertSame('Done', $key);
		$this->assertSame(HydratorTestState::Done, $case);
	}
}
