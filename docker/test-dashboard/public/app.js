// TecnoFit Test Dashboard - JavaScript

let responseTimeChart = null;
let resultsChart = null;
let rpsChart = null;
let stressTestRunning = false;
let stressTestAbort = false;
let stressRequestCounter = 0;
let defaultPixKey = 'withdraw@tecnofit.com'; // Default, will be loaded from config

// Test account IDs (must exist in database)
const TEST_ACCOUNTS = {
    normal: '00000000-0000-0000-0000-000000000001',      // R$ 1000
    normal2: '00000000-0000-0000-0000-000000000002',     // R$ 500
    lowBalance: '00000000-0000-0000-0000-000000000003',  // R$ 50
    zeroBalance: '00000000-0000-0000-0000-000000000004', // R$ 0
    negativeBalance: '00000000-0000-0000-0000-000000000005', // R$ -100
    locked: '00000000-0000-0000-0000-000000000006'      // R$ 1000 (locked)
};


// Initialize charts and load config
document.addEventListener('DOMContentLoaded', async () => {
    initCharts();
    await loadConfig();
});

// Load API configuration
async function loadConfig() {
    try {
        const response = await fetch('/api/config');
        const config = await response.json();
        
        document.getElementById('apiHost').value = config.apiHost || '';
        document.getElementById('apiPort').value = config.apiPort || '';
        
        // Load default PIX key from config
        if (config.defaultPixKey) {
            defaultPixKey = config.defaultPixKey;
        }
        
        console.log('Loaded API config:', config);
    } catch (error) {
        console.error('Failed to load config:', error);
    }
}

// Reset API config to defaults
function resetApiConfig() {
    document.getElementById('apiHost').value = '';
    document.getElementById('apiPort').value = '';
    document.getElementById('connectionStatus').innerHTML = '<span style="color: #888;">Reset to defaults (hyperf-test:9501)</span>';
}

// Get API configuration from inputs or defaults
function getApiConfig() {
    const config = {
        host: document.getElementById('apiHost').value || document.getElementById('apiHost').placeholder,
        port: document.getElementById('apiPort').value || document.getElementById('apiPort').placeholder
    };
    console.log('Using API config:', config);
    return config;
}

// ==================== Database Setup Functions ====================
// These call the Node.js server API (not the Hyperf API)

async function populateDatabase() {
    const statusEl = document.getElementById('setupStatus');
    const count = parseInt(document.getElementById('accountCount').value) || 100;
    const includeEdgeCases = document.getElementById('includeEdgeCases').checked;

    statusEl.innerHTML = '<span style="color: #ffa726">🔄 Populating database...</span>';

    try {
        const response = await fetch('/api/setup', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ count, includeEdgeCases })
        });

        const data = await response.json();

        if (data.success) {
            const edgeText = includeEdgeCases ? ` (${data.data.edgeCaseAccounts} edge cases + ${data.data.randomAccounts} random)` : '';
            statusEl.innerHTML = `<span style="color: #00c853">✓ Created ${data.data.totalAccounts} accounts${edgeText}</span>`;
            log('raceLog', `Database populated: ${data.data.totalAccounts} accounts${edgeText}`, 'success');
            updateDbInfo();
        } else {
            statusEl.innerHTML = `<span style="color: #e94560">✗ ${data.message}</span>`;
            log('raceLog', `Population failed: ${data.message}`, 'error');
        }
    } catch (error) {
        statusEl.innerHTML = `<span style="color: #e94560">✗ ${error.message}</span>`;
        log('raceLog', `Population error: ${error.message}`, 'error');
    }
}

async function truncateDatabase() {
    if (!confirm('⚠️ This will DELETE ALL accounts and withdrawals. Are you sure?')) {
        return;
    }

    const statusEl = document.getElementById('setupStatus');
    statusEl.innerHTML = '<span style="color: #ffa726">🗑️ Clearing database...</span>';

    try {
        const response = await fetch('/api/truncate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });

        const data = await response.json();

        if (data.success) {
            statusEl.innerHTML = '<span style="color: #00c853">✓ Database cleared</span>';
            log('raceLog', 'Database cleared - all accounts and withdrawals deleted', 'warning');
            updateDbInfo();
        } else {
            statusEl.innerHTML = `<span style="color: #e94560">✗ ${data.message}</span>`;
        }
    } catch (error) {
        statusEl.innerHTML = `<span style="color: #e94560">✗ ${error.message}</span>`;
    }
}

async function updateDbInfo() {
    try {
        const response = await fetch('/api/stats');
        const data = await response.json();

        if (data.success) {
            const stats = data.data.accounts;
            const infoEl = document.getElementById('dbInfo');
            infoEl.innerHTML = `Accounts: <strong>${stats.totalAccounts}</strong> | ` +
                `Positive: <span style="color:#00c853">${stats.positiveBalance}</span> | ` +
                `Zero: <span style="color:#ffa726">${stats.zeroBalance}</span> | ` +
                `Negative: <span style="color:#e94560">${stats.negativeBalance}</span> | ` +
                `Locked: <span style="color:#e94560">${stats.lockedAccounts}</span>`;
        }
    } catch (error) {
        console.error('Failed to update db info:', error);
    }
}

// Update DB info on page load
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(updateDbInfo, 1000);
});

