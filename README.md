![Tests](https://github.com/StarDragonStudios/pcp/actions/workflows/tests.yml/badge.svg)
![Packagist Version](https://img.shields.io/packagist/v/pcp/pcp)
![License](https://img.shields.io/github/license/StarDragonStudios/pcp)
# PCP — Preprocessed Component Pages

PCP is a component-based templating system for PHP inspired by modern UI frameworks like React, but compiled to pure PHP.

It introduces a JSX-like syntax that compiles into efficient PHP code using an AST-based compiler.

⚠️ **Status:** Alpha (`v0.0.1-alpha.0`)  
The API and syntax may change.

---

# Philosophy

PCP aims to bring modern component composition to PHP while keeping the runtime extremely small.

Key ideas:

- compile-time transformations
- minimal runtime
- component-first design
- explicit composition

Instead of interpreting templates at runtime, PCP **compiles components to native PHP**.

---

# Example

## Component

```pcp
namespace App\Components;

use PCP\Component;
use PCP\Runtime\Node;

final class Card extends Component
{
    public function __construct(
        private ?Node $header = null,
        private ?Node $body = null,
        private ?Node $footer = null,
    ) {
        parent::__construct();
    }

    public function render(): Node
    {
        return (
            <article>
                <div class="card__header"><Slot:Header /></div>
                <div class="card__body"><Slot:Body /></div>
                <div class="card__footer"><Slot:Footer /></div>
            </article>
        );
    }
}
```

## Usage

```pcp
<Card>
    <Card\Header><h1>Hello</h1></Card\Header>
    <Card\Body><p>Content</p></Card\Body>
    <Card\Footer><small>Footer</small></Card\Footer>
</Card>
```

---

# Slots

PCP uses **named node props** instead of runtime slot resolution.

Provide content:

```pcp
<Card\Header>...</Card\Header>
```

Consume content:

```pcp
<Slot:Header />
```

Compilation result:

```php
new Card(
    header: Runtime::fragment([...]),
    body: Runtime::fragment([...]),
    footer: Runtime::fragment([...])
);
```

---

# Features (alpha)

- JSX-like syntax
- component composition
- named slots
- AST compiler
- minimal runtime
- integration tests

---

# Installation

```bash
composer require pcp/pcp:@alpha
```

---

# Basic setup

```php
$config = PCPConfig::defaults();
$config->roots = ['components'];
$config->cacheDir = 'cache';

$pcp = new PCP($config);
$pcp->registerAutoload();
```

Now components are compiled automatically when used.

---

# Syntax overview

### Elements

```pcp
<div class="container">
    Hello
</div>
```

### Expressions

```pcp
<p>{ $name }</p>
```

### Components

```pcp
<MyComponent prop="value" />
```

### Fragments

```pcp
<>
    <h1>Title</h1>
    <p>Text</p>
</>
```

---

# Project structure

Recommended layout:

```
components/
  App/
    Components/
      Card.pcp
      Page.pcp
```

---

# Development

Run tests:

```bash
composer test
```

---

# Roadmap

Planned features:

- better error messages
- `<Slot:default>`
- `<Slot:Header />` self-closing support
- dev HMR improvements
- IDE tooling
- syntax highlighting

---

# Inspiration

PCP takes inspiration from:

- React
- Astro
- JSX
- modern component compilers

But compiles to **pure PHP**.

---

# License

MIT
