import { execFileSync, spawnSync } from 'node:child_process';
import { promises as fs } from 'node:fs';
import path from 'node:path';
import os from 'node:os';

const cwd = process.cwd();
const artifactRoot = path.resolve(cwd, 'artifacts', 'pilot');
const latestDir = path.resolve(artifactRoot, 'latest');
const runsDir = path.resolve(artifactRoot, 'runs');
const testResultsDir = path.resolve(cwd, 'test-results');
const playwrightReportDir = path.resolve(cwd, 'playwright-report');
const defaultDatabasePath = path.resolve(cwd, 'database', 'pilot.sqlite');
const port = String(process.env.PILOT_APP_PORT ?? '8010');
const applicationUrl = process.env.PILOT_APP_URL ?? `http://127.0.0.1:${port}`;

const mode = process.argv[2] ?? 'full';

if (!['clean', 'prepare', 'backend', 'build', 'e2e', 'report', 'full'].includes(mode)) {
    console.error(`Unknown pilot mode: ${mode}`);
    process.exit(1);
}

await main();

async function main() {
    switch (mode) {
        case 'clean':
            await runClean();
            return;
        case 'prepare':
            await runSinglePhase('prepare', 'php artisan pilot:prepare');
            return;
        case 'backend':
            await runSinglePhase('backend', 'php artisan test --filter=HardwareStorePilotTest');
            return;
        case 'build':
            await runSinglePhase('build', 'npm run build');
            return;
        case 'e2e':
            await runSinglePhase('e2e', 'npx playwright test tests/e2e/operations-pilot.spec.js');
            return;
        case 'report':
            await runReportFromLatest();
            return;
        default:
            await runFull();
    }
}

async function runFull() {
    const startedAt = new Date();
    await runClean();

    const runId = formatTimestamp(startedAt);
    const runDir = path.resolve(runsDir, runId);
    await fs.mkdir(runDir, { recursive: true });
    await fs.mkdir(latestDir, { recursive: true });

    const environment = await collectEnvironment();
    const phaseResults = [];

    phaseResults.push(await executePhase('prepare', 'php artisan pilot:prepare', runDir));
    phaseResults.push(await executePhase('backend', 'php artisan test --filter=HardwareStorePilotTest', runDir));
    phaseResults.push(await executePhase('build', 'npm run build', runDir));
    phaseResults.push(await executePhase('e2e', 'npx playwright test tests/e2e/operations-pilot.spec.js', runDir));

    const aggregated = await buildAggregatedPayload({
        startedAt,
        phaseResults,
        environment,
        runDir,
    });

    const runJsonPath = path.resolve(runDir, 'run.json');
    await fs.writeFile(runJsonPath, JSON.stringify(aggregated, null, 2) + os.EOL);

    const reportPhase = await executePhase('report', `php artisan pilot:report --input=${runJsonPath} --output-dir=${runDir}`, runDir);
    phaseResults.push(reportPhase);

    await copyRunToLatest(runDir, latestDir);
    await printSummary(path.resolve(latestDir, 'report.json'));

    process.exitCode = aggregated.status === 'not_ready' || reportPhase.status !== 'passed' ? 1 : 0;
}

async function runClean() {
    await fs.rm(latestDir, { recursive: true, force: true });
    await fs.rm(testResultsDir, { recursive: true, force: true });
    await fs.rm(playwrightReportDir, { recursive: true, force: true });
    await fs.mkdir(artifactRoot, { recursive: true });
    await fs.mkdir(runsDir, { recursive: true });
}

async function runSinglePhase(name, args) {
    const runDir = latestDir;
    await fs.mkdir(runDir, { recursive: true });
    const phase = await executePhase(name, args, runDir);

    if (name === 'e2e' && !(await exists(path.resolve(runDir, 'e2e-summary.json')))) {
        await fs.writeFile(
            path.resolve(runDir, 'e2e-summary.json'),
            JSON.stringify({
                browser: 'chromium',
                warnings: [],
                failures: phase.status === 'failed' ? [`${name} failed.`] : [],
                screenshots: {},
                metrics: {},
            }, null, 2) + os.EOL,
        );
    }
}