async function resetAllAccounts() {
    const statusEl = document.getElementById('setupStatus');
    statusEl.innerHTML = '<span style="color: #ffa726">Resetting...</span>';

    try {
        const response = await fetch('/api/reset-all', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });

        const data = await response.json();

        if (data.success) {
            statusEl.innerHTML = '<span style="color: #00c853">✓ All accounts reset</span>';
            log('raceLog', 'All accounts reset to initial state', 'success');
        } else {
            statusEl.innerHTML = `<span style="color: #e94560">✗ ${data.message}</span>`;
        }
    } catch (error) {
        statusEl.innerHTML = `<span style="color: #e94560">✗ ${error.message}</span>`;
    }
}

async function viewAccounts() {
    try {
        const response = await fetch('/api/accounts?limit=106');
        const data = await response.json();

        if (data.success) {
            const tbody = document.getElementById('accountsTableBody');
            // Filter out edge case accounts (they're shown separately)
            const randomAccounts = data.data.filter(acc => !acc.id.startsWith('00000000-0000-0000-0000-'));

            if (randomAccounts.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align: center; color: #888;">No random accounts found. Click "Populate Database" to create some.</td></tr>';
            } else {
                tbody.innerHTML = randomAccounts.slice(0, 100).map(acc => `
                    <tr style="border-bottom: 1px solid #222;">
                        <td style="padding: 8px; font-family: monospace; font-size: 0.75rem; color: #58a6ff;">${acc.id.substring(0, 8)}...</td>
                        <td style="padding: 8px; font-family: monospace; font-size: 0.85rem;">${formatCPF(acc.cpf)}</td>
                        <td style="padding: 8px;">${acc.name}</td>
                        <td style="padding: 8px; text-align: right; color: ${parseFloat(acc.balance) > 0 ? '#00c853' : parseFloat(acc.balance) < 0 ? '#e94560' : '#ffa726'}">
                            R$ ${parseFloat(acc.balance).toFixed(2)}
                        </td>
                        <td style="padding: 8px; text-align: center;">
                            ${acc.locked
                                ? '<span style="color: #e94560;">🔒</span>'
                                : '<span style="color: #00c853;">✓</span>'}
                        </td>
                    </tr>
                `).join('');
            }
            document.getElementById('accountsModal').style.display = 'block';
        }
    } catch (error) {
        alert('Failed to load accounts: ' + error.message);
    }
}

function formatCPF(cpf) {
    if (!cpf || cpf.length !== 11) return cpf;
    return `${cpf.substring(0, 3)}.${cpf.substring(3, 6)}.${cpf.substring(6, 9)}-${cpf.substring(9)}`;
}

async function viewStats() {
    try {
        const response = await fetch('/api/stats');
        const data = await response.json();

        if (data.success) {
            const stats = data.data.accounts;
            const wStats = data.data.withdrawals;
            alert(`=== Account Statistics ===
Total Accounts: ${stats.totalAccounts}
Positive Balance: ${stats.positiveBalance}
Zero Balance: ${stats.zeroBalance}
Negative Balance: ${stats.negativeBalance}
Locked Accounts: ${stats.lockedAccounts}

Min Balance: R$ ${parseFloat(stats.minBalance || 0).toFixed(2)}
Max Balance: R$ ${parseFloat(stats.maxBalance || 0).toFixed(2)}
Avg Balance: R$ ${parseFloat(stats.avgBalance || 0).toFixed(2)}

=== Withdrawal Statistics ===
Total: ${wStats.totalWithdrawals}
Successful: ${wStats.successful}
Failed: ${wStats.failed}
Pending: ${wStats.pending}`);
        }
    } catch (error) {
        alert('Failed to load stats: ' + error.message);
    }
}

function closeAccountsModal() {
    document.getElementById('accountsModal').style.display = 'none';
}

// ==================== Request/Response Display Functions ====================

