# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.1] 2026-11-07
### Added
- Filtro `cz_outline_bypass_content_filter` (default `false`): se restituisce `true`, il filtro `the_content` lascia invariato il contenuto ricevuto (rimuovendo solo lo shortcode `[outline]`) invece di sostituirlo con la pagina corrente e iniettare l'indice.

### Fixed
- Il tempo totale di lettura di cz-reading-time nei post paginati non varia più a seconda della pagina visualizzata: prima ogni pagina renderizzata per il calcolo veniva sostituita con quella corrente.

## [1.3.0] 2026-11-07
### Added
- Metodo pubblico `get_outline_export()`: restituisce l'outline come albero strutturato (`title`, `mode`, `depth`, `numbering`, `items[]` con `{ id, title, number?, children }`) più il contenuto grezzo con gli ID iniettati negli heading. Funziona anche fuori dal loop (es. REST API), usato da cz-article-api per l'export PDF.

## [1.2.0] 2026-11-05
### Changed
- L'icona dell'indice nella toolbar è registrata in PHP nella toolbar di cz-core (`cz_toolbar_add_button( 'outline', … )`, al centro con ordine 10, quindi a sinistra di "Su") invece di essere iniettata via JavaScript nella toolbar di cz-continue-reading. Richiede cz-core 1.1.0 (`mu-plugins/cz-toolbar`).
- Lo stato "aperto" dell'icona usa lo stile comune della toolbar (`.cz-toolbar__btn[aria-expanded="true"]`); con il drawer aperto su mobile viene nascosta `.cz-toolbar`.

## [1.1.0] 2026-11-05
### Changed
- L'indice è un pannello a sinistra aperto/chiuso da un pulsante: su desktop (>= 1024px) barra laterale con scroll autonomo che sposta il contenuto a destra, chiusa di default e con la scelta ricordata (localStorage `cz_outline_sidebar_open`); su schermi stretti drawer sopra il contenuto con sfondo oscurato e chiusura con ×, tocco fuori o Esc.
- Pulsanti di apertura: "indice" nel testo al posto del blocco indice (con icona) e, se presente la toolbar di cz-continue-reading, un'icona nella toolbar a sinistra di "Su". Funziona anche qualunque altro elemento `[data-cz-outline-toggle]` (etichette `data-label-show` / `data-label-hide`).
- La sezione corrente è evidenziata in grassetto (`.active`, con `aria-current="location"`) e mantenuta visibile nel pannello; calcolo sullo scroll invece che con IntersectionObserver.
- Apertura e chiusura del pannello con animazione di slide da sinistra (e del contenuto su desktop); disattivata con `prefers-reduced-motion`.
- Il pulsante di chiusura mostra "nascondi" su desktop e × su mobile.
- `outline.css` in forma leggibile; aggiunta regola `@media print` che nasconde l'indice.

### Added
- Classe `cz-outline-js` su `<html>` (in `wp_head`) per nascondere subito il blocco indice nel contenuto prima che diventi pannello.

### Removed
- Blocco indice nel contenuto con chiusura su desktop e relativo stato in localStorage (`cz_outline_desktop_collapsed:*`).

## [1.0.4] 2026-06-12
### Changed
- Updated author metadata to Roberto Mauro.
- Aligned `CZ_OUTLINE_VERSION` constant with plugin header.

## [1.0.3] 2026-02-27
### Fixed
- Ignore footnotes in headings

## [1.0.2] 2026-02-25
### Changed
- Update padding and title heading font-size

## [1.0.1] 2026-02-25
### Fixed
- Fix missing dark/light theme color-scheme

## [1.0.0] 2026-02-25
### Added
- Initial release.
- Shortcode `[outline]` con modalità `auto`, `manual` e `hybrid`.
- Supporto post multipagina (`<!--nextpage-->`) con mapping ID -> numero pagina.
- Generazione link multipagina compatibile con permalink pretty e fallback query arg.
- Parsing automatico heading con preservazione ID esistenti e generazione ID univoci mancanti.
- Supporto `[item]` nidificati in modalità `manual` con validazione target.
- Override heading in modalità `hybrid` via `data-outline`, `data-outline-title`, `data-outline-level`.
- Numerazione gerarchica lato PHP opzionale.
- Asset frontend (`outline.css` / `outline.js`) con smooth scroll, highlight dinamico e drawer mobile.
- Cache transient con invalidazione automatica su `save_post`.
- Caricamento automatico asset minificati `.min.css/.min.js` quando disponibili.

[Unreleased]: https://github.com/erremauro/cz-outline/compare/v1.3.1...HEAD
[1.3.1]: https://github.com/erremauro/cz-outline/releases/tag/v1.3.1
[1.3.0]: https://github.com/erremauro/cz-outline/releases/tag/v1.3.0
[1.2.0]: https://github.com/erremauro/cz-outline/releases/tag/v1.2.0
[1.1.0]: https://github.com/erremauro/cz-outline/releases/tag/v1.1.0
[1.0.4]: https://github.com/erremauro/cz-outline/releases/tag/v1.0.4
[1.0.3]: https://github.com/erremauro/cz-outline/releases/tag/v1.0.3
[1.0.2]: https://github.com/erremauro/cz-outline/releases/tag/v1.0.2
[1.0.1]: https://github.com/erremauro/cz-outline/releases/tag/v1.0.1
[1.0.0]: https://github.com/erremauro/cz-outline/releases/tag/v1.0.0
