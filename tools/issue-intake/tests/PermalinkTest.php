<?php

declare(strict_types=1);

use Alama\Tools\IssueIntake\Permalink;
use Alama\Tools\IssueIntake\SourceLocation;

it('builds a blob URL pinned to the commit with the line fragment', function () {
    $permalink = new Permalink('Mohammed-Alama/php-arazzo', 'abc1234');

    expect($permalink->url(SourceLocation::parse('src/Foo.php:88-92')))
        ->toBe('https://github.com/Mohammed-Alama/php-arazzo/blob/abc1234/src/Foo.php#L88-L92')
        ->and($permalink->url(SourceLocation::parse('src/Foo.php:7')))
        ->toBe('https://github.com/Mohammed-Alama/php-arazzo/blob/abc1234/src/Foo.php#L7')
        ->and($permalink->shortSha())->toBe('abc1234');
});

it('resolves the owner/name slug from every github remote form', function (string $remote, ?string $expected) {
    expect(Permalink::slugFromRemote($remote))->toBe($expected);
})->with([
    'scp' => ['git@github.com:Mohammed-Alama/php-arazzo.git', 'Mohammed-Alama/php-arazzo'],
    'https .git' => ['https://github.com/Mohammed-Alama/php-arazzo.git', 'Mohammed-Alama/php-arazzo'],
    'https bare' => ['https://github.com/Mohammed-Alama/php-arazzo', 'Mohammed-Alama/php-arazzo'],
    'ssh' => ['ssh://git@github.com/Mohammed-Alama/php-arazzo.git', 'Mohammed-Alama/php-arazzo'],
    'other host' => ['git@gitlab.com:o/r.git', null],
    'empty' => ['', null],
]);

it('folds the snippet behind a <details> summary that keeps the permalink visible', function () {
    $permalink = new Permalink('Mohammed-Alama/php-arazzo', 'abc1234def');
    $details = $permalink->details(SourceLocation::parse('src/Foo.php:3-4'), "    \$x = 1;\n    return \$x;");

    expect($details)->toBe(implode("\n", [
        '<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/abc1234def/src/Foo.php#L3-L4"><code>src/Foo.php:3-4</code></a> @ <code>abc1234</code></summary>',
        '',
        '```php',
        '    $x = 1;',
        '    return $x;',
        '```',
        '',
        '</details>',
    ]));
});

it('infers the fence language from the extension, defaulting to a plain fence', function (string $path, string $expected) {
    $details = (new Permalink('o/r', 'abc1234'))->details(SourceLocation::parse($path.':1'), 'x');

    preg_match('/^```(.*)$/m', $details, $matches);

    expect($matches[1] ?? null)->toBe($expected);
})->with([
    'php' => ['src/Foo.php', 'php'],
    'yaml' => ['config/x.yml', 'yaml'],
    'json' => ['composer.json', 'json'],
    'unknown' => ['LICENSE', ''],
]);

it('rejects a repo that is not owner/name or a non-hex SHA', function (string $repo, string $sha) {
    new Permalink($repo, $sha);
})->with([
    'no slash' => ['notaslug', 'abc1234'],
    'too many slashes' => ['a/b/c', 'abc1234'],
    'non-hex sha' => ['o/r', 'zzz1234'],
    'short sha' => ['o/r', 'abc'],
])->throws(InvalidArgumentException::class);