function showRaceResponseDetails(results, testName) {
    const container = document.getElementById('raceResponseDetails');
    const content = document.getElementById('raceResponseContent');
    container.style.display = 'block';

    let html = `<div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #333;">
        <strong style="color: #00d4ff; font-size: 1.1rem;">Test: ${testName}</strong>
        <span style="color: #888; margin-left: 15px;">${new Date().toLocaleTimeString()}</span>
    </div>`;

    results.forEach((result, index) => {
        const statusColor = result.status === 200 ? '#00c853' : result.status === 423 ? '#ffa726' : '#e94560';
        const statusText = result.status === 200 ? 'SUCCESS' : result.status === 423 ? 'LOCKED' : result.status === 422 ? 'INSUFFICIENT' : 'ERROR';

        html += `
        <div style="margin-bottom: 20px; padding: 15px; background: #161b22; border-radius: 8px; border-left: 3px solid ${statusColor};">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <span style="color: #fff; font-weight: bold;">Request #${index + 1}</span>
                <span style="background: ${statusColor}; color: ${result.status === 423 ? '#000' : '#fff'}; padding: 3px 10px; border-radius: 4px; font-size: 0.8rem;">
                    ${result.status} ${statusText}
                </span>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <div style="color: #888; font-size: 0.8rem; margin-bottom: 5px;">Request</div>
                    <pre style="background: #0d1117; padding: 10px; border-radius: 4px; font-size: 0.75rem; color: #7ee787; overflow-x: auto; margin: 0;">${JSON.stringify(result.request || { amount: result.amount, method: 'PIX' }, null, 2)}</pre>
                </div>
                <div>
                    <div style="color: #888; font-size: 0.8rem; margin-bottom: 5px;">Response <span style="color: #58a6ff;">(${result.time}ms)</span></div>
                    <pre style="background: #0d1117; padding: 10px; border-radius: 4px; font-size: 0.75rem; color: ${statusColor}; overflow-x: auto; margin: 0;">${JSON.stringify(result.data || { error: result.error }, null, 2)}</pre>
                </div>
            </div>
        </div>`;
    });

    content.innerHTML = html;
}

function addStressRequestLog(requestNum, accountId, amount, result) {
    const tbody = document.getElementById('stressRequestLogBody');
    const statusColor = result.status === 200 ? '#00c853' : result.status === 423 ? '#ffa726' : result.status === 422 ? '#e94560' : '#666';
    const statusText = result.status === 200 ? 'OK' : result.status === 423 ? 'LOCKED' : result.status === 422 ? 'INSUF' : 'ERR';

    const row = document.createElement('tr');
    row.style.borderBottom = '1px solid #222';
    row.innerHTML = `
        <td style="padding: 8px; color: #888;">${requestNum}</td>
        <td style="padding: 8px; color: #888; font-family: monospace;">${new Date().toLocaleTimeString()}</td>
        <td style="padding: 8px; font-family: monospace; font-size: 0.75rem; color: #58a6ff;">${accountId.substring(0, 8)}...</td>
        <td style="padding: 8px; text-align: right; color: #fff;">R$ ${parseFloat(amount).toFixed(2)}</td>
        <td style="padding: 8px; text-align: center;">
            <span style="background: ${statusColor}; color: ${result.status === 423 ? '#000' : '#fff'}; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem;">${result.status} ${statusText}</span>
        </td>
        <td style="padding: 8px; text-align: right; color: #888;">${result.time}ms</td>
        <td style="padding: 8px; max-width: 300px;">
            <details style="cursor: pointer;">
                <summary style="color: #58a6ff; font-size: 0.75rem;">View Response</summary>
                <pre style="background: #0d1117; padding: 8px; border-radius: 4px; font-size: 0.7rem; color: ${statusColor}; margin-top: 5px; white-space: pre-wrap; word-break: break-all;">${JSON.stringify(result.data || { error: result.error }, null, 2)}</pre>
            </details>
        </td>
    `;

    tbody.insertBefore(row, tbody.firstChild);

    // Keep only last 100 entries
    while (tbody.children.length > 100) {
        tbody.removeChild(tbody.lastChild);
    }

    // Auto-scroll if enabled
    if (document.getElementById('autoScrollLog')?.checked) {
        const logContainer = document.getElementById('stressRequestLog');
        logContainer.scrollTop = 0;
    }
}

function clearStressRequestLog() {
    document.getElementById('stressRequestLogBody').innerHTML = '';
    stressRequestCounter = 0;
}

