<?php

declare(strict_types=1);

namespace Reflexive\Model\Tests;

use PHPUnit\Framework\TestCase;
use Reflexive\Model\Column;
use Reflexive\Model\Model;
use Reflexive\Model\ModelId;
use Reflexive\Model\Property;
use Reflexive\Model\Table;

#[Table('model_test_articles')]
final class ModelTestArticle extends Model
{
	use ModelId;

	#[Property(maxLength: 80)]
	#[Column('title')]
	protected string $title = 'Draft';

	#[Property]
	#[Column('published')]
	protected bool $published = false;
}

final class ModelTest extends TestCase
{
	public function testGeneratedAccessorsReadAndWriteAttributedProperties(): void
	{
		// Verifies generated accessors read defaults and write managed properties.
		$model = new ModelTestArticle();

		$this->assertSame('Draft', $model->getTitle());
		$this->assertFalse($model->isPublished());

		$model->setTitle('Published title');
		$model->setPublished(true);

		$this->assertSame('Published title', $model->getTitle());
		$this->assertTrue($model->isPublished());
	}

	public function testDirtyTrackingRecordsChangedPropertiesOnce(): void
	{
		// Verifies changed attributed properties are tracked uniquely and can be reset.
		$model = new ModelTestArticle();

		$model->setTitle('First title');
		$model->setTitle('Second title');
		$model->setPublished(true);

		$this->assertTrue($model->hasModifiedProperties());
		$this->assertSame(['title', 'published'], array_values($model->getModifiedPropertiesNames()));

		$model->resetModifiedPropertiesNames();

		$this->assertFalse($model->hasModifiedProperties());
		$this->assertSame([], $model->getModifiedPropertiesNames());
	}

	public function testDebugInfoOmitsInternalBookkeeping(): void
	{
		// Verifies debug output exposes model data without persistence bookkeeping fields.
		$model = new ModelTestArticle();
		$model->setTitle('Inspectable');

		$debugInfo = $model->__debugInfo();

		$this->assertSame('Inspectable', $debugInfo['title']);
		$this->assertArrayNotHasKey('modifiedProperties', $debugInfo);
		$this->assertArrayNotHasKey('ignoreModifiedProperties', $debugInfo);
		$this->assertArrayNotHasKey('reflexive_subType', $debugInfo);
	}
}
