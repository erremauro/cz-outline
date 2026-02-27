<?php
/**
 * Plugin Name: CZ Outline
 * Description: Outline editoriale avanzato per articoli lunghi tramite shortcode [outline].
 * Version: 1.0.0
 * Author: CZ
 * Text Domain: cz-outline
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CZ_OUTLINE_VERSION', '1.0.0' );
define( 'CZ_OUTLINE_PATH', plugin_dir_path( __FILE__ ) );
define( 'CZ_OUTLINE_URL', plugin_dir_url( __FILE__ ) );

if ( ! function_exists( 'cz_outline_get_asset' ) ) {
	/**
	 * Restituisce URL e versione asset, preferendo file .min se disponibili.
	 *
	 * @param string $relative_path Percorso relativo.
	 * @return array{0:string,1:string}
	 */
	function cz_outline_get_asset( $relative_path ) {
		$relative_path = ltrim( (string) $relative_path, '/' );
		$source_path   = CZ_OUTLINE_PATH . $relative_path;
		$source_url    = CZ_OUTLINE_URL . $relative_path;
		$min_path      = preg_replace( '/(\\.js|\\.css)$/', '.min$1', $source_path );
		$min_url       = preg_replace( '/(\\.js|\\.css)$/', '.min$1', $source_url );
		$use_min       = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? false : true;

		$chosen_path = $source_path;
		$chosen_url  = $source_url;

		if ( $use_min && $min_path && file_exists( $min_path ) ) {
			$chosen_path = $min_path;
			$chosen_url  = $min_url;
		}

		$version = file_exists( $chosen_path ) ? (string) filemtime( $chosen_path ) : CZ_OUTLINE_VERSION;

		return array( $chosen_url, $version );
	}
}

final class CZ_Outline_Plugin {
	/**
	 * @var CZ_Outline_Plugin|null
	 */
	private static $instance = null;

	/**
	 * @var bool
	 */
	private $manual_mode_active = false;

	/**
	 * @var array<string,int>
	 */
	private $manual_id_to_page = array();

