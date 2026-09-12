<?php
/**
 * Ikon Solar (dari paket ikon template Meridian/Stisla), sudah di-generate
 * jadi inline SVG supaya tidak butuh koneksi internet/CDN ikon.
 */
$GLOBALS['__icons'] = json_decode(file_get_contents(__DIR__ . '/icons.json'), true) ?: [];

function ic(string $name): string
{
    return $GLOBALS['__icons'][$name] ?? '';
}
