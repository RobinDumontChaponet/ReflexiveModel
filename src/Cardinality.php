<?php

declare(strict_types=1);

namespace Reflexive\Model;

enum Cardinality: string
{
	case OneToOne = 'one to one';
	case OneToMany = 'one to many';
	case ManyToMany = 'many to many';
}