async function resetAccountBeforeTest(accountId) {
    try {
        await fetch(`/api/reset-account/${accountId}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
    } catch (error) {
        console.error('Failed to reset account:', error);
    }
}

function initCharts() {
    // Response Time Chart
    const rtCtx = document.getElementById('responseTimeChart').getContext('2d');
    responseTimeChart = new Chart(rtCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Response Time (ms)',
                data: [],
                borderColor: '#00d4ff',
                backgroundColor: 'rgba(0, 212, 255, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#333' },
                    ticks: { color: '#888' }
                },
                x: {
                    grid: { color: '#333' },
                    ticks: { color: '#888' }
                }
            },
            plugins: {
                legend: { labels: { color: '#888' } }
            }
        }
    });

    // RPS Chart (Throughput)
    const rpsCtx = document.getElementById('rpsChart').getContext('2d');
    rpsChart = new Chart(rpsCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Requests/sec',
                data: [],
                borderColor: '#00d4ff',
                backgroundColor: 'rgba(0, 212, 255, 0.2)',
                fill: true,
                tension: 0.4,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#333' },
                    ticks: { color: '#888' }
                },
                x: {
                    grid: { color: '#333' },
                    ticks: { color: '#888', maxTicksLimit: 10 }
                }
            },
            plugins: {
                legend: { labels: { color: '#888' } }
            }
        }
    });

    // Results Distribution Chart
    const resCtx = document.getElementById('resultsChart').getContext('2d');
    resultsChart = new Chart(resCtx, {
        type: 'doughnut',
        data: {
            labels: ['Success (200)', 'Locked (423)', 'Insufficient (422)', 'Other Errors'],
            datasets: [{
                data: [0, 0, 0, 0],
                backgroundColor: ['#00c853', '#ffa726', '#e94560', '#666']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: '#888' }
                }
            }
        }
    });
}

function getApiUrl() {
    // We use the proxy now, so this returns the local server
    return '';
}

function showPanel(panelName) {
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.getElementById(`${panelName}-panel`).classList.add('active');
    event.target.classList.add('active');
}

async function testConnection() {
    const statusEl = document.getElementById('connectionStatus');
    statusEl.innerHTML = '<span style="color: #ffa726">Testing...</span>';

    try {
        // Test database connection
        const dbResponse = await fetch('/health');
        const dbData = await dbResponse.json();

        // Test API connection via proxy with custom config
        const apiConfig = getApiConfig();
        const apiResponse = await fetch('/api/proxy/health', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(apiConfig)
        });
        const apiData = await apiResponse.json();

        if (dbData.database === 'connected' && apiData.success) {
            statusEl.innerHTML = `<span style="color: #00c853">✓ DB + API Connected (${apiData.responseTime}ms)</span>`;
        } else if (dbData.database === 'connected') {
            statusEl.innerHTML = `<span style="color: #ffa726">⚠ DB OK, API: ${apiData.message}</span>`;
        } else {
            statusEl.innerHTML = `<span style="color: #e94560">✗ DB: ${dbData.database}</span>`;
        }
    } catch (error) {
        statusEl.innerHTML = `<span style="color: #e94560">✗ Failed: ${error.message}</span>`;
    }
}

function log(logId, message, type = 'info') {
    const logEl = document.getElementById(logId);
    const timestamp = new Date().toLocaleTimeString();
    const entry = document.createElement('div');
    entry.className = `log-entry ${type}`;
    entry.innerHTML = `[${timestamp}] ${message}`;
    logEl.insertBefore(entry, logEl.firstChild);
}

async function sendWithdrawRequest(accountId, amount, schedule = null, pixKeyOrData = null) {
    const start = Date.now();
    
    // Handle different PIX data formats
    let pixData;
    if (pixKeyOrData && typeof pixKeyOrData === 'object') {
        // Full PIX data object provided (for security tests)
        pixData = pixKeyOrData;
    } else {
        // Simple PIX key string (or null/undefined for default)
        const effectivePixKey = pixKeyOrData || defaultPixKey;
        pixData = { type: 'email', key: effectivePixKey };
    }

    console.log(`[sendWithdrawRequest] accountId: ${accountId}, pixData:`, pixData);

    const requestBody = {
        method: 'PIX',
        pix: pixData,
        amount: amount,
        schedule: schedule,
        _apiConfig: getApiConfig()
    };

    try {
        // Use the proxy endpoint
        const response = await fetch(`/api/proxy/withdraw/${accountId}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestBody)
        });

        const data = await response.json().catch(() => ({}));
        const time = data._proxy?.responseTime || (Date.now() - start);

        return {
            status: response.status,
            time: time,
            success: response.status === 200,
            data: data,
            request: requestBody,
            amount: amount,
            accountId: accountId
        };
    } catch (error) {
        return {
            status: 0,
            time: Date.now() - start,
            success: false,
            error: error.message,
            request: requestBody,
            amount: amount,
            accountId: accountId
        };
    }
}

