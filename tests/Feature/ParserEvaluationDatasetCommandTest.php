<?php

namespace Tests\Feature;

use Tests\TestCase;

class ParserEvaluationDatasetCommandTest extends TestCase
{
    public function test_dataset_validator_command_succeeds_for_the_committed_dataset(): void
    {
        $this->artisan('parser:validate-evaluation-dataset --json')
            ->expectsOutputToContain('"valid": true')
            ->assertExitCode(0);
    }
}
