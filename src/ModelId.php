<?php

declare(strict_types=1);

namespace Reflexive\Model;

trait ModelId
{
	#[Property]
	#[Column('id', isId: true, type: 'BIGINT(20) UNSIGNED', autoIncrement: true)]
	protected int|string|array $id = -1;
}