	/**
	 * Singleton.
	 *
	 * @return CZ_Outline_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_shortcode( 'outline', array( $this, 'render_outline_shortcode' ) );
		add_shortcode( 'item', array( $this, 'render_item_shortcode' ) );
		add_filter( 'the_content', array( $this, 'filter_content_add_heading_ids' ), 9 );
		add_action( 'save_post', array( $this, 'invalidate_post_transients' ), 10, 2 );
	}

	/**
	 * Gestione contenuto:
	 * - in auto/hybrid inserisce ID mancanti negli heading,
	 * - se [outline] esiste nel post renderizza outline in tutte le pagine multipagina,
	 * - evita duplicazioni rimuovendo gli shortcode raw dalla pagina corrente.
	 *
	 * @param string $content Contenuto pagina corrente.
	 * @return string
	 */
	public function filter_content_add_heading_ids( $content ) {
		if ( is_admin() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post = get_post();
		if ( ! ( $post instanceof WP_Post ) ) {
			return $content;
		}

		if ( ! $this->content_has_outline_shortcode( $post->post_content ) ) {
			return $content;
		}

		if ( false !== strpos( $content, '<!--cz-outline-injected-->' ) ) {
			return $content;
		}

		$outline_shortcode = $this->extract_first_outline_shortcode( (string) $post->post_content );
		if ( empty( $outline_shortcode ) || ! is_array( $outline_shortcode ) ) {
			return $content;
		}

		$outline_atts    = isset( $outline_shortcode['atts'] ) && is_array( $outline_shortcode['atts'] ) ? $outline_shortcode['atts'] : array();
		$outline_content = isset( $outline_shortcode['content'] ) ? (string) $outline_shortcode['content'] : '';
		$mode            = isset( $outline_atts['mode'] ) ? strtolower( sanitize_text_field( (string) $outline_atts['mode'] ) ) : $this->detect_outline_mode_from_content( $post->post_content );
		if ( ! in_array( $mode, array( 'auto', 'manual', 'hybrid' ), true ) ) {
			$mode = 'auto';
		}
		$depth           = isset( $outline_atts['depth'] ) ? max( 1, min( 5, absint( $outline_atts['depth'] ) ) ) : 3;
		if ( 0 === $depth ) {
			$depth = 3;
		}
		$numbering       = isset( $outline_atts['numbering'] ) ? $this->sanitize_bool_like( $outline_atts['numbering'] ) : false;
		$output_content  = $content;

		if ( 'manual' !== $mode ) {
			$data         = $this->get_outline_data( $post, $mode, $depth, $numbering );
			$current_page = $this->get_current_page_number();
			if ( isset( $data['page_contents'][ $current_page ] ) && is_string( $data['page_contents'][ $current_page ] ) ) {
				$output_content = $data['page_contents'][ $current_page ];
			}
		}

		$output_content = $this->remove_outline_shortcodes( $output_content );
		$outline_html   = $this->render_outline_shortcode( $outline_atts, $outline_content );

		if ( '' === trim( $outline_html ) ) {
			return $output_content;
		}

		return '<!--cz-outline-injected-->' . $outline_html . $output_content;
	}

	/**
	 * Shortcode [outline].
	 *
	 * @param array<string,mixed> $atts Attributi.
	 * @param string|null         $content Contenuto annidato.
	 * @return string
	 */
	public function render_outline_shortcode( $atts, $content = null ) {
		$post = get_post();
		if ( ! ( $post instanceof WP_Post ) ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'mode'      => 'auto',
				'depth'     => '3',
				'numbering' => 'false',
				'sticky'    => 'false',
				'class'     => '',
			),
			(array) $atts,
			'outline'
		);

		$mode = strtolower( sanitize_text_field( (string) $atts['mode'] ) );
		if ( ! in_array( $mode, array( 'auto', 'manual', 'hybrid' ), true ) ) {
			$mode = 'auto';
		}

		$depth = max( 1, min( 5, absint( $atts['depth'] ) ) );
		if ( 0 === $depth ) {
			$depth = 3;
		}

		$numbering = $this->sanitize_bool_like( $atts['numbering'] );
		$sticky    = $this->sanitize_bool_like( $atts['sticky'] );
		$class     = $this->sanitize_css_class_list( (string) $atts['class'] );

		$data = $this->get_outline_data( $post, $mode, $depth, $numbering );
		if ( empty( $data['id_to_page'] ) || ! is_array( $data['id_to_page'] ) ) {
			return '';
		}

		if ( 'manual' === $mode ) {
			$manual_targets = isset( $data['existing_id_to_page'] ) && is_array( $data['existing_id_to_page'] ) ? $data['existing_id_to_page'] : array();
			$nodes          = $this->build_manual_nodes( (string) $content, $manual_targets );
		} else {
			$nodes = $this->build_auto_or_hybrid_nodes( $data['headings'], $mode, $depth );
		}

		if ( empty( $nodes ) ) {
			return '';
		}

		if ( $numbering ) {
			$this->apply_hierarchical_numbering( $nodes );
		}

		$this->enqueue_assets();

		$classes = array( 'cz-outline' );
		if ( $sticky ) {
			$classes[] = 'cz-outline--sticky';
		}
		if ( '' !== $class ) {
			foreach ( preg_split( '/\\s+/', $class ) as $extra_class ) {
				$extra_class = sanitize_html_class( $extra_class );
				if ( '' !== $extra_class ) {
					$classes[] = $extra_class;
				}
			}
		}

		$output  = '<nav class="' . esc_attr( implode( ' ', array_unique( $classes ) ) ) . '">';
		$output .= '<div class="cz-outline-inner">';
		$output .= '<div class="cz-outline-header">';
		$output .= '<h2 class="cz-outline-heading">' . esc_html__( 'Indice', 'cz-outline' ) . '</h2>';
		$output .= '<button type="button" class="cz-outline-close" aria-label="' . esc_attr__( 'Chiudi indice', 'cz-outline' ) . '">×</button>';
		$output .= '</div>';
		$output .= $this->render_outline_list( $nodes, $post->ID, $data['id_to_page'] );
		$output .= '</div>';
		$output .= '</nav>';

		return $output;
	}

