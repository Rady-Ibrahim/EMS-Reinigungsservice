<?php

/**
 * Validates the exported Postman collection:
 *  1. JSON is well-formed.
 *  2. Required v2.1.0 fields and the schema URL are present.
 *  3. Every folder/request has a name, method, and a URL.
 *  4. Every raw JSON body actually parses.
 *  5. Every collection variable referenced in a URL exists.
 *  6. Every concrete path resolves against the real Laravel route table.
 */

$root = dirname(__DIR__);
$file = $root.'/EMS_Reinigungsservice_Mobile_API.postman_collection.json';

$errors   = [];
$warnings = [];
$stats    = ['requests' => 0, 'bodies' => 0, 'neg' => 0, 'tests' => 0];

if (! is_file($file)) {
    fwrite(STDERR, "FAIL: collection not found at {$file}\n");
    exit(1);
}

$raw = file_get_contents($file);
$json = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    fwrite(STDERR, 'FAIL: invalid JSON — '.json_last_error_msg()."\n");
    exit(1);
}

echo "PASS  JSON is well-formed (".strlen($raw)." bytes)\n";

// ── Schema / structure ────────────────────────────────────────────────────
$required = ['info' => 'array', 'item' => 'array'];

foreach ($required as $key => $type) {
    if (! isset($json[$key]) || ! is_array($json[$key])) {
        $errors[] = "Missing top-level \"{$key}\"";
    }
}

$schema = $json['info']['schema'] ?? '';

if ($schema !== 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json') {
    $errors[] = "info.schema is not the v2.1.0 URL, got: {$schema}";
} else {
    echo "PASS  info.schema points at Postman v2.1.0\n";
}

foreach (['_postman_id', 'name'] as $key) {
    if (empty($json['info'][$key])) {
        $errors[] = "info.{$key} is missing";
    }
}

if (! empty($json['info']['_postman_id']) && ! preg_match('/^[0-9a-f-]{36}$/i', $json['info']['_postman_id'])) {
    $errors[] = 'info._postman_id is not a UUID';
}

// ── Collect declared variables ────────────────────────────────────────────
$variables = [];

foreach ($json['variable'] ?? [] as $v) {
    if (empty($v['key'])) {
        $errors[] = 'A collection variable has no key';
        continue;
    }

    $variables[$v['key']] = $v['value'] ?? null;
}

foreach (['base_url', 'token'] as $requiredVar) {
    if (! array_key_exists($requiredVar, $variables)) {
        $errors[] = "Required collection variable \"{$requiredVar}\" is missing";
    }
}

if (($variables['base_url'] ?? null) !== 'http://localhost:8000') {
    $errors[] = 'base_url should default to http://localhost:8000';
}

if (($variables['token'] ?? null) !== '{{sanctum_bearer_token}}') {
    $errors[] = 'token should default to {{sanctum_bearer_token}}';
}

echo 'PASS  collection variables: '.implode(', ', array_keys($variables))."\n";

// ── Walk the tree ─────────────────────────────────────────────────────────
$referencedVars = [];
$paths           = [];

$walk = function (array $nodes, string $trail) use (&$walk, &$errors, &$stats, &$referencedVars, &$paths, $variables) {
    foreach ($nodes as $node) {
        $name = $node['name'] ?? '(unnamed)';
        $path = $trail === '' ? $name : $trail.' / '.$name;

        if (! isset($node['request'])) {
            // A folder.
            if (! empty($node['item'])) {
                $walk($node['item'], $path);
                continue;
            }

            $errors[] = "{$path}: has neither a request nor child items";
            continue;
        }

        $stats['requests']++;

        if (str_starts_with($name, '[NEG]')) {
            $stats['neg']++;
        }

        $request = $node['request'];
        $method  = strtoupper($request['method'] ?? '');

        if (! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], true)) {
            $errors[] = "{$path}: invalid or missing method \"{$method}\"";
        }

        $url = $request['url'] ?? null;

        if (is_array($url)) {
            $url = $url['raw'] ?? null;
        }

        if (! is_string($url) || $url === '') {
            $errors[] = "{$path}: missing URL";
            continue;
        }

        // Variables used in the URL.
        preg_match_all('/\{\{([a-zA-Z0-9_]+)\}\}/', $url, $m);

        foreach ($m[1] as $v) {
            $referencedVars[$v] = true;

            if (! array_key_exists($v, $variables)) {
                $errors[] = "{$path}: uses undeclared variable {{{$v}}}";
            }
        }

        $paths[] = ['name' => $name, 'path' => $path, 'method' => $method, 'url' => $url];

        // Raw JSON body must parse.
        $body = $request['body'] ?? null;

        if (is_array($body) && ($body['mode'] ?? null) === 'raw') {
            $stats['bodies']++;

            $bodyRaw = $body['raw'] ?? '';
            // Substitute variables so `{{location_id}}` inside a number parses.
            $substituted = preg_replace('/\{\{[a-zA-Z0-9_]+\}\}/', '1', $bodyRaw);

            json_decode($substituted, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = "{$path}: raw body is not valid JSON — ".json_last_error_msg();
            }

            if (($body['options']['raw']['language'] ?? null) !== 'json') {
                $errors[] = "{$path}: raw body is not marked as JSON";
            }
        }

        // Test scripts.
        foreach ($node['event'] ?? [] as $event) {
            if (($event['listen'] ?? '') === 'test') {
                $stats['tests']++;
            }
        }
    }
};

