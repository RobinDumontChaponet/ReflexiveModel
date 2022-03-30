<?php

declare(strict_types=1);

namespace Reflexive\Model;

enum Cardinality: string
{
	case OneToOne = 'one to one';
	case OneToMany = 'one to many';
	case ManyToOne = 'many to one';
	case ManyToMany = 'many to many';
}