	/**
	 * Shortcode [item] valido solo dentro [outline mode="manual"].
	 *
	 * @param array<string,mixed> $atts Attributi.
	 * @param string|null         $content Contenuto.
	 * @return string
	 */
	public function render_item_shortcode( $atts, $content = null ) {
		if ( ! $this->manual_mode_active ) {
			return '';
		}

		$target_raw = isset( $atts['target'] ) ? (string) $atts['target'] : '';
		$target     = $this->sanitize_anchor_id( $target_raw );

		if ( '' === $target ) {
			$this->debug_log( 'CZ Outline: [item] ignorato, attributo target mancante o non valido.' );
			return '';
		}

		if ( ! isset( $this->manual_id_to_page[ $target ] ) ) {
			$this->debug_log( sprintf( 'CZ Outline: [item] ignorato, target "%s" non trovato nel post.', $target ) );
			return '';
		}

		$inner = do_shortcode( (string) $content );

		return '<cz-outline-item data-target="' . esc_attr( $target ) . '">' . $inner . '</cz-outline-item>';
	}

	/**
	 * Costruisce nodi modalità auto/hybrid.
	 *
	 * @param array<int,array<string,mixed>> $headings Heading estratti.
	 * @param string                         $mode Modalità.
	 * @param int                            $depth Profondità shortcode.
	 * @return array<int,array<string,mixed>>
	 */
	private function build_auto_or_hybrid_nodes( $headings, $mode, $depth ) {
		$max_level = min( 6, 1 + $depth );
		$tree_root = array(
			'level'    => 1,
			'children' => array(),
		);
		$stack     = array();
		$stack[]   = &$tree_root;

		foreach ( $headings as $heading ) {
			if ( ! isset( $heading['level'], $heading['id'], $heading['title'] ) ) {
				continue;
			}

			$level = (int) $heading['level'];
			if ( $level < 2 || $level > $max_level ) {
				continue;
			}

			$id    = $this->sanitize_anchor_id( (string) $heading['id'] );
			$title = sanitize_text_field( (string) $heading['title'] );
			if ( '' === $id || '' === $title ) {
				continue;
			}

			if ( 'hybrid' === $mode ) {
				if ( isset( $heading['data_outline'] ) && $this->attribute_is_false( (string) $heading['data_outline'] ) ) {
					continue;
				}

				if ( ! empty( $heading['data_outline_title'] ) ) {
					$title = sanitize_text_field( (string) $heading['data_outline_title'] );
				}

				if ( isset( $heading['data_outline_level'] ) && '' !== (string) $heading['data_outline_level'] ) {
					$forced = absint( $heading['data_outline_level'] );
					if ( $forced >= 2 && $forced <= 6 ) {
						$level = min( $forced, $max_level );
					}
				}
			}

			while ( count( $stack ) > 1 ) {
				$last_idx   = count( $stack ) - 1;
				$last_level = isset( $stack[ $last_idx ]['level'] ) ? (int) $stack[ $last_idx ]['level'] : 1;
				if ( $last_level < $level ) {
					break;
				}
				array_pop( $stack );
			}

			$parent_idx = count( $stack ) - 1;
			$stack[ $parent_idx ]['children'][] = array(
				'id'       => $id,
				'title'    => $title,
				'level'    => $level,
				'children' => array(),
			);

			$child_idx = count( $stack[ $parent_idx ]['children'] ) - 1;
			$stack[]   = &$stack[ $parent_idx ]['children'][ $child_idx ];
		}

		return $tree_root['children'];
	}

