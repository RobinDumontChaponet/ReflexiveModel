<?php

declare(strict_types=1);

namespace Reflexive\Model;

interface Collection
{
	public function has(SCRUDInterface $model): bool;
}
