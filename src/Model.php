<?php

declare(strict_types=1);

namespace Reflexive\Core;

use ReflectionClass;

abstract class Model implements \JsonSerializable
{
	private array $classAttributes = [];
	private array $propertiesAttributes = [];

	private array $getters = [];

    public function __construct(
		protected int|string $id = -1,
	) {
		$classReflection = new ReflectionClass($this);
		// get attributes of class
		foreach($classReflection->getAttributes(ModelAttribute::class) as $attributeReflection) {
			$this->classAttributes[] = $attributeReflection->newInstance();
		}

		// get attributes of properties
		foreach($classReflection->getProperties() as $propertyReflection) {
			foreach($propertyReflection->getAttributes(ModelAttribute::class) as $attributeReflection) {
				$modelAttribute = $attributeReflection->newInstance();
				$this->propertiesAttributes[$propertyReflection->getName()] = $modelAttribute;

				if(!empty($modelAttribute->makeGetter))
					$this->getters[is_string($modelAttribute->makeGetter)? $modelAttribute->makeGetter : 'get'.ucfirst($propertyReflection->getName())] = $propertyReflection->getName();
			}
		}
	}

    public function getId(): int|string
    {
        return $this->id;
    }

    public function setId(int|string $id): void
    {
        $this->id = $id;
    }

    public function __toString()
    {
        return  self::class.' [ id: '.$this->id.(((!get_parent_class())) ? ' ]' : ';  ');
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->getId(),
        ];
    }

	// function __get(string $name): mixed
	// {
	// 	if (array_key_exists($name, $this->model_data)) {
	// 		return $this->model_data[$name];
	// 	}
	// 	return null;
	// }

	public function __call($name, $arguments)
	{
		if(isset($this->getters[$name])) {
			return $this->{$this->getters[$name]};
		} else {
			trigger_error('Call to undefined method '.__CLASS__.'::'.$name.'()', E_USER_ERROR);
		}
	}
}
