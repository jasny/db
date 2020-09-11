Jasny DB
========

Jasny DB adds OOP design patterns to PHP's database extensions.

* [Service Locator](#service-locator)
* [Connection](#connections)
* [Entity](#entity)
* [Active record](#active-record)
* [Data mapper](#data-mapper)
* [Dataset](#dataset)
* [Entity set](#entity-set)
* [Metadata](#meta-data)
* Entity traits
  * [Type casting](#type-casting)
  * [Identifiable](#identifiable)
  * [Self aware](#self-aware)
  * [Change aware](#change-aware)
  * [Validation](#validation)
  * [Dynamic](#dynamic)
  * [Enrichable](#enrichable)
  * [Redactable](#redactable)
  * [Lazy loading](#lazy-loading)
  * [Soft deletion](#soft-deletion)
* [Maintainable code](#maintainable-code)
* [Code generation](#code-generation)

Jasny DB is a [data access layer](https://en.wikipedia.org/wiki/Data_access_layer) (*not* a DB abstraction layer) for
PHP. It does allow you properly structure your model, while still using the methods and functionality of PHP's native
database extensions.

### Installation
This library is not intended to be installed directly. The Jasny DB library contains design pattern definitions and
implementations. It serves as an abstract base for concrete libraries implemented for specific PHP extensions.

### Implementations

* [Jasny\DB\MySQL](http://github.com/jasny/db-mysql) extends [mysqli](http://php.net/mysqli)
* [Jasny\DB\Mongo](http://github.com/jasny/db-mongo) extends [mongo](http://php.net/mongo)
* [Jasny\DB\REST](http://github.com/jasny/db-rest) for datasources implementing
  [REST](http://en.wikipedia.org/wiki/Representational_state_transfer)


Service Locator
---

The static `Jasny\DB` is a [service locator](https://en.wikipedia.org/wiki/Service_locator_pattern). It grant access to
[factories](http://en.wikipedia.org/wiki/Factory_(object-oriented_programming)),
[registries](http://martinfowler.com/eaaCatalog/registry.html) and
[builders](https://en.wikipedia.org/wiki/Builder_pattern).

* `connectionFactory()` - Factory for DB connections
* `connectionRegistry()` - Registry for DB connections
* `entitySetFactory()` - Factory for entity sets


Connections
---

Connection objects are use used to interact with a database. Other object must use a connection object do actions like
getting getting, saving and deleting data from the DB.

### Registry
To register a connection use `Jasny\DB::connectionFactory()->register($name, $connection)`. To get a registered
connection, use `Jasny\DB::connectionFactory()->get($name)` or the shortcut `Jasny\DB::conn($name)`. Connections can be
removed from the registry using `Jasny\DB::connectionFactory()->unregister($name|$connection)`.

```php
$db = new Jasny\DB\MySQL\Connection();
Jasny\DB::connectionFactory()->register('foo');

Jasny\DB::conn('foo')->query();

Jasny\DB::connectionFactory()->unregister('foo');
```

The same connection may be registered multiple times under different names.

### Named connections
Connections implementing the `Namable` interface can register themselves to `Jasny\DB` using the `useAs($name)` method.
With the `getConnectionName()` you can get the name of a connection.

```php
$db = new Jasny\DB\MySQL\Connection();
$db->useAs('foo');

Jasny\DB::conn('foo')->query();
```

If you only have one DB connection name it 'default', since `$name` defaults to 'default'.

```php
$db = new Jasny\DB\MySQL\Connection();
$db->useAs('default');

Jasny\DB::conn()->query();
```

### Configuration

Instead of manually creating and configuring, you may configure connections using `Jasny\DB::configure()`. This static
property may hold the configuration for each connection. When using the `conn()` method, Jasny DB will automatically
create a new connection based on the configuration settings.

```php
Jasny\DB::configure([
    'default' => [
        'driver'    => 'mysql',
        'database'  => 'database',
        'host'      => 'localhost',
        'username'  => 'root',
        'password'  => 'secure',
        'charset'   => 'utf8'
    ],
    'external' => [
        'driver'    => 'rest',
        'host'      => 'api.example.com',
        'username'  => 'user',
        'password'  => 'secure'
    ]
]);

Jasny\DB::conn()->query();
Jasny\DB::conn('external')->get("/something");
```

`Jasny\DB::$drivers` holds a list of `Connection` classes with their driver name. The `createConnection($settings)`
method uses the `driver` setting to select the connection class. The other settings are passed to the connection's
constructor.


Entity
---

An entity is a "thing" you want to represent in a database or other data storages. It can be a new article on your
blog, a user in your message board or a permission in your rights management system.

The properties of an entity object is a representation of the data. Entities usually also carry business logic.

### Set values
The `setValues()` methods is a a helper function for setting all the properties from an array and works like a
[fluent interface](http://en.wikipedia.org/wiki/Fluent_interface).

```php
$foo = new Foo();
$foo->setValues(['red' => 10, 'green' => 20, 'blue' => 30])->doSomething();
```

### Instantiation
Using the `new` keyword is reserved for creating a new entity.

When the data of an entity is fetched, the `__set_state()` method is used to create the entity. This method sets the
properties of the entity object before calling the constructor.


Active Record
---

Enities may be implement the [Active Record pattern](http://en.wikipedia.org/wiki/Active_record_pattern). Active records
combine data and database access in a single object.

### Fetch
An entity can be loaded from the database using the `fetch($id)`.

```php
$foo = Foo::fetch(10); // id = 10
$foo = Foo::fetch(['reference' => 'myfoo']);
```

### Save
Objects that implement the ActiveRecord interface have a `save()` method for storing the entity in the database.

```php
$foo->save();
$foo->setValues($data)->save();
```

### Delete
Entities may be removed from the database using the `delete()` method.

```php
$foo->delete();
```

Optionally [soft deletion](#soft-deletion) can be implemented, so deleted entities can be restored.

```php
$foo->undelete();
```


Data Mapper
---

You may choose to separate database logic from business logic by using a
[Data Mapper](http://en.wikipedia.org/wiki/Data_mapper_pattern). The Data Mapper is responsible for loading entities
from and storing them to their database.

You should either use Data Mappers or Active Record, not both. When using Data Mappers, the entities should not be
aware of the database and contain no database code (eg SQL queries).

### Fetch
An entity can be loaded from the database using the `fetch($id)`.

```php
$foo = FooMapper::fetch(10); // id = 10
$foo = FooMapper::fetch(['reference' => 'myfoo']);
```

### Save
To store entities a Data Mapper implements the `save($entity)` method.

```php
FooMapper::save($foo);
FooMapper::save($foo->setValues($data));
```

### Delete
Entities may be removed from the database using the `delete($entity)` method.

```php
FooMapper::delete($foo);
```

Optionally [soft deletion](#soft-deletion) can be implemented, so deleted entities can be restored.

```php
FooMapper::undelete($foo);
```


Dataset
---

An entity tends to be a part of a set of data, like a table or collection. If it's possible to load multiple 
entities from that set, the Active Record or Data Mapper implement the `Dataset` interface.

The `fetch()` method returns a single entity. The `fetchAll()` method returns multiple enities. `fetchList()`
loads a list with the id and description as key/value pairs. The `count()` method counts the number of entities
in the set.

The fetch methods are intended to support only simple cases. For specific cases you SHOULD add a specific method 
and not overload the basic fetch methods.

### Filter

Fetch methods accept a `$filter` argument. The filter is an associated array with field name and corresponding
value. _Note that the `fetch()` methods takes either a unique ID or filter._

A filter SHOULD always return the same or less results that calling the method without a filter. 

```php
$foo   = Foo::fetch(['reference' => 'zoo']);
$foos  = Foo::fetchAll(['bar' => 10]);
$list  = Foo::fetchList(['bar' => 10]);
$count = Foo::count(['bar' => 10]);
```

Optinally filter keys may include an directives. The following directives are supported:

Key            | Value  | Description
-------------- | ------ | ---------------------------------------------------
"field"        | scalar | Field is the value
"field (not)"  | scalar | Field is not the value
"field (min)"  | scalar | Field is equal to or greater than the value
"field (max)"  | scalar | Field is equal to or less than the value
"field (any)"  | array  | Field is one of the values in the array
"field (none)" | array  | Field is none of the values in the array

If the field is an array, you may use the following directives

Key            | Value  | Description
-------------- | ------ | ---------------------------------------------------
"field"        | scalar | The value is part of the field
"field (not)"  | scalar | The value is not part of the field
"field (any)"  | array  | Any of the values are part of the field
"field (all)"  | array  | All of the values are part of the field
"field (none)" | array  | None of the values are part of the field

Filters SHOULD be alligned business logic, wich may not directly align to checking a value of a field. A recordset
SHOULD implement a method `filterToQuery` which converts the filter to a DB dependent query statement. You MAY
overload this method to support custom filter keys.

It's save to use query parameters (`$_GET`) and input data (`$_POST`) directly.

    // -> GET /foos?color=red&date(min)=2014-09-01&tags(not)=abandoned&created.user=12345
    
    $result = Foo::fetchAll($_GET);
    

Entity set
---

Whenever an array of entities would be returned, Jasny DB will return an `EntitySet` object instead. An entity set
can be used as array as well as object.

_further documentation required_


Metadata
---

An entity represents an element in the model. The [metadata](http://en.wikipedia.org/wiki/Metadata) holds 
information about the structure of the entity. Metadata should be considered static as it describes all the
entities of a certain type.

Metadata for a class might contain the table name where data should be stored. Metadata for a property might 
contain the data type, whether or not it is required and the property description.

Jasny DB support defining metadata through annotations by using [Jasny\Meta](http://www.github.com/jasny/meta).

```php
/**
 * User entity
 *
 * @entitySet UserSet
 */
class User
{
   /**
    * @var string
    * @required
    */
   public $name;
}
```

### Class annotations

    * @entitySet - Default entity set for this class of Entities

_Additional class annotations may be used by a specific Jasny DB driver._

### Property annotations

    * @var - (type casting) - Value type or class name
    * @type - (validation) - Value (sub)type
    * @required (validation) - Value should not be blank at validation.
    * @min (validation) - Minimal value
    * @max (validation) - Maximal value
    * @minLength (validation) - Minimal length of a string
    * @maxLength (validation) - Maximal length of a string
    * @options _values_ (validation) - Value should be one the the given options.
    * @pattern _regex_ (validation) - Value should match the regex pattern.
    * @immutable (validation) - Property can't be changed after it is created.
    * @unique (validation) - Entity should be unique accross it's dataset.
    * @unique _field_ (validation) - Entity should be unique for a group. The group is identified by _field_.
    * @censor (redact) - Skip property when outputting the entity.

_Additional property annotations may be used by a specific Jasny DB driver._

### Caveat
Metadata can be really powerfull in generalizing and abstracting code. However you can quickly fall into the trap of
coding through metadata. This tends to lead to code that's hard to read and maintain.

Only use the metadata to abstract widely use functionality and use overloading to implement special cases.


Type casting
---

Entities support type casting. This is done based on the metadata. Type casting is implemented by the
[Jasny\Meta](http://www.github.com/jasny/meta) library.

### Internal types
For [php internal types](http://php.net/types) normal [type juggling](http://php.net/type-juggling) is used. Values
aren't blindly casted. For instance casting `"foo"` to an integer would trigger a warning and skip the casting.

### Objects
Casting a value to an `Identifiable` entity that supports [Lazy Loading](#lazy-loading), creates a ghost object.
Entities that implement `ActiveRecord` or have a `DataMapper`, but do not support `LazyLoading` are fetched from the
database.

Casting a value to a non-identifiable entity will call the `Entity::fromData()` method.

Casting to any other type of object will create a new object normally. For instance casting "bar" to `Foo` would result
in `new Foo("bar")`.


Validation
---

Entities implementing the Validatable interface, can do some basic validation prior to saving them. This includes
checking that all required properties have values, checking the variable type matches and checking if values are
uniquely present in the database.

The `validate()` method will return a [`Jasny\ValidationResult`](https://github.com/jasny/validation-result#readme).

```php
$validation = $entity->validate();

if ($validation->failed()) {
    http_response_code(400); // Bad Request
    json_encode($validation->getErrors());
    exit();
}
```

Lazy loading
---

Jasny DB supports [lazy loading](http://en.wikipedia.org/wiki/Lazy_loading) of entities by allowing them to be created
as ghost. A ghost only hold a limited set of the entity's data, usually only the identifier. When other properties are
accessed it will load the rest of the data.

When a value is [casted](#type-casting) to an entity that supports lazy loading, a ghost of that entity is created.


Soft deletion
---

Entities that support soft deletion are deleted in such a way that they can restored.

Deleted entities may restored using `undelete()` or they can be permanently removed using `purge()`.

The `isDeleted()` method check whether this document has been deleted.

Fetch methods do not return deleted entities. Instead use `fetchDeleted($filter)` to load a deleted entity. Use
`fetchAllDeleted($filter)` to fetch all deleted entities from the database.


Maintainable code
---

To create maintainable code you SHOULD at least uphold the following rules:

* Don't access the database outside your model classes.
* Use traits or multiple classes to separate database logic (eg queries) from business.
* Keep the number of `if`s limited. Implement special cases by overloading.

### SOLID
[SOLID](http://en.wikipedia.org/wiki/SOLID_(object-oriented_design)) embodies 5 principles principles that, when 
used together, will make a code base more maintainable over time. While not forcing you to, Jasny DB supports 
building a SOLID code base.

Methods are kept small and each method is expected to be 
[overloaded](http://en.wikipedia.org/wiki/Function_overloading) by extending the class.

Functionality of Jasny DB is defined in interfaces and defined in traits around a single piece of functionality or
design pattern. The use an a specific interface will trigger behaviour. The trait may or may not be used to 
implement the interface without consequence.

### Active Record and SRP
Using the Active Records pattern is considered breaking the
[Single responsibility principle](http://en.wikipedia.org/wiki/Single_responsibility_principle). Active records tend to
combine database logic with business logic a single class.

However this pattern produces code that is more readable and easier to understand. For that reason it remains very
popular.

In the end the choice is up to you. Using the Active Record pattern is always optional with Jasny DB. Alternatively you
may choose to use Data Mapper for database interaction.

```php
// Active Record
$user = User::fetch(10);
$user->setValues($data);
$user->save();

// Data Mapper
$user = DB::mapper('User')->fetch(10);
$user->setValues($data);
DB::mapper('User')->save($user);
```


Code generation
---

_Present in version 1, but not yet available for version 2_
