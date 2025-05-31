<?php

require 'vendor/autoload.php';

#
# Pagenode
# http://pagenode.org
#
# (c) Dominic Szablewski
# https://phoboslab.org
#

use Pagenode\Node;
use Pagenode\Router;
use Pagenode\Selector;

const PN_VERSION = '0.1';

if (!defined('PN_DATE_FORMAT')) {
    define('PN_DATE_FORMAT', 'M d, Y - H:i:s');
}

if (!defined('PN_SYNTAX_HIGHLIGHT_LANGS')) {
    define('PN_SYNTAX_HIGHLIGHT_LANGS', 'php|js|sql|c');
}

if (!defined('PN_CACHE_INDEX_PATH')) {
    define('PN_CACHE_INDEX_PATH', null);
}

if (!defined('PN_CACHE_USE_INDICATOR_FILE')) {
    define('PN_CACHE_USE_INDICATOR_FILE', false);
}

if (!defined('PN_CACHE_INDICATOR_FILE')) {
    define('PN_CACHE_INDICATOR_FILE', '.git/FETCH_HEAD');
}

if (!defined('PN_JSON_API_FULL_DEBUG_INFO')) {
    define('PN_JSON_API_FULL_DEBUG_INFO', false);
}

if (defined('PN_TIMEZONE')) {
    date_default_timezone_set(PN_TIMEZONE);
} elseif (!date_default_timezone_get()) {
    date_default_timezone_set('UTC');
}

$PN_TimeStart = microtime(true);

header('Content-type: text/html; charset=UTF-8');
define('PN_ABS', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/');

// -----------------------------------------------------------------------------
// -----------------------------------------------------------------------------
// PAGENODE Public API

function select($path = '')
{
    return new Selector($path);
}

function foundNodes()
{
    return Selector::FoundNodes();
}

function route($path, $resolver = null)
{
    Router::AddRoute($path, $resolver);
}

function reroute($source, $target)
{
    route($source, function () use ($target) {
        $args = func_get_args();
        $target = preg_replace_callback(
            '/{(\w+)}/',
            function ($m) use ($args) {
                return $args[$m[1] - 1] ?? '';
            },
            $target
        );
        dispatch($target);
    });
}

function redirect($path = '/', $params = [])
{
    $query = !empty($params)
        ? '?' . http_build_query($params)
        : '';
    header('Location: ' . $path . $query);
    exit();
}

function dispatch($request = null)
{
    if ($request === null) {
        $request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $request = '/' . substr($request, strlen(PN_ABS));
    }

    $found = Router::Dispatch($request);
}

function getDebugInfo()
{
    global $PN_TimeStart;
    return [
        'totalRuntime' => (microtime(true) - $PN_TimeStart) * 1000,
        'selctorInfo' => Selector::$DebugInfo,
        'openedNodes' => Node::$DebugOpenedNodes
    ];
}

function printDebugInfo()
{
    echo "<pre>\n" . htmlSpecialChars(print_r(getDebugInfo(), true)) . "</pre>";
}

// -----------------------------------------------------------------------------
// PAGENODE JSON Route, disabled by default

if (defined('PN_JSON_API_PATH')) {
    route(PN_JSON_API_PATH, function () {
        $nodes = select($_GET['path'] ?? '')->query(
            $_GET['sort'] ?? 'date',
            $_GET['order'] ?? 'desc',
            $_GET['count'] ?? 0,
            [
                'keyword' => $_GET['keyword'] ?? null,
                'date' => $_GET['date'] ?? null,
                'tags' => $_GET['tags'] ?? null,
                'meta' => $_GET['meta'] ?? null,
                'page' => $_GET['page'] ?? null
            ],
            true
        );

        $fields = !empty($_GET['fields'])
            ? array_map('trim', explode(',', $_GET['fields']))
            : ['keyword'];

        header('Content-type: application/json; charset=UTF-8');
        echo json_encode([
            'nodes' => array_map(function ($n) use ($fields) {
                $ret = [];
                foreach ($fields as $f) {
                    $ret[$f] = $n->$f;
                }
                return $ret;
            }, $nodes),
            'info' => PN_JSON_API_FULL_DEBUG_INFO
                ? getDebugInfo()
                : ['totalRuntime' => getDebugInfo()['totalRuntime']]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    });
}
