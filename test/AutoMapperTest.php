<?php

namespace AutoMapperPlus;

use AutoMapperPlus\Configuration\AutoMapperConfig;
use AutoMapperPlus\Configuration\AutoMapperConfigInterface;
use AutoMapperPlus\MappingOperation\Operation;
use AutoMapperPlus\NameConverter\NamingConvention\CamelCaseNamingConvention;
use AutoMapperPlus\NameConverter\NamingConvention\SnakeCaseNamingConvention;
use PHPUnit\Framework\TestCase;
use Test\Models\NamingConventions\CamelCaseSource;
use Test\Models\NamingConventions\SnakeCaseSource;
use Test\Models\Nested\ChildClass;
use Test\Models\Nested\ChildClassDto;
use Test\Models\Nested\ParentClass;
use Test\Models\Nested\ParentClassDto;
use Test\Models\Post\CreatePostViewModel;
use Test\Models\Post\Post;
use Test\Models\SimpleProperties\Destination;
use Test\Models\SimpleProperties\Source;
use Test\Models\SpecialConstructor\Source as ConstructorSource;
use Test\Models\SpecialConstructor\Destination as ConstructorDestination;
use Test\Models\Visibility\Visibility;

/**
 * Class AutoMapperTest
 *
 * @package AutoMapperPlus
 */
class AutoMapperTest extends TestCase
{
    protected $source;
    protected $destination;

    /**
     * @var AutoMapperConfig
     */
    protected $config;

    protected function setUp()
    {
        $this->config = new AutoMapperConfig();
    }

    public function testItCanBeInstantiatedStatically()
    {
        $mapper = AutoMapper::initialize(function (AutoMapperConfigInterface $config) {
            $config->registerMapping(Source::class, Destination::class);
        });

        $destination = $mapper->map(new Source(), Destination::class);
        $this->assertInstanceOf(Destination::class, $destination);
    }

    public function testItMapsAPublicProperty()
    {
        $this->config->registerMapping(Source::class, Destination::class);
        $mapper = new AutoMapper($this->config);
        $source = new Source();
        $source->name = 'Hello';
        /** @var Destination $destination */
        $destination = $mapper->map($source, Destination::class);

        $this->assertInstanceOf(Destination::class, $destination);
        $this->assertEquals($source->name, $destination->name);
    }

    public function testItCanMapToAnExistingObject()
    {
        $this->config->registerMapping(Source::class, Destination::class);
        $mapper = new AutoMapper($this->config);
        $source = new Source();
        $source->name = 'Hello';
        $destination = new Destination();
        $destination = $mapper->mapToObject($source, $destination);

        $this->assertEquals($source->name, $destination->name);
    }

    public function testItCanMapWithACallback()
    {
        $this->config->registerMapping(Source::class, Destination::class)
            ->forMember('name', function () {
                return 'NewName';
            });
        $mapper = new AutoMapper($this->config);
        $source = new Source();
        $destination = $mapper->map($source, Destination::class);

        $this->assertEquals('NewName', $destination->name);
    }

    public function testTheConfigurationCanBeRetrieved()
    {
        $config = new AutoMapperConfig();
        $mapper = new AutoMapper($config);

        $this->assertEquals($config, $mapper->getConfiguration());
    }

    public function testItCanMapMultiple()
    {
        $this->config->registerMapping(Source::class, Destination::class);
        $mapper = new AutoMapper($this->config);

        $sourceCollection = [
            new Source('One'),
            new Source('Two'),
            new Source('Three')
        ];

        $destinationCollection = [
            new Destination('One'),
            new Destination('Two'),
            new Destination('Three')
        ];

        $this->assertEquals(
            $destinationCollection,
            $mapper->mapMultiple($sourceCollection, Destination::class)
        );
    }

    public function testItCanMapToAnObjectWithLessProperties()
    {
        $this->config->registerMapping(
            CreatePostViewModel::class,
            Post::class
        );
        $mapper = new AutoMapper($this->config);

        $source = new CreatePostViewModel();
        $source->title = 'Im a title';
        $source->body = 'Im a body';

        $expected = new Post(null, 'Im a title', 'Im a body');
        $this->assertEquals($expected, $mapper->map($source, Post::class));
    }

