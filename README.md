OroEntityBundle
===============

Entity and entity field selectors, extended functionality of Doctrine entity manager.

**Entity Manager**

In order to extend some native Doctrine Entity Manager functionality a new class `OroEntityManager` was implemented.
In case any other modification are required, your class should extend `OroEntityManager` instead of Doctrine Entity Manager.

**Filter Collection**

Standard Doctrine filter collection implementation allows to add/enable sql filter by passing class name only.
It makes impossible to inject custom services into filters. To provide this functionality,
a new `FilterCollection` class was implemented that allows to add filter objects directly.

Necessary filters can be automatically added to the filters collection by adding `oro_entity.orm.sql_filter` tag:

```yml
oro_security.orm.ownership_sql_filter:
    class: %oro_security.orm.ownership_sql_filter.class%
    arguments:
       - @doctrine.orm.entity_manager
    tags:
       - { name: oro_entity.orm.sql_filter, filter_name: ownershipFilter, enabled: true }
```

where

 - **filter_name** - required filter name,
 - **enbaled** - flag, if the filter must be enabled, by default filters are disabled

## Doctrine field types ##

Some entities have fields which data is money or percents.

For this data was created new field types - money and percent.

**money** field type allow to store money data. It's an alias to decimal(19,4) type.

You can use this field type like:

```php
    /**
     * @var decimal
     *
     * @ORM\Column(name="tax_amount", type="money")
     */
    protected $taxAmount;
```

**percent** field type allow to store percent data. It's an alias to float type.

You can use this field type like:

```php
    /**
     * @var float
     *
     * @ORM\Column(name="percent_field", type="percent")
     */
    protected $percentField;
```

This two data types are available in extend fields. You can create new fields with this types. Additionally in view pages, in grids and in edit pages this fields will be automatically formatted with currency or percent formatters.

In grid, for percent data type will be automatically generated percent filter.


## Entity name resolver and providers ##

**Entity name resolver**

The [Entity Name Resolver](./Provider/EntityNameResolver.php) service has been introduced to make the configuring of entity name formatting more flexible.

It provides two functions for getting the entity name:

- string *public* *getName*(object *entity*[, string *format*, string *locale*])

This method can be used to get a text representation of an entity formatted according to the format notation passed (e.g. "full", "short", etc.). If the format is not specified, the default one will be used.

To format the text representation using a specific locale, the *locale* parameter may be passed.

- string *public* *getNameDQL*(string *className*, string *alias*[, string *format*, string *locale*])

This method is useful for getting a DQL expression that can be used to get a text representation of the given type of entities formatted according to the format notation passed (e.g. "full", "short", etc.). If the format is not specified, the default one will be used.

To get a text representation using a specific locale, the *locale* parameter may be passed.

Example of usage:

```php
$entityNameResolver = $container->get('oro_entity.entity_name_resolver');
$user->setFirstName('John');
$user->setLastName('Doe');
echo $entityNameResolver->getName($user); // outputs: John Doe
echo $entityNameResolver->getNameDQL('Oro\Bundle\UserBundle\Entity\User', 'u'); // outputs: CONCAT(u.firstName, CONCAT(u.lastName, ' ')
```

The available entity formats can be configured in the `entity_name_formats` section of `Resources/config/oro/entity.yml` file:

```yaml
oro_entity:
    entity_name_formats:
        full:
            fallback: short
        short: ~
```

Note that it is possible to specify the fallback format for the entity that will be used when the given format is not supported.

**Entity name providers**

The Entity Name Resolver does not know how to get the entity name by itself but instead it expects to have a collection of Entity Name Providers that will do the job.
The first provider that is able to return a reliable result wins. The rest of providers will not be asked.

To create an Entity Name Provider you should implement the [EntityNameProviderInterface](./Provider/EntityNameProviderInterface.php):

```php
use Oro\Bundle\EntityBundle\Provider\EntityNameProviderInterface;

class FullNameProvider implements EntityNameProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function getName($format, $locale, $entity)
    {
        if ($format === self::FULL && $this->isFullFormatSupported(get_class($entity))) {
            // return entity format
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getNameDQL($format, $locale, $className, $alias)
    {
        if ($format === self::FULL && $this->isFullFormatSupported($className)) {
            // return DQL to get entity format
        }

        return false;
    }

    /**
     * @param string $className
     *
     * @return bool
     */
    protected function isFullFormatSupported($className)
    {
        // check if $className supports full name formatting, e.g. implements some required interfaces
    }
}
```

Note that if the provider cannot return a reliable result, FALSE should be returned to keep looking in the other providers in chain.

Entity name providers are registered in the DI container by `oro_entity.name_provider` tag:

```yml
    oro_entity.entity_name_provider.default:
        class: %oro_entity.entity_name_provider.default.class%
        public: false
        arguments:
            - @doctrine
        tags:
            - { name: oro_entity.name_provider, priority: 100 }
```

The priority can be specified to move the provider up or down the providers chain.

## Aliases ##

[Entity aliases](./Model/EntityAlias.php) were introduced to provide a simple and elegant way of referring to entities.

The usages for entity aliases can be numerous but they come especially handy when specifying entities in the API, removing the need of using bulky FQCNs.

You can use entity aliases with the help of [EntityAliasResolver](./ORM/EntityAliasResolver.php) which provides necessary functions for getting aliases for given class names and visa versa.

