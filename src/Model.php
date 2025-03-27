<?php

declare(strict_types=1);

namespace Reflexive\Model;

use Reflexive\Core\Comparator;
use ReflectionClass;
use ReflectionProperty;
use ReflectionNamedType;
use ReflectionUnionType;

use Composer\Script\Event;

const PHP_TAB = "\t";

abstract class Model implements SCRUDInterface
{
	protected static array $getters = [];
	protected static array $setters = [];
	protected static array $lengths = [];
	protected static array $attributedProperties = [];
	// public bool $autoInitializeProperties = true;

	protected array $modifiedProperties = [];
	public bool $ignoreModifiedProperties = false;
	public bool $updateUnmodified = false;
	public bool $updateReferences = true;

	// protected int|string|array $modelId = -1;
	#[Property(readOnly: true)]
	protected ?string $reflexive_subType = null;

	public static function getPropertyMaxLength(string $className, string $propertyName): int
	{
		return self::$lengths[$className][$propertyName] ?? 0;
	}

	public function getModifiedPropertiesNames(): array
	{
		return array_unique($this->modifiedProperties);
	}

	public function hasModifiedProperties(): bool
	{
		return !empty($this->modifiedProperties);
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
						self::$attributedProperties[static::class][$propertyReflection->getName()] = !$modelAttribute->readOnly;

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

	public function __construct()
	{
		static::initModelAttributes();
		$this->reflexive_subType = $this::class;
	}

	public function getModelId(): int|string|array
	{
		$schema = Schema::initFromAttributes($this::class);
		if($schema)
			return $schema->getModelId($this);

		return null;
	}
	public function getModelIdString(): ?string
	{
		$schema = Schema::initFromAttributes($this::class);
		if($schema)
			return ''.$schema->getModelIdString($this);

		return null;
	}

	public function setModelId(int|string ...$id): void
	{
		// if(count($id) == 1)
		// 	$id = array_values($id)[0];

		$schema = Schema::initFromAttributes($this::class);
		if($schema)
			foreach($id as $key => $value)
				$schema->setModelId($this, $key, $value);
	}

	public function __wakeup()
	{
		static::initModelAttributes();
	}

	public function __toString()
	{
		return '{'.static::class.'}';
	}

	public function __debugInfo(): array
	{
		$array = get_object_vars($this);
		unset($array['modifiedProperties']);
		unset($array['ignoreModifiedProperties']);
		unset($array['updateUnmodified']);
		unset($array['updateReferences']);
		unset($array['reflexive_subType']);

		return $array;
	}

	private function &getValue(string $name): mixed
	{
		if(isset(static::$attributedProperties[static::class][$name])) {
			if(property_exists($this, $name) && !isset($this->{$name})) {
				$propertyReflection = new ReflectionProperty(static::class, $name);
				$type = $propertyReflection->getType();
				if($type instanceof ReflectionNamedType && $type->getName() == Collection::class)
					$this->{$name} = new ModelCollection(self::class);
				elseif($type->allowsNull())
					$this->{$name} = null;
			}

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
				if(($this->{$name} ?? null) !== $value)
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

	function &__get(string $name): mixed
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
			if(empty($arguments))
				$arguments = [null];

			return $this->setValue(static::$setters[static::class][$name], ...$arguments);
		} else {
			set_error_handler(self::errorHandler());
			trigger_error('Call to undefined method '.static::class.'::'.$name.'()', E_USER_ERROR);
		}

		return null;
	}

	public static function getReflexiveSubTypes(): array
	{
		return Schema::getSchema(static::class)?->getSubTypes() ?? [];
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

	public static function search(?string $name = null, ?Comparator $comparator = null, string|int|float|array|bool|null $value = null): Pull
	{
		$query = new Search(static::class);

		if(isset($name))
			$query->where($name, $comparator, $value);

		return $query;
	}

	public static function create(Model &$model): Push
	{
		return new Create(static::class, $model);
	}

	public static function read(?string $name = null, ?Comparator $comparator = null, string|int|float|array|bool|null $value = null): Pull
	{
		$query = new Read(static::class);

		if(isset($name))
			$query->where($name, $comparator, $value);

		return $query;
	}

	public static function update(Model &$model): Push
	{
		return new Update(static::class, $model);
	}

	public static function delete(Model &$model): ModelStatement
	{
		return new Delete(static::class, $model);
	}

	public static function count(): Pull
	{
		return new Count(static::class);
	}

	public static function exportGraph(Event $event): void
	{
		$io = $event->getIO();
		// $extra = $event->getComposer()->getPackage()->getExtra();

		$classNames = [];

		$classLoader = require('vendor/autoload.php');
		if($classLoader->isClassMapAuthoritative()) {
			$io->write('//ClassMap is authoritative, using generated classMap', true, $io::VERBOSE);

			$classNames = array_keys($classLoader->getClassMap());
		} else {
			$io->write('//Using composer autoload with temporary classMap', true, $io::VERBOSE);
			foreach($event->getComposer()->getPackage()->getAutoload() as $type => $autoLoad) {
				$io->write('//Checking '.$type.' autoload', true, $io::VERBOSE);
				foreach($autoLoad as $nameSpace => $filePath) {
					$io->write('//Checking in '.$filePath.' for nameSpace "'.$nameSpace.'"', true, $io::VERBOSE);

					$classMap = \Composer\Autoload\ClassMapGenerator::createMap($filePath);
					foreach($classMap as $className => $classPath) {
						$io->write('//Loaded '.$className.' in '.$classPath, true, $io::VERY_VERBOSE);
						$classNames[] = $className;
					}
				}
			}
		}

		$io->write('//Found '.count($classNames).' model classes', true, $io::NORMAL);
		$io->write('', true);
		$count = 0;

		$io->writeRaw('digraph {');
		$io->writeRaw(PHP_TAB. 'edge [color=red, arrowsize=2];');
		$io->writeRaw(PHP_TAB. 'node [shape=plaintext, color=white];'. PHP_EOL);

		foreach($classNames as $className) {
			/** @psalm-var class-string $className */
			$classReflection = new ReflectionClass($className);
			if($classReflection->isAbstract() || $classReflection->isTrait() || (!is_a($className, Model::class, true) && !is_a($className, SCRUDInterface::class, true)))
				continue;

			$label = '<table bgcolor="grey90" border="0" style="rounded"><tr><td border="1" sides="B" colspan="2">'.$className.'</td></tr>';

			$schema = Schema::getSchema($className);
			if($schema) {
				// generated methods (with Property attribute)
				$generatedMethods = [];

				if($schema->isEnum()) { // is ModelEnum
					// enum cases
					/** @psalm-suppress UndefinedMethod */
					$label.= '<tr><td align="right"><font color="grey64">+</font></td><td>'. implode(', ', array_map(fn($case) => '::'.$case->name, $className::cases())). '</td></tr>';

				} elseif(is_a($className, Model::class, true))  { // is Model
					$className::initModelAttributes();

					// class properties
					foreach($classReflection->getProperties() as $propertyReflection) {
						if($propertyReflection->class != $className)
							continue;

						$propertyName = $propertyReflection->getName();

						$visibility = '<font color="grey64">';
						if(isset(static::$attributedProperties["$className"][$propertyName])) { // have Property attribute
							$visibility.= '®';
							$readonly = !static::$attributedProperties["$className"][$propertyName];
						} else {
							$readonly = $propertyReflection->isReadOnly();
						}
						$visibility.= $propertyReflection->isPrivate()? '-' : ($propertyReflection->isProtected()? '*' : '+');

						$visibility.= ' '.($readonly?'ro':'rw').'</font>';

						$type = $propertyReflection->getType();
						if($type instanceof ReflectionUnionType) {
							$typeString = implode(' | ', array_map(fn(ReflectionNamedType $v): string => $v->getName(), $type->getTypes()));
						} elseif($type instanceof ReflectionNamedType) {
							$typeString = $type->getName();
						} else {
							$typeString = 'undefined';
						}

						$label.= '<tr><td align="right">'.$visibility.'</td><td align="left" port="'.$propertyName.'">' .$propertyName.' <font color="grey64">: '. $typeString .'</font></td></tr>';
					}

					$label.= '<hr />';

					// getters
					if(isset($className::$getters["$className"])) {
						foreach($className::$getters["$className"] as $methodName => $propertyName) {
							if($propertyReflection->isPrivate()) {
								continue;
							}

							$propertyReflection = $classReflection->getProperty($propertyName);
							$visibility = '<font color="grey64">® +</font>';

							$type = $propertyReflection->getType();
							if($type instanceof ReflectionUnionType) {
								$typeString = implode(' | ', array_map(fn(ReflectionNamedType $v): string => $v->getName(), $type->getTypes()));
							} elseif($type instanceof ReflectionNamedType) {
								$typeString = $type->getName();
							} else {
								$typeString = 'undefined';
							}

							$label.= '<tr><td align="right">'.$visibility.'</td><td align="left" port="'.$methodName.'">' .$methodName.'() <font color="grey64">: '. $typeString .'</font></td></tr>';

							$generatedMethods[] = $methodName;
						}
					}
					// setters
					if(isset($className::$setters["$className"])) {
						foreach($className::$setters["$className"] as $methodName => $propertyName) {
							if($propertyReflection->isPrivate()) {
								continue;
							}

							$propertyReflection = $classReflection->getProperty($propertyName);
							$visibility = '<font color="grey64">® +</font>';

							$type = $propertyReflection->getType();
							if($type instanceof ReflectionUnionType) {
								$typeString = implode(' | ', array_map(fn(ReflectionNamedType $v): string => $v->getName(), $type->getTypes()));
							} elseif($type instanceof ReflectionNamedType) {
								$typeString = $type->getName();
							} else {
								$typeString = 'undefined';
							}

							$label.= '<tr><td align="right">'.$visibility.'</td><td align="left" port="'.$methodName.'">' .$methodName.'() <font color="grey64">: '. $typeString .'</font></td></tr>';

							$generatedMethods[] = $methodName;
						}
					}
				}

				// class methods
				foreach($classReflection->getMethods() as $methodReflection) {
					if($methodReflection->class !== $className || in_array($methodReflection->getName(), $generatedMethods))
						continue;

					$visibility = '<font color="grey64">';
					$visibility.= $methodReflection->isPrivate()? '-' : ($methodReflection->isProtected()? '*' : '+');
					$visibility.= '</font>';

					$type = $methodReflection->getReturnType();
					if($type instanceof ReflectionUnionType) {
						$typeString = implode(' | ', array_map(fn(ReflectionNamedType $v): string => $v->getName(), $type->getTypes()));
					} elseif($type instanceof ReflectionNamedType) {
						$typeString = $type->getName();
					} else {
						$typeString = 'undefined';
					}

					$label.= '<tr><td align="right">'.$visibility.'</td><td align="left" port="'.$methodReflection->getName().'">' .$methodReflection->getName().'() <font color="grey64">: '. $typeString .'</font></td></tr>';
				}

				$io->writeRaw(PHP_TAB. '"'.$className.'"' . ' [label=<' .$label. '</table>>];');

				// inheritance
				if($parentClass = $classReflection->getParentClass()) {
						$io->writeRaw(PHP_TAB. '"' .$className. '" -> "' .$parentClass->getName(). '" [arrowhead=onormal color=grey64];');
				}

				// associations
				foreach($schema->getReferences() as $propertyName => $reference) {
					$io->writeRaw(PHP_TAB. '"' .$className.'":'.$propertyName. ' -> "'.$reference['type'].'"', false);

					// if(in_array($reference['cardinality'], [Cardinality::OneToMany, Cardinality::ManyToMany]))
						$io->writeRaw(' [arrowhead=odiamond]', false);

					$io->writeRaw(';');
				}
			}

			// fputs($stream, PHP_TAB. $ligne['filleul_id'] . ' [label="' .$ligne['filleul_nom']. '"];'. PHP_EOL);

			$io->writeRaw('');
			$count++;
		}
		$io->writeRaw('}');
		$io->write(PHP_EOL.'//Created '.$count.' nodes', true, $io::NORMAL);
	}
}