	/**
	 * Costruisce nodi modalità manual da shortcode [item] annidati.
	 *
	 * @param string               $content Contenuto shortcode.
	 * @param array<string,int>    $id_to_page Mappa ID->pagina.
	 * @return array<int,array<string,mixed>>
	 */
	private function build_manual_nodes( $content, $id_to_page ) {
		$this->manual_mode_active = true;
		$this->manual_id_to_page  = $id_to_page;
		$rendered                 = do_shortcode( $content );
		$this->manual_mode_active = false;
		$this->manual_id_to_page  = array();

		if ( '' === trim( $rendered ) ) {
			return array();
		}

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<div id="cz-outline-root">' . $rendered . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );
		$root  = $xpath->query( '//*[@id="cz-outline-root"]' )->item( 0 );
		if ( ! $root ) {
			return array();
		}

		$nodes = array();
		foreach ( $root->childNodes as $child ) {
			if ( XML_ELEMENT_NODE !== $child->nodeType || 'cz-outline-item' !== strtolower( $child->nodeName ) ) {
				continue;
			}

			$node = $this->parse_manual_dom_item( $child );
			if ( ! empty( $node ) ) {
				$nodes[] = $node;
			}
		}

		return $nodes;
	}

	/**
	 * Parsing ricorsivo nodo manual.
	 *
	 * @param DOMNode $item_node Nodo custom.
	 * @return array<string,mixed>
	 */
	private function parse_manual_dom_item( DOMNode $item_node ) {
		if ( ! ( $item_node instanceof DOMElement ) ) {
			return array();
		}

		$target = $this->sanitize_anchor_id( (string) $item_node->getAttribute( 'data-target' ) );
		if ( '' === $target ) {
			return array();
		}

		$title_text = '';
		$children   = array();

		foreach ( $item_node->childNodes as $child ) {
			if ( XML_ELEMENT_NODE === $child->nodeType && 'cz-outline-item' === strtolower( $child->nodeName ) ) {
				$parsed_child = $this->parse_manual_dom_item( $child );
				if ( ! empty( $parsed_child ) ) {
					$children[] = $parsed_child;
				}
				continue;
			}

			$title_text .= ' ' . $child->textContent;
		}

		$title_text = $this->normalize_outline_title_text( $title_text );
		if ( '' === $title_text ) {
			$title_text = $target;
		}

		return array(
			'id'       => $target,
			'title'    => sanitize_text_field( $title_text ),
			'children' => $children,
		);
	}