**Defining entity aliases**

In most cases aliases are generated automatically.
The generation rules are the following:
- For all Oro entities (basically, all classes starting with "Oro") the lowercase short class name is used, e.g. `Oro\Bundle\CalendarBundle\Entity\CalendarEvent` = `calendarevent`.
- For non-Oro (3-rd party) entities, the bundle name is prepended to the short class name if it does not already start with the bundle name, e.g. `Acme\Bundle\DemoBundle\Entity\MyEntity` = `demomyentity`, `Acme\Bundle\DemoBundle\Entity\DemoEntity` = `demoentity`.
- For "enums" the enum code is used as the entity alias, but the underscore (_) character is removed.
- For custom entities the lower case short class name is used prepended with "extend" key word.
- Hidden entities are ignored.

It is possible, however, to define custom rules for entity aliases in the `Resources/config/oro/entity.yml` configuration file.
This can help to avoid naming conflicts or make the entity aliases more readable or more user-friendly.

You can explicitly define aliases for a specific entity in the `entity_aliases` section of `entity.yml`:

```yml
oro_entity:
    entity_aliases:
        JMS\JobQueueBundle\Entity\Job:
            alias:        job
            plural_alias: jobs
```

To exclude certain entities from alias generation process, for example some internal entities, you can add `entity_alias_exclusions` section:

```yml
oro_entity:
    entity_alias_exclusions:
        - Oro\Bundle\ConfigBundle\Entity\Config
        - Oro\Bundle\ConfigBundle\Entity\ConfigValue
```

**Entity alias provider**

There can be situations when you need more complicated rules for creating entity aliases that can not be simply configured via `entity.yml` file.
In this case you'll need to create an entity alias provider.

For this you need to register a new provider service in the DI container using the `oro_entity.alias_provider` tag:

```yml
    oro_email.entity_alias_provider:
        class: Oro\Bundle\EmailBundle\Provider\EmailEntityAliasProvider
        public: false
        arguments:
            - @oro_email.email.address.manager
        tags:
            - { name: oro_entity.alias_provider }
```

And implement the [EntityAliasProviderInterface](./Provider/EntityAliasProviderInterface.php) interface in your provider class:

```php
use Oro\Bundle\EmailBundle\Entity\Manager\EmailAddressManager;
use Oro\Bundle\EntityBundle\Model\EntityAlias;
use Oro\Bundle\EntityBundle\Provider\EntityAliasProviderInterface;

class EmailEntityAliasProvider implements EntityAliasProviderInterface
{
    /** @var string */
    protected $emailAddressProxyClass;

    /**
     * @param EmailAddressManager $emailAddressManager
     */
    public function __construct(EmailAddressManager $emailAddressManager)
    {
        $this->emailAddressProxyClass = $emailAddressManager->getEmailAddressProxyClass();
    }

    /**
     * {@inheritdoc}
     */
    public function getEntityAlias($entityClass)
    {
        if ($entityClass === $this->emailAddressProxyClass) {
            return new EntityAlias('emailaddress', 'emailaddresses');
        }

        return null;
    }
}
```

**Viewing existing entity aliases**

You can use `php app/console oro:entity-alias:debug` CLI command to see all the aliases.
The output will look something like this:

```
Class                                                    Alias                  Plural Alias
Oro\Bundle\ActivityListBundle\Entity\ActivityList        activitylist           activitylists
Oro\Bundle\AddressBundle\Entity\Address                  address                addresses
Oro\Bundle\AddressBundle\Entity\AddressType              addresstype            addresstypes
Oro\Bundle\AddressBundle\Entity\Country                  country                countries
Oro\Bundle\AddressBundle\Entity\Region                   region                 regions
Oro\Bundle\AttachmentBundle\Entity\Attachment            attachment             attachments
Oro\Bundle\AttachmentBundle\Entity\File                  file                   files
Oro\Bundle\CalendarBundle\Entity\Calendar                calendar               calendars
Oro\Bundle\CalendarBundle\Entity\CalendarEvent           calendarevent          calendarevents
```

Alternatively, you may use `GET /api/rest/{version}/entities/aliases.{_format}` API to get the list of all available aliases.

**Suggestions for aliases naming**

To solve the conflict situations when the auto-generated entity alias is already in use. Or when there might be another, more general, entity introduced later, for which your generated alias will suit better, we advise to follow naming rules described below:
- For Oro entities, in most cases, it is sufficient to simply prepend the short class name with the bundle name, e.g. `OroCRM\Bundle\MagentoBundle\Entity\Customer` = `magentocustomer`.
- More general entities should have a more general alias, e.g. `Oro\Bundle\EmailBundle\Entity\Email` = `email`, `OroCRM\Bundle\ContactBundle\Entity\ContactEmail` = `contactemail`. Basically if the bundle name and the short class name are the same, it should be used as the alias. For other entities with the same short class, the bundle name should be used as a prefix.
- For non-Oro entities, if not sure that the auto-generated alias is unique enough and it is likely (usually it is) that such entity will be added in the Oro core, you can prefix the alias with the bundle vendor (and category if needed), e.g. `Acme\Bundle\BlogBundle\Entity\MyEntity` = `acmeblogmyentity`, `Acme\Bundle\Social\BlogBundle\Entity\MyEntity` = `acmesocialblogmyentity`.
