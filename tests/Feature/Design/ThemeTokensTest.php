<?php

/**
 * UI-01 / UI-02 / UI-05 / UI-06 / UI-09 / UI-11 / UI-13: the design system is
 * declared once in the Tailwind 4 `@theme` block of `resources/css/app.css`
 * and the component layer consumes those tokens. These are file-reading
 * compliance gates (same approach as `tests/Feature/Compliance`), since the
 * requirement is the presence/absence of declarations in a stylesheet.
 */
function themeStylesheet(): string
{
    return file_get_contents(resource_path('css/app.css'));
}

function themeBlock(): string
{
    preg_match('/@theme\s*\{(.*?)\n\}/s', themeStylesheet(), $matches);

    return $matches[1] ?? '';
}

/**
 * @return array<string, string> token name => hex value (uppercase)
 */
function themeColorTokens(): array
{
    preg_match_all('/--color-([a-z0-9-]+)\s*:\s*(#[0-9a-fA-F]{6})\s*;/', themeBlock(), $matches, PREG_SET_ORDER);

    $tokens = [];

    foreach ($matches as $match) {
        $tokens[$match[1]] = strtoupper($match[2]);
    }

    return $tokens;
}

function componentRule(string $selector): string
{
    $pattern = '/'.preg_quote($selector, '/').'\s*\{(.*?)\}/s';

    preg_match($pattern, themeStylesheet(), $matches);

    return $matches[1] ?? '';
}

test('@theme keeps the font-sans token', function () {
    expect(themeBlock())->toContain('--font-sans:');
});

test('@theme declares the ten named design tokens', function () {
    $tokens = themeColorTokens();

    foreach ([
        'primary',
        'primary-hover',
        'primary-active',
        'secondary',
        'background',
        'surface',
        'border',
        'text',
        'text-muted',
        'focus',
    ] as $token) {
        expect($tokens)->toHaveKey($token);
    }
});

test('@theme declares the six semantic state tokens with soft tints', function () {
    $tokens = themeColorTokens();

    foreach (['success', 'warning', 'error', 'info', 'atraso', 'concluido'] as $token) {
        expect($tokens)->toHaveKey($token);
        expect($tokens)->toHaveKey("{$token}-soft");
    }
});

test('the nine §16 hex values are bound to the correct tokens', function () {
    $tokens = themeColorTokens();

    expect($tokens['primary'])->toBe('#9E0128')
        ->and($tokens['primary-hover'])->toBe('#802036')
        ->and($tokens['primary-active'])->toBe('#661F35')
        ->and($tokens['secondary'])->toBe('#520C1F')
        ->and($tokens['surface'])->toBe('#FFFFFF')
        ->and($tokens['background'])->toBe('#F7F7F8')
        ->and($tokens['border'])->toBe('#E5E7EB')
        ->and($tokens['text'])->toBe('#202124')
        ->and($tokens['text-muted'])->toBe('#6B7280')
        ->and($tokens['focus'])->toBe('#9E0128');
});

test('primary hover and active resolve to wine tones, never blue', function () {
    $tokens = themeColorTokens();

    foreach (['primary-hover', 'primary-active'] as $token) {
        expect($tokens[$token])->toBeIn(['#802036', '#661F35', '#520C1F']);

        [$red, $green, $blue] = sscanf($tokens[$token], '#%02x%02x%02x');

        expect($red)->toBeGreaterThan($blue)
            ->and($red)->toBeGreaterThan($green);
    }
});

test('semantic tokens are distinct per state so no two statuses share a color', function () {
    $tokens = themeColorTokens();

    $states = array_intersect_key($tokens, array_flip(['success', 'warning', 'error', 'info', 'atraso', 'concluido']));

    expect(array_unique($states))->toHaveCount(6);
});

test('info token is a blue that is not from the sky palette', function () {
    $tokens = themeColorTokens();

    [$red, $green, $blue] = sscanf($tokens['info'], '#%02x%02x%02x');

    expect($blue)->toBeGreaterThan($red)
        ->and($blue)->toBeGreaterThan($green)
        ->and(themeStylesheet())->not->toContain('sky-');
});

test('the stylesheet contains no sky palette, gradients, heavy shadows or animations', function () {
    $css = themeStylesheet();

    expect($css)->not->toContain('sky-')
        ->not->toContain('bg-gradient')
        ->not->toContain('shadow-xl')
        ->not->toContain('shadow-2xl')
        ->not->toContain('animate-');
});

test('.btn-primary is built on the primary token with wine hover and active states', function () {
    $rule = componentRule('.btn-primary');

    expect($rule)->toContain('bg-primary')
        ->toContain('hover:bg-primary-hover')
        ->toContain('active:bg-primary-active')
        ->toContain('ring-focus')
        ->not->toContain('rounded-full');
});

test('.btn-secondary is a white surface with neutral border and wine text', function () {
    $rule = componentRule('.btn-secondary');

    expect($rule)->toContain('bg-surface')
        ->toContain('border-border')
        ->toContain('text-secondary')
        ->not->toContain('rounded-full');
});

test('.form-control focuses with the focus token and has a visible disabled state', function () {
    $rule = componentRule('.form-control');

    expect($rule)->toContain('focus:ring-focus')
        ->toContain('rounded-md')
        ->toContain('disabled:bg-background');

    expect(componentRule('.form-error'))->toContain('text-error');
});

test('.card and .data-table consume surface, border and background tokens', function () {
    expect(componentRule('.card'))
        ->toContain('bg-surface')
        ->toContain('border-border')
        ->toContain('rounded-lg')
        ->toContain('shadow-sm');

    expect(componentRule('.data-table thead th'))
        ->toContain('bg-background')
        ->toContain('border-border');
});

test('.badge exposes one variant per semantic state', function () {
    expect(componentRule('.badge'))->not->toBeEmpty();

    foreach (['neutral', 'info', 'success', 'warning', 'error', 'secondary', 'atraso', 'concluido'] as $variant) {
        expect(componentRule(".badge-{$variant}"))->not->toBeEmpty("missing .badge-{$variant}");
    }
});

test('.pedido-atrasado rule still exists with an overdue treatment', function () {
    expect(componentRule('.pedido-atrasado'))->toContain('atraso');
});

test('navigation, alert and empty-state classes are defined on tokens', function () {
    expect(componentRule('.nav-link'))->not->toBeEmpty();
    expect(componentRule('.nav-link-active'))->toContain('primary');

    foreach (['success', 'error', 'info'] as $state) {
        expect(componentRule(".alert-{$state}"))->toContain($state);
    }

    expect(componentRule('.empty-state'))->toContain('text-text-muted');
});
