<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Core\Comparator;
use ReflectionClass;
use ReflectionNamedType;

abstract class Model implements \JsonSerializable
{
	protected static array $getters = [];
	protected static array $setters = [];
	protected static array $lengths = [];
	protected static array $attributedProperties = [];

	protected array $modifiedProperties = [];
	public bool $ignoreModifiedProperties = false;
	public bool $updateUnmodified = false;

	public static function getPropertyMaxLength(string $className, string $propertyName): int
	{
		return self::$lengths[$className][$propertyName] ?? 0;
	}

	public function getModifiedPropertiesNames(): array
	{
		return array_unique($this->modifiedProperties);
	}

	public function resetModifiedPropertiesNames(): void
	{
		$this->modifiedProperties = [];
	}

	public static function initModelAttributes(): void
	{
		if(!isset(self::$attributedProperties[static::class])) {
			$classReflection = new ReflectionClass(static::class);

			self::$attributedProperties[static::class] = [];
			// get attributes of properties
			foreach($classReflection->getProperties() as $propertyReflection) {
				foreach($propertyReflection->getAttributes(Property::class) as $attributeReflection) {
					$modelAttribute = $attributeReflection->newInstance();

					if($propertyReflection->isProtected())
						self::$attributedProperties[static::class][$propertyReflection->getName()] = !$modelAttribute->readonly;

					if($modelAttribute->maxLength)
						static::$lengths[static::class][$propertyReflection->getName()] = $modelAttribute->maxLength;

					if(!empty($modelAttribute->makeGetter)) {
						$type = $propertyReflection->getType();
						$prefix = ($type instanceof ReflectionNamedType && $type->getName() == 'bool')? 'is' : 'get';

						static::$getters[static::class][is_string($modelAttribute->makeGetter)? $modelAttribute->makeGetter : $prefix.ucfirst($propertyReflection->getName())] = $propertyReflection->getName();
					}

					if(!empty($modelAttribute->makeSetter))
						static::$setters[static::class][is_string($modelAttribute->makeSetter)? $modelAttribute->makeSetter : 'set'.ucfirst($propertyReflection->getName())] = $propertyReflection->getName();
				}
			}
		}
	}

    public function __construct(
		#[Column('id', isId: true, type: 'BIGINT(20) UNSIGNED', autoIncrement: true)]
		protected int $id = -1,
	)
	{
		static::initModelAttributes();
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
        return  static::class.' [ id: '.$this->id.(((!get_parent_class())) ? ' ]' : ';  ');
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->getId(),
        ];
    }

	private function getValue(string $name): mixed
	{
		if(isset(static::$attributedProperties[static::class][$name])) {
			return $this->{$name};
		}

		set_error_handler(self::errorHandler());
		trigger_error('Access (get) to undefined property '.static::class.'::$'.$name, E_USER_ERROR);

		return null;
	}

	private function setValue(string $name, mixed $value): void
	{
		if(isset(static::$attributedProperties[static::class][$name])) {
			if(static::$attributedProperties[static::class][$name]) {
				if($this->{$name} !== $value)
					$this->modifiedProperties[] = $name;

				$this->{$name} = $value;
			} else {
				set_error_handler(self::errorHandler());
				trigger_error(static::class.'::$'.$name.' is readonly', E_USER_ERROR);
			}
		} else {
			set_error_handler(self::errorHandler());
			trigger_error('Access (set) to undefined property '.static::class.'::$'.$name, E_USER_ERROR);
		}
	}

	function __get(string $name): mixed
	{
		return $this->getValue($name);
	}

	public function __set($name, $value)
	{
		$this->setValue($name, $value);
	}

	public function __isset($name)
	{
		return isset(static::$attributedProperties[static::class][$name]) && isset($this->{$name});
	}

	public function __call(string $name, array $arguments): mixed
	{
		if(isset(static::$getters[static::class][$name])) { // auto-getter
			return $this->getValue(static::$getters[static::class][$name]);
		} elseif(isset(static::$setters[static::class][$name])) { // auto-setter
			return $this->setValue(static::$setters[static::class][$name], ...$arguments);
		} else {
			set_error_handler(self::errorHandler());
			trigger_error('Call to undefined method '.static::class.'::'.$name.'()', E_USER_ERROR);
		}

		return null;
	}

	private static function errorHandler(): null|callable
	{
		return function($level, $message, $file, $line) {
			$level; $line; // shut up, IDEs
			if($file == __FILE__) {
				$debug = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

				echo PHP_EOL, '<strong>'.$message.'</strong> in '.($debug[2]['file']??'?').' on line '.($debug[2]['line']??'?'), PHP_EOL;
				return true; // prevent the PHP error handler from continuing
			}
			return false;
		};
	}

	/*
	 * Active Record
	 */

	public static function search(?string $name = null, ?Comparator $comparator = null, string|int|float|array|bool $value = null): ModelStatement
	{
		$query = new Search(static::class);

		if(isset($name))
			$query->where($name, $comparator, $value);

		return $query;
	}

	public static function create(Model &$model): ModelStatement
	{
		return new Create($model);
	}

	public static function read(?string $name = null, ?Comparator $comparator = null, string|int|float|array|bool $value = null): ModelStatement
	{
		$query = new Read(static::class);

		if(isset($name))
			$query->where($name, $comparator, $value);

		return $query;
	}

	public static function update(Model &$model): ModelStatement
	{
		return new Update($model);
	}

	public static function delete(Model &$model): ModelStatement
	{
		return new Delete($model);
	}

	public static function count(): ModelStatement
	{
		return new Count(static::class);
	}
}
