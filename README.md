<p align="center">
  <img src="https://drive.google.com/uc?export=view&id=16hFZW-htUOv26djSxxxr8yhyLkRrM5-i" alt="MeiliSearch-Symfony" width="200" height="200" />
</p>

<h1 align="center">MeiliSearch Symfony Bundle</h1>

<h4 align="center">
  <a href="https://github.com/meilisearch/MeiliSearch">MeiliSearch</a> |
  <a href="https://www.meilisearch.com">Website</a> |
  <a href="https://blog.meilisearch.com">Blog</a> |
  <a href="https://twitter.com/meilisearch">Twitter</a> |
  <a href="https://docs.meilisearch.com">Documentation</a> |
  <a href="https://docs.meilisearch.com/faq">FAQ</a>
</h4>

<p align="center">
  <a href="https://packagist.org/packages/meilisearch/meilisearch-bundle"><img src="https://img.shields.io/packagist/v/meilisearch/meilisearch-bundle" alt="Latest Stable Version"></a>
  <a href="https://github.com/emulienfou/meilisearch-bundle/actions"><img src="https://github.com/emulienfou/meilisearch-bundle/workflows/Tests/badge.svg" alt="Test"></a>
  <a href="https://github.com/emulienfou/meilisearch-bundle/blob/master/LICENSE"><img src="https://img.shields.io/badge/license-MIT-informational" alt="License"></a>
  <a href="https://slack.meilisearch.com"><img src="https://img.shields.io/badge/slack-MeiliSearch-blue.svg?logo=slack" alt="Slack"></a>
</p>


<p align="center">⚡ Lightning Fast, Ultra Relevant, and Typo-Tolerant Search Engine MeiliSearch Symfony Bundle</p>

**MeiliSearchBundle** is a Bundle to integrate **MeiliSearch** within your Symfony Project.   
**MeiliSearch** is a powerful, fast, open-source, easy to use and deploy search engine. Both searching and indexing are highly customizable. Features such as typo-tolerance, filters, and synonyms are provided out-of-the-box.

## Table of Contents <!-- omit in toc -->
- [🤖 Compatibility with MeiliSearch](#-compatibility-with-meilisearch)
- [✨ Features](#-features)
- [📖 Documentation](#-documentation)
- [⚙️ Development Workflow](#️-development-workflow)
  - [Run DOcker environment](#run-docker-environment)
  - [Release](#release)

## 🤖 Compatibility with MeiliSearch
This package is compatible with the following MeiliSearch versions:
- `v0.12.X`
- `v0.11.X`

## ✨ Features
* **Require** PHP 7.2 and later.
* **Compatible** with Symfony 4.0 and later.
* **Support** Doctrine ORM and Doctrine MongoDB.

## 📖 Documentation
Complete documentation of the MeiliSearch Bundle is available in the [Wiki](https://github.com/emulienfou/meilisearch-bundle/wiki) section.

## ⚙️ Development Workflow
If you want to contribute, this section describes the steps to follow.

Thank you for your interest in a MeiliSearch tool! ♥️

### Run Docker Environment
To start and build your Docker environment, just execute the next command in a terminal:
```sh
docker-compose up -d
```

#### Tests
Each Pull Request should pass the tests, and the linter to be accepted.   
To execute the tests, run the next command:
```sh
docker-compose exec -e MEILISEARCH_URL=http://meilisearch:7700 php composer test:unit
```

### Release

MeiliSearch tools follow the [Semantic Versioning Convention](https://semver.org/).

You must do a PR modifying the file `src/MeiliSearchBundle.php` with the right version.<br>

```php
const VERSION = 'X.X.X';
```

Then, you must create a release (with this name `vX.X.X`) via the GitHub interface.<br>
A webhook will be triggered and push the new package on [Packagist](https://packagist.org/packages/meilisearch/meilisearch-bundle).

<hr>

**MeiliSearch** provides and maintains many **SDKs and Integration tools** like this one. We want to provide everyone with an **amazing search experience for any kind of project**. If you want to contribute, make suggestions, or just know what's going on right now, visit us in the [integration-guides](https://github.com/meilisearch/integration-guides) repository.
