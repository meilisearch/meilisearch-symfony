<p align="center">
  <img src="https://raw.githubusercontent.com/meilisearch/integration-guides/main/assets/logos/meilisearch_symfony.svg" alt="Meilisearch-Symfony" width="200" height="200" />
</p>

<h1 align="center">Meilisearch Symfony Bundle</h1>

<h4 align="center">
  <a href="https://github.com/meilisearch/meilisearch">Meilisearch</a> |
  <a href="https://www.meilisearch.com/pricing?utm_campaign=oss&utm_source=integration&utm_medium=meilisearch-symfony">Meilisearch Cloud</a> |
  <a href="https://docs.meilisearch.com">Documentation</a> |
  <a href="https://discord.meilisearch.com">Discord</a> |
  <a href="https://roadmap.meilisearch.com/tabs/1-under-consideration">Roadmap</a> |
  <a href="https://www.meilisearch.com">Website</a> |
  <a href="https://www.meilisearch.com/docs/faq">FAQ</a>
</h4>

<p align="center">
  <a href="https://codecov.io/gh/meilisearch/meilisearch-symfony/branch/main"><img src="https://codecov.io/gh/meilisearch/meilisearch-symfony/branch/main/graph/badge.svg" alt="Codecov coverage"></a>
  <a href="https://packagist.org/packages/meilisearch/search-bundle"><img src="https://img.shields.io/packagist/v/meilisearch/search-bundle" alt="Latest Stable Version"></a>
  <a href="https://github.com/meilisearch/meilisearch-symfony/actions"><img src="https://github.com/meilisearch/meilisearch-symfony/workflows/Tests/badge.svg" alt="Test"></a>
  <a href="https://github.com/meilisearch/meilisearch-symfony/blob/main/LICENSE"><img src="https://img.shields.io/badge/license-MIT-informational" alt="License"></a>
</p>

<p align="center">⚡ The Meilisearch bundle for Symfony</p>

**Meilisearch Symfony** is a search bundle to integrate **Meilisearch** within your Symfony project.

**Meilisearch** is an open-source search engine. [Discover what Meilisearch is!](https://github.com/meilisearch/meilisearch)

## Table of Contents <!-- omit in TOC -->

- [📖 Documentation](#-documentation)
- [⚡ Supercharge your Meilisearch experience](#-supercharge-your-meilisearch-experience)
- [📝 Requirements](#-requirements)
- [Doctrine Integration](#doctrine-integration)
- [Asynchronous indexing with Symfony Messenger](#asynchronous-indexing-with-symfony-messenger)
- [🤖 Compatibility with Meilisearch](#-compatibility-with-meilisearch)
- [💡 Learn More](#-learn-more)
- [⚙️ Development Workflow and Contributing](#️-development-workflow-and-contributing)

## 📖 Documentation

Check out the [Wiki](https://github.com/meilisearch/meilisearch-symfony/wiki) of this repository to get started! 🚀

Also, see our [Documentation](https://www.meilisearch.com/docs/learn/getting_started/installation) or our [API References](https://www.meilisearch.com/docs/reference/api/overview).

## ⚡ Supercharge your Meilisearch experience

Say goodbye to server deployment and manual updates with [Meilisearch Cloud](https://www.meilisearch.com/pricing?utm_campaign=oss&utm_source=integration&utm_medium=meilisearch-symfony). No credit card required.

## 📝 Requirements

* **Require** PHP 8.1 and later.
* **Compatible** with Symfony 6.4 and later.

For support of older versions, see older versions of this bundle.

## Doctrine Integration

To enable Doctrine integration you have to install `doctrine/orm` and `doctrine/doctrine-bundle`
packages.

By default, every persisted, updated or removed entity is indexed **synchronously** inside the
request: the bundle performs one blocking Meilisearch HTTP call per entity. See the next section to
move that work out of the request cycle.

## Asynchronous indexing with Symfony Messenger

Indexing can be delegated to [Symfony Messenger](https://symfony.com/doc/current/messenger.html), the
same way Symfony Mailer makes email delivery asynchronous: it is **opt-in** and controlled purely by
message routing. Nothing changes unless you enable it, so this is fully backward compatible.

First install the component:

```bash
composer require symfony/messenger
```

Then enable the integration:

```yaml
# config/packages/meilisearch.yaml
meilisearch:
    messenger:
        enabled: true
        # bus: messenger.bus.default # optional: a specific bus service id
```

When enabled, the bundle's synchronous Doctrine subscriber is replaced by an asynchronous one. During
each `flush()` it buffers the changes and, once the flush succeeds, dispatches at most two messages:

- `Meilisearch\Bundle\Messenger\IndexSearchablesMessage` — objects to (re)index. The handler reloads the
  live entities through the data provider of their index before normalizing them, so
  it is not tied to Doctrine ORM and works with custom data providers too.
- `Meilisearch\Bundle\Messenger\RemoveDocumentsMessage` — documents to delete, addressed by their
  already-resolved id. The deleted entity is **never reloaded**, which avoids the "entity not found"
  error described in [#62](https://github.com/meilisearch/meilisearch-symfony/issues/62).

By default both messages are handled synchronously (nothing runs in the background yet). To actually
process them out of the request, route them to an async transport and run a consumer:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async: '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
            'Meilisearch\Bundle\Messenger\IndexSearchablesMessage': async
            'Meilisearch\Bundle\Messenger\RemoveDocumentsMessage': async
```

```bash
php bin/console messenger:consume async
```

Only the routed messages become asynchronous; anything left unrouted keeps running in-process.

The handlers are registered even without Doctrine, so objects served by a custom data provider can be
(re)indexed asynchronously by dispatching the message yourself:

```php
$bus->dispatch(new IndexSearchablesMessage(['actor' => [Actor::class => [1, 2, 3]]]));
```

## 🤖 Compatibility with Meilisearch

This package guarantees compatibility with [version v1.x of Meilisearch](https://github.com/meilisearch/meilisearch/releases/latest), but some features may not be present. Please check the [issues](https://github.com/meilisearch/meilisearch-symfony/issues?q=is%3Aissue+is%3Aopen+label%3A%22good+first+issue%22+label%3Aenhancement) for more info.

## 💡 Learn More

The following sections may interest you:

- **Manipulate documents**: see the [API references](https://www.meilisearch.com/docs/reference/api/documents) or read more about [documents](https://www.meilisearch.com/docs/learn/core_concepts/documents).
- **Search**: see the [API references](https://www.meilisearch.com/docs/reference/api/search) or follow our guide on [search parameters](https://www.meilisearch.com/docs/reference/api/search#search-parameters).
- **Manage the indexes**: see the [API references](https://www.meilisearch.com/docs/reference/api/indexes) or read more about [indexes](https://www.meilisearch.com/docs/learn/core_concepts/indexes).
- **Configure the index settings**: see the [API references](https://www.meilisearch.com/docs/reference/api/settings) or follow our guide on [settings parameters](https://www.meilisearch.com/docs/reference/api/settings#settings_parameters).

📖 Also, check out the [Wiki](https://github.com/meilisearch/meilisearch-symfony/wiki) of this repository!

## ⚙️ Development Workflow and Contributing

Any new contribution is more than welcome in this project!

If you want to know more about the development workflow or want to contribute, please visit our [contributing guidelines](/CONTRIBUTING.md) for detailed instructions!

<hr>

**Meilisearch** provides and maintains many **SDKs and Integration tools** like this one. We want to provide everyone with an **amazing search experience for any kind of project**. If you want to contribute, make suggestions, or just know what's going on right now, visit us in the [integration-guides](https://github.com/meilisearch/integration-guides) repository.