	/**
	 * Aggiunge numerazione gerarchica ai nodi.
	 *
	 * @param array<int,array<string,mixed>> $nodes Nodi.
	 * @param array<int,int>                 $prefix Prefisso corrente.
	 * @return void
	 */
	private function apply_hierarchical_numbering( &$nodes, $prefix = array() ) {
		$index = 1;
		foreach ( $nodes as &$node ) {
			$current_prefix   = $prefix;
			$current_prefix[] = $index;
			$node['number']   = implode( '.', $current_prefix ) . ( 1 === count( $current_prefix ) ? '.' : '' );

			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				$this->apply_hierarchical_numbering( $node['children'], $current_prefix );
			}

			$index++;
		}
		unset( $node );
	}

	/**
	 * Rendering UL/LI ricorsivo.
	 *
	 * @param array<int,array<string,mixed>> $nodes Nodi.
	 * @param int                            $post_id Post ID.
	 * @param array<string,int>              $id_to_page Mappa ID->pagina.
	 * @return string
	 */
	private function render_outline_list( $nodes, $post_id, $id_to_page ) {
		if ( empty( $nodes ) ) {
			return '';
		}

		$output = '<ul>';
		foreach ( $nodes as $node ) {
			if ( empty( $node['id'] ) || empty( $node['title'] ) ) {
				continue;
			}

			$id    = $this->sanitize_anchor_id( (string) $node['id'] );
			$title = sanitize_text_field( (string) $node['title'] );
			if ( '' === $id || '' === $title ) {
				continue;
			}

			$href       = $this->build_heading_link( $id, $post_id, $id_to_page );
			$link_class = 'cz-outline-link';

			$output .= '<li>';
			$output .= '<a class="' . esc_attr( $link_class ) . '" href="' . esc_url( $href ) . '">';
			if ( isset( $node['number'] ) ) {
				$output .= '<span class="cz-outline-number">' . esc_html( trim( (string) $node['number'] ) ) . '</span> ';
			}
			$output .= '<span class="cz-outline-title">' . esc_html( $title ) . '</span>';
			$output .= '</a>';

			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				$output .= $this->render_outline_list( $node['children'], $post_id, $id_to_page );
			}

			$output .= '</li>';
		}
		$output .= '</ul>';

		return $output;
	}

	/**
	 * Costruisce href corretto con supporto multipagina.
	 *
	 * @param string            $id Heading ID.
	 * @param int               $post_id Post ID.
	 * @param array<string,int> $id_to_page Mappa ID->pagina.
	 * @return string
	 */
	private function build_heading_link( $id, $post_id, $id_to_page ) {
		$current_page = $this->get_current_page_number();
		$target_page  = isset( $id_to_page[ $id ] ) ? max( 1, (int) $id_to_page[ $id ] ) : 1;

		if ( $target_page === $current_page ) {
			return '#' . rawurlencode( $id );
		}

		return $this->get_multipage_permalink( $post_id, $target_page ) . '#' . rawurlencode( $id );
	}

	/**
	 * URL pagina specifica per post multipagina.
	 *
	 * @param int $post_id Post ID.
	 * @param int $page_number Numero pagina.
	 * @return string
	 */
	private function get_multipage_permalink( $post_id, $page_number ) {
		$page_number = max( 1, (int) $page_number );
		$permalink   = get_permalink( $post_id );
		if ( ! $permalink || $page_number <= 1 ) {
			return (string) $permalink;
		}

		global $wp_rewrite;
		if ( $wp_rewrite instanceof WP_Rewrite && $wp_rewrite->using_permalinks() ) {
			return trailingslashit( $permalink ) . user_trailingslashit( (string) $page_number, 'single_paged' );
		}

		return add_query_arg( 'page', $page_number, $permalink );
	}

	/**
	 * Estrae e cache-a dati outline del post.
	 *
	 * @param WP_Post $post Post.
	 * @param string  $mode Modalità.
	 * @param int     $depth Profondità.
	 * @param bool    $numbering Numerazione.
	 * @return array<string,mixed>
	 */
	private function get_outline_data( WP_Post $post, $mode, $depth, $numbering ) {
		$transient_key = $this->build_transient_key( $post->ID, $mode, $depth, $numbering );
		$cached        = get_transient( $transient_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$data = $this->parse_full_post_content( (string) $post->post_content );

		set_transient( $transient_key, $data, DAY_IN_SECONDS );
		$this->track_transient_key_for_post( $post->ID, $transient_key );

		return $data;
	}

	/**
	 * Parsing completo contenuto, incluso split multipagina.
	 *
	 * @param string $post_content Contenuto intero post.
	 * @return array<string,mixed>
	 */
	private function parse_full_post_content( $post_content ) {
		$pages = preg_split( '/<!--nextpage-->/i', $post_content );
		if ( ! is_array( $pages ) || empty( $pages ) ) {
			$pages = array( $post_content );
		}

		$used_ids            = array();
		$id_to_page          = array();
		$existing_id_to_page = array();
		$all_headings        = array();
		$page_contents       = array();

		foreach ( $pages as $index => $page_html ) {
			$page_number = $index + 1;
			$result      = $this->parse_single_page( (string) $page_html, $page_number, $used_ids, $id_to_page, $existing_id_to_page );

			$page_contents[ $page_number ] = $result['content'];
			$all_headings                  = array_merge( $all_headings, $result['headings'] );
		}

		return array(
			'id_to_page'          => $id_to_page,
			'existing_id_to_page' => $existing_id_to_page,
			'headings'            => $all_headings,
			'page_contents'       => $page_contents,
		);
	}

	/**
	 * Parsing singola pagina: heading + ID mancanti.
	 *
	 * @param string            $html HTML pagina.
	 * @param int               $page_number Numero pagina.
	 * @param array<string,bool> $used_ids Registro ID usati.
	 * @param array<string,int> $id_to_page Mappa ID->pagina.
	 * @param array<string,int> $existing_id_to_page Mappa soli ID originari.
	 * @return array{content:string,headings:array<int,array<string,mixed>>}
	 */
	private function parse_single_page( $html, $page_number, &$used_ids, &$id_to_page, &$existing_id_to_page ) {
		$headings = array();

		$processed = preg_replace_callback(
			'/<h([1-6])\\b([^>]*)>(.*?)<\\/h\\1>/is',
			function ( $matches ) use ( $page_number, &$used_ids, &$id_to_page, &$headings ) {
				$level     = (int) $matches[1];
				$attr_raw  = isset( $matches[2] ) ? (string) $matches[2] : '';
				$inner_html = isset( $matches[3] ) ? (string) $matches[3] : '';
				$attrs     = $this->parse_html_attributes( $attr_raw );
				$id        = isset( $attrs['id'] ) ? trim( (string) $attrs['id'] ) : '';
				$title     = $this->normalize_outline_title_text( $inner_html );

				$had_original_id = '' !== $id;

				if ( ! $had_original_id ) {
					$id = $this->generate_unique_heading_id( $title, $used_ids );
					$attr_raw = trim( $attr_raw );
					$attr_raw = '' === $attr_raw ? 'id="' . esc_attr( $id ) . '"' : $attr_raw . ' id="' . esc_attr( $id ) . '"';
				} else {
					if ( isset( $used_ids[ $id ] ) ) {
						$this->debug_log( sprintf( 'CZ Outline: ID heading duplicato "%s" rilevato.', $id ) );
					} else {
						$used_ids[ $id ] = true;
					}
				}

				if ( ! isset( $id_to_page[ $id ] ) ) {
					$id_to_page[ $id ] = $page_number;
				}
				if ( $had_original_id && ! isset( $existing_id_to_page[ $id ] ) ) {
					$existing_id_to_page[ $id ] = $page_number;
				}

				$headings[] = array(
					'id'                 => $id,
					'level'              => $level,
					'title'              => $title,
					'page'               => $page_number,
					'data_outline'       => isset( $attrs['data-outline'] ) ? (string) $attrs['data-outline'] : '',
					'data_outline_title' => isset( $attrs['data-outline-title'] ) ? (string) $attrs['data-outline-title'] : '',
					'data_outline_level' => isset( $attrs['data-outline-level'] ) ? (string) $attrs['data-outline-level'] : '',
				);

				$open_tag = '<h' . $level . ( '' !== trim( $attr_raw ) ? ' ' . trim( $attr_raw ) : '' ) . '>';
				return $open_tag . $inner_html . '</h' . $level . '>';
			},
			$html
		);

		return array(
			'content'  => is_string( $processed ) ? $processed : $html,
			'headings' => $headings,
		);
	}

	/**
	 * Normalizza titolo outline rimuovendo note inline (es. <sup><a>1</a></sup>).
	 *
	 * @param string $raw_title Titolo HTML o testo.
	 * @return string
	 */
	private function normalize_outline_title_text( $raw_title ) {
		$clean = preg_replace( '/<sup\\b[^>]*>.*?<\\/sup>/is', ' ', (string) $raw_title );
		$clean = is_string( $clean ) ? $clean : (string) $raw_title;
		$clean = wp_strip_all_tags( $clean );
		$clean = preg_replace( '/\\s+/u', ' ', $clean );
		$clean = is_string( $clean ) ? $clean : '';

		return trim( $clean );
	}

	/**
	 * Parsing attributi tag HTML.
	 *
	 * @param string $attr_string Attributi grezzi.
	 * @return array<string,string>
	 */
	private function parse_html_attributes( $attr_string ) {
		$attributes = array();

		if ( preg_match_all( "/([a-zA-Z_:][a-zA-Z0-9:._-]*)\\s*=\\s*(\"([^\"]*)\"|'([^']*)'|([^\\s\"'>]+))/", $attr_string, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$key = strtolower( (string) $match[1] );
				$val = '';
				if ( isset( $match[3] ) && '' !== $match[3] ) {
					$val = (string) $match[3];
				} elseif ( isset( $match[4] ) && '' !== $match[4] ) {
					$val = (string) $match[4];
				} elseif ( isset( $match[5] ) ) {
					$val = (string) $match[5];
				}

				$attributes[ $key ] = html_entity_decode( $val, ENT_QUOTES, 'UTF-8' );
			}
		}

		return $attributes;
	}

	/**
	 * Genera ID heading univoco e registralo.
	 *
	 * @param string             $title Titolo heading.
	 * @param array<string,bool> $used_ids Registro.
	 * @return string
	 */
	private function generate_unique_heading_id( $title, &$used_ids ) {
		$base = sanitize_title( $title );
		if ( '' === $base ) {
			$base = 'section';
		}

		$id      = $base;
		$suffix  = 2;
		while ( isset( $used_ids[ $id ] ) ) {
			$id = $base . '-' . $suffix;
			$suffix++;
		}

		$used_ids[ $id ] = true;
		return $id;
	}

	/**
	 * Costruisce chiave transient.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $mode Mode.
	 * @param int    $depth Depth.
	 * @param bool   $numbering Numbering.
	 * @return string
	 */
	private function build_transient_key( $post_id, $mode, $depth, $numbering ) {
		return sprintf(
			'cz_outline_%d_%s_%d_%d',
			(int) $post_id,
			sanitize_key( $mode ),
			(int) $depth,
			$numbering ? 1 : 0
		);
	}

	/**
	 * Registra transient creato per post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key transient key.
	 * @return void
	 */
	private function track_transient_key_for_post( $post_id, $key ) {
		$meta_key = '_cz_outline_transients';
		$keys     = get_post_meta( $post_id, $meta_key, true );
		$keys     = is_array( $keys ) ? $keys : array();

		if ( ! in_array( $key, $keys, true ) ) {
			$keys[] = $key;
			update_post_meta( $post_id, $meta_key, $keys );
		}
	}

	/**
	 * Invalida cache transient su save_post.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public function invalidate_post_transients( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}

		$meta_key = '_cz_outline_transients';
		$keys     = get_post_meta( $post_id, $meta_key, true );
		if ( is_array( $keys ) ) {
			foreach ( $keys as $key ) {
				delete_transient( (string) $key );
			}
		}

		delete_post_meta( $post_id, $meta_key );
	}

	/**
	 * Enqueue asset plugin.
	 *
	 * @return void
	 */
	private function enqueue_assets() {
		list( $css_url, $css_ver ) = cz_outline_get_asset( 'assets/css/outline.css' );
		list( $js_url, $js_ver )   = cz_outline_get_asset( 'assets/js/outline.js' );

		wp_enqueue_style( 'cz-outline', $css_url, array(), $css_ver );
		wp_enqueue_script( 'cz-outline', $js_url, array(), $js_ver, true );
	}

	/**
	 * Verifica presenza shortcode [outline].
	 *
	 * @param string $content Contenuto post.
	 * @return bool
	 */
	private function content_has_outline_shortcode( $content ) {
		return false !== strpos( $content, '[outline' ) && has_shortcode( $content, 'outline' );
	}

	/**
	 * Estrae il primo shortcode [outline] dal contenuto completo del post.
	 *
	 * @param string $content Contenuto completo.
	 * @return array<string,mixed>
	 */
	private function extract_first_outline_shortcode( $content ) {
		if ( preg_match( '/\\[outline\\b([^\\]]*)\\](.*?)\\[\\/outline\\]/is', $content, $match ) ) {
			$atts = shortcode_parse_atts( isset( $match[1] ) ? trim( (string) $match[1] ) : '' );
			return array(
				'atts'    => is_array( $atts ) ? $atts : array(),
				'content' => isset( $match[2] ) ? (string) $match[2] : '',
			);
		}

		if ( preg_match( '/\\[outline\\b([^\\]]*)\\/?\\]/i', $content, $match ) ) {
			$atts = shortcode_parse_atts( isset( $match[1] ) ? trim( (string) $match[1] ) : '' );
			return array(
				'atts'    => is_array( $atts ) ? $atts : array(),
				'content' => '',
			);
		}

		return array();
	}

	/**
	 * Rimuove shortcode outline dalla pagina corrente per evitare duplicazioni output.
	 *
	 * @param string $content Contenuto pagina corrente.
	 * @return string
	 */
	private function remove_outline_shortcodes( $content ) {
		$content = preg_replace( '/\\[outline\\b[^\\]]*\\].*?\\[\\/outline\\]/is', '', $content );
		$content = preg_replace( '/\\[outline\\b[^\\]]*\\/?\\]/i', '', (string) $content );
		return is_string( $content ) ? $content : '';
	}

	/**
	 * Rileva modalità primo shortcode outline nel post.
	 *
	 * @param string $content Contenuto completo.
	 * @return string
	 */
	private function detect_outline_mode_from_content( $content ) {
		if ( preg_match( "/\\[outline\\s+[^\\]]*mode\\s*=\\s*[\"']?([a-zA-Z]+)[\"']?[^\\]]*\\]/", $content, $match ) ) {
			$mode = strtolower( sanitize_text_field( (string) $match[1] ) );
			if ( in_array( $mode, array( 'auto', 'manual', 'hybrid' ), true ) ) {
				return $mode;
			}
		}

		return 'auto';
	}

	/**
	 * Pagina multipagina corrente.
	 *
	 * @return int
	 */
	private function get_current_page_number() {
		global $page;

		$current = absint( get_query_var( 'page' ) );
		if ( $current < 1 && isset( $page ) ) {
			$current = absint( $page );
		}

		return max( 1, $current );
	}

	/**
	 * Sanitizza ID ancora.
	 *
	 * @param string $id ID.
	 * @return string
	 */
	private function sanitize_anchor_id( $id ) {
		$id = sanitize_text_field( $id );
		$id = trim( $id );
		$id = preg_replace( '/[^A-Za-z0-9_:\\-.]/', '', $id );

		return is_string( $id ) ? $id : '';
	}

	/**
	 * Sanitizza class list.
	 *
	 * @param string $class_string String classi.
	 * @return string
	 */
	private function sanitize_css_class_list( $class_string ) {
		$classes = preg_split( '/\\s+/', trim( $class_string ) );
		$clean   = array();

		if ( is_array( $classes ) ) {
			foreach ( $classes as $class ) {
				$class = sanitize_html_class( $class );
				if ( '' !== $class ) {
					$clean[] = $class;
				}
			}
		}

		return implode( ' ', array_unique( $clean ) );
	}

	/**
	 * Normalizza boolean-like shortcode attrs.
	 *
	 * @param mixed $value Valore.
	 * @return bool
	 */
	private function sanitize_bool_like( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		$value = strtolower( trim( (string) $value ) );
		return in_array( $value, array( '1', 'true', 'yes', 'on' ), true );
	}

	/**
	 * Riconosce attributo impostato a false.
	 *
	 * @param string $value Valore.
	 * @return bool
	 */
	private function attribute_is_false( $value ) {
		$value = strtolower( trim( $value ) );
		return in_array( $value, array( 'false', '0', 'no', 'off' ), true );
	}

	/**
	 * Log condizionale in debug.
	 *
	 * @param string $message Messaggio.
	 * @return void
	 */
	private function debug_log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( $message );
		}
	}
}

CZ_Outline_Plugin::instance();