async function runReportFromLatest() {
    const inputPath = path.resolve(latestDir, 'run.json');
    if (!(await exists(inputPath))) {
        throw new Error(`Missing pilot run JSON at ${inputPath}`);
    }

    await executePhase('report', `php artisan pilot:report --input=${inputPath} --output-dir=${latestDir}`, latestDir);
    await printSummary(path.resolve(latestDir, 'report.json'));
}

async function executePhase(name, command, runDir) {
    const startedAt = Date.now();
    const env = {
        ...process.env,
        APP_URL: applicationUrl,
        PILOT_ENABLED: process.env.PILOT_ENABLED ?? 'true',
        PILOT_APP_PORT: port,
        PILOT_APP_URL: applicationUrl,
        PILOT_SCENARIO: process.env.PILOT_SCENARIO ?? 'small-hardware-store',
        PILOT_ARTIFACT_ROOT: artifactRoot,
        PILOT_ARTIFACT_LATEST: latestDir,
        PILOT_ARTIFACT_RUNS: runsDir,
        PILOT_DB_PATH: process.env.PILOT_DB_PATH ?? defaultDatabasePath,
        PILOT_ARTIFACT_DIR: runDir,
        PLAYWRIGHT_BASE_URL: applicationUrl,
    };

    const result = spawnSync(command, {
        cwd,
        shell: true,
        env,
        encoding: 'utf8',
        maxBuffer: 1024 * 1024 * 20,
    });

    const phase = {
        name,
        status: result.status === 0 ? 'passed' : 'failed',
        duration_seconds: (Date.now() - startedAt) / 1000,
        command,
        exit_code: result.status,
        stdout: result.stdout ?? '',
        stderr: result.stderr ?? '',
        warnings: [],
        failures: [],
        artifacts: {},
    };

    if (result.error) {
        phase.stderr = `${phase.stderr}\n${result.error.message}`.trim();
        phase.failures.push(result.error.message);
    }

    if (phase.status === 'failed') {
        phase.failures.push(`${name} failed with exit code ${result.status ?? 1}.`);
    }

    await writePhaseLog(runDir, name, phase.stdout, phase.stderr);
    return phase;
}

async function buildAggregatedPayload({ startedAt, phaseResults, environment, runDir }) {
    const backendPhase = phaseResults.find((phase) => phase.name === 'backend');
    const buildPhase = phaseResults.find((phase) => phase.name === 'build');
    const e2ePhase = phaseResults.find((phase) => phase.name === 'e2e');
    const e2eSummaryPath = path.resolve(runDir, 'e2e-summary.json');
    const e2eSummary = (await exists(e2eSummaryPath)) ? JSON.parse(await fs.readFile(e2eSummaryPath, 'utf8')) : {};
    const backendMetrics = parseBackendMetrics(backendPhase?.stdout ?? '');
    const buildMetrics = await parseBuildMetrics(buildPhase?.stdout ?? '');

    const metrics = {
        ...backendMetrics,
        ...buildMetrics,
        ...(e2eSummary.metrics ?? {}),
        browser: e2eSummary.browser ?? 'chromium',
        console_error_count: (e2eSummary.console_errors ?? []).length,
        http_500_count: (e2eSummary.http_500_responses ?? []).length,
        failed_request_count: (e2eSummary.failed_requests ?? []).length,
        backend_status_dispatched: Boolean(e2eSummary.backend_status_dispatched),
        workflow_completed: Boolean(e2eSummary.workflow_completed),
        completed_ui_inconsistent: Boolean(e2eSummary.metrics?.completed_ui_inconsistent),
    };

    const warnings = [
        ...(e2eSummary.warnings ?? []),
    ];

    const failures = [
        ...(backendPhase?.status === 'failed' ? ['Backend pilot tests failed.'] : []),
        ...(buildPhase?.status === 'failed' ? ['Vite build failed.'] : []),
        ...(e2ePhase?.status === 'failed' ? ['Browser E2E failed.'] : []),
        ...(e2eSummary.failures ?? []),
    ];

    return {
        status: failures.length > 0 ? 'not_ready' : (warnings.length > 0 ? 'warning' : 'ready'),
        started_at: startedAt.toISOString(),
        finished_at: new Date().toISOString(),
        duration_seconds: (Date.now() - startedAt.getTime()) / 1000,
        phases: phaseResults,
        metrics,
        warnings,
        failures,
        artifacts: {
            screenshots: e2eSummary.screenshots ?? {},
            run_dir: runDir,
            playwright_report: path.resolve(cwd, 'playwright-report', 'index.html'),
        },
        environment,
    };
}

