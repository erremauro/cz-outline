# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[Unreleased]: https://github.com/erremauro/cz-outline/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/erremauro/cz-outline/releases/tag/v1.0.0