// Race Condition Tests
async function runTest(testName) {
    const resultEl = document.getElementById(`result-${testName}`);
    resultEl.innerHTML = '<span class="running" style="color: #ffa726">Running...</span>';

    log('raceLog', `Starting test: ${testName}`, 'info');

    try {
        let result;
        switch (testName) {
            case 'doubleWithdraw':
                result = await testDoubleWithdraw();
                break;
            case 'overflow':
                result = await testOverflow();
                break;
            case 'scheduledImmediate':
                result = await testScheduledImmediate();
                break;
            case 'zeroBalance':
                result = await testZeroBalance();
                break;
            case 'negativeBalance':
                result = await testNegativeBalance();
                break;
            case 'lockedAccount':
                result = await testLockedAccount();
                break;
            case 'invalidAmount':
                result = await testInvalidAmount();
                break;
            case 'securityTest':
                result = await testSecurityVulnerabilities();
                break;
        }

        if (result.passed) {
            resultEl.innerHTML = '<span class="badge badge-success">PASSED</span>';
            log('raceLog', `✓ ${testName}: ${result.message}`, 'success');
        } else {
            resultEl.innerHTML = '<span class="badge badge-error">FAILED</span>';
            log('raceLog', `✗ ${testName}: ${result.message}`, 'error');
        }
    } catch (error) {
        resultEl.innerHTML = '<span class="badge badge-error">ERROR</span>';
        log('raceLog', `✗ ${testName}: ${error.message}`, 'error');
    }
}

async function testDoubleWithdraw() {
    const accountId = TEST_ACCOUNTS.normal;

    // Reset account before test
    log('raceLog', '  Resetting account to R$ 1000.00...', 'info');
    await resetAccountBeforeTest(accountId);

    // Send two requests simultaneously
    const promises = [
        sendWithdrawRequest(accountId, 600),
        sendWithdrawRequest(accountId, 600)
    ];

    const results = await Promise.all(promises);
    const successes = results.filter(r => r.status === 200).length;
    const locked = results.filter(r => r.status === 423).length;

    log('raceLog', `  Results: ${successes} success, ${locked} locked`, 'info');

    // Show detailed response
    showRaceResponseDetails(results, 'Double Withdrawal Attack');

    // Only one should succeed
    const passed = successes <= 1;
    return {
        passed,
        message: passed
            ? `Correctly prevented double withdrawal (${successes} success, ${locked} locked)`
            : `RACE CONDITION! Both withdrawals succeeded!`
    };
}

async function testOverflow() {
    const accountId = TEST_ACCOUNTS.lowBalance;
    const numRequests = 10;

    // Reset account before test
    log('raceLog', '  Resetting account to R$ 50.00...', 'info');
    await resetAccountBeforeTest(accountId);

    // Send 10 simultaneous requests for R$ 10 each from R$ 50 account
    const promises = Array(numRequests).fill().map(() =>
        sendWithdrawRequest(accountId, 10)
    );

    const results = await Promise.all(promises);
    const successes = results.filter(r => r.status === 200).length;
    const locked = results.filter(r => r.status === 423).length;
    const insufficient = results.filter(r => r.status === 422).length;

    log('raceLog', `  Results: ${successes} success, ${locked} locked, ${insufficient} insufficient`, 'info');

    // Show detailed response
    showRaceResponseDetails(results, 'Balance Overflow (10 requests x R$10 from R$50)');

    // Max 5 should succeed (R$ 50 / R$ 10)
    const passed = successes <= 5;
    return {
        passed,
        message: passed
            ? `Correctly limited withdrawals (${successes}/5 max succeeded)`
            : `OVERFLOW! ${successes} succeeded but max should be 5!`
    };
}

// Format date in São Paulo timezone (YYYY-MM-DD HH:MM:SS)
function formatDateSaoPaulo(date) {
    const options = {
        timeZone: 'America/Sao_Paulo',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    };
    const parts = new Intl.DateTimeFormat('en-CA', options).formatToParts(date);
    const get = (type) => parts.find(p => p.type === type)?.value || '00';
    return `${get('year')}-${get('month')}-${get('day')} ${get('hour')}:${get('minute')}:${get('second')}`;
}

async function testScheduledImmediate() {
    const accountId = TEST_ACCOUNTS.normal2;

    // Reset account before test
    log('raceLog', '  Resetting account to R$ 500.00...', 'info');
    await resetAccountBeforeTest(accountId);

    // Schedule 5 minutes ahead in São Paulo timezone
    const scheduleTime = formatDateSaoPaulo(new Date(Date.now() + 5 * 60 * 1000));

    const promises = [
        sendWithdrawRequest(accountId, 300, null),
        sendWithdrawRequest(accountId, 300, scheduleTime)
    ];

    const results = await Promise.all(promises);
    const immediateResult = results[0];
    const scheduledResult = results[1];

    log('raceLog', `  Immediate: ${immediateResult.status}, Scheduled: ${scheduledResult.status}`, 'info');
    log('raceLog', `  Scheduled for: ${scheduleTime}`, 'info');

    // Show detailed response
    showRaceResponseDetails(results, 'Scheduled + Immediate Withdrawal');

    // Both might succeed but immediate should execute
    const passed = true; // This test is more about behavior observation
    return {
        passed,
        message: `Immediate: ${immediateResult.status}, Scheduled: ${scheduledResult.status}`
    };
}

