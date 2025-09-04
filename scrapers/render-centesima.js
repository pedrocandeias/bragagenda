#!/usr/bin/env node
const puppeteer = require('puppeteer');

async function renderCentesimaPage() {
    let browser;
    try {
        // Launch browser with necessary flags for server environments
        browser = await puppeteer.launch({
            headless: 'new',
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-accelerated-2d-canvas',
                '--no-first-run',
                '--no-zygote',
                '--disable-gpu'
            ]
        });
        
        const page = await browser.newPage();
        
        // Set viewport and user agent
        await page.setViewport({ width: 1280, height: 720 });
        await page.setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        // Navigate to the page and wait for Angular to load
        await page.goto('https://centesima.com/agenda', {
            waitUntil: 'domcontentloaded', // Don't wait for all resources
            timeout: 60000
        });
        
        // Wait for Angular app to initialize
        try {
            await page.waitForSelector('app-root', { timeout: 15000 });
        } catch (e) {
            console.error('Angular app failed to load');
            return;
        }
        
        // Wait for main content to render and try different selectors
        await new Promise(resolve => setTimeout(resolve, 15000));
        
        // Try to wait for specific event elements to appear
        try {
            await page.waitForSelector('.information', { timeout: 10000 });
        } catch (e) {
            console.error('Event information elements not found');
        }
        
        // Check if we have any meaningful content
        const bodyText = await page.evaluate(() => document.body.innerText);
        if (bodyText.length < 100) {
            console.error('Page content appears to be empty or not fully loaded');
        }
        
        // Get the fully rendered HTML
        const html = await page.content();
        
        // Output the HTML
        console.log(html);
        
    } catch (error) {
        console.error('Error rendering page:', error.message);
        process.exit(1);
    } finally {
        if (browser) {
            await browser.close();
        }
    }
}

// Only run if called directly
if (require.main === module) {
    renderCentesimaPage();
}

module.exports = { renderCentesimaPage };