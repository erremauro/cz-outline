# CZ Outline

**CZ Outline** è un plugin WordPress per generare un outline editoriale avanzato per articoli lunghi tramite shortcode `[outline]`.

Il plugin supporta tre modalità operative:

- `auto`: parsing automatico heading.
- `manual`: struttura definita con shortcode nidificati `[item]`.
- `hybrid`: parsing automatico con override selettivi tramite attributi `data-outline-*`.

L'outline viene generato **solo** dove è presente lo shortcode; non viene inserito automaticamente nel contenuto.

---

## Funzionalità principali

- Shortcode principale:

```php
[outline mode="auto|manual|hybrid" depth="3" numbering="false" sticky="false" class=""]
```

- Supporto multipagina (`<!--nextpage-->`) con mappa ID -> pagina.
- Link intelligenti:
  - stessa pagina: `#id`
  - pagina diversa: URL paginato + `#id` (pretty permalink e fallback query arg).
- Modalità `auto`:
  - parsing heading `h2-h4` (in base a `depth`),
  - preserva ID esistenti,
  - genera ID univoci se mancanti.
- Modalità `manual`:
  - supporto `[item target="..."]...[/item]` annidati,
  - `target` obbligatorio,
  - validazione target esistente,
  - item invalidi ignorati con log in `WP_DEBUG`.
- Modalità `hybrid` con attributi heading:
  - `data-outline="false"` per escludere,
  - `data-outline-title="..."` per titolo alternativo,
  - `data-outline-level="2"` per livello forzato.
- Numerazione gerarchica lato PHP (`1.`, `1.1`, `1.2`, `2.`) quando `numbering="true"`.
- Highlight dinamico e smooth-scroll lato JS.
- Drawer mobile sotto `1024px` senza librerie esterne.
- Cache con transient basata su:
  - post ID,
  - mode,
  - depth,
  - numbering.
- Invalidazione cache automatica su `save_post`.
- Caricamento asset con preferenza file minificati (`.min.js/.min.css`) quando disponibili.

---

## Requisiti

- WordPress 6.x
- PHP 7.4+

---

## Installazione

1. Copia la cartella `cz-outline` in `wp-content/plugins/`.
2. Attiva il plugin da **Plugin > Plugin installati**.
3. Inserisci lo shortcode `[outline]` nel contenuto del post/pagina.

---

## Esempi rapidi

Modalità automatica:

```php
[outline mode="auto" depth="3" numbering="true" sticky="true"]
```

Modalità manuale:

```php
[outline mode="manual"]
  [item target="contesto"]Contesto[/item]
  [item target="testo"]
    Testo
    [item target="parte1"]Parte 1[/item]
  [/item]
[/outline]
```

Modalità hybrid:

```php
[outline mode="hybrid" depth="3"]
```

Con heading nel contenuto:

```html
<h2 id="x" data-outline="false">Titolo escluso</h2>
<h2 id="y" data-outline-title="Titolo alternativo">Titolo originale</h2>
<h3 id="z" data-outline-level="2">Forza livello</h3>
```

---

## Note

- Il parsing viene eseguito solo se nel contenuto è presente `[outline]`.
- Nessuna modifica al layout globale del tema.
- Nessuna dipendenza esterna JavaScript/CSS.
