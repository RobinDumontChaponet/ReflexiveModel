# ReflexiveModel

$$ {{∀ x ∈ X : x R x}} $$

---

An attribute-driven model layer that sits on top of `reflexive/query` (and `reflexive/core`).

It combines:
- schema inference from PHP attributes
- active-record style CRUD helpers
- model hydration and identity caching
- lazy or cached result collections
- SQL schema export from the inferred metadata

## Requirements
- PHP `^8.4`
- `ext-reflection`
- `reflexive/core`
- `reflexive/query`
- `psr/simple-cache`

## Installation
```bash
composer require reflexive/model
```

## Defining a model
Models extend `Reflexive\Model\Model` and are described with PHP attributes.

```php
use Reflexive\Model\Model;
use Reflexive\Model\ModelId;
use Reflexive\Model\Table;
use Reflexive\Model\Property;
use Reflexive\Model\Column;

#[Table('users')]
final class User extends Model
{
	use ModelId;

	#[Property]
	#[Column('email_address', type: 'VARCHAR(255)')]
	protected string $email;

	#[Property]
	#[Column] // using defaults from type and default value
	protected bool $active = true;
}
```

Key attributes and helpers:
- `#[Table(...)]` defines table-level metadata, inheritance rules, and super/sub-type flags.
- `#[Property(...)]` marks protected properties as managed, optionally generating getters/setters.
- `#[Column(...)]` maps a property to a database column and can declare id, type, nullability, defaults, and auto-increment.
- `#[Reference(...)]` describes object relationships.
- `ModelId` adds a conventional auto-increment `id` property.
- `ModelEnum` adapts PHP enums to the same CRUD API shape.

## Generated accessors and dirty tracking
Managed properties are accessed through `__get()`, `__set()`, and generated methods created from `#[Property]`.

By default:
- `#[Property]` creates a getter and setter
- setting a different value marks the property as modified
- read-only properties are enforced by the model layer

The write path is then used by `create()` and `update()` to decide which columns should be sent to SQL.

## CRUD API

Every model gets static builders:
- `::create(Model $model)`
- `::read(...)`
- `::search(...)`
- `::update(Model $model)`
- `::delete(Model $model)`
- `::count()`

Example:
```php
use Reflexive\Core\Condition;

$user = new User();
$user->setEmail('person@example.com');

User::create($user)->execute($pdo);

$loaded = User::read()->where(Condition::EQUAL('id', $user->id))->execute($pdo);

$matches = User::search()->where(Condition::EQUAL('active', true))
	->limit(50)
	->execute($pdo);
```

The read/search/count builders translate model property names into real table and column names using `Schema`.

## Relationships
`#[Reference]` metadata lets the model layer turn object-level conditions into SQL joins or foreign-key predicates.

Supported cardinalities:
- `OneToOne`
- `OneToMany`
- `ManyToOne`
- `ManyToMany`

At runtime, references are hydrated into:
- a single related model (or enum)
- a lazily-backed `ModelCollection`

For many-to-many updates, `Push` builds extra insert/update/delete statements for the join table based on collection changes.

## Hydration and collections
`Hydrator` is responsible for turning rows into model instances and caching them by model id.

Notable behavior:
- repeated reads of the same id can reuse cached objects
- sub-types can be resolved through the internal `reflexive_subType` field
- references may be loaded lazily using PHP lazy ghosts/proxies

`ModelCollection` wraps either a `PDOStatement` or a `Reflexive\Query\Composed` query and implements:
- `Iterator`
- `ArrayAccess`
- `Countable`
- `JsonSerializable`

It can auto-execute, cache fetched objects, and track added/modified/removed keys for relationship syncing.

## Schema inspection and export

`Schema::initFromAttributes()` reflects a model class and builds the runtime mapping used by the whole package.

Useful entry points:
- `Schema::getSchema(FQCN)` to fetch the inferred schema
- `Schema::dumpSQL()` to generate SQL for all discovered models
- `Schema::exportSQL()` to print that SQL directly

The generated SQL is aimed at MySQL/MariaDB-style schemas and includes foreign-key creation statements.

## Rough edges
- Some inheritance and reference flows are implemented, but they are opinionated and tightly coupled to the inferred schema layout.
