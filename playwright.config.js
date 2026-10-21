import { defineConfig } from '@playwright/test';
import fs from 'node:fs';

const windowsChrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
export default defineConfig({
    testDir: './tests/Browser',
    testMatch: '**/*.spec.js',
    fullyParallel: false,
    workers: 1,
    reporter: 'list',
    outputDir: 'storage/framework/testing/browser-results',
    use: {
        baseURL: 'http://127.0.0.1:8765',
        headless: true,
        launchOptions: process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : fs.existsSync(windowsChrome) ? { executablePath: windowsChrome } : {},
        screenshot: 'only-on-failure',
    },
    webServer: {
        command: 'node tests/Browser/server.mjs',
        url: 'http://127.0.0.1:8765/up',
        reuseExistingServer: false,
        timeout: 60000,
    },
});
