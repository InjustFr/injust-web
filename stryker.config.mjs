export default {
    testRunner: 'vitest',
    plugins: ['@stryker-mutator/vitest-runner'],
    vitest: { configFile: 'vite.config.js' },
    coverageAnalysis: 'perTest',
    tempDirName: 'var/stryker-tmp',
    ignorePatterns: ['vendor', 'var', 'public', 'src', 'tests', 'migrations', 'e2e', 'templates', 'config', 'graft', '.git', '.github', '.phpunit.cache'],
    reporters: ['clear-text', 'progress'],
    thresholds: { high: 90, low: 80, break: 80 },
};