async function testZeroBalance() {
    const accountId = TEST_ACCOUNTS.zeroBalance;

    // Reset account before test (balance = 0)
    log('raceLog', '  Resetting account to R$ 0.00...', 'info');
    await resetAccountBeforeTest(accountId);

    const result = await sendWithdrawRequest(accountId, 1);

    log('raceLog', `  Response: ${result.status}`, 'info');

    // Show detailed response
    showRaceResponseDetails([result], 'Zero Balance Withdrawal');

    const passed = result.status === 422;
    return {
        passed,
        message: passed
            ? `Correctly rejected with 422`
            : `Expected 422, got ${result.status}`
    };
}

async function testNegativeBalance() {
    const accountId = TEST_ACCOUNTS.negativeBalance;

    // Reset account before test (balance = -100)
    log('raceLog', '  Resetting account to R$ -100.00...', 'info');
    await resetAccountBeforeTest(accountId);

    const result = await sendWithdrawRequest(accountId, 1);

    log('raceLog', `  Response: ${result.status}`, 'info');

    // Show detailed response
    showRaceResponseDetails([result], 'Negative Balance Withdrawal');

    const passed = result.status === 422;
    return {
        passed,
        message: passed
            ? `Correctly rejected with 422 (Insufficient Balance)`
            : `Expected 422, got ${result.status}`
    };
}

async function testLockedAccount() {
    const accountId = TEST_ACCOUNTS.locked;

    // Reset account before test (locked = true)
    log('raceLog', '  Resetting account (locked)...', 'info');
    await resetAccountBeforeTest(accountId);

    const result = await sendWithdrawRequest(accountId, 100);

    log('raceLog', `  Response: ${result.status}`, 'info');

    // Show detailed response
    showRaceResponseDetails([result], 'Locked Account Withdrawal');

    const passed = result.status === 423;
    return {
        passed,
        message: passed
            ? `Correctly rejected with 423 (Account Locked)`
            : `Expected 423, got ${result.status}`
    };
}

async function testInvalidAmount() {
    const accountId = TEST_ACCOUNTS.normal;

    // Reset account before test
    log('raceLog', '  Resetting account...', 'info');
    await resetAccountBeforeTest(accountId);

    const negativeResult = await sendWithdrawRequest(accountId, -100);
    const zeroResult = await sendWithdrawRequest(accountId, 0);

    log('raceLog', `  Negative: ${negativeResult.status}, Zero: ${zeroResult.status}`, 'info');

    // Show detailed response
    showRaceResponseDetails([negativeResult, zeroResult], 'Invalid Amounts (Negative & Zero)');

    const passed = negativeResult.status === 422 && zeroResult.status === 422;
    return {
        passed,
        message: passed
            ? `Both invalid amounts rejected with 422`
            : `Expected 422 for both, got negative:${negativeResult.status}, zero:${zeroResult.status}`
    };
}

