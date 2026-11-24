<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BAP_MMDB_Reader {
    private $fh;
    private $file_size;
    private $node_count;
    private $record_size;
    private $ip_version;
    private $node_byte_size;
    private $search_tree_size;
    private $data_section_start;

    public function __construct( $file_path ) {
        if ( ! is_readable( $file_path ) ) {
            throw new Exception( 'Archivo MMDB no legible' );
        }
        $this->file_size = filesize( $file_path );
        $this->fh        = fopen( $file_path, 'rb' );
        if ( ! $this->fh ) {
            throw new Exception( 'No se pudo abrir MMDB' );
        }

        $marker    = hex2bin( 'abcdef' ) . 'MaxMind.com';
        $read_size = min( $this->file_size, 131072 );
        fseek( $this->fh, $this->file_size - $read_size );
        $tail = fread( $this->fh, $read_size );
        $pos  = strrpos( $tail, $marker );
        if ( false === $pos ) {
            throw new Exception( 'Metadatos MMDB no encontrados' );
        }

        $meta_res                 = $this->decode( ( $this->file_size - $read_size + $pos ) + strlen( $marker ) );
        $metadata                 = reset( $meta_res );
        $this->node_count         = (int) $metadata['node_count'];
        $this->record_size        = (int) $metadata['record_size'];
        $this->ip_version         = (int) $metadata['ip_version'];
        $this->node_byte_size     = (int) ( $this->record_size / 4 );
        $this->search_tree_size   = $this->node_count * $this->node_byte_size;
        $this->data_section_start = $this->search_tree_size + 16;
    }

    public function __destruct() {
        if ( is_resource( $this->fh ) ) {
            fclose( $this->fh );
        }
    }

    public function lookup_country( $ip ) {
        $packed = @inet_pton( $ip );
        if ( false === $packed ) {
            return '';
        }
        if ( 4 === strlen( $packed ) && 6 === $this->ip_version ) {
            $packed = str_repeat( "\0", 12 ) . $packed;
        }

        $bit_count = strlen( $packed ) * 8;
        $node      = 0;

        for ( $i = 0; $i < $bit_count; $i++ ) {
            if ( $node >= $this->node_count ) {
                break;
            }
            $byte = ord( substr( $packed, $i >> 3, 1 ) );
            $bit  = 1 & ( $byte >> ( 7 - ( $i % 8 ) ) );
            $node = $this->read_node( $node, $bit );
        }

        if ( $node > $this->node_count ) {
            $offset = ( $node - $this->node_count ) - 16 + $this->data_section_start;
            $res    = $this->decode( $offset );
            $record = reset( $res );
            return $record['country']['iso_code'] ?? ( $record['registered_country']['iso_code'] ?? '' );
        }
        return '';
    }

    public function lookup_asn( $ip ) {
        $packed = @inet_pton( $ip );
        if ( false === $packed ) {
            return '';
        }
        if ( 4 === strlen( $packed ) && 6 === $this->ip_version ) {
            $packed = str_repeat( "\0", 12 ) . $packed;
        }

        $bit_count = strlen( $packed ) * 8;
        $node      = 0;

        for ( $i = 0; $i < $bit_count; $i++ ) {
            if ( $node >= $this->node_count ) {
                break;
            }
            $byte = ord( substr( $packed, $i >> 3, 1 ) );
            $bit  = 1 & ( $byte >> ( 7 - ( $i % 8 ) ) );
            $node = $this->read_node( $node, $bit );
        }

        if ( $node > $this->node_count ) {
            $offset = ( $node - $this->node_count ) - 16 + $this->data_section_start;
            $res    = $this->decode( $offset );
            $record = reset( $res );
            $num    = $record['autonomous_system_number'] ?? '';
            $org    = $record['autonomous_system_organization'] ?? '';
            if ( '' !== $num ) {
                return 'AS' . $num . ( '' !== $org ? ' — ' . $org : '' );
            }
        }
        return '';
    }

    private function read_node( $node_number, $index ) {
        fseek( $this->fh, $node_number * $this->node_byte_size );
        $bytes = fread( $this->fh, $this->node_byte_size );

        if ( 24 === $this->record_size ) {
            $sub = ( 0 === $index ) ? substr( $bytes, 0, 3 ) : substr( $bytes, 3, 3 );
            $unp = unpack( 'N', "\0" . $sub );
            return (int) reset( $unp );
        }
        if ( 28 === $this->record_size ) {
            $mid = ord( substr( $bytes, 3, 1 ) );
            if ( 0 === $index ) {
                $sub = chr( ( $mid & 0xF0 ) >> 4 ) . substr( $bytes, 0, 3 );
            } else {
                $sub = chr( $mid & 0x0F ) . substr( $bytes, 4, 3 );
            }
            $unp = unpack( 'N', $sub );
            return (int) reset( $unp );
        }
        if ( 32 === $this->record_size ) {
            $sub = ( 0 === $index ) ? substr( $bytes, 0, 4 ) : substr( $bytes, 4, 4 );
            $unp = unpack( 'N', $sub );
            return (int) reset( $unp );
        }
        return 0;
    }

    private function decode( $offset ) {
        fseek( $this->fh, $offset );
        $ctrl = ord( fread( $this->fh, 1 ) );
        $type = $ctrl >> 5;

        if ( 1 === $type ) {
            $size     = ( ( $ctrl >> 3 ) & 0x3 ) + 1;
            $val_bits = $ctrl & 0x7;
            $extra    = ( $size < 4 ) ? fread( $this->fh, $size ) : fread( $this->fh, 3 );
            $next_pos = ftell( $this->fh );
            $packed   = str_pad( ( $size < 4 ) ? chr( $val_bits ) . $extra : $extra, 4, "\0", STR_PAD_LEFT );
            $unp      = unpack( 'N', $packed );
            $ptr      = (int) reset( $unp );
            $offsets  = [ 1 => 0, 2 => 2048, 3 => 526336, 4 => 0 ];
            $res      = $this->decode( $ptr + $offsets[ $size ] + $this->data_section_start );
            return [ reset( $res ), $next_pos ];
        }

        if ( 0 === $type ) {
            $type = 7 + ord( fread( $this->fh, 1 ) );
        }

        $size = $ctrl & 0x1F;
        if ( $size >= 29 ) {
            $raw = fread( $this->fh, $size - 28 );
            if ( 29 === $size ) {
                $size = 29 + ord( $raw );
            } elseif ( 30 === $size ) {
                $unp  = unpack( 'n', $raw );
                $size = 285 + (int) reset( $unp );
            } elseif ( 31 === $size ) {
                $unp  = unpack( 'N', "\0" . $raw );
                $size = 65821 + (int) reset( $unp );
            }
        }

        if ( 2 === $type || 4 === $type ) {
            return [ ( $size > 0 ) ? fread( $this->fh, $size ) : '', ftell( $this->fh ) ];
        }
        if ( 5 === $type || 6 === $type || 8 === $type ) {
            $raw = ( $size > 0 ) ? fread( $this->fh, $size ) : '';
            $unp = unpack( 'N', str_pad( $raw, 4, "\0", STR_PAD_LEFT ) );
            return [ (int) reset( $unp ), ftell( $this->fh ) ];
        }
        if ( 7 === $type ) {
            $map = [];
            $pos = ftell( $this->fh );
            for ( $i = 0; $i < $size; $i++ ) {
                $k_res                 = $this->decode( $pos );
                $v_res                 = $this->decode( end( $k_res ) );
                $map[ reset( $k_res ) ] = reset( $v_res );
                $pos                   = end( $v_res );
            }
            return [ $map, $pos ];
        }
        if ( 11 === $type ) {
            $arr = [];
            $pos = ftell( $this->fh );
            for ( $i = 0; $i < $size; $i++ ) {
                $v_res = $this->decode( $pos );
                $arr[] = reset( $v_res );
                $pos   = end( $v_res );
            }
            return [ $arr, $pos ];
        }
        if ( 14 === $type ) {
            return [ (bool) $size, ftell( $this->fh ) ];
        }
        if ( 3 === $type ) {
            $unp = unpack( 'E', fread( $this->fh, 8 ) );
            return [ reset( $unp ), ftell( $this->fh ) ];
        }
        if ( 15 === $type ) {
            $unp = unpack( 'G', fread( $this->fh, 4 ) );
            return [ reset( $unp ), ftell( $this->fh ) ];
        }
        $raw = ( $size > 0 ) ? fread( $this->fh, $size ) : '';
        return [ bin2hex( $raw ), ftell( $this->fh ) ];
    }
}
