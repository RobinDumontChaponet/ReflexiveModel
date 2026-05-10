<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Model\Model;
use Reflexive\Model\ModelEnum;
use Reflexive\Model\ModelId;
use Reflexive\Model\SCRUDInterface;
use Reflexive\Model\Table;

#[Table('scrud_interface_test_records')]
final class SCRUDInterfaceTestRecord extends Model
{
	use ModelId;
}

#[Table('scrud_interface_test_states')]
enum SCRUDInterfaceTestState implements SCRUDInterface
{
	use ModelEnum;

	case Draft;
}

final class SCRUDInterfaceTest extends TestCase
{
	public function testModelImplementsCrudInterface(): void
	{
		// Verifies models expose the package CRUD contract.
		$this->assertInstanceOf(SCRUDInterface::class, new SCRUDInterfaceTestRecord());
	}

	public function testModelEnumImplementsCrudInterface(): void
	{
		// Verifies model enums expose the package CRUD contract.
		$this->assertInstanceOf(SCRUDInterface::class, SCRUDInterfaceTestState::Draft);
	}
}
