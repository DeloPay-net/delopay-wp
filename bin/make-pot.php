#!/usr/bin/env php
<?php
/**
 * Minimal .pot generator for the WP DeloPay plugin and DeloPay Shop theme.
 *
 * Usage:
 *   php bin/make-pot.php
 *
 * Scans plugin/ and theme/ for WordPress translation calls and emits
 *   plugin/languages/wp-delopay.pot
 *   theme/languages/delopay-shop.pot
 *
 * Supported calls:
 *   __()  _e()  _x()  _ex()  _n()  _nx()
 *   esc_html__()  esc_html_e()  esc_html_x()
 *   esc_attr__()  esc_attr_e()  esc_attr_x()
 *
 * This is a deliberately tiny extractor; for translator hand-off, prefer
 * `wp i18n make-pot` once wp-cli is on the build machine.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "Run from the CLI.\n" );
    exit( 1 );
}

$root = realpath( __DIR__ . '/..' );

$packages = [
    [
        'label'   => 'plugin',
        'domain'  => 'wp-delopay',
        'name'    => 'WP DeloPay',
        'src'     => $root . '/plugin',
        'out_dir' => $root . '/plugin/languages',
        'out'     => $root . '/plugin/languages/wp-delopay.pot',
    ],
    [
        'label'   => 'theme',
        'domain'  => 'delopay-shop',
        'name'    => 'DeloPay Shop',
        'src'     => $root . '/theme',
        'out_dir' => $root . '/theme/languages',
        'out'     => $root . '/theme/languages/delopay-shop.pot',
    ],
];

foreach ( $packages as $pkg ) {
    $entries = collect_strings( $pkg['src'], $pkg['domain'] );
    if ( ! is_dir( $pkg['out_dir'] ) ) {
        mkdir( $pkg['out_dir'], 0755, true );
    }
    file_put_contents( $pkg['out'], render_pot( $pkg['name'], $pkg['domain'], $entries ) );
    printf( "%s: %d strings -> %s\n", $pkg['label'], count( $entries ), $pkg['out'] );
}

function collect_strings( $src, $domain ) {
    $entries = [];
    foreach ( iterate_php_files( $src ) as $file ) {
        $code = file_get_contents( $file );
        if ( $code === false ) {
            continue;
        }
        scan_calls( $code, $file, $domain, $entries );
    }
    ksort( $entries );
    return $entries;
}

function iterate_php_files( $dir ) {
    $skip = [ 'node_modules', 'vendor', '.git', 'dist' ];
    $rii  = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
            function ( $current ) use ( $skip ) {
                return ! in_array( $current->getFilename(), $skip, true );
            }
        )
    );
    foreach ( $rii as $f ) {
        if ( $f->isFile() && strtolower( $f->getExtension() ) === 'php' ) {
            yield $f->getPathname();
        }
    }
}

function scan_calls( $code, $file, $domain, array &$entries ) {
    $tokens = token_get_all( $code );
    $count  = count( $tokens );

    $singular = [ '__', '_e', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e' ];
    $context  = [ '_x', '_ex', 'esc_html_x', 'esc_attr_x' ];
    $plural   = [ '_n', '_nx' ];

    for ( $i = 0; $i < $count; $i++ ) {
        $tok = $tokens[ $i ];
        if ( ! is_array( $tok ) || $tok[0] !== T_STRING ) {
            continue;
        }
        $name = $tok[1];
        if ( ! in_array( $name, array_merge( $singular, $context, $plural ), true ) ) {
            continue;
        }
        // Must be followed by '('
        $j = $i + 1;
        while ( $j < $count && is_array( $tokens[ $j ] ) && $tokens[ $j ][0] === T_WHITESPACE ) {
            $j++;
        }
        if ( $j >= $count || $tokens[ $j ] !== '(' ) {
            continue;
        }
        $args = parse_args( $tokens, $j );
        if ( ! $args ) {
            continue;
        }
        $is_plural  = in_array( $name, $plural, true );
        $has_ctx    = in_array( $name, $context, true ) || $name === '_nx';

        if ( $is_plural ) {
            $sing = $args[0] ?? null;
            $plur = $args[1] ?? null;
            // _n( $single, $plural, $number, $domain )
            // _nx( $single, $plural, $number, $context, $domain )
            $msg_domain_idx = $name === '_nx' ? 4 : 3;
            $ctx            = $name === '_nx' ? ( $args[3] ?? null ) : null;
            $msg_domain     = $args[ $msg_domain_idx ] ?? null;
        } else {
            $sing       = $args[0] ?? null;
            $plur       = null;
            $ctx        = $has_ctx ? ( $args[1] ?? null ) : null;
            $domain_idx = $has_ctx ? 2 : 1;
            $msg_domain = $args[ $domain_idx ] ?? null;
        }

        if ( $sing === null || $sing['type'] !== 'string' ) {
            continue;
        }
        if ( $msg_domain && ( $msg_domain['type'] !== 'string' || $msg_domain['value'] !== $domain ) ) {
            continue;
        }

        $key = ( $ctx && $ctx['type'] === 'string' ? $ctx['value'] . "\x04" : '' ) . $sing['value'];
        if ( ! isset( $entries[ $key ] ) ) {
            $entries[ $key ] = [
                'msgid'        => $sing['value'],
                'msgid_plural' => $plur && $plur['type'] === 'string' ? $plur['value'] : null,
                'msgctxt'      => $ctx && $ctx['type'] === 'string' ? $ctx['value'] : null,
                'refs'         => [],
            ];
        }
        $entries[ $key ]['refs'][] = $file . ':' . $tok[2];
    }
}

function parse_args( array $tokens, int $open_paren_idx ) {
    $depth = 0;
    $args  = [];
    $cur   = null;
    $count = count( $tokens );
    for ( $i = $open_paren_idx; $i < $count; $i++ ) {
        $t = $tokens[ $i ];
        if ( $t === '(' ) {
            $depth++;
            if ( $depth === 1 ) {
                continue;
            }
        } elseif ( $t === ')' ) {
            $depth--;
            if ( $depth === 0 ) {
                if ( $cur !== null ) {
                    $args[] = $cur;
                }
                return $args;
            }
        } elseif ( $t === ',' && $depth === 1 ) {
            $args[] = $cur;
            $cur    = null;
            continue;
        }
        if ( $depth >= 1 ) {
            if ( is_array( $t ) ) {
                if ( $t[0] === T_WHITESPACE || $t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT ) {
                    continue;
                }
                if ( $t[0] === T_CONSTANT_ENCAPSED_STRING ) {
                    if ( $cur === null ) {
                        $cur = [ 'type' => 'string', 'value' => decode_php_string( $t[1] ) ];
                    } else {
                        $cur = [ 'type' => 'other' ];
                    }
                    continue;
                }
            }
            // Anything else means non-literal arg.
            if ( $cur === null ) {
                $cur = [ 'type' => 'other' ];
            }
        }
    }
    return $args;
}

function decode_php_string( $raw ) {
    if ( $raw === '' ) {
        return '';
    }
    $quote = $raw[0];
    $body  = substr( $raw, 1, -1 );
    if ( $quote === "'" ) {
        return strtr( $body, [ "\\'" => "'", '\\\\' => '\\' ] );
    }
    // Double-quoted: handle common escapes only.
    return strtr( $body, [
        "\\n"  => "\n",
        "\\t"  => "\t",
        "\\r"  => "\r",
        "\\\\" => "\\",
        "\\\"" => "\"",
        "\\\$" => "\$",
    ] );
}

function pot_escape( $str ) {
    return str_replace(
        [ "\\", "\"", "\n", "\t" ],
        [ "\\\\", "\\\"", "\\n\"\n\"", "\\t" ],
        $str
    );
}

function render_pot( $project, $domain, array $entries ) {
    $year = date( 'Y' );
    $out  = "# Copyright (C) {$year} DeloPay\n";
    $out .= "# This file is distributed under the MIT License.\n";
    $out .= "msgid \"\"\n";
    $out .= "msgstr \"\"\n";
    $out .= "\"Project-Id-Version: {$project}\\n\"\n";
    $out .= "\"Report-Msgid-Bugs-To: https://delopay.net\\n\"\n";
    $out .= "\"POT-Creation-Date: " . gmdate( 'Y-m-d H:i' ) . "+0000\\n\"\n";
    $out .= "\"MIME-Version: 1.0\\n\"\n";
    $out .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
    $out .= "\"Content-Transfer-Encoding: 8bit\\n\"\n";
    $out .= "\"X-Domain: {$domain}\\n\"\n\n";

    foreach ( $entries as $e ) {
        foreach ( $e['refs'] as $ref ) {
            $out .= "#: " . str_replace( $GLOBALS['root'] ?? '', '', $ref ) . "\n";
        }
        if ( $e['msgctxt'] !== null ) {
            $out .= 'msgctxt "' . pot_escape( $e['msgctxt'] ) . "\"\n";
        }
        $out .= 'msgid "' . pot_escape( $e['msgid'] ) . "\"\n";
        if ( $e['msgid_plural'] !== null ) {
            $out .= 'msgid_plural "' . pot_escape( $e['msgid_plural'] ) . "\"\n";
            $out .= "msgstr[0] \"\"\n";
            $out .= "msgstr[1] \"\"\n";
        } else {
            $out .= "msgstr \"\"\n";
        }
        $out .= "\n";
    }
    return $out;
}