$walk($json['item'], '');

echo sprintf(
    "PASS  %d requests across %d folders (%d with raw JSON bodies, %d negative paths, %d test scripts)\n",
    $stats['requests'],
    count($json['item']),
    $stats['bodies'],
    $stats['neg'],
    $stats['tests']
);

// Collection-level auto-login script.
$prereq = $json['event'][0]['script']['exec'] ?? [];

if (! in_array('prerequest', array_column($json['event'], 'listen'), true)) {
    $warnings[] = 'No collection-level prerequest script, so the token must be pasted by hand';
} else {
    echo "PASS  collection-level auto-login script is present\n";
}

// ── Route-table check ─────────────────────────────────────────────────────
require $root.'/vendor/autoload.php';

$app    = require $root.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$registered = [];

// Route uris already carry the `api/` prefix (`api/v1/login`), so they are
// compared against the client path with a normalised leading slash.
foreach (app('router')->getRoutes() as $route) {
    $uri = $route->uri();

    if (! str_starts_with($uri, 'api/v1')) {
        continue;
    }

    // Collapse `{param}` segments to a single marker so a collection path
    // with `{{order_id}}` can be compared against a route with `{extraAuftrag}`.
    $normalised = preg_replace('#\{[^}]+\}#', ':param', '/'.ltrim($uri, '/'));
    $normalised = preg_replace('#/:param#', ':param', $normalised);

    // The negative paths use a literal id (`999999`) that must still match the
    // parameterised route — it resolves to a 404 at runtime, not a 404 route.
    $normalised = preg_replace('#/\d+#', ':param', $normalised);

    foreach ($route->methods() as $m) {
        if (in_array($m, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $registered[$m.' '.$normalised] = true;
        }
    }
}

echo 'PASS  resolved the live route table ('.count($registered)." api/v1 method+path combinations)\n";

$unmatched = [];

foreach ($paths as $entry) {
    $url = $entry['url'];

    if (! str_contains($url, '{{base_url}}')) {
        $warnings[] = "{$entry['path']}: URL is not built from {{base_url}}";
        continue;
    }

    $rest  = substr($url, strlen('{{base_url}}'));
    $parts = explode('?', $rest, 2);
    $path  = $parts[0];

    // Every {{var}} segment is a route parameter; collapse it the same way
    // the route table is normalised above.
    $concrete = preg_replace('#\{\{[a-zA-Z0-9_]+\}\}#', ':param', $path);
    $concrete = preg_replace('#/:param#', ':param', '/'.ltrim($concrete, '/'));
    $concrete = preg_replace('#/\d+#', ':param', $concrete);

    if (! isset($registered[$entry['method'].' '.$concrete])) {
        $unmatched[] = "{$entry['method']} {$concrete}   (from: {$entry['path']})";
    }
}

if ($unmatched !== []) {
    $warnings[] = "These paths do not exist in the route table:\n      ".implode("\n      ", $unmatched);
}

// ── Report ────────────────────────────────────────────────────────────────
foreach ($warnings as $w) {
    echo "\nWARN  {$w}\n";
}

echo "\n";

if ($errors !== []) {
    foreach ($errors as $e) {
        echo "FAIL  {$e}\n";
    }

    echo "\n".count($errors)." error(s).\n";
    exit(1);
}

echo 'OK    the collection is structurally valid'.($warnings === [] ? '.' : " (with warnings above).\n");

exit(0);