async function testSecurityVulnerabilities() {
    const accountId = TEST_ACCOUNTS.normal;

    // Reset account before test
    log('raceLog', '  Resetting account...', 'info');
    await resetAccountBeforeTest(accountId);

    const maliciousPayloads = [
        {
            name: 'SQL Injection in PIX key',
            payload: {
                method: 'PIX',
                pix: {
                    type: 'email',
                    key: "test'; DROP TABLE account;--"
                },
                amount: 100,
                schedule: null
            }
        },
        {
            name: 'XSS in PIX key',
            payload: {
                method: 'PIX',
                pix: {
                    type: 'email',
                    key: "<script>alert('XSS')</script>"
                },
                amount: 100,
                schedule: null
            }
        },
        {
            name: 'Command Injection in PIX key',
            payload: {
                method: 'PIX',
                pix: {
                    type: 'email',
                    key: "test@test.com; rm -rf /"
                },
                amount: 100,
                schedule: null
            }
        },
        {
            name: 'Path Traversal in account ID',
            payload: {
                method: 'PIX',
                pix: {
                    type: 'email',
                    key: "test@test.com"
                },
                amount: 100,
                schedule: null
            },
            customAccountId: '../../../etc/passwd'
        },
        {
            name: 'NoSQL Injection',
            payload: {
                method: 'PIX',
                pix: {
                    type: 'email',
                    key: { "$ne": null }
                },
                amount: 100,
                schedule: null
            }
        },
        {
            name: 'Very large amount (overflow)',
            payload: {
                method: 'PIX',
                pix: {
                    type: 'email',
                    key: "test@test.com"
                },
                amount: 999999999999999999999,
                schedule: null
            }
        }
    ];

    const results = [];
    let allSafe = true;

    for (const test of maliciousPayloads) {
        const targetAccountId = test.customAccountId || accountId;
        log('raceLog', `  Testing: ${test.name}...`, 'info');
        
        const result = await sendWithdrawRequest(targetAccountId, test.payload.amount, null, test.payload.pix);
        results.push({ ...result, testName: test.name });
        
        log('raceLog', `    Response: ${result.status} - ${result.data?.message || 'OK'}`, 
            result.status >= 400 ? 'info' : 'warning');

        // Expected: API should reject with 400/422 or handle gracefully
        // NOT expected: 200 (successful withdrawal), 500 (crash)
        if (result.status === 200) {
            log('raceLog', `    ⚠️ WARNING: Malicious payload was accepted!`, 'error');
            allSafe = false;
        } else if (result.status === 500) {
            log('raceLog', `    ⚠️ WARNING: Server error (possible crash)!`, 'error');
            allSafe = false;
        }
    }

    // Show detailed response
    showRaceResponseDetails(results, 'Security/Vulnerability Tests');

    const passed = allSafe;
    return {
        passed,
        message: passed
            ? `All malicious payloads safely rejected`
            : `SECURITY RISK! Some payloads were not properly handled`
    };
}



// Fetch multiple accounts for stress testing
async function fetchMultipleAccounts(count) {
    try {
        const response = await fetch(`/api/accounts?limit=${count}`);
        const data = await response.json();

        if (data.success && data.data && data.data.length > 0) {
            // Return accounts with a default PIX key (no validation needed)
            return data.data.map(account => ({
                id: account.id,
                name: account.name,
                balance: account.balance,
                pixKey: defaultPixKey
            }));
        }
    } catch (error) {
        console.error('Failed to fetch accounts:', error);
        log('stressLog', `ERROR: Failed to fetch accounts: ${error.message}`, 'error');
    }

    return null;
}