async function collectEnvironment() {
    const phpVersion = execFileSync('php', ['-r', 'echo PHP_VERSION;'], { cwd, encoding: 'utf8' }).trim();
    const laravelVersion = execFileSync('php', ['artisan', '--version'], { cwd, encoding: 'utf8' }).trim();

    return {
        php_version: phpVersion,
        laravel_version: laravelVersion,
        node_version: process.version,
        database_driver: process.env.DB_CONNECTION ?? 'sqlite',
        pilot_database_path: process.env.PILOT_DB_PATH ?? defaultDatabasePath,
        application_url: applicationUrl,
        timestamp: new Date().toISOString(),
    };
}

function parseBackendMetrics(stdout) {
    const summary = stdout.match(/Tests:\s*(\d+),\s*Assertions:\s*(\d+),\s*Failures:\s*(\d+),\s*Errors:\s*(\d+)/i);

    return {
        backend_tests_passed: summary ? Number(summary[1]) : null,
        backend_assertions: summary ? Number(summary[2]) : null,
        backend_failures: summary ? Number(summary[3]) + Number(summary[4]) : null,
    };
}

async function parseBuildMetrics(stdout) {
    const warnings = Array.from(stdout.matchAll(/warning[:\s].*$/gim)).map((match) => match[0].trim());

    return {
        build_warnings: warnings,
        vite_manifest_exists: await exists(path.resolve(cwd, 'public', 'build', 'manifest.json')),
    };
}

async function writePhaseLog(dir, name, stdout, stderr) {
    await fs.mkdir(dir, { recursive: true });
    await fs.writeFile(path.resolve(dir, `${name}.stdout.log`), stdout ?? '');
    await fs.writeFile(path.resolve(dir, `${name}.stderr.log`), stderr ?? '');
}

async function copyRunToLatest(runDir, targetDir) {
    await fs.rm(targetDir, { recursive: true, force: true });
    await fs.mkdir(path.dirname(targetDir), { recursive: true });
    await fs.cp(runDir, targetDir, { recursive: true });
}

async function printSummary(reportJsonPath) {
    if (!(await exists(reportJsonPath))) {
        return;
    }

    const report = JSON.parse(await fs.readFile(reportJsonPath, 'utf8'));
    const metrics = report.metrics ?? {};

    console.log('========================================');
    console.log('BENDITIO PILOT READINESS');
    console.log('========================================');
    console.log(`Backend tests ........ ${phaseLabel(report.phases, 'backend')}`);
    console.log(`Build ................ ${phaseLabel(report.phases, 'build')}`);
    console.log(`Browser E2E .......... ${phaseLabel(report.phases, 'e2e')}`);
    console.log(`Console errors ....... ${metrics.console_error_count ?? 0}`);
    console.log(`HTTP 500 responses ... ${metrics.http_500_count ?? 0}`);
    console.log(`Business flow ........ ${metrics.workflow_completed ? 'PASS' : 'FAIL'}`);
    console.log(`Completed UI ......... ${metrics.completed_ui_inconsistent ? 'WARNING' : 'PASS'}`);
    console.log('');
    console.log(`OVERALL .............. ${{
        ready: 'READY',
        warning: 'READY WITH WARNINGS',
        not_ready: 'NOT READY',
    }[report.status ?? 'not_ready'] ?? 'NOT READY'}`);
    console.log('');
    console.log('HTML report:');
    console.log(path.relative(cwd, path.resolve(path.dirname(reportJsonPath), 'report.html')).replaceAll('/', '\\'));
    console.log('========================================');
}

function phaseLabel(phases, name) {
    const phase = phases.find((entry) => entry.name === name);
    return phase?.status === 'passed' ? 'PASS' : 'FAIL';
}

function formatTimestamp(date) {
    return date.toISOString().replaceAll(':', '-').replace('T', '_').slice(0, 19);
}

async function exists(filePath) {
    try {
        await fs.access(filePath);
        return true;
    } catch {
        return false;
    }
}
