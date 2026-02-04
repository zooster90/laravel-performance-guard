<?php

declare(strict_types=1);

it('runs the install command successfully', function () {
    $this->artisan('performance-guard:install')
        ->assertExitCode(0)
        ->expectsOutputToContain('Performance Guard installed successfully');
});
