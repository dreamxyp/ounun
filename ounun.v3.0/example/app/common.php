<?php

/**
 * 静态地址
 * @param array|string $url
 * @param string       $pre_str
 * @return string
 */
function surl($url, string $pre_str = ""): string
{
    return \ounun\scfg::surl($url, \ounun\scfg::$url_static.$pre_str);
}

/**
 * 静态地址(G)
 * @param string|array $url
 * @param string       $pre_str
 * @return string
 */
function gurl($url, string $pre_str = ""): string
{
    return \ounun\scfg::surl($url,  \ounun\scfg::$url_static_g.$pre_str);
}