    public function testItCanSkipTheConstructor()
    {
        $this->config->registerMapping(
            ConstructorSource::class,
            ConstructorDestination::class
        );
        $mapper = new AutoMapper($this->config);

        $source = new ConstructorSource();
        /** @var ConstructorDestination $result */
        $result = $mapper->map($source, ConstructorDestination::class);

        $this->assertFalse($result->constructorRan);
    }

    public function testItCanMapNestedProperties()
    {
        $this->config->registerMapping(ChildClass::class, ChildClassDto::class);
        $this->config->registerMapping(ParentClass::class, ParentClassDto::class)
            ->forMember('child', Operation::mapTo(ChildClassDto::class));
        $mapper = new AutoMapper($this->config);

        $parent = new ParentClass();
        $child = new ChildClass();
        $child->name = 'ChildName';
        $parent->child = $child;

        $result = $mapper->map($parent, ParentClassDto::class);

        $this->assertInstanceOf(ChildClassDto::class, $result->child);
        $this->assertEquals('ChildName', $result->child->name);
    }

    public function testItCanResolveNamingConventions()
    {
        $this->config->registerMapping(CamelCaseSource::class, SnakeCaseSource::class)
            ->withNamingConventions(
                new CamelCaseNamingConvention(),
                new SnakeCaseNamingConvention()
            )
            // Test if fromProperty overrides the naming convention.
            ->forMember('some_other_property', Operation::fromProperty('anotherProperty'))
            ->reverseMap();
        $mapper = new AutoMapper($this->config);

        $camel = new CamelCaseSource();
        $camel->propertyName = 'camel';
        $camel->anotherProperty = 'someOther';

        /** @var SnakeCaseSource $snake */
        $snake = $mapper->map($camel, SnakeCaseSource::class);

        $this->assertEquals('camel', $snake->property_name);
        $this->assertEquals('someOther', $snake->some_other_property);

        // Let's try the reverse as well.
        $snake->property_name = 'snake';
        $snake->some_other_property = 'snakeprop';
        $camel = $mapper->map($snake, CamelCaseSource::class);

        $this->assertEquals('snake', $camel->propertyName);
        // Let's see if reverseMap() takes fromProperty into account.
        $this->assertEquals('snakeprop', $camel->anotherProperty);
    }

    /**
     * @group stdClass
     */
    public function testItCanMapFromAStdClass()
    {
        $this->config->registerMapping(\stdClass::class, Destination::class);
        $mapper = new AutoMapper($this->config);

        $source = new \stdClass();
        $source->name = 'sourceName';
        /** @var Destination $destination */
        $destination = $mapper->map($source, Destination::class);

        $this->assertEquals('sourceName', $destination->name);
    }

    /**
     * Will test if we can map by providing the source property name.
     */
    public function testItMapsFromAProperty()
    {
        $this->config->registerMapping(Visibility::class, Destination::class)
            ->forMember('name', Operation::fromProperty('privateProperty'));
        $mapper = new AutoMapper($this->config);

        $source = new Visibility();
        $result = $mapper->map($source, Destination::class);

        $this->assertTrue($result->name);
    }

    public function testItCanSetARestrictedProperties()
    {
        $this->config->registerMapping(\stdClass::class, Visibility::class);
        $mapper = new AutoMapper($this->config);

        $source = new \stdClass();
        $source->protectedProperty = 'protected';
        $source->privateProperty = 'private';

        /** @var Visibility $result */
        $result = $mapper->map($source, Visibility::class);

        $this->assertEquals('protected', $result->getProtectedProperty());
        $this->assertEquals('private', $result->getPrivateProperty());
    }

    /**
     * @group test
     */
    public function testItCanReverseMapWithReversibles()
    {
        $this->config->registerMapping(Source::class, Visibility::class)
            ->forMember('privateProperty', Operation::fromProperty('name'))
            ->reverseMap();
        $mapper = new AutoMapper($this->config);

        $source = new Source();
        $source->name = 'Hello';
        /** @var Visibility $result */
        $result = $mapper->map($source, Visibility::class);

        // Assert the initial mapping succeeds.
        $this->assertEquals('Hello', $result->getPrivateProperty());

        // The privateProperty of $source is now true.
        $source = new Visibility();
        $result = $mapper->map($source, Source::class);

        $this->assertTrue($result->name);
    }
}