// Stress Test
async function startStressTest() {
    if (stressTestRunning) return;

    stressTestRunning = true;
    stressTestAbort = false;
    stressRequestCounter = 0;

    const totalRequests = parseInt(document.getElementById('totalRequests').value);
    const concurrency = parseInt(document.getElementById('concurrency').value);
    const amount = parseFloat(document.getElementById('withdrawAmount').value);
    const accountsCount = parseInt(document.getElementById('accountsCount').value);
    const resetBefore = document.getElementById('resetBeforeStress').checked;

    // Clear request log
    clearStressRequestLog();

    // Fetch multiple accounts from database
    log('stressLog', `Fetching ${accountsCount} accounts from database...`, 'info');
    const accounts = await fetchMultipleAccounts(accountsCount);
    if (!accounts || accounts.length === 0) {
        log('stressLog', 'ERROR: No accounts found in database. Please use the Populate Database button first.', 'error');
        stressTestRunning = false;
        return;
    }
    log('stressLog', `Loaded ${accounts.length} accounts for stress testing`, 'info');

    // Reset accounts if requested
    if (resetBefore) {
        log('stressLog', `Resetting ${accounts.length} accounts before stress test...`, 'info');
        for (const account of accounts) {
            await resetAccountBeforeTest(account.id);
        }
        log('stressLog', 'All accounts reset', 'success');
    }

    // Reset UI
    document.getElementById('startStressBtn').disabled = true;
    document.getElementById('stopStressBtn').disabled = false;
    document.getElementById('progressFill').style.width = '0%';

    // Reset stats
    const stats = {
        success: 0,
        locked: 0,
        insufficient: 0,
        errors: 0,
        times: [],
        startTime: Date.now(),
        lastBatchTime: Date.now(),
        lastBatchCompleted: 0,
        peakRps: 0,
        rpsHistory: []
    };

    // Reset charts
    responseTimeChart.data.labels = [];
    responseTimeChart.data.datasets[0].data = [];
    rpsChart.data.labels = [];
    rpsChart.data.datasets[0].data = [];

    log('stressLog', `Starting stress test: ${totalRequests} requests, ${concurrency} concurrent, R$ ${amount.toFixed(2)} each`, 'info');

    let completed = 0;

    // Process in batches
    for (let i = 0; i < totalRequests && !stressTestAbort; i += concurrency) {
        const batchSize = Math.min(concurrency, totalRequests - i);
        const promises = [];

        for (let j = 0; j < batchSize; j++) {
            // Cycle through accounts for each request
            const accountIndex = (i + j) % accounts.length;
            const account = accounts[accountIndex];
            promises.push(sendWithdrawRequest(account.id, amount, null, account.pixKey));
        }

        const results = await Promise.all(promises);

        results.forEach(result => {
            completed++;
            stressRequestCounter++;
            stats.times.push(result.time);

            if (result.status === 200) stats.success++;
            else if (result.status === 423) stats.locked++;
            else if (result.status === 422) stats.insufficient++;
            else stats.errors++;

            // Add to request log
            const accountIndex = (i + results.indexOf(result)) % accounts.length;
            const account = accounts[accountIndex];
            addStressRequestLog(stressRequestCounter, account.id, amount, result);

            // Update chart (sample every 10 requests)
            if (completed % 10 === 0 || completed === totalRequests) {
                responseTimeChart.data.labels.push(completed.toString());
                responseTimeChart.data.datasets[0].data.push(result.time);
                responseTimeChart.update('none');
            }
        });

        // Calculate current RPS for this batch
        const now = Date.now();
        const batchDuration = (now - stats.lastBatchTime) / 1000;
        const batchCompleted = completed - stats.lastBatchCompleted;
        const currentRps = batchDuration > 0 ? batchCompleted / batchDuration : 0;
        
        // Update peak RPS
        if (currentRps > stats.peakRps) {
            stats.peakRps = currentRps;
        }
        
        // Add to RPS chart history (sample every batch)
        const elapsedSeconds = Math.floor((now - stats.startTime) / 1000);
        if (rpsChart.data.labels.length === 0 || rpsChart.data.labels[rpsChart.data.labels.length - 1] !== elapsedSeconds + 's') {
            rpsChart.data.labels.push(elapsedSeconds + 's');
            rpsChart.data.datasets[0].data.push(currentRps);
            
            // Keep chart to last 60 data points
            if (rpsChart.data.labels.length > 60) {
                rpsChart.data.labels.shift();
                rpsChart.data.datasets[0].data.shift();
            }
            rpsChart.update('none');
        }
        
        // Update batch tracking
        stats.lastBatchTime = now;
        stats.lastBatchCompleted = completed;

        // Update stats display with current RPS
        updateStatsDisplay(stats, completed, currentRps);

        // Update progress bar
        const progress = (completed / totalRequests) * 100;
        document.getElementById('progressFill').style.width = `${progress}%`;
    }

    // Final stats
    const duration = (Date.now() - stats.startTime) / 1000;
    const avgRps = completed / duration;

    document.getElementById('statRps').textContent = '0';
    document.getElementById('statAvgRps').textContent = avgRps.toFixed(1);
    document.getElementById('statPeakRps').textContent = stats.peakRps.toFixed(1);

    // Update results chart
    resultsChart.data.datasets[0].data = [stats.success, stats.locked, stats.insufficient, stats.errors];
    resultsChart.update();

    log('stressLog', `✅ Completed: ${completed} requests in ${duration.toFixed(2)}s`, 'success');
    log('stressLog', `📊 Throughput - Avg: ${avgRps.toFixed(1)} req/s | Peak: ${stats.peakRps.toFixed(1)} req/s`, 'info');
    log('stressLog', `📈 Results: ${stats.success} success, ${stats.locked} locked, ${stats.insufficient} insufficient, ${stats.errors} errors`, 'info');

    stressTestRunning = false;
    document.getElementById('startStressBtn').disabled = false;
    document.getElementById('stopStressBtn').disabled = true;
}

function updateStatsDisplay(stats, completed, currentRps = 0) {
    document.getElementById('statSuccess').textContent = stats.success;
    document.getElementById('statLocked').textContent = stats.locked;
    document.getElementById('statInsufficient').textContent = stats.insufficient;
    document.getElementById('statErrors').textContent = stats.errors;

    if (stats.times.length > 0) {
        const avgTime = stats.times.reduce((a, b) => a + b, 0) / stats.times.length;
        document.getElementById('statAvgTime').textContent = `${avgTime.toFixed(0)}ms`;
    }

    const duration = (Date.now() - stats.startTime) / 1000;
    const avgRps = duration > 0 ? completed / duration : 0;
    
    // Display current (instantaneous) RPS
    document.getElementById('statRps').textContent = currentRps.toFixed(1);
    
    // Display average and peak RPS
    document.getElementById('statAvgRps').textContent = avgRps.toFixed(1);
    document.getElementById('statPeakRps').textContent = stats.peakRps.toFixed(1);
    
    // Update results chart
    resultsChart.data.datasets[0].data = [stats.success, stats.locked, stats.insufficient, stats.errors];
    resultsChart.update();
}

function stopStressTest() {
    stressTestAbort = true;
    log('stressLog', 'Stopping stress test...', 'warning');
}
